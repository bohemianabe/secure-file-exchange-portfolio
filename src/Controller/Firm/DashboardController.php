<?php

namespace App\Controller\Firm;

use App\Controller\Traits\EntityActions;
use App\Entity\Clients;
use App\Entity\Files;
use App\Entity\FilesExportRequest;
use App\Entity\Folders;
use App\Entity\Notifications;
use App\Enum\FileType;
use App\Enum\NotificationEmailMode;
use App\Enum\NotificationResource;
use App\Enum\NotificationType;
use App\Form\FileFormType;
use App\Form\SendFilesFormType;
use App\Message\GenerateZipFileExport;
use App\Repository\FilesRepository;
use App\Security\Voter\FileVoter;
use App\Service\FilePreviewService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[IsGranted("ROLE_FIRM")]
final class DashboardController extends AbstractController
{
    use EntityActions;

    #[Route('/firm/dashboard', name: 'firm_dashboard')]
    public function firmDashboard(FilesRepository $fileRepo, SessionInterface $session, Request $request): Response
    {
        $userProfile = $this->getUser()->getUserProfile();
        $firm = $userProfile->getFirm();
        // ag: check if 2fa is part of the firm's plan then handle logic to show 2fa prompt modal to user
        $showTwoFactorPrompt = false;
        if ($firm->getStoragePlan()->getIsTwoFactorAuth()) {
            // Adjust this check to match your app's actual logic
            $hasTwoFactorEnabled = $this->getUser()->isTotpAuthenticationEnabled() || $this->getUser()->isEmailAuthEnabled();

            if (!$hasTwoFactorEnabled) {
                $session = $request->getSession();
                // $session->remove('admin_show_2fa_prompt');

                if (!$session->has('admin_show_2fa_prompt')) {
                    // 40% chance
                    $shouldShow = random_int(1, 100) <= 40;
                    $session->set('admin_show_2fa_prompt', $shouldShow);
                }

                $showTwoFactorPrompt = (bool) $session->get('admin_show_2fa_prompt', false);
            }
        }

        // ag: used storage including soft deleted files
        $remainingBytes = $firm->getRemainingStorageBytes();

        $totalFiles = count($fileRepo->findForFirmUserProfile($userProfile));

        $tz = new \DateTimeZone($firm->getTimezone());

        $todayStart = (new \DateTime('today', $tz))->setTime(0, 0, 0);
        $todayEnd = (new \DateTime('today', $tz))->setTime(23, 59, 59);
        $todaysFileActivity = count($fileRepo->findForFirmUserProfile($userProfile, null, $todayStart, $todayEnd));

        // ag: calculate files from last month
        $lastWeekStart = (new \DateTime('first day of last week'))->setTime(0, 0, 0);
        $lastWeekEnd   = (new \DateTime('last day of last week'))->setTime(23, 59, 59);
        $lastWeekActivity = count($fileRepo->findForFirmUserProfile($userProfile, null, $lastWeekStart, $lastWeekEnd));

        //ag: this month activity
        $thisWeekStart = (new \DateTime('first day of this week'))->setTime(0, 0, 0);
        $thisWeekActivity = count($fileRepo->findForFirmUserProfile($userProfile, null, $thisWeekStart, $todayStart));

        // ag: calculate firm percentage change from last month to this month
        $lastMonthStart = (new \DateTime('first day of last month'))->setTime(0, 0, 0);
        $lastMonthEnd   = (new \DateTime('last day of last month'))->setTime(23, 59, 59);
        $lastMonthActivityFirm = count($fileRepo->findForFirm($firm, null, $lastMonthStart, $lastMonthEnd));

        $thisMonthStart = (new \DateTime('first day of this month'))->setTime(0, 0, 0);
        $thisMonthEnd   = (new \DateTime('last day of this month'))->setTime(23, 59, 59);
        $thisMonthActivityFirm = count($fileRepo->findForFirm($firm, null, $thisMonthStart, $thisMonthEnd));

        $totalFilesFirmChange = $this->_percentChange($lastMonthActivityFirm, $thisMonthActivityFirm);

        $pageDataPointBasicsArray = $this->_pageDataPointBasics($fileRepo);
        $fullPageDataArray = array_merge($pageDataPointBasicsArray, [
            'user' => $this->getUser(),
            'remaining_storage' => $remainingBytes,
            'todays_file_activity' => $todaysFileActivity,
            'total_files' => $totalFiles,
            'this_week_file_activity' => $thisWeekActivity,
            'total_files_firm_percent_change' => $totalFilesFirmChange,
            'showTwoFactorPrompt' => $showTwoFactorPrompt,
            'modals_to_include' => []

        ]);
        // dd($fullPageDataArray);
        return $this->render(
            'firm_user_access/dashboard.html.twig',
            $fullPageDataArray
        );
    }

