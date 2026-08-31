<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Resources;

use ZeroAI\Boss\Sdk\ResourceRecord;

trait CreatesRecords
{
    protected function createdRecord(array $response, string $recordKey): ResourceRecord
    {
        $record = $response[$recordKey] ?? $response;
        return new ResourceRecord(is_array($record) ? $record : []);
    }
}
