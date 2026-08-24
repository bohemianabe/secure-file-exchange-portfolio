<?php

namespace App\Controller\Traits;

use App\DropboxSign\DropboxSignClient;
use App\Entity\AuditLogs;
use App\Entity\Clients;
use App\Entity\ClientUserProfiles;
use App\Entity\Files;
use App\Entity\Firms;
use App\Entity\Folders;
use App\Entity\Notifications;
use App\Entity\User;
use App\Logging\AuditLogger;
use App\Repository\FilesRepository;
use App\Service\FileUploader;
use App\Service\NotificationService;
use App\Enum\NotificationResource;
use App\Enum\NotificationType;
use App\Security\Voter\FileVoter;
use App\Security\Voter\FolderVoter;
use App\Service\FileAccessLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Messenger\MessageBusInterface;

// ag: A helper Class only meant for use in controllers to encapsulate logic related to entity fetching and response rendering. Handling errors by returning a view to the user (e.g., “record not found,” “database error”). 

trait EntityActions
{
    private \Exception $lastException;
    private EntityManagerInterface $em;
    private ParameterBagInterface $params;
    private AuditLogger $audit;
    private MessageBusInterface $messageBus;
    private DropboxSignClient $dropboxSignClient;
    private FileUploader $fileUploader;
    private NotificationService $notificationService;
    private FileAccessLogService $fileAccessLogService;

    public function __construct(EntityManagerInterface $em, ParameterBagInterface $params, AuditLogger $audit, MessageBusInterface $messageBus, DropboxSignClient $dropboxSignClient, FileUploader $fileUploader, NotificationService $notificationService, FileAccessLogService $fileAccessLogService)
    {
        $this->em = $em;
        $this->params = $params;
        $this->audit = $audit;
        $this->messageBus = $messageBus;
        $this->dropboxSignClient = $dropboxSignClient;
        $this->fileUploader = $fileUploader;
        $this->notificationService = $notificationService;
        $this->fileAccessLogService = $fileAccessLogService;
    }

    /**
     * Calculates the percentage of active firms.
     *
     * Example:
     * - 75.00 means 75% of firms are active.
     * - 100.00 means all firms are active.
     *
     * @return float Percentage of active firms.
     */
    protected function _getActiveFirmMetrics(): float
    {
        $allFirms = $this->em->getRepository(Firms::class)->findAll();
        $activeFirms = $this->em->getRepository(Firms::class)->findBy(['active' => true]);

        $total = count($allFirms);
        $active = count($activeFirms);

        if ($total === 0) {
            return 0; // avoid division by zero
        }

        return round(($active / $total) * 100, 2);
    }

    /**
     * Finds out percentage of storage plans in relations to firms.
     *
     * @return array
     */
    protected function _getStoragePlanMetrics(): array
    {
        // Get total number of firms
        $allFirms = $this->em->getRepository(Firms::class)->findAll();

        $total = count($allFirms);

        if ($total === 0) {
            return [];
        }

        // Count firms per storage plan
        $plans = [];
        foreach ($allFirms as $firm) {
            $planName = $firm->getStoragePlan()->getName();
            if (!isset($plans[$planName])) {
                $plans[$planName] = 0;
            }
            $plans[$planName]++;
        }

        // Convert counts to percentages
        foreach ($plans as $name => $count) {
            $plans[$name] = round(($count / $total) * 100, 2);
        }

        return $plans;
    }

    /**
     * Returns array of storage plan revenue metrics.
     *
     * @return array
     */
    protected function _getStorageRevenueMetrics(): array
    {
        // Fetch all firms
        $allFirms = $this->em->getRepository(Firms::class)->findAll();

        $total = count($allFirms);

        if ($total === 0) {
            return [];
        }

        $revenueByPlan = [];
        $totalRevenue = 0;

        // Aggregate revenue per plan
        foreach ($allFirms as $firm) {
            $plan = $firm->getStoragePlan();
            if (!$plan) {
                continue; // skip firms without a storage plan
            }

            $planName = $plan->getName();
            $planPrice = (float) $plan->getPrice();

            if (!isset($revenueByPlan[$planName])) {
                $revenueByPlan[$planName] = 0;
            }

            $revenueByPlan[$planName] += $planPrice;
            $totalRevenue += $planPrice;
        }

        // Compute percentage of total revenue per plan
        $percentages = [];
        foreach ($revenueByPlan as $name => $revenue) {
            $percentages[$name] = [
                'revenue' => round($revenue, 2),
                'percentage' => $totalRevenue > 0 ? round(($revenue / $totalRevenue) * 100, 2) : 0
            ];
        }

        // Include a total for summary display
        $percentages['_total'] = round($totalRevenue, 2);

        return $percentages;
    }

