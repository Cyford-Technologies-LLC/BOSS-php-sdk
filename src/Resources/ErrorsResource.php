<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Resources;

/**
 * Wraps errors.* (/errors) - lets a consuming app report its own errors into
 * the same Error Capture pipeline BOSS uses for itself. Named ErrorsResource,
 * not Errors, to avoid colliding with PHP's own \Errors-adjacent globals.
 */
final class ErrorsResource extends AbstractResource
{
    /** POST /errors - auth: signed_client or bearer. Scope: errors.write. */
    public function report(array $data): array
    {
        return $this->client->call('POST', '/errors', [], $data);
    }

    /** GET /errors - auth: token_or_session (not signed_client). Scope: errors.read. */
    public function list(array $filters = []): array
    {
        return $this->client->call('GET', '/errors', $filters);
    }

    /** GET /errors/{id} - auth: token_or_session (not signed_client). Scope: errors.read. */
    public function get(int $id): array
    {
        return $this->client->call('GET', "/errors/{$id}");
    }
}
