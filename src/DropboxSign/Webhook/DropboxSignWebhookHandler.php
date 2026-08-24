<?php

namespace App\DropboxSign\Webhook;

use App\DropboxSign\DropboxSignClient;
use App\Entity\SignatureRequests;
use App\Entity\SignatureTemplates;
use App\Entity\User;
use App\Enum\SignatureRequestStatus;
use App\Enum\SignatureTemplateStatus;
use App\Logging\AuditLogger;
use App\Service\FileUploader;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class DropboxSignWebhookHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private AuditLogger $audit,
        private DropboxSignClient $dropboxSignClient,
        private FileUploader $fileUploader,
        private string $exportDirectory,
        private NotificationService $notificationService,
    ) {}

    /**
     * @param array $payload
     */
    public function handleSignatureRequestViewed($payload)
    {
        $signatureRequestEventMetadata = $payload['event']['event_metadata'];
        $signatureId = $signatureRequestEventMetadata['related_signature_id'];
        $signatureRequestEntity = $this->em->getRepository(SignatureRequests::class)->findOneBy(['signatureId' => $signatureId]);
        $signatureRequestEntity->setIsViewed(true);
        $this->em->persist($signatureRequestEntity);

        // ag: add event log
        $this->audit->log(
            action: 'signatureRequest.signatureRequestViewed',
            resourceType: 'SignatureRequests',
            resourceId: (string)$signatureRequestEntity->getId(),
            success: true,
            metadata: ['message' => 'Dropbox Sign Signature Request Viewed API Call Back Event.', 'signatureRequestId' => $signatureRequestEntity->getId(), 'signee' => $signatureRequestEntity->getSignerEmail()],
            severity: 'info'
        );

        $this->em->flush();
    }

    /**
     * @param array $payload
     */
    public function handleSignatureRequestAllSigned($payload)
    {
        // $allPartiesSign = true;
        // $signers = $signatureRequest['signatures'];
        // $signatureRequestTitle = $signatureRequest['title'];
        $signatureRequest = $payload['signature_request'];
        $signatureRequestId = $signatureRequest['signature_request_id'];

        $allSignatureRequests = $this->em->getRepository(SignatureRequests::class)->findBy(['signatureRequestId' => $signatureRequestId, 'status' => SignatureRequestStatus::Signed]);

        foreach ($allSignatureRequests as $request) {
            // ag: update the status to reflect it was downloaded into the system.
            $request->setStatus(SignatureRequestStatus::AllSigned);
            $this->em->persist($request);
        }
        // ag: add notification logic
        $this->notificationService->notifySignatureRequestCompleted($allSignatureRequests[0]);
        $this->em->flush();
    }

    /**
     * @param array $payload
     */
    public function handleSignatureRequestDownloadable($payload)
    {
        $signatureRequest = $payload['signature_request'];
        $signatureRequestTitle = $signatureRequest['title'];
        $signatureRequestId = $signatureRequest['signature_request_id'];

        $allSignatureRequests = $this->em->getRepository(SignatureRequests::class)->findBy(['signatureRequestId' => $signatureRequestId]);

        // ag: if multiple signatures required make sure all are signed before downloading
        foreach ($allSignatureRequests as $request) {
            if ($request->getStatus() == SignatureRequestStatus::AwaitingSignature) {
                return;
            }
        }

        $slugger = new AsciiSlugger();
        $fileName = $slugger->slug($allSignatureRequests[0]->getTitle())->lower()->toString() . '.pdf';

        // ag: download the file
        $response = $this->dropboxSignClient->downloadSignatureRequestFile($signatureRequestId);

        $tempPath = $response->getRealPath();
        $tmpDir = $this->exportDirectory . '/docu-sign';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $tmpFilePath = sprintf(
            '%s/%s.pdf',
            $tmpDir,
            $signatureRequestId
        );

        copy($tempPath, $tmpFilePath);

        $uploadedFile = new UploadedFile(
            path: $tmpFilePath,
            originalName: $fileName,
            mimeType: 'application/pdf',
            error: null,
            test: true // REQUIRED (not an HTTP upload)
        );

        $signersEmails = '';
        foreach ($allSignatureRequests as $request) {
            $signersEmails .= $request->getSignerEmail() . ', ';
        }

        // ag: add event log
        $this->audit->log(
            action: 'signatureRequest.signatureRequestAllSigned',
            resourceType: 'SignatureRequests',
            // resourceId: (string)$signatureRequestEntity->getId(),
            success: true,
            metadata: ['message' => 'Dropbox Sign Signature Request All Signed API Call Back Event.', 'signatureRequestId' => $allSignatureRequests[0]->getSignatureId(), 'signees' => $signersEmails],
            severity: 'info'
        );

        $this->fileUploader->upload($uploadedFile, $allSignatureRequests[0]->getFirm(), $allSignatureRequests[0]->getFirmUserProfile()->getUser(), 'DOCU SIGN: ' . $signatureRequestTitle, null, null, null, $allSignatureRequests);

        // ag: remove access.
        $this->dropboxSignClient->signatureRequestRemove($signatureRequestId);
    }

    /**
     * @param array $payload
     */
    public function handleSignatureRequestCanceled($payload)
    {
        $signatureRequest = $payload['signature_request'];
        $signatureRequestId = $signatureRequest['signature_request_id'];
        // ag: find the original signatureRequest created. there should only be one at this point.
        $signatureRequestEntity = $this->em->getRepository(SignatureRequests::class)->findOneBy(['signatureRequestId' => $signatureRequestId]);

        if (!empty($signatureRequestEntity) && !is_null($signatureRequestEntity)) {
            $this->em->getRepository(SignatureRequests::class)->updateStatusBySignatureRequestId($signatureRequestId, SignatureRequestStatus::RequestCanceled);

            // ag: add event log
            $this->audit->log(
                action: 'signatureRequest.requestCanceled',
                resourceType: 'SignatureRequests',
                resourceId: (string)$signatureRequestEntity->getId(),
                success: true,
                metadata: ['message' => 'Dropbox Sign Signature Request Canceled API Call Back Event.', 'signature_request_id' => $signatureRequestEntity->getId()],
                severity: 'info'
            );
        }
    }

    /**
     * ag: handle event when user signs a request document
     * @param array $payload
     */
    public function handleSignatureRequestSigned($payload)
    {
        $signatureRequest = $payload['signature_request'];

        foreach ($signatureRequest['signatures'] as $client) {
            // ag: if client status_code is still awaiting_signature then skip
            if ($client['status_code'] == 'signed') {
                $signatureRequestEntity = $this->em->getRepository(SignatureRequests::class)->findOneBy(['signerEmail' => $client['signer_email_address'], 'signatureId' => $client['signature_id']]);

                $signatureRequestEntity->setStatus(SignatureRequestStatus::Signed);

                $this->em->persist($signatureRequestEntity);
                // ag: todo add event log
                $this->audit->log(
                    action: 'signatureRequest.updated',
                    resourceType: 'SignatureRequests',
                    resourceId: (string)$signatureRequestEntity->getId(),
                    success: true,
                    metadata: ['signer_email' => $client['signer_email_address'], 'Signature_id' => $client['signature_id']],
                    severity: 'info'
                );
                $this->em->flush();
            }
        }
        // ag: always respond quickly after processing the data to let dropbox know the webhook was successful 
        // return new Response('Hello API Event Received', Response::HTTP_OK);
    }

    /**
     * @param array $payload
     */
    public function handleSignatureRequestSent($payload)
    {
        $signatureRequest = $payload['signature_request'];
        $signatureRequestTitle = $signatureRequest['title'];
        $signatureRequestId = $signatureRequest['signature_request_id'];
        $signers = $signatureRequest['signatures'];
        // ag: find the original signatureRequest created
        $signatureRequestEntity = $this->em->getRepository(SignatureRequests::class)->findOneBy(['signatureRequestId' => $signatureRequestId]);
        if (!empty($signatureRequestEntity) && !is_null($signatureRequestEntity)) {
            $fup = $signatureRequestEntity ? $signatureRequestEntity->getFirmUserProfile() : null;

            // ag: separate the signatures from the first signer and the rest if there are any other signers. 
            $primarySignature = $signers[0] ?? null;
            $otherSignatures  = array_slice($signers, 1);

            // dd($otherSignatures);
            $signatureRequestEntity->setSignatureId($primarySignature['signature_id'])
                ->setSignerEmail($primarySignature['signer_email_address'])
                ->setSignerName($primarySignature['signer_name'])
                ->setStatus(SignatureRequestStatus::AwaitingSignature)
                ->setTitle($signatureRequestTitle);

            $this->em->persist($signatureRequestEntity);

            // ag: add event log
            $this->audit->log(
                action: 'signatureRequest.created',
                resourceType: 'SignatureRequests',
                resourceId: (string)$signatureRequestEntity->getId(),
                success: true,
                metadata: ['message' => 'Dropbox Sign Signature Request API Call Back Event.', 'signature_request_id' => $signatureRequestEntity->getId()],
                severity: 'info'
            );

            if (isset($otherSignatures) && !empty($otherSignatures)) {
                foreach ($otherSignatures as $signer) {
                    $newSignatureRequestEntity = new SignatureRequests();
                    $newSignatureRequestEntity->setSignatureRequestId($signatureRequestId)
                        ->setSignatureId($signer['signature_id'])
                        ->setSignerEmail($signer['signer_email_address'])
                        ->setSignerName($signer['signer_name'])
                        ->setStatus(SignatureRequestStatus::AwaitingSignature)
                        ->setFirmUserProfile($fup)
                        ->setFirm($fup->getFirm())
                        ->setTitle($signatureRequestTitle);

                    $this->em->persist($newSignatureRequestEntity);

                    // ag: add event log
                    $this->audit->log(
                        action: 'signatureRequest.created',
                        resourceType: 'SignatureRequests',
                        resourceId: (string)$newSignatureRequestEntity->getId(),
                        success: true,
                        metadata: ['message' => 'Dropbox Sign Signature Request API Call Back Event.', 'signature_request_id' => $newSignatureRequestEntity->getId()],
                        severity: 'info'
                    );
                }
            }
            $this->em->flush();
        }
    }

    /**
     * @param array $payload
     */
    public function handleTemplateCreated($payload)
    {
        $signatureTemplateEntity = $this->em->getRepository(SignatureTemplates::class)->findOneBy(['templateId' => $payload['template']['template_id']]);
        $signatureTemplateEntity->setStatus(SignatureTemplateStatus::Completed);

        $this->audit->log(
            action: 'signatureTemplate.edited',
            resourceType: 'SignatureTemplates',
            resourceId: (string)$signatureTemplateEntity->getId(),
            success: 'success',
            metadata: ['message' => 'Dropbox Sign Template Created Call Back Event', 'signature_template_id' => $signatureTemplateEntity->getId(), 'signature_template_status' => SignatureTemplateStatus::Completed],
        );

        $this->em->persist($signatureTemplateEntity);
        $this->em->flush();
    }
}
