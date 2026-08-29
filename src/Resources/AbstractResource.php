<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Resources;

use ZeroAI\Boss\Sdk\Client;

abstract class AbstractResource
{
    protected Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }
}
