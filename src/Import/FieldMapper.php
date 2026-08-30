<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Import;

use ZeroAI\Boss\Sdk\Exceptions\ValidationException;

/**
 * Registry of BOSS canonical target fields per entity type, and validator for
 * caller-supplied column maps (project 43 task #515, decision #27).
 *
 * Entity types: 'lead', 'customer', 'sale', 'product'.
 * A column map is ['source_column' => 'boss_target_field']. Target fields not
 * in the canonical list for the entity type are rejected before any import
 * runs - so a human-approved mapping never silently passes unknown fields to
 * the BOSS API.
 *
 * Usage:
 *   $mapper = new FieldMapper('lead');
 *   $fields  = $mapper->canonicalFields();           // all valid targets
 *   $suggest = $mapper->suggestMapping($csvHeaders); // pre-fill for UI
 *   // ... human reviews and confirms $columnMap ...
 *   $mapper->validate($columnMap);                   // throws if invalid
 */
final class FieldMapper
{
    private const FIELDS = [
        'lead' => [
            'first_name', 'last_name', 'email', 'phone',
            'company_name', 'job_title', 'lead_status', 'lead_score',
            'lead_source', 'notes', 'city', 'state', 'country',
            'zip_code', 'website',
        ],
        'customer' => [
            'first_name', 'last_name', 'email', 'phone',
            'company_name', 'job_title', 'lead_status', 'lead_score',
            'lead_source', 'notes', 'city', 'state', 'country',
            'zip_code', 'website',
        ],
        'sale' => [
            'deal_name', 'deal_value', 'deal_stage', 'close_date',
            'currency', 'notes',
        ],
        'product' => [
            'product_name', 'sku', 'price', 'quantity',
            'category', 'description',
        ],
    ];

    private string $entityType;

    public function __construct(string $entityType)
    {
        if (!array_key_exists($entityType, self::FIELDS)) {
            throw new ValidationException(sprintf(
                "Unknown entity type '%s'. Valid types: %s.",
                $entityType,
                implode(', ', array_keys(self::FIELDS))
            ));
        }
        $this->entityType = $entityType;
    }

    /** @return list<string> canonical BOSS target field names for this entity type. */
    public function canonicalFields(): array
    {
        return self::FIELDS[$this->entityType];
    }

    /**
     * Validates a column map against the canonical field list. Throws before
     * any import runs so a bad mapping is caught at review time, not at row 500.
     *
     * @param array<string,string> $columnMap source_col => boss_target_field
     * @throws ValidationException on empty map or unknown target fields
     */
    public function validate(array $columnMap): void
    {
        if ($columnMap === []) {
            throw new ValidationException('Column map is empty - nothing to import.');
        }
        $unknown = array_diff(array_values($columnMap), self::FIELDS[$this->entityType]);
        if ($unknown !== []) {
            throw new ValidationException(sprintf(
                "Column map targets unknown field(s) for entity type '%s': %s. Canonical fields are: %s.",
                $this->entityType,
                implode(', ', $unknown),
                implode(', ', self::FIELDS[$this->entityType])
            ));
        }
    }

    /**
     * Suggests a naive exact-name mapping: any source column whose name exactly
     * matches a canonical field is pre-mapped to it. Non-matching columns map to
     * null (unmapped). This is a UI pre-fill convenience - the human must still
     * review and confirm the mapping before importWithMapping() is called.
     *
     * @param list<string> $sourceColumns from describeColumns() or CsvImporter::headers()
     * @return array<string,string|null> source_col => matched_boss_field, or null if unmatched
     */
    public function suggestMapping(array $sourceColumns): array
    {
        $canonical = array_flip(self::FIELDS[$this->entityType]);
        $result = [];
        foreach ($sourceColumns as $col) {
            $result[$col] = isset($canonical[$col]) ? $col : null;
        }
        return $result;
    }
}
