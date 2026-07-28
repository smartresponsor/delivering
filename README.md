# Delivering

Provider-neutral outbound delivery capability for the Smart Responsor ecosystem.

## Responsibility

Delivering accepts application-level delivery commands and delegates them to an external provider. The first vertical slice sends SMS through Telnyx.

Delivering owns outbound delivery commands, provider-neutral sender contracts, provider adapters, provider error normalization, and Messenger handlers.

Delivering does not own chat rooms, conversations, domain event routing, CRM data, or the physical queue transport. Symfony Messenger and its transport remain host-application infrastructure.

## Host integration

Register `App\Delivering\DeliveringBundle` in the host application, provide the required environment variables, and route `App\Delivering\Message\DeliveringSendSms` to an asynchronous Messenger transport.

```dotenv
DELIVERING_TELNYX_API_KEY=
DELIVERING_TELNYX_FROM=
```

## Initial flow

```text
Domain workflow
    -> DeliveringSendSms
    -> Symfony Messenger
    -> DeliveringSendSmsHandler
    -> DeliveringTelnyxSmsSender
    -> Telnyx Messaging API
```

## Observability

Delivering emits structured PSR-3 records for provider and Messenger lifecycles. Messenger records are limited to messages owned by this component and include `correlation_id`, `idempotency_key`, receiver, and the one-based processing attempt.

```text
delivering.delivery.duplicate
delivering.delivery.succeeded
delivering.delivery.failed
delivering.messenger.handled
delivering.messenger.retried
delivering.messenger.failed_terminal
```

Retry scheduling is logged at warning level. Exhausted or unrecoverable processing is logged at error level with exception class and message.
