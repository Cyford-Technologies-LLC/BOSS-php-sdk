<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Http;

/**
 * Minimal transport contract the SDK depends on. Deliberately not PSR-18
 * (which would pull in psr/http-message + psr/http-factory as hard
 * dependencies for a v1 skeleton). Wrap a Guzzle/PSR-18 client behind this
 * interface to use it instead of the built-in CurlHttpClient - pass the
 * wrapper as Config's http_client option.
 */
interface HttpClientInterface
{
    /**
     * @param array<string,string> $headers
     * @return array{status:int, headers:array<string,string>, body:string}
     */
    public function send(string $method, string $url, array $headers, ?string $body): array;
}
