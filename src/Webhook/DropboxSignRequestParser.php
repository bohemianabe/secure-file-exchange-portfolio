<?php

// ag: entry point for the api calls from dropbox
namespace App\Webhook;

use Dropbox\Sign\EventCallbackHelper;
use Dropbox\Sign\Model\EventCallbackRequest;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

final class DropboxSignRequestParser extends AbstractRequestParser
{
    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new MethodRequestMatcher('POST');
    }

    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): ?RemoteEvent
    {
        $rawJson = $request->request->get('json');

        if (!is_string($rawJson) || trim($rawJson) === '') {
            throw new RejectWebhookException(
                Response::HTTP_BAD_REQUEST,
                'Missing Dropbox Sign JSON payload.',
            );
        }

        try {
            $payload = json_decode(
                $rawJson,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException $e) {
            throw new RejectWebhookException(
                Response::HTTP_BAD_REQUEST,
                'Invalid Dropbox Sign JSON payload.',
                $e,
            );
        }

        if (!is_array($payload)) {
            throw new RejectWebhookException(
                Response::HTTP_BAD_REQUEST,
                'Dropbox Sign payload must be an array.',
            );
        }

        try {
            $callbackEvent = EventCallbackRequest::init($payload);
        } catch (JsonException $e) {
            throw new RejectWebhookException(
                400,
                'Invalid Dropbox Sign JSON payload.',
                $e,
            );
        }

        /*
         * $secret is the value configured under: framework.webhook.routing.dropbox_sign.secret
         */
        if (!EventCallbackHelper::isValid($secret, $callbackEvent)) {
            throw new RejectWebhookException(
                401,
                'Invalid Dropbox Sign callback signature.',
            );
        }

        $eventType = $callbackEvent['event']['event_type'] ?? null;
        $eventTime = $callbackEvent['event']['event_time'] ?? null;
        $eventHash = $callbackEvent['event']['event_hash'] ?? null;

        if (!is_string($eventType) || $eventType === '') {
            throw new RejectWebhookException(
                400,
                'Missing Dropbox Sign event type.',
            );
        }

        if ((!is_string($eventTime) && !is_int($eventTime)) || !is_string($eventHash) || $eventHash === '') {
            throw new RejectWebhookException(
                400,
                'Missing Dropbox Sign event identification data.',
            );
        }

        /*
         * Dropbox Sign does not provide a conventional event UUID, so create a deterministic ID from signed event values.
         */
        $eventId = hash(
            'sha256',
            sprintf(
                '%s|%s|%s',
                $eventType,
                $eventTime,
                $eventHash,
            ),
        );

        return new RemoteEvent(
            name: $eventType,
            id: $eventId,
            payload: $payload,
        );
    }

    public function createSuccessfulResponse(): Response
    {
        return new Response(
            content: 'Hello API Event Received',
            status: Response::HTTP_OK,
            headers: [
                'Content-Type' => 'text/plain',
            ],
        );
    }
}
