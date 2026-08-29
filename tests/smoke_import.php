<?php
declare(strict_types=1);

/**
 * Standalone smoke test for the onboarding-import connector (BOSS task 514).
 * php www/dev-clients/php-sdk/tests/smoke_import.php
 */

require __DIR__ . '/../src/Exceptions/SdkException.php';
require __DIR__ . '/../src/Exceptions/ValidationException.php';
require __DIR__ . '/../src/Import/SensitiveColumnFilter.php';
require __DIR__ . '/../src/Import/ImportResult.php';
require __DIR__ . '/../src/Import/DbConnectionImporter.php';

use ZeroAI\Boss\Sdk\Exceptions\ValidationException;
use ZeroAI\Boss\Sdk\Import\DbConnectionImporter;

$failures = 0;
function check(string $label, bool $cond): void {
    global $failures;
    echo ($cond ? "PASS" : "FAIL") . " - {$label}\n";
    if (!$cond) { $failures++; }
}

// Simulate "a business's own already-live DB connection" with an in-memory SQLite PDO -
// the point of this test is that DbConnectionImporter never opens its own connection.
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE legacy_customers (
    id INTEGER PRIMARY KEY,
    full_name TEXT,
    contact_email TEXT,
    password_hash TEXT,
    credit_card_number TEXT
)");
$stmt = $pdo->prepare('INSERT INTO legacy_customers (full_name, contact_email, password_hash, credit_card_number) VALUES (?, ?, ?, ?)');
for ($i = 1; $i <= 5; $i++) {
    $stmt->execute(["Customer {$i}", "customer{$i}@example.com", 'bcrypt$$fakehash' . $i, '4111-1111-1111-' . (1000 + $i)]);
}

$importer = new DbConnectionImporter($pdo, 'legacy_customers');

$columns = $importer->describeColumns();
check('describeColumns() finds all 5 source columns', count($columns) === 5 && in_array('contact_email', $columns, true));

$sensitive = $importer->sensitiveColumns();
check('sensitiveColumns() flags password_hash and credit_card_number', in_array('password_hash', $sensitive, true) && in_array('credit_card_number', $sensitive, true));
check('sensitiveColumns() does not flag contact_email/full_name', !in_array('contact_email', $sensitive, true) && !in_array('full_name', $sensitive, true));

$sample = $importer->sampleRows(10);
check('sampleRows() returns all 5 rows', count($sample) === 5);
check('sampleRows() redacts password_hash', $sample[0]['password_hash'] === '[REDACTED-SENSITIVE-COLUMN]');
check('sampleRows() redacts credit_card_number', $sample[0]['credit_card_number'] === '[REDACTED-SENSITIVE-COLUMN]');
check('sampleRows() leaves contact_email intact', $sample[0]['contact_email'] === 'customer1@example.com');

check('countRows() reports 5', $importer->countRows() === 5);

// importWithMapping(): an approved mapping drops unlisted columns (including the sensitive
// ones) - they were never in the map, so they never reach $create() at all.
$pushed = [];
$columnMap = ['full_name' => 'name', 'contact_email' => 'email'];
$result = $importer->importWithMapping(function (array $row) use (&$pushed) {
    $pushed[] = $row;
}, $columnMap);

check('importWithMapping() imports all 5 rows', $result->imported === 5 && $result->failureCount() === 0);
check('importWithMapping() maps to target field names', $pushed[0] === ['name' => 'Customer 1', 'email' => 'customer1@example.com']);
check('importWithMapping() never passes unmapped/sensitive columns to create()', !array_key_exists('password_hash', $pushed[0]) && !array_key_exists('credit_card_number', $pushed[0]));

// A row-level failure is recorded, not fatal to the whole import.
$callCount = 0;
$result2 = $importer->importWithMapping(function (array $row) use (&$callCount) {
    $callCount++;
    if ($callCount === 2) {
        throw new \RuntimeException('simulated API rejection');
    }
}, $columnMap);
check('importWithMapping() continues past a per-row failure', $result2->imported === 4 && $result2->failureCount() === 1);
check('importWithMapping() records the failure reason', $result2->failures[0]['error'] === 'simulated API rejection');

try {
    $importer->importWithMapping(fn(array $r) => null, []);
    check('importWithMapping() rejects an empty mapping', false);
} catch (ValidationException $e) {
    check('importWithMapping() rejects an empty mapping', true);
}

try {
    new DbConnectionImporter($pdo, 'bad; DROP TABLE legacy_customers;--');
    check('Constructor rejects a non-identifier table name', false);
} catch (ValidationException $e) {
    check('Constructor rejects a non-identifier table name', true);
}

echo "\n" . ($failures === 0 ? "ALL PASSED\n" : "{$failures} FAILURE(S)\n");
exit($failures === 0 ? 0 : 1);
