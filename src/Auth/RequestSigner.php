<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Auth;

/**
 * Produces the auth headers for one outbound request, given a resolved
 * Config. Mirrors ZeroAI\API\V2\Kernel::authenticateSignedClient() and
 * ::authenticateBearer() exactly - the canonical string, hash algorithm, and
 * header names here MUST match that server code or every signed-client call
 * fails signature verification.
 */
final class RequestSigner
{
    /**
     * @param array<string,string> $query
     * @return array<string,string> headers to merge into the request
     */
    public static function bearerHeaders(string $bearerToken): array
    {
        return ['Authorization' => "Bearer {$bearerToken}"];
    }

    /**
     * @param array<string,string> $query
     * @return array<string,string> headers to merge into the request
     */
    public static function signedClientHeaders(
        string $clientId,
        string $clientSecret,
        string $method,
        string $path,
        array $query,
        string $rawBody
    ): array {
        $timestamp = (string)time();
        $bodyHash = hash('sha256', $rawBody);

        ksort($query);
        $canonicalQuery = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        $canonical = implode("\n", [
            strtoupper($method),
            $path,
            $canonicalQuery,
            $bodyHash,
            $timestamp,
        ]);

        $signature = hash_hmac('sha256', $canonical, $clientSecret);

        return [
            'X-ZeroAI-Client' => $clientId,
            'X-ZeroAI-Timestamp' => $timestamp,
            'X-ZeroAI-Signature' => $signature,
        ];
    }
}
