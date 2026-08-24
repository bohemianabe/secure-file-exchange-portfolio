<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\Process;

// ag: service to create the thumbnail image of the uploaded pdf file
final class PdfThumbnailService
{
    private string $thumbnailDir;
    private string $pdfTemplateDir;

    public function __construct(
        ParameterBagInterface $params,
    ) {
        $this->thumbnailDir = $params->get('pdf_template_thumbnail_dir');
        $this->pdfTemplateDir = $params->get('pdf_template_dir');
    }

    public function create(string $baseFileName): string
    {
        $pdfPath = $this->pdfTemplateDir . '/' . $baseFileName . '.pdf';

        if (!is_file($pdfPath)) {
            throw new \RuntimeException(
                sprintf('PDF file does not exist: %s', $pdfPath)
            );
        }

        if (!is_dir($this->thumbnailDir)) {
            if (
                !mkdir($this->thumbnailDir, 0775, true)
                && !is_dir($this->thumbnailDir)
            ) {
                throw new \RuntimeException(
                    'Could not create PDF thumbnail directory.'
                );
            }
        }

        $thumbnailFileName = $baseFileName . '.jpg';

        // $thumbnailFileName = pathinfo(
        //     $baseFileName,
        //     PATHINFO_FILENAME
        // ) . '.jpg';

        $thumbnailPath = $this->thumbnailDir . '/' . $thumbnailFileName;

        $process = new Process([
            'magick',
            '-density',
            '250',

            // First page only
            $pdfPath . '[0]',

            '-thumbnail',
            '600x800>',

            '-background',
            'white',

            '-alpha',
            'remove',

            '-quality',
            '92',

            $thumbnailPath,
        ]);

        $process->setTimeout(30);
        $process->mustRun();

        if (!is_file($thumbnailPath)) {
            throw new \RuntimeException(
                'PDF thumbnail was not generated.'
            );
        }

        return $thumbnailFileName;
    }

    public function delete(string $storageKey): void
    {
        $path = sprintf(
            '%s/%s.jpg',
            $this->thumbnailDir,
            $storageKey
        );

        if (is_file($path)) {
            unlink($path);
        }
    }
}
