<?php

declare(strict_types=1);

namespace App\Delivering\Service\Query\Push;

final readonly class DeliveryPushReadinessService
{
    public function __construct(
        private string $apnsTeamId,
        private string $apnsKeyId,
        private string $apnsPrivateKey,
        private string $apnsTopicMapJson,
        private string $apnsEnvironment,
        private string $fcmServiceAccountJson,
        private string $fcmProjectMapJson,
    ) {
    }

    /**
     * @return array{
     *     apns: array{configured: bool, appKeys: list<string>, issues: list<string>},
     *     fcm: array{configured: bool, appKeys: list<string>, issues: list<string>},
     *     configured: bool
     * }
     */
    public function status(): array
    {
        $apns = $this->apnsStatus();
        $fcm = $this->fcmStatus();

        return [
            'apns' => $apns,
            'fcm' => $fcm,
            'configured' => $apns['configured'] && $fcm['configured'],
        ];
    }

    /** @return array{configured: bool, appKeys: list<string>, issues: list<string>} */
    private function apnsStatus(): array
    {
        $issues = [];
        foreach ([
            'DELIVERING_APNS_TEAM_ID' => $this->apnsTeamId,
            'DELIVERING_APNS_KEY_ID' => $this->apnsKeyId,
            'DELIVERING_APNS_PRIVATE_KEY' => $this->apnsPrivateKey,
        ] as $name => $value) {
            if ('' === trim($value)) {
                $issues[] = $name.' is missing.';
            }
        }

        if (!in_array(strtolower(trim($this->apnsEnvironment)), ['development', 'production'], true)) {
            $issues[] = 'DELIVERING_APNS_ENVIRONMENT must be development or production.';
        }

        [$topics, $mapIssue] = $this->decodeStringMap($this->apnsTopicMapJson);
        if (null !== $mapIssue) {
            $issues[] = 'DELIVERING_APNS_TOPIC_MAP '.$mapIssue;
        } elseif ([] === $topics) {
            $issues[] = 'DELIVERING_APNS_TOPIC_MAP has no application mappings.';
        }

        return [
            'configured' => [] === $issues,
            'appKeys' => array_keys($topics),
            'issues' => $issues,
        ];
    }

    /** @return array{configured: bool, appKeys: list<string>, issues: list<string>} */
    private function fcmStatus(): array
    {
        $issues = [];
        $account = json_decode($this->fcmServiceAccountJson, true);
        if (!is_array($account)) {
            $issues[] = 'DELIVERING_FCM_SERVICE_ACCOUNT_JSON must contain a valid JSON object.';
        } else {
            foreach (['client_email', 'private_key'] as $field) {
                if (!isset($account[$field]) || !is_string($account[$field]) || '' === trim($account[$field])) {
                    $issues[] = sprintf('DELIVERING_FCM_SERVICE_ACCOUNT_JSON is missing %s.', $field);
                }
            }
        }

        [$projects, $mapIssue] = $this->decodeStringMap($this->fcmProjectMapJson);
        if (null !== $mapIssue) {
            $issues[] = 'DELIVERING_FCM_PROJECT_MAP '.$mapIssue;
        } elseif ([] === $projects) {
            $issues[] = 'DELIVERING_FCM_PROJECT_MAP has no application mappings.';
        }

        return [
            'configured' => [] === $issues,
            'appKeys' => array_keys($projects),
            'issues' => $issues,
        ];
    }

    /** @return array{0: array<string, string>, 1: ?string} */
    private function decodeStringMap(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [[], 'must contain a valid JSON object.'];
        }

        $map = [];
        foreach ($decoded as $key => $value) {
            if (!is_string($key) || '' === trim($key) || !is_string($value) || '' === trim($value)) {
                return [[], 'must map non-empty application keys to non-empty string values.'];
            }
            $map[$key] = $value;
        }
        ksort($map);

        return [$map, null];
    }
}
