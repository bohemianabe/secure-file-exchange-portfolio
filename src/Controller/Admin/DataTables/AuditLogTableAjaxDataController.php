<?php

namespace App\Controller\Admin\DataTables;

use App\Controller\Traits\EntityActions;
use App\Entity\AuditLogs;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;


final class AuditLogTableAjaxDataController extends AbstractController
{
    use EntityActions;

    #[Route('/admin/ajax/audit-log/data/call', name: 'admin_ajax_audit_log_data_call')]
    public function __invoke(Request $request, EntityManagerInterface $em): JsonResponse
    {
        // dd($this->params->get('app_timezone'));
        $start  = max(0, (int) $request->query->get('start', 0));
        $length = min((int) $request->query->get('length', 10), 500); // cap for safety
        $draw   = (int) $request->query->get('draw', 0);

        $search = (string) ($request->query->all('search')['value'] ?? '');
        $order  = $request->query->all('order')[0] ?? null;

        // Column map in the same order as the DataTables columns
        $cols = [
            'idAuditLog',
            'userEmail',
            'action',
            'resourceId',
            'ipAddress',
            'success',
            'severity',
            'occurredAt',
            'metadata'
        ];

        $qb = $em->getRepository(AuditLogs::class)->createQueryBuilder('a');

        // Global search (add/remove fields as you like)
        if ($search !== '') {
            $qb->andWhere('a.userEmail LIKE :q OR a.action LIKE :q OR a.route LIKE :q OR a.ip LIKE :q OR a.sessionId LIKE :q OR a.requestId LIKE :q OR a.severity LIKE :q')
                ->setParameter('q', '%' . $search . '%');
        }

        // Sorting
        if ($order) {
            $idx = (int) ($order['column'] ?? 0);
            $dir = strtoupper($order['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
            $col = $cols[$idx] ?? 'occurredAt';
            $qb->orderBy('a.' . $col, $dir);
        } else {
            $qb->orderBy('a.occurredAt', 'DESC');
        }

        // Counts
        $qbCount = clone $qb;
        $recordsFiltered = (int) $qbCount->select('COUNT(a.idAuditLog)')->getQuery()->getSingleScalarResult();

        $recordsTotal = (int) $em->getRepository(AuditLogs::class)
            ->createQueryBuilder('a')->select('COUNT(a.idAuditLog)')->getQuery()->getSingleScalarResult();

        // Page
        $rows = $qb->setFirstResult($start)->setMaxResults($length)->getQuery()->getResult();

        $tz   = new \DateTimeZone($this->params->get('app_timezone'));

        // Transform rows to flat JSON objects for DataTables
        $data = array_map(function (AuditLogs $a) use ($tz) {
            /** @var \DateTimeImmutable $dt */
            $dt = $a->getOccurredAt();         // stored in UTC
            $local = $dt?->setTimezone($tz);

            return [
                'id'         => $a->getId(),
                'userEmail'  => $a->getUserEmail(), // fallback to id
                'action'     => $a->getAction(),
                'resourceId' => $a->getResourceId(),
                'ip'         => $a->getIp(),
                'success'    => $a->getSuccess() ? 'True' : 'False',
                'severity'   => $a->getSeverity(),
                'occurredAt' => $local?->format('y-m-d g:ia'),
                'metadata'   => $a->getMetadata() ? json_encode($a->getMetadata(), JSON_UNESCAPED_SLASHES) : '',
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
