<?php

namespace App\Controller\Admin;

use App\Controller\Traits\EntityActions;
use App\Entity\AuditLogs;
use App\Entity\Tickets;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted("ROLE_ADMIN")]
class AuditLogController extends AbstractController
{
    use EntityActions;

    #[Route('/admin/audit-log-view', name: 'audit_log_view')]
    public function adminAuditLogView(Request $request)
    {
        $isOpenStatusTicket = $this->em->getRepository(Tickets::class)->isOpenStatusTicket();
        $auditLogs = $this->em->getRepository(AuditLogs::class)->findAll();
        return $this->render('admin_user_access/audit-log-view.html.twig', [
            'audit_logs' => $auditLogs,
            // ag: twig can't echo an array so json_encode() it.
            'chart_data' => ['auditLogSeverityMetrics' => $this->_getAuditLogSeverityMetrics(60)],
            // 'modals_to_include' => [''],
            'isOpenStatusTicket' => $isOpenStatusTicket,
        ]);
    }

    #[Route('/admin/audit-log/{auditLog}/view/modal', name: 'admin_view_log_metadata_modal')]
    public function adminViewAuditLogModal(Request $request, AuditLogs $auditLog)
    {
        return $this->render('/layouts/modals/admin/view-audit-log-metadata.html.twig', [
            'auditLog' => $auditLog,
        ]);
    }
}
