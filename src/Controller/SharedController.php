<?php

namespace App\Controller;

use App\Controller\Traits\EntityActions;
use App\Entity\SignatureDocumentTemplates;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

final class SharedController extends AbstractController
{
    use EntityActions;

    #[Route('{role}/announcement/{id}/dismiss', name: 'announcement_dismiss', methods: ['POST'])]
    public function dismissAnnouncement(int $id, Request $request): JsonResponse
    {
        $session = $request->getSession();

        $dismissed = $session->get('dismissed_announcements', []);

        if (!in_array($id, $dismissed, true)) {
            $dismissed[] = $id;
        }

        $session->set('dismissed_announcements', $dismissed);

        return new JsonResponse([
            'success' => true,
            'dismissed' => $dismissed,
        ]);
    }

    #[Route('/signature-template/{uuid}/thumbnail', name: 'signature_template_thumbnail', methods: ['GET'])]
    public function thumbnail(#[MapEntity(mapping: ['uuid' => 'uuid'])] SignatureDocumentTemplates $template): Response
    {
        if ($this->isGranted('ROLE_FIRM') && !$template->getIsActive()) {
            throw $this->createNotFoundException();
        }

        if ($this->isGranted('ROLE_CLIENT')) {
            throw $this->createNotFoundException();
        }

        $path = $this->params->get('pdf_template_thumbnail_dir')
            . '/'
            . $template->getStorageKey()
            . '.jpg';

        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        return $this->file($path);
    }

    #[Route('/signature-template/{uuid}/view', name: 'signature_template_view')]
    public function adminSignatureTemplateView(#[MapEntity(mapping: ['uuid' => 'uuid'])] SignatureDocumentTemplates $template): Response
    {
        if ($this->isGranted('ROLE_FIRM') && !$template->getIsActive()) {
            throw $this->createNotFoundException();
        }

        if ($this->isGranted('ROLE_CLIENT')) {
            throw $this->createNotFoundException();
        }

        return $this->render('/layouts/modals/view-sig-docu-template.html.twig', [
            'template' => $template,
        ]);
    }

    #[Route('/signature-template/{uuid}/preview-page/{page}', name: 'signature_template_preview_page', requirements: ['page' => '\d+',], methods: ['GET'])]
    public function previewPage(SignatureDocumentTemplates $template, int $page): Response
    {
        if ($this->isGranted('ROLE_FIRM') && !$template->getIsActive()) {
            throw $this->createNotFoundException();
        }

        if ($this->isGranted('ROLE_CLIENT')) {
            throw $this->createNotFoundException();
        }

        $documentName = $template->getStorageKey();

        $previewDir = $this->params->get('pdf_template_preview_dir')
            . '/'
            . $documentName;

        $pageFileName = sprintf(
            'page-%03d.jpg',
            $page
        );

        $pagePath = $previewDir . '/' . $pageFileName;

        if (!is_file($pagePath)) {
            throw $this->createNotFoundException(
                sprintf(
                    'Preview page %d could not be found.',
                    $page
                )
            );
        }

        return $this->file(
            $pagePath,
            $pageFileName,
            ResponseHeaderBag::DISPOSITION_INLINE
        );
    }
}
