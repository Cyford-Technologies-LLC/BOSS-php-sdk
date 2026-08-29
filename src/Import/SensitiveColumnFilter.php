<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Import;

/**
 * Strips credential/PII-shaped columns from sample rows before they can ever
 * be handed to anything else - an AI field-mapping suggestion (BOSS task
 * 516), a log line, a UI preview. Per project 43 decision #27: "sensitive-
 * looking columns must be stripped at the connector level before any sample
 * rows are ever sent to the AI - never rely on the AI's judgment to ignore
 * them." This runs regardless of whether AI mapping is even used, since a
 * manual-mapping UI preview shouldn't display raw password hashes either.
 */
final class SensitiveColumnFilter
{
    private const PATTERN = '/password|passwd|pwd_hash|ssn|social_security|tax_id|credit_card|card_number|card_num|cvv|cvc|secret|api_key|apikey|access_token|refresh_token|private_key|bank_account|routing_number|iban|swift/i';

    /** @return list<string> column names this filter would strip. */
    public static function sensitiveColumnsIn(array $columnNames): array
    {
        return array_values(array_filter($columnNames, static fn(string $c): bool => (bool)preg_match(self::PATTERN, $c)));
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>> same rows, sensitive columns replaced with a redaction marker
     */
    public static function redactRows(array $rows): array
    {
        foreach ($rows as &$row) {
            foreach (array_keys($row) as $column) {
                if (preg_match(self::PATTERN, $column)) {
                    $row[$column] = '[REDACTED-SENSITIVE-COLUMN]';
                }
            }
        }
        return $rows;
    }
}
