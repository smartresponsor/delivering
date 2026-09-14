# Delivering

Provider-neutral outbound delivery capability for the Smart Responsor ecosystem.

## Responsibility

Delivering accepts application-level delivery commands and delegates them to an external provider. The first vertical slice sends SMS through Telnyx; push delivery is modeled as a provider-neutral command boundary for APNs and FCM adapters.

Delivering owns outbound delivery commands, provider-neutral sender contracts, provider adapters, provider error normalization, and Messenger handlers. Push commands carry the concrete device token and notification payload only at the delivery boundary; Notifying remains the source of notification semantics and subscription ownership.

Delivering does not own chat rooms, conversations, domain event routing, CRM data, or the physical queue transport. Symfony Messenger and its transport remain host-application infrastructure.

## Host integration

Register `App\Delivering\DeliveringBundle` in the host application, provide the required environment variables, and route `App\Delivering\Message\Command\Delivery\DeliverySendSms` to an asynchronous Messenger transport.

The PHP Sodium extension is a runtime requirement because Telnyx webhook authenticity is verified with Ed25519 before any payload parsing or dispatch occurs.

```dotenv
DELIVERING_TELNYX_API_KEY=
DELIVERING_TELNYX_FROM=
DELIVERING_APNS_TEAM_ID=
DELIVERING_APNS_KEY_ID=
DELIVERING_APNS_PRIVATE_KEY=
DELIVERING_APNS_TOPIC_MAP={"one_tasker":"com.smartresponsor.mobile.onetasker","platform":"com.smartresponsor.mobile.platform"}
DELIVERING_APNS_ENVIRONMENT=production
DELIVERING_FCM_SERVICE_ACCOUNT_JSON=
DELIVERING_FCM_PROJECT_MAP={"one_tasker":"firebase-project-id","platform":"firebase-platform-project-id"}

Push provider credentials are intentionally provider-specific and must be supplied by the Host. Delivering does not invent fallback APNs/FCM credentials.
```

Check provider readiness without printing any secret values:

```bash
php bin/console delivering:push:status
```

The command returns success only when both APNs and FCM credentials plus their `appKey` mappings are present. Missing or malformed configuration returns a non-zero exit code and reports only variable names, mapping keys, and validation issues.

## Initial flow

```text
Domain workflow
    -> DeliverySendSms / DeliverySendPush
    -> Symfony Messenger
    -> DeliverySendSmsHandler / DeliverySendPushHandler
    -> DeliveryTelnyxSmsSender / DeliveryPushSenderRouter
    -> provider adapter (Telnyx / APNs / FCM)
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
