<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Exceptions;

/**
 * Generic catch-all for any non-2xx response that isn't one of the more
 * specific exception types (auth, rate limit, validation). Carries the API's
 * own error code/message/details verbatim - see error.code in the v2 response
 * envelope ({"success":false,"error":{"code":...,"message":...,"details":...}}).
 */
class ApiException extends SdkException
{
    private int $statusCode;
    private string $errorCode;
    private array $details;
    private string $requestId;

    public function __construct(int $statusCode, string $errorCode, string $message, array $details = [], string $requestId = '')
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode;
        $this->details = $details;
        $this->requestId = $requestId;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function details(): array
    {
        return $this->details;
    }

    /** The API's X-Request-Id / meta.request_id - include this when reporting a bug against BOSS. */
    public function requestId(): string
    {
        return $this->requestId;
    }
}
