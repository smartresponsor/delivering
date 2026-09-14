<?php

declare(strict_types=1);

namespace App\Delivering\Tests\Controller\Webhook\Telnyx;

use App\Delivering\Controller\Webhook\Telnyx\DeliveryTelnyxWebhookController;
use App\Delivering\Message\Command\Delivery\DeliverySendSms;
use App\Delivering\Message\Command\Receipt\DeliveryProcessReceipt;
use App\Delivering\Provider\Telnyx\DeliveryTelnyxConversationNotificationParser;
use App\Delivering\Provider\Telnyx\DeliveryTelnyxReceiptParser;
use App\Delivering\Provider\Telnyx\DeliveryTelnyxWebhookSignatureVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class DeliveryTelnyxWebhookControllerTest extends TestCase
{
    public function testRejectInvalidSignatureBeforeParsingOrDispatch(): void
    {
        [$verifier, $request] = $this->signedRequest('{"data":{}}');
        $request->headers->set('telnyx-signature-ed25519', base64_encode(str_repeat('x', 64)));
        $bus = new DeliveryRecordingBus();

        $response = $this->controller($verifier, $bus)($request);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame([], $bus->messages);
    }

    public function testRejectMalformedSignedPayload(): void
    {
        [$verifier, $request] = $this->signedRequest('null');
        $bus = new DeliveryRecordingBus();

        $response = $this->controller($verifier, $bus)($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame([], $bus->messages);
    }

    public function testRejectSignedAiPayloadWithoutRecognizedLeadFields(): void
    {
        [$verifier, $request] = $this->signedRequest('{"unexpected":"value"}');
        $bus = new DeliveryRecordingBus();

        $response = $this->controller($verifier, $bus)($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame([], $bus->messages);
    }

    public function testDispatchAiNotificationWithoutPassingThroughReceiptParser(): void
    {
        $payload = '{"customer_name":"Alex","service":"TV mounting"}';
        [$verifier, $request] = $this->signedRequest($payload);
        $bus = new DeliveryRecordingBus();

        $response = $this->controller($verifier, $bus)($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertCount(1, $bus->messages);
        self::assertInstanceOf(DeliverySendSms::class, $bus->messages[0]);
    }

    public function testDispatchStandardReceipt(): void
    {
        $payload = json_encode([
            'data' => [
                'event_type' => 'message.sent',
                'id' => 'event-1',
                'occurred_at' => '2026-09-13T12:00:00+00:00',
                'payload' => ['id' => 'message-1'],
            ],
        ], JSON_THROW_ON_ERROR);
        [$verifier, $request] = $this->signedRequest($payload);
        $bus = new DeliveryRecordingBus();

        $response = $this->controller($verifier, $bus)($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertCount(1, $bus->messages);
        self::assertInstanceOf(DeliveryProcessReceipt::class, $bus->messages[0]);
    }

    public function testAcceptedIgnoredEventDoesNotDispatch(): void
    {
        $payload = '{"data":{"event_type":"message.received"}}';
        [$verifier, $request] = $this->signedRequest($payload);
        $bus = new DeliveryRecordingBus();

        $response = $this->controller($verifier, $bus)($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([], $bus->messages);
    }

    private function controller(DeliveryTelnyxWebhookSignatureVerifier $verifier, MessageBusInterface $bus): DeliveryTelnyxWebhookController
    {
        return new DeliveryTelnyxWebhookController(
            $verifier,
            new DeliveryTelnyxConversationNotificationParser('+13465550101'),
            new DeliveryTelnyxReceiptParser(),
            $bus,
        );
    }

    /** @return array{DeliveryTelnyxWebhookSignatureVerifier, Request} */
    private function signedRequest(string $payload): array
    {
        $keyPair = sodium_crypto_sign_keypair();
        $secretKey = sodium_crypto_sign_secretkey($keyPair);
        $publicKey = sodium_crypto_sign_publickey($keyPair);
        $timestamp = (string) time();
        $signature = sodium_crypto_sign_detached($timestamp.'|'.$payload, $secretKey);
        $request = Request::create('/webhook/delivering/telnyx', 'POST', server: [
            'HTTP_TELNYX_SIGNATURE_ED25519' => base64_encode($signature),
            'HTTP_TELNYX_TIMESTAMP' => $timestamp,
        ], content: $payload);

        return [new DeliveryTelnyxWebhookSignatureVerifier(base64_encode($publicKey)), $request];
    }
}

final class DeliveryRecordingBus implements MessageBusInterface
{
    /** @var list<object> */
    public array $messages = [];

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->messages[] = $message;

        return new Envelope($message, $stamps);
    }
}
