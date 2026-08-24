<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\Process;

// ag: service to create screenshot of each page of the new pdf file uploaded
final class PdfPreviewService
{
    private string $previewDir;
    private string $pdfTemplateDir;

    public function __construct(
        ParameterBagInterface $params,
    ) {
        $this->previewDir = $params->get('pdf_template_preview_dir');
        $this->pdfTemplateDir = $params->get('pdf_template_dir');
    }

    /**
     * @return string[] Preview filenames relative to the preview folder.
     */
    public function create(string $baseFileName): array
    {
        $pdfPath = $this->pdfTemplateDir . '/' . $baseFileName . '.pdf';

        if (!is_file($pdfPath)) {
            throw new \RuntimeException(
                sprintf('PDF file does not exist: %s', $pdfPath)
            );
        }

        $documentName = $baseFileName;

        // $documentName = pathinfo(
        //     $baseFileName,
        //     PATHINFO_FILENAME
        // );

        $documentPreviewDir =
            $this->previewDir . '/' . $documentName;

        if (!is_dir($documentPreviewDir)) {
            if (
                !mkdir($documentPreviewDir, 0775, true)
                && !is_dir($documentPreviewDir)
            ) {
                throw new \RuntimeException(
                    'Could not create PDF preview directory.'
                );
            }
        }

        $outputPattern =
            $documentPreviewDir . '/page-%03d.jpg';

        $process = new Process([
            'magick',

            '-density',
            '250',

            $pdfPath,

            '-background',
            'white',

            '-alpha',
            'remove',

            '-resize',
            '1600x>',

            '-quality',
            '88',

            '-scene',
            '1',

            $outputPattern,
        ]);

        $process->setTimeout(120);
        $process->mustRun();

        $files = glob(
            $documentPreviewDir . '/page-*.jpg'
        );

        if (!$files) {
            throw new \RuntimeException(
                'PDF preview pages were not generated.'
            );
        }

        sort($files);

        return [
            'pageCount' => count($files),
            'pages' => array_map(
                static fn(string $path): string => basename($path),
                $files
            ),
        ];

        // return array_map(
        //     static fn(string $path): string => basename($path),
        //     $files
        // );
    }

    public function delete(string $storageKey): void
    {
        $directory = sprintf(
            '%s/%s',
            $this->previewDir,
            $storageKey
        );

        if (!is_dir($directory)) {
            return;
        }

        foreach (glob($directory . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        rmdir($directory);
    }
}
