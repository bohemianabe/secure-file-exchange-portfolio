<?php

namespace App\Controller\Admin\DataTables;

use App\Controller\Traits\EntityActions;
use App\Entity\Firms;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class FirmTableAjaxDataController extends AbstractController
{
    use EntityActions;

    #[Route('/admin/ajax/firm-table/data/call', name: 'admin_ajax_firm_table_data_call')]
    public function __invoke(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $start  = max(0, (int) $request->query->get('start', 0));
        $length = min((int) $request->query->get('length', 10), 500); // cap for safety
        $draw   = (int) $request->query->get('draw', 0);

        $search = (string) ($request->query->all('search')['value'] ?? '');
        $order  = $request->query->all('order')[0] ?? null;

        // Column map in the same order as the DataTables columns
        $cols = [
            // 'id',
            'name',
            'account',
            'storagePlan',
            'phone',
            'active',
            'createdAt',
            'updatedAt',
            'view',
        ];

        // ag: left join the storagePlan table so I can search for it by name.
        $qb = $em->getRepository(Firms::class)->createQueryBuilder('f')->leftJoin('f.storagePlan', 'p')->addSelect('p');;


        if ($search !== '') {
            $qb->setParameter('q', '%' . $search . '%');

            // Map common words to a boolean
            $normalized = strtolower(trim($search));
            $map = [
                'active'   => true,
                'inactive' => false,
            ];
            $isActive = array_key_exists($normalized, $map) ? $map[$normalized] : null;

            $or = $qb->expr()->orX(
                'f.name    LIKE :q',
                'f.account LIKE :q',
                'p.name LIKE :q',
            );

            if ($isActive !== null) {
                $or->add('f.active = :active');
                $qb->setParameter('active', $isActive, Types::BOOLEAN);
            }

            $qb->andWhere($or);
        }
        // Sorting
        if ($order) {
            $idx = (int) ($order['column'] ?? 0);
            $dir = strtoupper($order['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
            $col = $cols[$idx] ?? 'createdAt';
            $qb->orderBy('f.' . $col, $dir);
        } else {
            $qb->orderBy('f.createdAt', 'DESC');
        }

        // Counts
        $qbCount = clone $qb;
        $recordsFiltered = (int) $qbCount->select('COUNT(f.idFirm)')->getQuery()->getSingleScalarResult();
        // dd($recordsFiltered);
        $recordsTotal = (int) $em->getRepository(Firms::class)
            ->createQueryBuilder('f')->select('COUNT(f.idFirm)')->getQuery()->getSingleScalarResult();

        // Page
        $rows = $qb->setFirstResult($start)->setMaxResults($length)->getQuery()->getResult();

        $tz   = new \DateTimeZone($this->params->get('app_timezone'));

        // Transform rows to flat JSON objects for DataTables
        $data = array_map(function (Firms $f) use ($tz) {
            /** @var \DateTimeImmutable $dt */
            $dtCreatedAt      = $f->getCreatedAt();
            $localCreatedAt   = $dtCreatedAt?->setTimezone($tz);

            $dtUpdatedAt      = $f->getUpdatedAt();
            $localUpdatedAt   = $dtUpdatedAt?->setTimezone($tz);

            return [
                'id'            => $f->getId(),
                'name'          => $f->getName(), // fallback to id
                'account'       => $f->getAccount(),
                'storagePlan'   => $f->getStoragePlan()->getName(),
                'phone'         => $f->getPhone(),
                'active'        => $f->getActive(),
                'createdAt'     => $localCreatedAt?->format('y-m-d g:ia'),
                'updatedAt'     => $localUpdatedAt?->format('y-m-d g:ia'),
                'view'          => $f->getId(),
            ];
        }, $rows);

        return $this->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }
}
