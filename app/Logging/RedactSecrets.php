<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;

/**
 * Log tap that strips known API keys/secrets out of log messages and context
 * before they reach a handler (file, Discord webhook, etc). Exception messages
 * from HTTP clients often embed the full request URL — including any secret
 * passed as a query parameter — so this runs on every record, not just ones we
 * expect to contain a secret.
 */
class RedactSecrets
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(function (LogRecord $record): LogRecord {
            $secrets = array_values(array_filter([
                config('app.vatusa_api_key'),
                config('app.vatsim_client_secret'),
            ], fn ($secret) => is_string($secret) && $secret !== ''));

            if ($secrets === []) {
                return $record;
            }

            return $record->with(
                message: str_replace($secrets, '[REDACTED]', $record->message),
                context: $this->redact($record->context, $secrets),
            );
        });
    }

    private function redact(array $data, array $secrets): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = str_replace($secrets, '[REDACTED]', $value);
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value, $secrets);
            }
        }

        return $data;
    }
}