    /**
     * Normalize audit value from old to new changes in DB
     * @param mixed $v 
     * 
     * @return mixed
     */
    protected function _normalizeAuditValue(mixed $v): mixed
    {
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d H:i:s');
        }
        if ($v instanceof \BackedEnum) {
            return $v->value;
        }

        if (is_object($v)) {
            // tailor to your associations
            if (method_exists($v, 'getCode')) {
                return $v->getCode();
            }
            // e.g. State/Country code
            if (method_exists($v, 'getName')) {
                return $v->getName();
            }
            if (method_exists($v, 'getId')) {
                return $v->getId();
            }
            return (string) $v; // last resort
        }
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_string($v) && ctype_digit($v)) {
            return (int) $v;
        }
        return $v;
    }

    /**
     * Returns data for audit log based on number of days
     * @param int $days
     * 
     * @return array
     */
    protected function _getAuditLogSeverityMetrics(int $days = 30): array
    {
        $end   = new \DateTimeImmutable('now');                // stored in UTC is fine
        $start = $end->sub(new \DateInterval('P' . $days . 'D'));

        $qb = $this->em->getRepository(AuditLogs::class)->createQueryBuilder('a');
        // Count by severity for the window
        $rows = $qb->select('a.severity AS sev, COUNT(a.idAuditLog) AS cnt')
            ->where('a.occurredAt >= :start')
            ->andWhere('a.occurredAt < :end')
            ->groupBy('a.severity')
            ->setParameter('start', $start)
            ->setParameter('end',   $end)
            ->getQuery()
            ->getArrayResult();

        // Normalize to fixed keys
        $counts = ['info' => 0, 'warning' => 0, 'error' => 0];
        foreach ($rows as $r) {
            $sev = strtolower((string) $r['sev']);
            if (isset($counts[$sev])) {
                $counts[$sev] = (int) $r['cnt'];
            }
        }

        $total = array_sum($counts);
        $pct = $total === 0
            ? ['info' => 0.0, 'warning' => 0.0, 'error' => 0.0]
            : array_map(static fn(int $c) => round(($c / $total) * 100, 2), $counts);
        return [
            'labels'       => ['info', 'warning', 'error'],
            'counts'       => [$counts['info'], $counts['warning'], $counts['error']],
            'percentages'  => [$pct['info'], $pct['warning'], $pct['error']],
            'window'       => ['start' => $start, 'end' => $end],
        ];
    }

    /**
     * calculate the percent different from $old and $new
     *
     * @param int $old 
     * @param int $new
     * @return string   
     */
    protected function _percentChange(int $old, int $new): string
    {
        // Case 1: no change at all
        if ($old === $new) {
            return '0%';
        }

        // Case 2: old = 0 and new > 0 → infinite increase
        if ($old === 0 && $new > 0) {
            return '~';   // or 'new', or 'N/A'
        }

        // Case 3: old = 0 and new = 0 → no change
        if ($old === 0 && $new === 0) {
            return '0%';
        }

        // Case 4: normal calculation
        $percent = (($new - $old) / $old) * 100;

        // Format without too many decimals
        return number_format($percent, 2) . '%';
    }

    /**
     * fetch an array of basic datapoints for the firm views to be displayed in the side bar/nav
     *
     * @param FilesRepository $fileRepo to perform custom search on files
     * @return array
     */
    protected function _pageDataPointBasics(?FilesRepository $fileRepo): array
    {
        $userProfile = $this->getUser()->getUserProfile();
        $firm = $userProfile->getFirm();
        $notifications = $this->em->getRepository(Notifications::class)->getUnreadNotificationsByUser($this->getUser());
        // dd($notifications);
        $totalBytes = $firm->getStoragePlan()->getStorage(); // from your StoragePlans entity
        $usedBytes = $this->em->getRepository(Firms::class)->getUsedStorageBytesIncludingDeleted($firm);
        $softDeletedFiles = $fileRepo->findSoftDeleted($firm, null);
        $usedPercent = $totalBytes > 0 ? ($usedBytes / $totalBytes) * 100 : 0;

        return [
            'firm' => $firm,
            'storage_plan' => $firm->getStoragePlan(),
            'used_percent' => $usedPercent,
            'trash_count' => count($softDeletedFiles),
            'used_storage' => $usedBytes,
            'notifications' => $notifications,
        ];
    }

    /**
     * fetch an array of basic datapoints for the client views to be displayed in the side bar/nav
     *
     * @return array
     */
    protected function _clientPageDataPointBasics(): array
    {
        $userProfile = $this->getUser()->getUserProfile();
        $firm = $userProfile->getFirm();
        $notifications = $this->em->getRepository(Notifications::class)->getUnreadNotificationsByUser($this->getUser());
        $unreadMsgCount = 0;
        foreach ($notifications as $notification) {
            if ($notification->getType() == NotificationResource::Conversation) {
                $unreadMsgCount++;
            }
        }

        return [
            'firm' => $firm,
            'notifications' => $notifications,
            'unreadMsgCount' => $unreadMsgCount,
        ];
    }

    /**
     * AG: TODO DEPRECATING THIS FUNCTIONALITY WILL BE HANDLED IN THE FILEACCESSLOG SERVICE INSTEAD.
     * @param iterable<Files> $files
     * @param User $user 
     * 
     * @return void 
     */
    // protected function _fileViewedTracker(array $files, User $user): void
    // {
    //     foreach ($files as $file) {
    //         if (!$file instanceof Files) {
    //             throw new \InvalidArgumentException('Expected Files entity');
    //         }

    //         if ($file->getLastAccessedAt() !== null) {
    //             continue;
    //         }

    //         $profile = $user->getUserProfile();
    //         $now = new \DateTimeImmutable('now');

    //         // Firm uploaded → client views
    //         if (($file->getUploadedByFirmUser() !== null && $profile instanceof ClientUserProfiles) || ($file->getUploadedByFirmUser() !== null && $file->getIsDocusign()) || ($file->getUploadedByFirmUser() !== null && $file->isFileRequest())) {
    //             $file->setLastAccessedAt($now);
    //             $this->em->persist($file);

    //             // ag: when client views file handle notification, and notify FUP if needed.
    //             $this->messageBus->dispatch(new NotificationMessage(
    //                 userId: $user->getId(),
    //                 type: NotificationType::FileDownloaded,
    //                 data: ['file_name' => $file->getOriginalName(), 'viewer_email' => $user->getEmail()],
    //                 // fileRequestId: $fileRequest->getId(),
    //             ));
    //         }

    //         // Client uploaded → firm views
    //         if ($file->getUploadedByClientUser() !== null && $profile instanceof FirmUserProfiles) {
    //             $file->setLastAccessedAt($now);
    //             $this->em->persist($file);
    //         }
    //     }
    // }

    /**
     * @param int $count
     * 
     * @return array
     */
    public function _generatePlainCodes(int $count = 10): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = $this->_generateSingleCode();
        }

        return $codes;
    }

    /**
     * @param array $plainCodes
     * 
     * @return array
     */
    public function _hashCodes(array $plainCodes): array
    {
        return array_map(
            static fn(string $code): string => password_hash($code, PASSWORD_DEFAULT),
            $plainCodes
        );
    }

    /**
     * 
     * @return string
     */
    private function _generateSingleCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $length = 8;
        $raw = '';

        for ($i = 0; $i < $length; $i++) {
            $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return substr($raw, 0, 4) . '-' . substr($raw, 4, 4);
    }

    /**
     * @param ClientUserProfiles $clientUserProfile
     * @param Clients $client
     * 
     * @return bool confirms profile is associated with the client
     */
    public function _clientUserProfileBelongsToClient(ClientUserProfiles $clientUserProfile, Clients $client): bool
    {
        return $client->getClientUserProfiles()->contains($clientUserProfile);
    }

    /**
     * @param \DateTimeInterface $date
     * @param \DateTimeZone $tz
     * 
     * @return string message day labels for chat conversation organizability 
     */
    private function _getMessageDayLabel(\DateTimeInterface $date, \DateTimeZone $tz): string
    {
        $messageDate = \DateTimeImmutable::createFromInterface($date)->setTimezone($tz);

        $today = new \DateTimeImmutable('today', $tz);
        $yesterday = $today->modify('-1 day');

        if ($messageDate->format('Y-m-d') === $today->format('Y-m-d')) {
            return 'Today';
        }

        if ($messageDate->format('Y-m-d') === $yesterday->format('Y-m-d')) {
            return 'Yesterday';
        }

        return $messageDate->format('F j, Y');
    }


    /**
     * @param FormInterface $forms
     * 
     * @return array
     */
    private function _getFormErrors(FormInterface ...$forms): array
    {
        $errors = [];

        foreach ($forms as $form) {
            foreach ($form->getErrors(true, true) as $error) {
                $errors[] = $error->getMessage();
            }
        }

        return $errors;
    }

    /**
     * @param Folders $folder
     * 
     * @return void
     */
    public function _deleteFolderTree(Folders $folder): void
    {
        foreach ($folder->getFiles() as $file) {
            $this->denyAccessUnlessGranted(FileVoter::DELETE, $file);

            if ($file->getFirm() !== $folder->getFirm()) {
                throw $this->createAccessDeniedException();
            }
            $folder->removeAssignedFile($file);
            $file->delete(); // soft delete
        }

        foreach ($folder->getChildren() as $childFolder) {
            $this->_deleteFolderTree($childFolder);
        }

        $this->denyAccessUnlessGranted(FolderVoter::EDIT, $folder);

        $this->em->remove($folder);
    }

    /**
     * @param Folders $folder
     * @param int $sizeBytes
     * 
     * @return void
     */
    private function _incrementParentTreeFolderSizeBytes(Folders $folder, int $sizeBytes): void
    {
        $parentFolder = $folder->getParent();

        while ($parentFolder !== null) {
            $parentFolder->incrementSizeBytes($sizeBytes);
            $parentFolder = $parentFolder->getParent();
        }
    }

    /**
     * @param Folders $folder
     * @param int $sizeBytes
     * 
     * @return void
     */
    private function _decrementParentTreeFolderSizeBytes(Folders $folder, int $sizeBytes): void
    {
        $parentFolder = $folder->getParent();

        while ($parentFolder !== null) {
            $parentFolder->decrementSizeBytes($sizeBytes);
            $parentFolder = $parentFolder->getParent();
        }
    }

    /**
     * @param array $ids
     * 
     * @return void
     */
    public function _restoreSoftDeletedFiles(array $ids): void
    {
        // Disable the soft delete filter
        $this->em->getFilters()->disable('soft_delete');

        $files = $this->em->getRepository(Files::class)->findBy(['idFile' => $ids]);
        $fileNotifications = $this->em->getRepository(Notifications::class)->findBy(['type' => NotificationType::FileUploaded, 'resourceId' => $ids]);

        // ag: restore notifications
        if (!empty($fileNotifications)) {
            foreach ($fileNotifications as $notify) {
                $notify->restore();
            }
        }

        foreach ($files as $file) {
            $this->denyAccessUnlessGranted(FileVoter::RESTORE, $file);
            $this->fileAccessLogService->logRestored($file, $this->getUser(), false);
            // ag: find if file is a signature request and restore the sig request row it too, and remove it's notification
            if ($file->hasSignatureRequests()) {
                foreach ($file->getSignatureRequests() as $sigRequest) {
                    $sigRequestNotification = $this->em->getRepository(Notifications::class)->findOneBy(['type' => NotificationType::SignatureRequestCompleted, 'resourceId' => $sigRequest->getSignatureId()]);
                    $sigRequest->restore();
                    $sigRequestNotification->restore();
                }
            }
            $file->restore();
        }
        $this->em->flush();

        // IMPORTANT: re-enable the filter
        $this->em->getFilters()->enable('soft_delete');
    }

    /**
     * @param array $ids
     * 
     * @return void
     */
    public function _softDeleteFiles(array $ids): void
    {
        $files = $this->em->getRepository(Files::class)->findBy(['idFile' => $ids]);
        $fileNotifications = $this->em->getRepository(Notifications::class)->findBy(['type' => NotificationType::FileUploaded, 'resourceId' => $ids]);

        // ag: restore notifications
        if (!empty($fileNotifications)) {
            foreach ($fileNotifications as $notify) {
                $notify->delete();
            }
        }
        foreach ($files as $file) {
            $this->denyAccessUnlessGranted(FileVoter::DELETE, $file);
            $this->fileAccessLogService->logSoftDeleted($file, $this->getUser(), false);
            // ag: find if file is a signature request and delete the sig request row it too, and remove it's notification
            if ($file->hasSignatureRequests()) {
                foreach ($file->getSignatureRequests() as $sigRequest) {
                    $sigRequestNotification = $this->em->getRepository(Notifications::class)->findOneBy(['type' => NotificationType::SignatureRequestCompleted, 'resourceId' => $sigRequest->getSignatureId()]);
                    $sigRequest->delete();
                    if (!is_null($sigRequestNotification)) {
                        $sigRequestNotification->delete();
                    }
                }
            }

            // ag: if file is assigned to a folder unassign it before soft deleting
            if ($file->hasFolder()) {
                $folder = $file->getFolder();
                $folder->removeAssignedFile($file);
                $folder->decrementSizeBytes($file->getSizeBytes());
                // ag: increment folder tree size
                $this->_decrementParentTreeFolderSizeBytes($folder, $file->getSizeBytes());
                $this->fileAccessLogService->logFolderRemoved($file, $this->getUser(), true);
            }

            $file->delete();
        }
        $this->em->flush();
    }

    /**
     * @param Files $file
     * @param bool $flush
     * 
     * @return void
     */
    public function _clearFileNotification(Files $file, bool $flush = false): void
    {
        if ($file->isFileRequest()) {
            $notification = $this->em->getRepository(Notifications::class)->findOneBy(
                [
                    'type' => NotificationType::FileRequestCompleted,
                    'resourceType' => NotificationResource::FileRequest,
                    'resourceId' => (string)$file->getFileRequest()->getId(),
                    'firm' => $this->getUser()->getUserProfile()->getFirm(),
                    'isRead' => false,
                ]
            );
            if (!is_null($notification)) {
                $this->notificationService->notifyRead($notification->getId());
            }
        } elseif ($file->hasSignatureRequests()) {
            foreach ($file->getSignatureRequests() as $sigRequest) {
                $notification = $this->em->getRepository(Notifications::class)->findOneBy(['type' => NotificationType::SignatureRequestCompleted, 'resourceId' => $sigRequest->getId(), 'isRead' => false]);

                if (!is_null($notification)) {
                    $this->notificationService->notifyRead($notification->getId());
                }
            }
        } elseif ($file->getVisibility() == Files::FIRM_ALL_CLIENTS) {
            // ag: if file is a public upload just clear the notification for the specific user
            $notification = $this->em->getRepository(Notifications::class)->findOneBy(
                [
                    'type' => NotificationType::FileUploaded,
                    'resourceType' => NotificationResource::File,
                    'resourceId' => (string)$file->getId(),
                    'firm' => $this->getUser()->getUserProfile()->getFirm(),
                    'recipientUser' => $this->getUser(),
                    'isRead' => false,
                ]
            );
            if (!is_null($notification)) {
                $this->notificationService->notifyRead($notification->getId());
            }
        } else {
            $this->notificationService->notifyReadFileUploaded($file, $this->getUser());
        }

        if ($flush) {
            $this->em->flush();
        }
    }
}
