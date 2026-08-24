<?php

namespace App\EventSubscriber;

use App\Entity\FilesExportRequest;
use App\Entity\FoldersExportRequest;
use App\Enum\FolderRequestStatus;
use App\Logging\AuditLogger;
use App\Message\AuditLogMessage;
use App\Message\GenerateZipFileExport;
use App\Message\GenerateZipFolderExport;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

// ag: custom handling for messenger events that fail
final class MessengerFailureSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $audit,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => 'onWorkerFailedMessageEvent',
        ];
    }

    public function onWorkerMessageFailedEvent(WorkerMessageFailedEvent $event): void
    {
        // Do not mark it failed while Messenger still intends to retry it.
        if ($event->willRetry()) {
            return;
        }

        $message = $event->getEnvelope()->getMessage();
        $exception = $event->getThrowable();

        /*
         * Avoid an endless loop:
         * AuditLogMessage fails -> listener logs another audit message
         * -> that audit message fails -> listener logs another...
         */
        if ($message instanceof AuditLogMessage) {
            return;
        }

        match (true) {
            $message instanceof GenerateZipFileExport =>
            $this->handleFileExportFailure($message, $event),

            $message instanceof GenerateZipFolderExport =>
            $this->handleFolderExportFailure($message, $event),

            default => null,
        };

        // Record every final Messenger failure.
        $this->logFailure($message, $exception);
    }

    // ag: if this files grows consider moving the functions into a dedicated service
    private function handleFileExportFailure(GenerateZipFileExport $message, WorkerMessageFailedEvent $event): void
    {
        $export = $this->em
            ->getRepository(FilesExportRequest::class)
            ->find($message->exportId);

        if (!$export) {
            return;
        }

        $export->setStatus(FilesExportRequest::STATUS_FAILED);
        $export->setErrorMessage(
            mb_substr($event->getThrowable()->getMessage(), 0, 1000)
        );

        $this->em->flush();
    }

    private function handleFolderExportFailure(GenerateZipFolderExport $message, WorkerMessageFailedEvent $event): void
    {
        $export = $this->em
            ->getRepository(FoldersExportRequest::class)
            ->find($message->getExportId());

        if (!$export) {
            return;
        }

        $export->setStatus(FolderRequestStatus::Failed);
        $export->setErrorMessage(
            mb_substr($event->getThrowable()->getMessage(), 0, 1000)
        );

        $this->em->flush();
    }

    private function logFailure(object $message, \Throwable $exception): void
    {
        $payload = [
            'class' => $message::class,
        ];

        if (property_exists($message, 'action')) {
            $payload['action'] = $message->action;
        }

        if (property_exists($message, 'resourceType')) {
            $payload['resourceType'] = $message->resourceType;
        }

        if (property_exists($message, 'resourceId')) {
            $payload['resourceId'] = $message->resourceId;
        }

        $this->audit->log(
            action: 'messengerMessages.failure',
            resourceType: 'MessengerMessages',
            success: false,
            metadata: [
                'error_message' => $exception->getMessage(),
                'message_payload' => $payload,
            ],
            severity: 'error',
        );
    }
}
