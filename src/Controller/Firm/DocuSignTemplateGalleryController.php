<?php

namespace App\Controller\Firm;

use App\Controller\Traits\EntityActions;
use App\Entity\SignatureDocumentTemplates;
use App\Entity\SignatureRequests;
use App\Enum\SignatureDocumentTemplateCategory;
use App\Enum\SignatureRequestStatus;
use App\Repository\FilesRepository;
use App\Repository\SignatureDocumentTemplatesRepository;
use App\Security\Voter\StoragePlanVoter;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[IsGranted("ROLE_FIRM")]
final class DocuSignTemplateGalleryController extends AbstractController
{
    use EntityActions;

    #[Route('/firm/docu-sign-template-gallery', name: 'docu_sign_template_gallary_view')]
    public function firmDocuSignTemplateView(Request $request, FilesRepository $fileRepo, SignatureDocumentTemplatesRepository $sigDocTempRepo): Response
    {
        $limit = 8;
        $page = max(1, $request->query->getInt('page', 1));
        $categoryValue = $request->query->get('category');

        $category = $categoryValue ? SignatureDocumentTemplateCategory::tryFrom($categoryValue) : null;

        $totalTemplates = $sigDocTempRepo->countByCategory($category, true);
        $totalPages = max(1, (int) ceil($totalTemplates / $limit));

        // Prevent requests for a page past the end
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $limit;

        $sigDocTemplates = $sigDocTempRepo->findPaginated($limit, $offset, $category, true);

        $categoryValue = $request->query->get('category');

        $pageDataPointBasicsArray = $this->_pageDataPointBasics($fileRepo);
        $fullPageDataArray = array_merge($pageDataPointBasicsArray, [
            'sigDocTemplates' => $sigDocTemplates,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalTemplates' => $totalTemplates,
            'limit' => $limit,
            'categories' => SignatureDocumentTemplateCategory::cases(),
            'selectedCategory' => $category?->value,
        ]);

        return $this->render('firm_user_access/docu-sign-template-gallery.html.twig', $fullPageDataArray);
    }

    #[Route('/firm/signature-template/{uuid}/use', name: 'firm_signature_template_use')]
    public function firmDocuSignTemplateUse(Request $request, #[MapEntity(mapping: ['uuid' => 'uuid'])] SignatureDocumentTemplates $template): Response
    {
        $userProfile = $this->getUser()->getUserProfile();
        $this->denyAccessUnlessGranted(StoragePlanVoter::DOCUSIGN, $userProfile->getFirm());

        if (!$template->getIsActive()) {
            return $this->json([
                'success' => false,
                'message' => 'This template is no longer available.',
            ], 404);
        }

        $pdfPath = sprintf(
            '%s/%s.pdf',
            $this->params->get('pdf_template_dir'),
            $template->getStorageKey()
        );

        if (!is_file($pdfPath)) {
            throw $this->createNotFoundException(
                'Template PDF could not be found.'
            );
        }

        try {
            $requesterEmailAddress = $this->getUser()->getEmail();
            $unclaimedDraftCreateEmbedded = $this->dropboxSignClient->createUnclaimedDraftCreateEmbedded($requesterEmailAddress, $pdfPath);

            // ag: log and create the rough raft entry for the signatureRequest in order to keep track of the FUP that made the request. Must be done here and not in the webhook.
            $signatureRequestId = $unclaimedDraftCreateEmbedded->getUnclaimedDraft()->getSignatureRequestId();

            $signatureRequestEntity = new SignatureRequests();
            $signatureRequestEntity->setSignatureRequestId($signatureRequestId)
                ->setFirm($userProfile->getFirm())
                ->setStatus(SignatureRequestStatus::DraftRequest)
                ->setFirmUserProfile($userProfile);
            $this->em->persist($signatureRequestEntity);
            $this->em->flush();

            // ag: todo add event log
            $this->audit->log(
                action: 'signatureRequest.draftCreated',
                resourceType: 'SignatureRequests',
                resourceId: (string)$signatureRequestEntity->getId(),
                success: true,
                metadata: ['message' => 'Dropbox Sign Unclaimed Draft Embedded Submitted', 'signature_request_id' => $signatureRequestId],
                severity: 'info'
            );

            return $this->json([
                'success' => true,
                'data' => [
                    'fileUrl' => $unclaimedDraftCreateEmbedded->getUnclaimedDraft()->getClaimUrl(),
                    'dropboxSignClientId' => $this->params->get('dropbox_client_id'),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => 'Unable to start the signature request.',
            ], 500);
        }
    }
}
