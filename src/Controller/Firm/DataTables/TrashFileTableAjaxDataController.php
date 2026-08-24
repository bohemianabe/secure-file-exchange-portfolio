<?php

namespace App\Controller\Firm\DataTables;

use App\Controller\Traits\EntityActions;
use App\Entity\Files;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class TrashFileTableAjaxDataController extends AbstractController
{
    use EntityActions;

    #[Route('/firm/ajax/trash-file-table/data/call', name: 'firm_ajax_trash_file_table_data_call')]
    public function __invoke(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $start  = max(0, (int) $request->query->get('start', 0));
        $length = min((int) $request->query->get('length', 10), 500); // cap for safety
        $draw   = (int) $request->query->get('draw', 0);
        $userProfile = $this->getUser()->getUserProfile();

        $search = (string) ($request->query->all('search')['value'] ?? '');
        $order  = $request->query->all('order')[0] ?? null;

        // Column map in the same order as the DataTables columns
        $cols = [
            'id',
            'name',
            'fileName',
            'uploadedBy',
            'assignedTo',
            'createdAt',
            'expirationDate',
            'restore',
        ];

        $qb = $em->getRepository(Files::class)->createQueryBuilder('f')->where('f.deletedAt is NOT NULL');

        if (!in_array($userProfile->getUserType(), ['primary', 'admin'], true)) {
            $clients = $userProfile->getAssignedClients()->toArray();
            $qb->andWhere('f.client IN (:client)')->setParameter('client', $clients);
        }

        if ($search !== '') {
            $qb->setParameter('q', '%' . $search . '%');

            // Map common words to a boolean
            $normalized = strtolower(trim($search));

            $or = $qb->expr()->orX(
                'f.name    LIKE :q',
            );

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

        $this->em->getFilters()->disable('soft_delete');
        // Counts
        $qbCount = clone $qb;
        $recordsFiltered = (int) $qbCount->select('COUNT(f.idFile)')->getQuery()->getSingleScalarResult();
        // ag: the count number at the bottom of the datatable
        $recordsTotal = (int) $em->getRepository(Files::class)
            ->createQueryBuilder('f')->select('COUNT(f.idFile)')->where('f.deletedAt IS NOT NULL')->getQuery()->getSingleScalarResult();


        // Disable the soft delete filter

        $rows = $qb->setFirstResult($start)->setMaxResults($length)->getQuery()->getResult();

        // IMPORTANT: re-enable the filter
        $this->em->getFilters()->enable('soft_delete');

        $firm = $userProfile->getFirm();
        $tzId = (string) $firm->getTimezone();
        $tz   = new \DateTimeZone($tzId);

        // Transform rows to flat JSON objects for DataTables
        $data = array_map(function (Files $f) use ($tz) {
            /** @var \DateTimeImmutable $dt */
            $dtCreatedAt          = $f->getCreatedAt();
            $localCreatedAt       = $dtCreatedAt?->setTimezone($tz);

            $dtUpdatedAt          = $f->getUpdatedAt();

            $dtExpirationDate       = $f->getExpirationDate();
            $localExpirationDate    = $dtExpirationDate?->setTimezone($tz);

            $assignedTo = 'Unassigned';
            $uploadedBy = $f->getUploadedByFirmUser()?->getFullName()
                ?? $f->getUploadedByClientUser()?->getFullName()
                ?? 'System';

            if ($f->hasSignatureRequests()) {
                $assignedTo = 'Docu Sign';
            } elseif ($f->isFileRequest()) {
                $assignedTo = 'File Request';
                $uploadedBy = $f->getFileRequest()->getRecipientName();
            } else {
                $assignedTo = $f->getClient()?->getName() ?? 'Public';
            }

            return [
                'id'                => $f->getId(),
                'name'              => $f->getName() ?? 'n/a', // fallback to id
                'fileName'          => $f->getOriginalName(),
                'uploadedBy'        => $uploadedBy,
                'assignedTo'        => $assignedTo,
                'createdAt'         => $localCreatedAt?->format('y-m-d g:ia'),
                'expirationDate'    => $localExpirationDate->format('y-m-d'),
                'restore'           => $f->getId(),
            ];
        }, $rows);

        return $this->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    function trimStringWithDots(string $text, int $maxLength = 20, string $ellipsis = '...'): string
    {
        if (mb_strlen($text) > $maxLength) {
            // Subtract the length of the ellipsis from the maximum length
            // to ensure the total length (text + ellipsis) does not exceed $maxLength.
            $trimmedLength = $maxLength - mb_strlen($ellipsis);
            return mb_substr($text, 0, $trimmedLength) . $ellipsis;
        }
        return $text;
    }
}
