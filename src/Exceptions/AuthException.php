<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Exceptions;

/** Any 401 response - invalid/expired/revoked bearer token, or a signed-client signature failure. */
class AuthException extends SdkException
{
    private string $errorCode;

    public function __construct(string $errorCode, string $message)
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
