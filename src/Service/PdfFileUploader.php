<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

final class PdfFileUploader
{
    private string $baseDir;

    public function __construct(
        ParameterBagInterface $params,
        private SluggerInterface $slugger,
    ) {
        $this->baseDir = $params->get('pdf_template_dir');
    }

    public function upload(UploadedFile $file, ?string $fileName = null): string
    {
        if (!$file->isValid()) {
            throw new \RuntimeException('The uploaded file is not valid.');
        }

        if ($file->getMimeType() !== 'application/pdf') {
            throw new \InvalidArgumentException('Only PDF files may be uploaded.');
        }

        $originalFilename = $fileName ?: $file->getClientOriginalName();

        $baseName = pathinfo($originalFilename, PATHINFO_FILENAME);

        $safeName = (string) $this->slugger
            ->slug($baseName)
            ->lower();

        $storedFilename = sprintf(
            '%s-%s.pdf',
            $safeName,
            bin2hex(random_bytes(8))
        );

        if (!is_dir($this->baseDir)) {
            if (!mkdir($this->baseDir, 0775, true) && !is_dir($this->baseDir)) {
                throw new \RuntimeException(sprintf(
                    'Unable to create PDF template directory "%s".',
                    $this->baseDir
                ));
            }
        }

        try {
            $file->move(
                $this->baseDir,
                $storedFilename
            );
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Unable to store the PDF template.',
                previous: $e
            );
        }

        return pathinfo($storedFilename, PATHINFO_FILENAME);
        // return $baseName;
    }

    public function delete(string $storageKey): void
    {
        $path = sprintf(
            '%s/%s.pdf',
            $this->baseDir,
            $storageKey
        );

        if (is_file($path)) {
            unlink($path);
        }
    }
}
