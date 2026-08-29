<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Import;

use ZeroAI\Boss\Sdk\Exceptions\SdkException;
use ZeroAI\Boss\Sdk\Exceptions\ValidationException;

/**
 * Onboarding-import connector for a business that already has a live PHP app
 * with its own database connection (project 43 decision #27, connection
 * tier 1). The caller passes their OWN already-open PDO instance - this
 * class never receives or transmits DB credentials, and never opens its own
 * connection. Works against any PDO driver (MySQL, Postgres, SQLite, ...)
 * since it only relies on standard PDO fetch behavior, not driver-specific
 * schema introspection.
 *
 * Generic across entity types (leads/customers/sales/products - decision
 * #26): the caller supplies the target via a create callback, this class
 * doesn't know or care what BOSS resource it's feeding.
 *
 * This class does NOT do field mapping UI or AI suggestions - see tasks
 * 515 (manual mapping) and 516 (AI-assisted mapping), which sit on top of
 * describeColumns()/sampleRows() below. It only introspects, samples
 * (redacted), and executes an already-approved mapping.
 *
 * Usage:
 *   $importer = new DbConnectionImporter($theirPdo, 'legacy_customers');
 *   $columns = $importer->describeColumns();       // for a mapping UI
 *   $preview = $importer->sampleRows(20);           // sensitive columns redacted
 *   // ... human (or human-approved AI) builds $columnMap = ['email' => 'contact_email', ...] ...
 *   $result = $importer->importWithMapping(
 *       fn(array $row) => $boss->leads()->create($row),
 *       $columnMap
 *   );
 */
final class DbConnectionImporter
{
    private \PDO $connection;
    private string $table;

    public function __construct(\PDO $connection, string $table)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            throw new ValidationException("Table name '{$table}' contains characters outside [A-Za-z0-9_] - refusing to interpolate it into SQL.");
        }
        $this->connection = $connection;
        $this->table = $table;
    }

    /** @return list<string> column names, discovered from a single sample row rather than driver-specific schema APIs (PDO::getColumnMeta() isn't supported on every driver, e.g. pgsql). */
    public function describeColumns(): array
    {
        $stmt = $this->connection->query("SELECT * FROM {$this->table} LIMIT 1");
        if ($stmt === false) {
            throw new SdkException("Could not query table '{$this->table}' to discover its columns.");
        }
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row === false ? [] : array_keys($row);
    }

    /** @return list<string> columns describeColumns() would flag as sensitive - surface this to the human doing manual mapping too, not just the AI path. */
    public function sensitiveColumns(): array
    {
        return SensitiveColumnFilter::sensitiveColumnsIn($this->describeColumns());
    }

    /**
     * @return array<int,array<string,mixed>> up to $limit rows, sensitive columns redacted.
     * This is the only view of source data safe to hand to an AI mapping suggestion or a UI preview.
     */
    public function sampleRows(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->connection->query("SELECT * FROM {$this->table} LIMIT {$limit}");
        if ($stmt === false) {
            throw new SdkException("Could not sample rows from table '{$this->table}'.");
        }
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        return SensitiveColumnFilter::redactRows($rows);
    }

    public function countRows(): int
    {
        $stmt = $this->connection->query("SELECT COUNT(*) AS c FROM {$this->table}");
        if ($stmt === false) {
            throw new SdkException("Could not count rows in table '{$this->table}'.");
        }
        return (int)($stmt->fetch(\PDO::FETCH_ASSOC)['c'] ?? 0);
    }

    /**
     * Executes an already-approved column mapping. Never call this with a
     * mapping that hasn't been shown to and confirmed by a human - per
     * decision #27, an AI-proposed mapping must be approved first, and this
     * method has no way to enforce that itself; the caller owns that gate.
     *
     * @param callable(array<string,mixed>):mixed $create Pushes one mapped row into BOSS, e.g. fn($row) => $boss->leads()->create($row). Exceptions are caught per-row and recorded in the result rather than aborting the whole import.
     * @param array<string,string> $columnMap Source column name => target field name. Source columns not listed here are dropped, not passed through - an approved mapping is explicit about every field it moves.
     * @param int $batchSize Rows fetched from the source per round-trip. Does not batch the create() calls themselves - BOSS's write routes are single-record.
     */
    public function importWithMapping(callable $create, array $columnMap, int $batchSize = 200): ImportResult
    {
        if ($columnMap === []) {
            throw new ValidationException('columnMap is empty - nothing to import. This must be an explicit, approved mapping, not a passthrough of every source column.');
        }

        $batchSize = max(1, min(1000, $batchSize));
        $result = new ImportResult();
        $offset = 0;
        $rowIndex = 0;

        do {
            $stmt = $this->connection->query("SELECT * FROM {$this->table} LIMIT {$batchSize} OFFSET {$offset}");
            if ($stmt === false) {
                throw new SdkException("Could not read table '{$this->table}' at offset {$offset}.");
            }
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as $sourceRow) {
                $mappedRow = [];
                foreach ($columnMap as $sourceColumn => $targetField) {
                    if (array_key_exists($sourceColumn, $sourceRow)) {
                        $mappedRow[$targetField] = $sourceRow[$sourceColumn];
                    }
                }
                try {
                    $create($mappedRow);
                    $result->recordSuccess();
                } catch (\Throwable $e) {
                    $result->recordFailure($rowIndex, $e->getMessage());
                }
                $rowIndex++;
            }

            $offset += $batchSize;
        } while (count($rows) === $batchSize);

        return $result;
    }
}
