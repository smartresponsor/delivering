<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Controller;

use App\Delivering\Provider\Telnyx\DeliveringTelnyxConversationNotificationParser;
use App\Delivering\Provider\Telnyx\DeliveringTelnyxReceiptParser;
use App\Delivering\Provider\Telnyx\DeliveringTelnyxWebhookSignatureVerifier;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use UnexpectedValueException;

final readonly class DeliveringTelnyxWebhookController
{
    public function __construct(
        private DeliveringTelnyxWebhookSignatureVerifier $signatureVerifier,
        private DeliveringTelnyxConversationNotificationParser $conversationNotificationParser,
        private DeliveringTelnyxReceiptParser $receiptParser,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/webhook/delivering/telnyx', name: 'delivering_telnyx_webhook', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = (string) $request->headers->get('telnyx-signature-ed25519', '');
        $timestamp = (string) $request->headers->get('telnyx-timestamp', '');

        if (!$this->signatureVerifier->verify($payload, $signature, $timestamp)) {
            return new JsonResponse(['status' => 'invalid_signature'], Response::HTTP_FORBIDDEN);
        }

        try {
            $notification = $this->conversationNotificationParser->parse($payload, $timestamp);
            $receipt = $this->receiptParser->parse($payload);
        } catch (UnexpectedValueException) {
            return new JsonResponse(['status' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }

        if (null !== $notification) {
            $this->messageBus->dispatch($notification);
        }

        if (null !== $receipt) {
            $this->messageBus->dispatch($receipt);
        }

        return new JsonResponse(['status' => 'accepted']);
    }
}
