<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Http;

use ZeroAI\Boss\Sdk\Exceptions\SdkException;

/** Default transport - plain curl, zero extra Composer dependencies. */
final class CurlHttpClient implements HttpClientInterface
{
    private int $timeoutMs;

    public function __construct(int $timeoutMs = 10000)
    {
        $this->timeoutMs = $timeoutMs;
    }

    public function send(string $method, string $url, array $headers, ?string $body): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new SdkException("Failed to initialize curl for {$url}.");
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = "{$name}: {$value}";
        }

        $responseHeaders = [];

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => $this->timeoutMs,
            CURLOPT_CONNECTTIMEOUT_MS => min($this->timeoutMs, 5000),
            CURLOPT_HEADERFUNCTION => function ($curl, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[trim($parts[0])] = trim($parts[1]);
                }
                return strlen($line);
            },
        ]);

        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new SdkException("HTTP request to {$url} failed: {$error}");
        }

        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => (string)$responseBody,
        ];
    }
}