    #[Route('/firm/upload-file/modal', name: 'firm_upload_file_modal', methods: ['GET'])]
    #[Route('/firm/upload-file/{client}/modal', name: 'firm_upload_client_file_modal', methods: ['GET'])]
    #[Route('/firm/ajax/upload-file/modal/submit', name: 'firm_ajax_upload_file_modal_submit', methods: ['POST'])]
    public function firmUploadFile(Request $request, ?Clients $client): Response
    {
        $userProfile = $this->getUser()->getUserProfile();
        $firm = $userProfile->getFirm();
        $storagePlan = $userProfile->getFirm()->getStoragePlan();

        $sendFileForm = $this->createForm(SendFilesFormType::class, null, [
            'storage_plan' => $storagePlan->getName(),
        ]);

        // ag: get client list to populate in the file upload modal
        if ($userProfile->getUserType() == 'employee') {
            $assignedClients = $userProfile->getAssignedClients(true);
        } else {
            $assignedClients = $userProfile->getFirm()->getClients(true);
        }
        $customMessages = $userProfile->getCustomMessages();
        // ag: upload file logic
        if ($request->attributes->get('_route') == 'firm_ajax_upload_file_modal_submit') {
            $sendFileForm->handleRequest($request);

            if ($sendFileForm->isSubmitted() && $sendFileForm->isValid()) {
                /** @var Files[] $files */
                $files = $sendFileForm->get('files')->getData();
                $defaultEmailNotification = $request->request->get('default_email_notification') == '1' ? true : false;
                $writtenEmailNotification = $request->request->get('written_email_notification') == '1' ? true : false;
                $customMessageEmailNotification = $request->request->get('custom_message_id') == '1' ? true : false;

                $writtenMessage =  $request->request->get('written_email_notification_textarea') ? $request->request->get('written_email_notification_textarea') : null;

                $emailMsgData = [];
                if ($defaultEmailNotification) {
                    $emailMsgData = ['email_notification_type' => NotificationEmailMode::Default];
                }
                if ($writtenEmailNotification) {
                    $emailMsgData = ['email_notification_type' => NotificationEmailMode::Written, 'written_msg' => $writtenMessage];
                }
                if ($customMessageEmailNotification) {
                    $emailMsgData = ['email_notification_type' => NotificationEmailMode::CustomMessage, 'custom_message_id' => $request->request->get('custom_message_select')];
                }

                $recipientClient = $request->request->get('file_upload_recipient');
                $errors = array();

                // ag: find folder if set
                if (!empty($request->request->get('file_upload_assigned_folder'))) {
                    $folder = $this->em->getRepository(Folders::class)->findOneBy(['uuid' => $request->request->get('file_upload_assigned_folder')]);
                } else {
                    $folder = null;
                }

                foreach ($files as $key => $file) {
                    try {
                        // ag: file name is optional
                        if (!empty($request->request->get('file_name_' . $key))) {
                            $fileName = $request->request->get('file_name_' . $key);
                        } else {
                            $fileName = null;
                        }
                        $uploadedFile = $this->fileUploader->upload($file, $firm, $this->getUser(), $fileName, $recipientClient, $folder, null, null);
                    } catch (\Throwable $e) {
                        dd($e->getMessage());
                        $errors[] = sprintf(
                            'Failed to upload %s: %s',
                            $file->getClientOriginalName(),
                            $e->getMessage()
                        );

                        continue;
                    }
                    // ag: separate the notification service from the file upload so if one fails both don't always fail
                    try {
                        $this->notificationService->notifyFileUploaded($uploadedFile, $this->getUser(), $emailMsgData, $recipientClient);
                    } catch (\Throwable $e) {
                        $errors[] = $e->getMessage();
                        // don't fail the upload because notification failed
                    }
                }

                if (empty($errors)) {
                    $this->audit->log(
                        action: 'file.uploaded',
                        resourceType: 'Files',
                        resourceId: null,
                        success: true,
                        metadata: ['userEmail' => $this->getUser()->getEmail()],
                        severity: 'info'
                    );

                    return new JsonResponse([
                        'success' => true,
                        'message' => 'File(s) uploaded.',
                    ]);
                } else {
                    $this->audit->log(
                        action: 'file.uploaded',
                        resourceType: 'Files',
                        resourceId: null,
                        success: false,
                        metadata: ['user_id' => $this->getUser()->getId(), 'errors' => $errors],
                        severity: 'info'
                    );

                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Unable to upload one or more files. Please review, and contact an administrator.',
                    ]);
                }
            } else {
                $errors = $this->_getFormErrors($sendFileForm);
                $this->audit->log(
                    action: 'file.created',
                    resourceType: 'Files',
                    // resourceId: (string)$user->getId(),
                    success: false,
                    metadata: $errors,
                    severity: 'error'
                );
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Unable to create file. Please contact an administrator.',
                ]);
            }
        } elseif ($request->attributes->get('_route') == 'firm_upload_client_file_modal') {
            $folders = $client->getFolders()
                ->filter(
                    fn(Folders $folder) => $folder->getParent() === null
                );
            return $this->render('/layouts/modals/firm/upload-file.html.twig', [
                'userProfile' => $userProfile,
                'sendFileForm' => $sendFileForm->createView(),
                'assignedClients' => $assignedClients,
                'max_file_size' => $storagePlan->getMaxFileSizeLabel(),
                'customMessages' => $customMessages,
                'client' => $client,
                'folders' => $folders,
            ]);
        }
        return $this->render('/layouts/modals/firm/upload-file.html.twig', [
            'userProfile' => $userProfile,
            'sendFileForm' => $sendFileForm->createView(),
            'assignedClients' => $assignedClients,
            'max_file_size' => $storagePlan->getMaxFileSizeLabel(),
            'customMessages' => $customMessages,
        ]);
    }

    #[Route('/firm/export/{exportType}/all-files/modal', name: 'firm_export_all_files_modal', methods: ['GET'])]
    #[Route('/firm/export/{exportType}/all-files/submit', name: 'firm_export_all_files_submit', methods: ['GET', 'POST'])]
    #[Route('/firm/export/{exportType}/{client}/all-files/submit', name: 'firm_export_client_all_files_submit', methods: ['GET', 'POST'])]
    public function exportAllFiles(Request $request, string $exportType, ?Clients $client)
    {
        $user = $this->getUser();

        if ($request->attributes->get('_route') == 'firm_export_all_files_modal') {
            return $this->render('/layouts/modals/firm/download-all-files.html.twig', [
                'exportType' => $exportType,
            ]);
        } else {
            // ag: find if export request is already being proccessed
            $findPendingRequest = $this->em->getRepository(FilesExportRequest::class)->findByUser($user, FilesExportRequest::STATUS_PENDING);
            $findProcessingRequest = $this->em->getRepository(FilesExportRequest::class)->findByUser($user, FilesExportRequest::STATUS_PROCESSING);
            if (!empty($findPendingRequest) || !empty($findProcessingRequest)) {
                $this->addFlash('error', 'Pending file export already requested. Please wait until current request is completed.');
                return $this->redirect($request->headers->get('referer'));
            }
            try {
                // Create job record
                $export = new FilesExportRequest();
                $export->setUser($user);
                $export->setStatus(FilesExportRequest::STATUS_PENDING);

                $this->em->persist($export);
                $this->em->flush();

                if ($exportType == 'firm') {
                    $firmDownload = true;
                    $clientDownload = false;
                } elseif ($exportType == 'client') {
                    $firmDownload = false;
                    $clientDownload = true;
                } elseif ($exportType == 'user') {
                    $firmDownload = false;
                    $clientDownload = false;
                }

                // ag: check route if it's for client files pass in the clientId parameter.
                if ($request->attributes->get('_route') == 'firm_export_client_all_files_submit') {
                    // Dispatch async job
                    $this->messageBus->dispatch(new GenerateZipFileExport($export->getId(), $firmDownload, $clientDownload, $client->getId()));
                } else {
                    // Dispatch async job
                    $this->messageBus->dispatch(new GenerateZipFileExport($export->getId(), $firmDownload, $clientDownload));
                }

                $this->audit->log(
                    action: 'zipFileRequest.created',
                    resourceType: 'FilesExportRequest',
                    resourceId: $export->getId(),
                    success: true,
                    metadata: [],
                    severity: 'info',
                );

                $this->addFlash('success', 'Your export is being prepared. You will be notified when it is ready.');
                return $this->redirect($request->headers->get('referer'));
            } catch (\Throwable $e) {
                $this->audit->log(
                    action: 'zipFileRequest.created',
                    resourceType: 'FilesExportRequest',
                    resourceId: null,
                    success: false,
                    metadata: ['error' => $e->getMessage()],
                    severity: 'error',
                );

                $this->addFlash('error', 'Export is unavailable. Please contact an administrator.');
                return $this->redirect($request->headers->get('referer'));
            }
        }
    }

    #[Route('/firm/{file}/edit/modal', name: 'firm_file_edit_modal', methods: ['GET'])]
    #[Route('/firm/{file}/edit/modal/submit', name: 'firm_file_edit_modal_submit', methods: ['POST'])]
    #[Route('/firm/{file}/delete', name: 'firm_file_edit_delete', methods: ['GET'])]
    public function firmFileEdit(Request $request, #[MapEntity(mapping: ['file' => 'uuid'])] Files $file): Response
    {

        $fileForm = $this->createForm(FileFormType::class, $file);
        if ($request->attributes->get('_route') == 'firm_file_edit_modal_submit') {
            $this->denyAccessUnlessGranted(FileVoter::EDIT, $file);
            $fileForm->handleRequest($request);
            if ($fileForm->isSubmitted() && $fileForm->isValid()) {
                $this->fileAccessLogService->logEdited($file, $this->getUser(), false);

                $this->audit->log(
                    action: 'file.updated',
                    resourceType: 'file',
                    resourceId: $file->getId(),
                    success: true,
                    metadata: [],
                    severity: 'info',
                );

                $this->em->flush();
                $this->addFlash('success', 'File updated.');
                return $this->redirect($request->headers->get('referer'));
            } else {
                $errors = $this->_getFormErrors($fileForm);

                $this->audit->log(
                    action: 'file.updated',
                    resourceType: 'file',
                    resourceId: $file->getId(),
                    success: false,
                    metadata: $errors,
                    severity: 'error',
                );
                $this->addFlash('error', 'Unable to update file. Please contact an administrator.');
                return $this->redirect($request->headers->get('referer'));
            }
        } elseif ($request->attributes->get('_route') == 'firm_file_edit_delete') {
            $this->denyAccessUnlessGranted(FileVoter::DELETE, $file);
            try {
                $this->_softDeleteFiles(array($file->getId()));

                $this->audit->log(
                    action: 'file.softDeleted',
                    resourceType: 'file',
                    resourceId: $file->getId(),
                    success: true,
                    metadata: [],
                    severity: 'info',
                );

                $this->addFlash('success', 'File deleted');
                return $this->redirect($request->headers->get('referer'));
            } catch (\Throwable $e) {
                // dd($e->getMessage());
                $this->audit->log(
                    action: 'file.softDeleted',
                    resourceType: 'file',
                    resourceId: $file->getId(),
                    success: false,
                    metadata: ['error' => $e->getMessage()],
                    severity: 'error',
                );
                $this->addFlash('error', 'Unable to delete file. Please contact an administrator.');
                return $this->redirect($request->headers->get('referer'));
            }
        }
        $this->denyAccessUnlessGranted(FileVoter::EDIT, $file);
        $nonPreviewableFiles = FileType::getNonPreviewableExtensions();
        return $this->render('/layouts/modals/firm/edit-file.html.twig', [
            'file' => $file,
            'fileForm' => $fileForm->createView(),
            'nonPreviewableFiles' => $nonPreviewableFiles,
        ]);
    }

    #[Route('/firm/files/download', name: 'firm_files_download')]
    public function firmDownloadFiles(Request $request): Response
    {
        // ag: safety check so only an auth user can reach here.
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $ids = array_filter(array_map('intval', explode(',', (string) $request->query->get('ids', ''))));
        if ($ids === []) {
            throw $this->createNotFoundException('No files selected.');
        }

        $files = $this->em->getRepository(Files::class)->findBy(['idFile' => $ids]);

        if (count($files) !== count($ids)) {
            throw $this->createAccessDeniedException('One or more files were not found.');
        }

        // If only 1 file selected → direct download
        if (count($files) === 1) {
            $file = $files[0];
            $this->denyAccessUnlessGranted(FileVoter::DOWNLOAD, $file);
            // ag: check if file is a file_request upload and make that notification as read
            $this->_clearFileNotification($file);
            $this->fileAccessLogService->logDownloaded($file, $this->getUser(), false);
            $this->em->flush();

            $this->audit->log(
                action: 'file.download',
                resourceType: 'Files',
                resourceId: (string) $file->getId(),
                success: true,
                metadata: [
                    'original_name' => $file->getOriginalName(),
                    'storage_path' => $file->getStoragePath(),
                ],
                severity: 'info'
            );

            return $this->file($this->params->get('file_upload_dir') . '/' . $file->getStoragePath(), $file->getOriginalName());
        }

        // Multi-file → ZIP
        $zipName = 'files-' . date('YmdHis') . '.zip';
        $zipPath = sys_get_temp_dir() . '/' . $zipName;

        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);

        /** @var Files $f */
        foreach ($files as $f) {
            $this->denyAccessUnlessGranted(FileVoter::DOWNLOAD, $f);
            $this->fileAccessLogService->logDownloaded($f, $this->getUser(), false);
            $this->_clearFileNotification($f);

            $zip->addFile($this->params->get('file_upload_dir') . '/' .  $f->getStoragePath(), $f->getOriginalName());
        }

        $zip->close();

        $this->audit->log(
            action: 'file.download.bulk',
            resourceType: 'Files',
            resourceId: implode(',', array_map(fn(Files $f) => (string) $f->getId(), $files)),
            success: true,
            metadata: [
                'count' => count($files),
                'zip_name' => $zipName,
            ],
            severity: 'info'
        );
        // ag: flush for the notifyReadFileUploaded & fileAccessLogs
        $this->em->flush();
        return $this->file($zipPath, $zipName)->deleteFileAfterSend(true);
    }

    #[Route('/firm/{file}/preview', name: 'firm_file_preview', methods: ['GET'])]
    public function firmFilePreview(#[MapEntity(mapping: ['file' => 'uuid'])] Files $file, FilePreviewService $filePreviewService)
    {
        $this->denyAccessUnlessGranted(FileVoter::PREVIEW, $file);

        // ag: check file type if the browser can preview natively or needs to be converted into a preview file
        $extension = strtolower(pathinfo($file->getOriginalName(), PATHINFO_EXTENSION));
        $inlineExtensions = FileType::getBrowserPreviewableExtensions();

        try {
            $this->fileAccessLogService->logPreviewed($file, $this->getUser(), true);
            $this->_clearFileNotification($file, true);

            // ag: log event
            $this->audit->log(
                action: 'file.preview',
                resourceType: 'Files',
                resourceId: (string) $file->getId(),
                success: true,
                metadata: [],
                severity: 'info',
            );

            if (in_array($extension, $inlineExtensions, true)) {
                return $this->file(
                    $this->params->get('file_upload_dir') . '/' . $file->getStoragePath(),
                    $file->getOriginalName(),
                    ResponseHeaderBag::DISPOSITION_INLINE
                );
            }

            $officeExtensions = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
            if (in_array($extension, $officeExtensions, true)) {
                $previewPath = $filePreviewService->getPreviewPath($file);

                return $this->file(
                    $previewPath,
                    pathinfo($this->params->get('file_upload_dir') . '/' . $file->getOriginalName(), PATHINFO_FILENAME) . '.pdf',
                    ResponseHeaderBag::DISPOSITION_INLINE
                )->deleteFileAfterSend(true);
            }

            throw $this->createNotFoundException('This file type cannot be previewed.');
        } catch (\Throwable $e) {
            // ag: log event
            $this->audit->log(
                action: 'file.preview',
                resourceType: 'Files',
                resourceId: (string) $file->getId(),
                success: false,
                metadata: ['error' => $e->getMessage()],
                severity: 'error',
            );
        }
    }

    // ag: endpoint (from email) for the zip file requested with all of the firm's files
    #[Route('/firm/files/export/{uuid}/download', name: 'files_export_download')]
    public function firmDownloadSelectedFiles(#[MapEntity(mapping: ['uuid' => 'uuid'])] FilesExportRequest $export): Response
    {
        try {
            $user = $this->getUser();
            // $export = $this->em->getRepository(FilesExportRequest::class)->findOneBy(['uuid' => $uuid]);

            if (!$export || $export->getUser() !== $user) {
                throw $this->createAccessDeniedException('Unauthorized download.');
            }

            if ($export->getStatus() !== FilesExportRequest::STATUS_COMPLETED) {
                throw $this->createNotFoundException('Export not ready.');
            }

            $path = $export->getZipPath();

            // ag: check file exist. It may have been deleted after it's first download
            if (!is_file($path)) {
                $this->addFlash('error', 'Zip file does not exist. Please contact an administrator.');
                return $this->redirectToRoute('firm_dashboard');
            }

            // ag: change status to downloaded
            $export->setZipDownloaded(true)
                ->setZipRemoved(true);
            // ag: find notification and then mark it as read
            $notification = $this->em->getRepository(Notifications::class)->findOneBy(['recipientUser' => $this->getUser(), 'resourceType' => NotificationResource::FileExportRequest, 'resourceId' => $export->getId(), 'isRead' => false]);
            if ($notification) {
                $notification->setIsRead(true);
            }
            $this->em->flush();

            $this->audit->log(
                action: 'zipFileRequest.downloaded',
                resourceType: 'FilesExportRequest',
                resourceId: $export->getId(),
                success: true,
                metadata: [],
                severity: 'info',
            );

            return $this->file($path)->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            // dd($e->getMessage());
            $this->audit->log(
                action: 'zipFileRequest.downloaded',
                resourceType: 'FilesExportRequest',
                resourceId: $export->getId(),
                success: false,
                metadata: ['error' => $e->getMessage()],
                severity: 'error',
            );

            $this->addFlash('error', 'Unable to download file. Please contact an administrator.');
            return $this->redirectToRoute('firm_dashboard');
        }
    }
}
