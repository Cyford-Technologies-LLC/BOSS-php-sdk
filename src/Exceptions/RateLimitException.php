<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Exceptions;

/** A 429 rate_limited response. The SDK already retries these per Config::$retryPolicy before giving up and throwing. */
class RateLimitException extends SdkException
{
    private int $retryAfterSeconds;

    public function __construct(string $message, int $retryAfterSeconds)
    {
        parent::__construct($message);
        $this->retryAfterSeconds = $retryAfterSeconds;
    }

    public function retryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }
}
