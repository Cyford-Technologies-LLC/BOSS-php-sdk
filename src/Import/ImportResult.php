<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Import;

/** Outcome of DbConnectionImporter::importWithMapping() - a partial import (some rows fail, most succeed) is normal, not exceptional. */
final class ImportResult
{
    public int $imported = 0;

    /** @var list<array{row_index:int, error:string}> */
    public array $failures = [];

    public function recordSuccess(): void
    {
        $this->imported++;
    }

    public function recordFailure(int $rowIndex, string $error): void
    {
        $this->failures[] = ['row_index' => $rowIndex, 'error' => $error];
    }

    public function failureCount(): int
    {
        return count($this->failures);
    }
}
