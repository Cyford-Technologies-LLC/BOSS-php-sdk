<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Resources;

/**
 * BOSS project 43 feature #120. Wraps core/communications - server-triggered
 * email/SMS/push.
 *
 * IMPORTANT: these routes are auth "token_or_session" - a signed-client
 * (client_id/client_secret) credential CANNOT call them, only a
 * bearer_token-configured Client.
 */
final class Communications extends AbstractResource
{
    public function sendEmail(array $data): array
    {
        return $this->client->call('POST', '/communications/email', [], $data);
    }

    public function sendSms(array $data): array
    {
        return $this->client->call('POST', '/communications/sms', [], $data);
    }

    public function sendPush(array $data): array
    {
        return $this->client->call('POST', '/communications/push', [], $data);
    }

    public function registerDevice(array $data): array
    {
        return $this->client->call('POST', '/communications/devices', [], $data);
    }
}
