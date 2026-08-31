<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk;

/**
 * Small response wrapper for single records.
 *
 * SDK examples use object access ($lead->id), while PHP integrations often use
 * array access ($lead['id']). ArrayObject with ARRAY_AS_PROPS supports both.
 */
final class ResourceRecord extends \ArrayObject
{
    public function __construct(array $record)
    {
        parent::__construct($record, \ArrayObject::ARRAY_AS_PROPS);
    }
}
