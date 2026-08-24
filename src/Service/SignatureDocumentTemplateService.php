<?php

namespace App\Service;

use App\Entity\SignatureDocumentTemplates;
use App\Service\PdfFileUploader;
use App\Service\PdfPreviewService;
use App\Service\PdfThumbnailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

// ag: service for uploading a pdf to the template gallery and ensuing logic
final class SignatureDocumentTemplateService
{
    public function __construct(
        private PdfFileUploader $pdfFileUploader,
        private PdfThumbnailService $pdfThumbnailService,
        private PdfPreviewService $pdfPreviewService,
        private EntityManagerInterface $em,
    ) {}

    public function update(SignatureDocumentTemplates $template, ?UploadedFile $replacementFile = null): SignatureDocumentTemplates
    {
        if ($replacementFile !== null) {
            $this->replaceDocument($template, $replacementFile);
        }

        $this->em->flush();

        return $template;
    }

    private function replaceDocument(SignatureDocumentTemplates $template, UploadedFile $file): void
    {
        // Save old key before changing anything
        $oldStorageKey = $template->getStorageKey();

        $originalName = $file->getClientOriginalName();
        $sizeBytes = $file->getSize();

        /*
         * Upload new PDF first.
         *
         * Don't delete the old files yet. If generation of the new
         * assets fails, you still have the existing working template.
         */
        $newStorageKey = $this->pdfFileUploader->upload($file, $template->getName());

        try {
            $thumbnailFileName =
                $this->pdfThumbnailService->create(
                    $newStorageKey
                );

            $pdfService =
                $this->pdfPreviewService->create(
                    $newStorageKey
                );
        } catch (\Throwable $e) {
            // New upload failed partway through.
            // Clean up whatever was created.
            $this->deleteAssets($newStorageKey);

            throw $e;
        }

        /*
         * New PDF + thumbnail + previews now exist.
         * Safe to remove old assets.
         */
        if ($oldStorageKey !== null) {
            $this->deleteAssets($oldStorageKey);
        }

        $template
            ->setStorageKey(
                pathinfo($newStorageKey, PATHINFO_FILENAME)
            )
            ->setOriginalName($originalName)
            ->setSizeBytes($sizeBytes)
            ->setPageCount($pdfService['pageCount']);
    }

    public function deleteAssets(string $storageKey): void
    {
        $this->pdfFileUploader->delete($storageKey);
        $this->pdfThumbnailService->delete($storageKey);
        $this->pdfPreviewService->delete($storageKey);
    }
}
