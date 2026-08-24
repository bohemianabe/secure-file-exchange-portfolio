<?php

namespace App\Controller\Firm;

use App\Controller\Traits\EntityActions;
use App\Entity\Clients;
use App\Entity\FileAccessLogs;
use App\Entity\Files;
use App\Entity\Firms;
use App\Entity\Folders;
use App\Entity\FoldersExportRequest;
use App\Enum\FileType;
use App\Enum\FolderRequestStatus;
use App\Form\FolderFormType;
use App\Message\GenerateZipFolderExport;
use App\Repository\FilesRepository;
use App\Security\Voter\ClientVoter;
use App\Security\Voter\FileVoter;
use App\Security\Voter\FolderVoter;
use App\Utils\FileSizeFormatter;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FileManagerController extends AbstractController
{
    use EntityActions;

    #[Route('/firm/file-manager', name: 'firm_file_manager')]
    public function firmFileManagerView(Request $request, FilesRepository $fileRepo, FileSizeFormatter $fileSizeFormatter): Response
    {
        $clientUuid = $request->query->get('client');

        $client = null;
        if ($clientUuid) {
            $client = $this->em->getRepository(Clients::class)->findOneBy(['uuid' => $clientUuid]);
            if (!$client) {
                throw $this->createNotFoundException();
            }
            $this->denyAccessUnlessGranted(ClientVoter::VIEW, $client);
        }

        $userProfile = $this->getUser()->getUserProfile();
        $firm = $userProfile->getFirm();
        if (in_array($userProfile->getUserType(), ['primary', 'admin'], true)) {
            $clients = $firm->getClients();
            $fileBreakdowns = $fileRepo->getFirmFileTypeBreakdown($firm, null);
        } else {
            $clients = $userProfile->getAssignedClients();
            $fileBreakdowns = $fileRepo->getFirmFileTypeBreakdown($firm, $userProfile, $clients->toArray());
        }
        $totalBytes = $firm->getStoragePlan()->getStorage(); // from your StoragePlans entity
        $usedBytes = $this->em->getRepository(Firms::class)->getUsedStorageBytesIncludingDeleted($firm);
        $usagePercent = $totalBytes > 0 ? ($usedBytes / $totalBytes) * 100 : 0;

        $fileBreakdowns = array_map(function (array $item) use ($fileSizeFormatter) {
            $type = FileType::fromExtension($item['fileType']);
            return [
                'fileType' => $item['fileType'],
                'fileCount' => $item['fileCount'],
                // 'totalBytes' => (int) $item['totalBytes'],
                'totalBytesHuman' => $fileSizeFormatter->format((int) $item['totalBytes']),
                'icon' => $type->icon(),
                'bgClass' => $type->getBackgroundClass(),
                // 'displayName' => $type->getDisplayName(),
            ];
        }, $fileBreakdowns);

        // ag: only get root folders, not children
        if ($client) {
            $folders = $this->em->getRepository(Folders::class)->findBy(['firm' => $firm, 'client' => $client, 'parent' => null], ['createdAt' => 'DESC']);
            $fileBreakdowns = $fileRepo->getClientFileTypeBreakdown($client);
            $fileBreakdowns = array_map(function (array $item) use ($fileSizeFormatter) {
                $type = FileType::fromExtension($item['fileType']);
                return [
                    'fileType' => $item['fileType'],
                    'fileCount' => $item['fileCount'],
                    'totalBytesHuman' => $fileSizeFormatter->format((int) $item['totalBytes']),
                    'icon' => $type->icon(),
                    'bgClass' => $type->getBackgroundClass(),
                ];
            }, $fileBreakdowns);
            $usedBytes = $this->em->getRepository(Clients::class)->getUsedStorageBytesIncludingDeleted($client);
            $usagePercent = $totalBytes > 0 ? ($usedBytes / $totalBytes) * 100 : 0;
        }
        $pageDataPointBasicsArray = $this->_pageDataPointBasics($fileRepo);
        $fullPageDataArray = array_merge($pageDataPointBasicsArray, [
            'firm' => $firm,
            'folders' => $folders ?? null,
            'clients' => $clients,
            'client' => $client,
            'selectedClient' => $client,
            'usagePercent' => $usagePercent,
            'fileBreakdowns' => $fileBreakdowns,
        ]);

        return $this->render('firm_user_access/file-manager.html.twig', $fullPageDataArray);
    }

    #[Route('/firm/edit/{folder}/modal', name: 'firm_edit_folder_modal', methods: ['GET'])]
    #[Route('/firm/delete/{folder}/modal/submit', name: 'firm_delete_folder_modal_submit', methods: ['GET'])]
    public function firmEditFolder(Request $request, #[MapEntity(mapping: ['folder' => 'uuid'])] Folders $folder)
    {
        $firm = $folder->getFirm();
        $client = $folder->getClient();
        // ag: find unassigned files for admins
        $orphanFiles = $this->em->getRepository(Files::class)->findBy(['folder' => null, 'firm' => $firm, 'client' => $client], ['createdAt' => 'DESC']);
        if ($request->attributes->get('_route') == 'firm_edit_folder_modal') {
            $nonPreviewableFiles = FileType::getNonPreviewableExtensions();
            return $this->render('/layouts/modals/firm/edit-folder.html.twig', [
                'folder' => $folder,
                'rootFolder' => $folder->getTopParent(),
                'unassignedFiles' => $orphanFiles,
                'nonPreviewableFiles' => $nonPreviewableFiles,
            ]);
        } elseif ($request->attributes->get('_route') == 'firm_delete_folder_modal_submit') {
            try {
                // ag: if files selected remove them
                if ($folder->hasFiles()) {
                    foreach ($folder->getFiles() as $file) {
                        if ($file) {
                            $file->setFolder(null);
                            $file->delete();
                        }
                    }
                }

                $folder->clearAllAssignedFiles();
                $this->em->remove($folder);

                // ag: log event
                $this->audit->log(
                    action: 'folder.deleted',
                    resourceType: 'Folders',
                    resourceId: (string)$folder->getId(),
                    success: true,
                    metadata: [],
                    severity: 'info',
                );

                $this->em->flush();
                $this->addFlash('success', 'Folder deleted.');
                return $this->redirect($request->headers->get('referer'));
            } catch (\Throwable $e) {
                // ag: log event
                $this->audit->log(
                    action: 'folder.deleted',
                    resourceType: 'Folders',
                    resourceId: (string)$folder->getId(),
                    success: false,
                    metadata: ['error' => $e->getMessage()],
                    severity: 'error',
                );

                $this->addFlash('error', 'Unable to delete folder. Please contact an administrator.');
                return $this->redirect($request->headers->get('referer'));
            }
        }
    }

    #[Route('/firm/add/folder/{client}/modal', name: 'firm_add_folder_modal', methods: ['GET'])]
    #[Route('/firm/add/folder/{client}/modal/submit', name: 'firm_add_folder_modal_submit', methods: ['POST'])]
    public function firmCreateNewClientFolder(Request $request, Clients $client): Response
    {
        $unassignedFiles = new ArrayCollection;
        // ag: loop through the files and populate new array for files that are unassigned to any folder.
        foreach ($client->getFiles() as $file) {
            if (!$file->hasFolder()) {
                $unassignedFiles[] = $file;
            }
        }
        $newFolder = new Folders();
        $folderForm = $this->createForm(FolderFormType::class, $newFolder, [
            'files' => $unassignedFiles,
        ]);
        if ($request->attributes->get('_route') == 'firm_add_folder_modal_submit') {
            $folderFormArr = $request->request->all()['folder_form'];
            $assignedFiles = $folderFormArr['files'] ?? null;
            // ag: first create folder
            $folderForm->handleRequest($request);
            if ($folderForm->isSubmitted() && $folderForm->isValid()) {
                try {
                    $userProfile = $this->getUser()->getUserProfile();
                    $newFolder->setFirm($userProfile->getFirm())
                        ->setClient($client)
                        ->setCreatedByUser($this->getUser());

                    $this->em->persist($newFolder);

                    if (!is_null($assignedFiles)) {
                        foreach ($assignedFiles as $file) {
                            $file = $this->em->getRepository(Files::class)->find($file);
                            $newFolder->assignFile($file);
                            $newFolder->incrementSizeBytes($file->getSizeBytes());
                        }
                    }
                    // ag: log event
                    $this->audit->log(
                        action: 'folder.created',
                        resourceType: 'Folders',
                        resourceId: (string)$newFolder->getId(),
                        success: true,
                        metadata: [],
                        severity: 'info',
                    );

                    $this->em->flush();
                    $this->addFlash('success', 'Folder created.');
                    return $this->redirect($request->headers->get('referer'));
                } catch (\Throwable $e) {
                    // ag: log event
                    $this->audit->log(
                        action: 'folder.created',
                        resourceType: 'Folders',
                        resourceId: (string)$newFolder->getId(),
                        success: false,
                        metadata: ['error' => $e->getMessage()],
                        severity: 'error',
                    );

                    $this->addFlash('error', 'Unable to create folder. Please contact an administrator.');
                    return $this->redirect($request->headers->get('referer'));
                }
            } else {
                $errors = $this->_getFormErrors($folderForm);

                $this->audit->log(
                    action: 'folder.updated',
                    resourceType: 'Folders',
                    // resourceId: (string)$folder->getId(),
                    success: false,
                    metadata: $errors,
                    severity: 'error'
                );
                $this->addFlash('error', 'There was an error with the folder form submission. Please contact an administrator.');
                return $this->redirect($request->headers->get('referer'));
            }
        }
        return $this->render('/layouts/modals/firm/add-folder.html.twig', [
            'folderForm' => $folderForm->createView(),
            'client' => $client,
        ]);
    }

    #[Route('/firm/ajax/create/subfolder', name: 'firm_ajax_create_subfolder')]
    public function firmCreateSubFolder(Request $request): JsonResponse
    {
        $subFolderName = preg_replace("/[^a-zA-Z0-9\s]/", "", $request->request->all()['folderName']);
        $parentFolder = $this->em->getRepository(Folders::class)->findOneBy(['uuid' => $request->request->all()['folderId']]);

        $maxDepth = 5;
        if ($parentFolder->getDepth() >= $maxDepth) {
            return $this->json([
                'success' => false,
                'message' => 'You have reached the maximum folder depth.'
            ], 400);
        }
        $html = '';
        $message = '';
        $success = false;
        $folderUuid = '';
        try {
            $this->denyAccessUnlessGranted(FolderVoter::EDIT, $parentFolder);
            $subFolder = new Folders();
            $subFolder->setName($subFolderName)
                ->setPosition($parentFolder->getChildren()->count())
                ->setFirm($parentFolder->getFirm())
                ->setClient($parentFolder->getClient())
                ->setCreatedByUser($this->getUser())
                ->setParent($parentFolder);

            // ag: update the subFolder in the object memory immediately
            $parentFolder->addChild($subFolder);
            $this->em->persist($subFolder);
            $this->em->flush();

            $rootFolder = $parentFolder->getTopParent();
            $orphanFiles = $this->em->getRepository(Files::class)->findBy(['folder' => null, 'firm' => $parentFolder->getFirm(), 'client' => $parentFolder->getClient()], ['createdAt' => 'DESC']);
            $nonPreviewableFiles = FileType::getNonPreviewableExtensions();
            $html = $this->renderView('/layouts/sections/edit-folder-modal-content.html.twig', [
                'folder' => $subFolder,
                'rootFolder' => $rootFolder,
                'unassignedFiles' => $orphanFiles,
                'nonPreviewableFiles' => $nonPreviewableFiles,
            ]);
            $folderUuid = $subFolder->getUuid();
            $success = true;
            $this->audit->log(
                action: 'folder.createSubFolder',
                resourceType: 'Folders',
                resourceId: (string) $subFolder->getId(),
                success: false,
                metadata: [],
                severity: 'info'
            );
        } catch (\Throwable $e) {
            $message = 'Unable to created sub folder. Please contact an administrator.';
            $this->audit->log(
                action: 'folder.createSubFolder',
                resourceType: 'Folders',
                resourceId: (string) $parentFolder->getId(),
                success: false,
                metadata: ['error' => $e->getMessage()],
                severity: 'error'
            );
        }
        return new JsonResponse([
            'success' => $success,
            'message' => $message,
            'html' => $html,
            'folderUuid' => $folderUuid,
        ]);
    }

    #[Route('/firm/ajax/folder/remove/file', name: 'firm_ajax_folder_remove_file', methods: ['POST'])]
    public function firmFolderRemoveFile(Request $request): JsonResponse
    {
        $folderId = $request->request->all()['folderId'];
        $folder = $this->em->getRepository(Folders::class)->findOneBy(['uuid' => $folderId]);
        $this->denyAccessUnlessGranted(FolderVoter::EDIT, $folder);
        $fileId = $request->request->all()['fileId'];
        $file = $this->em->getRepository(Files::class)->findOneBy(['uuid' => $fileId]);

        try {
            $folder->removeAssignedFile($file);
            $folder->decrementSizeBytes($file->getSizeBytes());
            // ag: increment folder tree size
            $this->_decrementParentTreeFolderSizeBytes($folder, $file->getSizeBytes());
            $this->fileAccessLogService->logFolderRemoved($file, $this->getUser(), true);
            $success = true;
            $message = 'File removed.';
            // ag: log event
            $this->audit->log(
                action: 'folder.removeFile',
                resourceType: 'Folders',
                resourceId: (string) $folderId,
                success: true,
                metadata: ['file_id' => $fileId],
                severity: 'info',
            );
        } catch (\Throwable $e) {
            $this->audit->log(
                action: 'folder.removeAssignedFile',
                resourceType: 'Folders',
                resourceId: (string)$folder->getId(),
                success: false,
                metadata: ['error' => $e->getMessage(), 'file_id' => $fileId],
                severity: 'error'
            );

            $success = false;
            $message = 'Unable to remove file from folder. Contact an administrator.';
        }

        return new JsonResponse([
            'success' => $success,
            'message' => $message,
            'removedFileData' => [
                'fileId' => $fileId,
                'fileName' => $file->getName() ?? $file->getOriginalName(),
                'sizeHuman' => $file->getSizeHuman(),
            ],
            'folderUuid' => $folder->getUuid(),
        ]);
    }

    #[Route('/firm/ajax/folder/add/files', name: 'firm_ajax_folder_add_files', methods: ['POST'])]
    public function firmFolderAddFile(Request $request): JsonResponse
    {
        $folder = $this->em->getRepository(Folders::class)->findOneBy(['uuid' => $request->request->all()['folderId']]);
        $ids = array_filter(array_map('intval', explode(',', (string) $request->request->all()['fileIds'])));
        $files = $this->em->getRepository(Files::class)->findBy(['idFile' => $ids]);
        $nonPreviewableFiles = FileType::getNonPreviewableExtensions();

        try {
            $this->denyAccessUnlessGranted(FolderVoter::EDIT, $folder);
            // ag: make sure user has access to the file
            $html = '';
            foreach ($files as $key => $file) {
                $this->denyAccessUnlessGranted(FileVoter::ACCESS, $file);

                if ($file->getFirm() !== $folder->getFirm()) {
                    throw $this->createAccessDeniedException();
                }

                // ag: keeps from public files being assigned
                if ($file->getClient() !== $folder->getClient()) {
                    throw $this->createAccessDeniedException();
                }
                $folder->assignFile($file);
                $folder->incrementSizeBytes($file->getSizeBytes());
                // ag: increment folder tree size
                $this->_incrementParentTreeFolderSizeBytes($folder, $file->getSizeBytes());
                $this->fileAccessLogService->logFolderAdded($file, $this->getUser(), true);

                $html .= $this->renderView(
                    '/layouts/sections/partials/_file-row.html.twig',
                    [
                        'file' => $file,
                        'folder' => $folder,
                        'nonPreviewableFiles' => $nonPreviewableFiles,
                    ]
                );
            }
            $this->em->flush();
            $this->audit->log(
                action: 'folder.assignedFiles',
                resourceType: 'Folders',
                resourceId: (string)$folder->getId(),
                success: true,
                metadata: [],
                severity: 'info'
            );

            return new JsonResponse([
                'success'       => true,
                'message'       => 'File(s) added!',
                'html'          => $html,
                'fileCount'     => count($files),
                'folderUuid'    => $folder->getUuid(),
            ]);
        } catch (\Throwable $e) {
            $this->audit->log(
                action: 'folder.assignedFiles',
                resourceType: 'Folders',
                resourceId: (string)$folder->getId(),
                success: false,
                metadata: ['error' => $e->getMessage()],
                severity: 'error'
            );

            return new JsonResponse([
                'success' => false,
                'message' => 'Unable to assign files. Contact an administrator.',
            ]);
        }
    }

    #[Route('/firm/ajax/open/folder', name: 'firm_ajax_open_folder', methods: ['POST'])]
    public function firmOpenFolder(Request $request): JsonResponse
    {
        $folderId = $request->request->all()['folderId'];
        $currentFolderId = $request->request->all()['currentFolderId'];

        if ($folderId !== $currentFolderId) {
            $folder = $this->em->getRepository(Folders::class)->findOneBy(['uuid' => $folderId]);
            // ag: access to the root folder can make changes to all subfolders
            $this->denyAccessUnlessGranted(FolderVoter::EDIT, $folder->getTopParent());
            try {
                $firm = $folder->getFirm();
                $client = $folder->getClient();
                $orphanFiles = $this->em->getRepository(Files::class)->findBy(['folder' => null, 'firm' => $firm, 'client' => $client], ['createdAt' => 'DESC']);
                $nonPreviewableFiles = FileType::getNonPreviewableExtensions();
                $html = $this->renderView('/layouts/sections/edit-folder-modal-content.html.twig', [
                    'folder' => $folder,
                    'rootFolder' => $folder->getTopParent(),
                    'unassignedFiles' => $orphanFiles,
                    'nonPreviewableFiles' => $nonPreviewableFiles,
                ]);

                return new JsonResponse([
                    'success' => true,
                    'html' => $html,
                    'folderUuid' => $folder->getUuid(),
                ]);
            } catch (\Throwable $e) {
                $this->audit->log(
                    action: 'folder.openFolder',
                    resourceType: 'Folders',
                    resourceId: (string)$folderId,
                    success: false,
                    metadata: ['error' => $e->getMessage()],
                    severity: 'error',
                );
                return $this->json([
                    'success' => false,
                    'message' => 'Unable to open folder. Please contact an administrator.'
                ], 500);
            }
        }
        return $this->json([
            'success' => false,
        ], 400);
    }

    #[Route('/firm/ajax/delete/folder', name: 'firm_ajax_delete_folder', methods: ['POST'])]
    public function firmDeleteFolder(Request $request): JsonResponse
    {
        $folder = $this->em->getRepository(Folders::class)->findOneBy(['uuid' => $request->request->all()['folderId']]);
        $success = false;
        $message = '';
        $closeModal = false;
        $parentFolderHtml = '';
        $parentFolderUuid = '';
        $deletedFolderUuid = '';
        try {
            $deletedFolderUuid = $folder->getUuid();
            // ag: first delete files inside the folder
            if ($folder->getParent()) {
                $parentFolder = $folder->getParent();
                $orphanFiles = $this->em->getRepository(Files::class)->findBy(['folder' => null, 'firm' => $folder->getFirm(), 'client' => $folder->getClient()], ['createdAt' => 'DESC']);
                $nonPreviewableFiles = FileType::getNonPreviewableExtensions();

                $parentFolderHtml = $this->renderView('/layouts/sections/edit-folder-modal-content.html.twig', [
                    'folder' => $parentFolder,
                    'rootFolder' => $parentFolder->getTopParent(),
                    'unassignedFiles' => $orphanFiles,
                    'nonPreviewableFiles' => $nonPreviewableFiles,
                ]);

                $parentFolderUuid = $parentFolder->getUuid();
            }
            $this->_deleteFolderTree($folder);
            $this->em->flush();

            $success = true;
            $closeModal = true;
            $message = 'Folder deleted.';

            $this->audit->log(
                action: 'folder.deleted',
                resourceType: 'Folders',
                resourceId: (string) $folder->getId(),
                success: true,
                metadata: [],
                severity: 'info',
            );
        } catch (\Throwable $e) {
            $message = 'Unable to delete folder. Please contact an administrator.';
            $this->audit->log(
                action: 'folder.delete',
                resourceType: 'Folders',
                resourceId: (string) $folder->getId(),
                success: false,
                metadata: ['error' => $e->getMessage()],
                severity: 'error',
            );
        }
        return new JsonResponse([
            'success' => $success,
            'message' => $message,
            'closeModal' => $closeModal,
            'parentFolderHtml' => $parentFolderHtml,
            'parentFolderUuid' => $parentFolderUuid,
            'deletedFolderUuid' => $deletedFolderUuid,
        ]);
    }

    #[Route('/firm/ajax/folder/export/start', name: 'firm_folder_export_start', methods: ['POST'])]
    public function startFolderExport(Request $request): JsonResponse
    {
        $folderUuid = $request->request->get('folderId');

        $folder = $this->em
            ->getRepository(Folders::class)
            ->findOneBy(['uuid' => $folderUuid]);

        if (!$folder) {
            return $this->json([
                'success' => false,
                'message' => 'Folder not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(FolderVoter::VIEW, $folder);

        try {
            $export = (new FoldersExportRequest())
                ->setFolder($folder)
                ->setCreatedByUser($this->getUser())
                ->setStatus(FolderRequestStatus::Pending)
                ->setProgress(0);

            $this->em->persist($export);
            $this->em->flush();

            $this->messageBus->dispatch(
                new GenerateZipFolderExport($export->getId())
            );

            $this->audit->log(
                action: 'folder.export.started',
                resourceType: 'Folders',
                resourceId: (string) $folder->getId(),
                success: true,
                metadata: ['export_uuid' => (string) $export->getUuid()],
                severity: 'info',
            );

            return $this->json([
                'success' => true,
                'exportUuid' => $export->getUuid(),
            ]);
        } catch (\Throwable $e) {

            $this->audit->log(
                action: 'folder.exportStarted',
                resourceType: 'Folders',
                resourceId: (string) $folder->getId(),
                success: false,
                metadata: ['error' => $e->getMessage()],
                severity: 'error',
            );

            return $this->json([
                'success' => false,
                'message' => 'Unable to start the folder export. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/firm/ajax/folder/export/status/{uuid}', name: 'firm_folder_export_status', methods: ['GET'])]
    public function folderExportStatus(FoldersExportRequest $export): JsonResponse
    {
        if ($export->getCreatedByUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        try {
            $downloadUrl = null;

            if ($export->getStatus() === FolderRequestStatus::Completed) {
                $downloadUrl = $this->generateUrl(
                    'firm_folder_export_download',
                    [
                        'uuid' => $export->getUuid(),
                    ]
                );
            }

            return $this->json([
                'success' => true,
                'status' => $export->getStatus(),
                'progress' => $export->getProgress(),
                'downloadUrl' => $downloadUrl,
            ]);
        } catch (\Throwable $e) {

            // ag: since this endpoint is a polling and called multiple times only audit failed calls.
            $this->audit->log(
                action: 'folder.exportStatus',
                resourceType: 'FoldersExportRequest',
                resourceId: (string) $export->getId(),
                success: false,
                metadata: ['error' => $e->getMessage()],
                severity: 'error',
            );

            return $this->json([
                'success' => false,
                'message' => 'Unable to retrieve folder export status.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/firm/folder/export/download/{uuid}', name: 'firm_folder_export_download', methods: ['GET'])]
    public function downloadFolderExport(FoldersExportRequest $export): Response
    {
        if ($export->getCreatedByUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($export->getStatus() !== FolderRequestStatus::Completed || !$export->getZipPath()) {
            throw $this->createNotFoundException();
        }

        if (!is_file($export->getZipPath())) {
            throw $this->createNotFoundException();
        }

        $fileName = $export->getFolder()->getName() . '.zip';

        return $this->file($export->getZipPath(), $fileName)
            ->deleteFileAfterSend(true);
    }

    #[Route('/firm/{file}/accesslog/modal', name: 'firm_file_accesslog_modal', methods: ['GET'])]
    public function firmFileAccesslog(#[MapEntity(mapping: ['file' => 'uuid'])] Files $file): Response
    {

        $this->denyAccessUnlessGranted(FileVoter::ACCESS, $file);
        try {
            $accessLogs = $this->em->getRepository(FileAccessLogs::class)->findBy(['file' => $file], ['createdAt' => 'DESC']);

            return $this->render(
                '/layouts/sections/partials/_access-log-canvas-modal.html.twig',
                [
                    'firm' => $file->getFirm(),
                    'file' => $file,
                    'accessLogs' => $accessLogs,
                ]
            );
        } catch (\Throwable $e) {
            $this->audit->log(
                action: 'fileAccessLog.modal',
                resourceType: 'FileAccessLogs',
                // resourceId: (string) $request->request->all()['folderId'],
                success: false,
                metadata: ['error' => $e->getMessage()],
                severity: 'error',
            );

            return $this->render(
                '/layouts/sections/partials/_access-log-canvas-error.html.twig',
                [
                    'message' => 'The file access history could not be loaded. Please try again.',
                ],
                new Response(status: Response::HTTP_INTERNAL_SERVER_ERROR)
            );
        }
    }
}
