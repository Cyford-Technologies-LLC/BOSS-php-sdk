<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Exceptions;

/** Thrown for SDK-side misconfiguration (bad Config) or a 422 validation_failed response from the API. */
class ValidationException extends SdkException
{
    private array $details;

    public function __construct(string $message, array $details = [])
    {
        parent::__construct($message);
        $this->details = $details;
    }

    public function details(): array
    {
        return $this->details;
    }
}
