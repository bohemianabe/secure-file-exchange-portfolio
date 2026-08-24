<?php

namespace App\RemoteEvent;

use App\DropboxSign\Webhook\DropboxSignWebhookHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;

#[AsRemoteEventConsumer('dropbox_sign')]
final class DropboxSignWebhookConsumer implements ConsumerInterface
{
    public function __construct(
        private DropboxSignWebhookHandler $dropboxSignHandler,
        private LoggerInterface $logger,
        private ParameterBagInterface $params
    ) {}

    public function consume(RemoteEvent $event): void
    {
        /** @var array<string, mixed> $payload */
        $payload = $event->getPayload();

        // ag: for testing. comment out otherwise.
        // file_put_contents(
        //     $this->params->get('kernel.project_dir') . '/var/log/dropbox-sign-webhook.log',
        //     json_encode($payload, JSON_PRETTY_PRINT) . PHP_EOL,
        //     FILE_APPEND
        // );


        $this->logger->info('Processing Dropbox Sign webhook.', [
            'event_id' => $event->getId(),
            'event_type' => $event->getName(),
            'signature_request_id' =>
            $payload['signature_request']['signature_request_id'] ?? null,
        ]);

        match ($event->getName()) {
            'template_created'                  => $this->dropboxSignHandler->handleTemplateCreated($payload),
            'signature_request_sent'            => $this->dropboxSignHandler->handleSignatureRequestSent($payload),
            'signature_request_viewed'          => $this->dropboxSignHandler->handleSignatureRequestViewed($payload),
            'signature_request_signed'          => $this->dropboxSignHandler->handleSignatureRequestSigned($payload),
            'signature_request_canceled'        => $this->dropboxSignHandler->handleSignatureRequestCanceled($payload),
            'signature_request_all_signed'      => $this->dropboxSignHandler->handleSignatureRequestAllSigned($payload),
            'signature_request_downloadable'    => $this->dropboxSignHandler->handleSignatureRequestDownloadable($payload),

            default => $this->logger->info(
                'Ignoring unsupported Dropbox Sign event.',
                [
                    'event_type' => $event->getName(),
                    'event_id' => $event->getId(),
                ]
            ),
        };
    }
}
