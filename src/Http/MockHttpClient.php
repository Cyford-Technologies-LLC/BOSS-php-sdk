<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Http;

use ZeroAI\Boss\Sdk\Exceptions\SdkException;

/**
 * Fake transport for unit-testing a consumer's own integration without
 * hitting real BOSS infrastructure. Pass an instance as Config's http_client:
 *
 *   $mock = new MockHttpClient();
 *   $mock->queue(200, ['success' => true, 'data' => ['lead' => ['id' => 1]]]);
 *   $boss = new Client(['client_id' => 'x', 'client_secret' => 'y', 'http_client' => $mock]);
 *   $boss->leads()->create(['name' => 'Jane']);
 *   assert($mock->requests[0]['method'] === 'POST');
 */
final class MockHttpClient implements HttpClientInterface
{
    /** @var list<array{status:int, headers:array<string,string>, body:string}> */
    private array $queue = [];

    /** @var list<array{method:string, url:string, headers:array<string,string>, body:?string}> */
    public array $requests = [];

    public function queue(int $status, array $decodedBody, array $headers = []): void
    {
        $this->queue[] = [
            'status' => $status,
            'headers' => $headers,
            'body' => (string)json_encode($decodedBody),
        ];
    }

    public function send(string $method, string $url, array $headers, ?string $body): array
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];

        if ($this->queue === []) {
            throw new SdkException('MockHttpClient::send() called with no queued response - call queue() first.');
        }

        return array_shift($this->queue);
    }
}
