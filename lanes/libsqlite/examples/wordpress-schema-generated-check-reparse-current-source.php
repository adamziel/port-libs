<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSchemaGeneratedCheckReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, $sql, $rowId);

$current = <<<'SQL'
CREATE TABLE wp_options(
  option_id INTEGER PRIMARY KEY,
  option_name TEXT NOT NULL,
  option_value TEXT NOT NULL DEFAULT '',
  option_slug TEXT GENERATED ALWAYS AS (lower(option_name)) VIRTUAL CHECK(option_slug <> '')
)
SQL;

$next = <<<'SQL'
CREATE TABLE wp_options(
  option_id INTEGER PRIMARY KEY,
  option_name TEXT NOT NULL,
  option_value TEXT NOT NULL DEFAULT '',
  autoload TEXT NOT NULL DEFAULT 'yes',
  option_slug TEXT GENERATED ALWAYS AS (lower(option_name)) STORED CHECK(option_slug <> '' AND option_slug NOT LIKE 'tmp_%'),
  option_value_len INTEGER AS (length(option_value || ' CHECK(option_value) ')) STORED CHECK(option_value_len BETWEEN 0 AND 1048576),
  CHECK(option_name <> 'deprecated')
)
SQL;

$plan = SQLiteSchemaGeneratedCheckReparsePlan::currentNext(
    [$record($current, 1)],
    [$record($next, 2)],
    'wp_options',
    ['schema_version_before' => 116, 'schema_version_after' => 117],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['requiresReparse'] === true);
    assert($plan['status'] === 'reparse-required');
    assert(array_column($plan['generatedChecksAdded'], 'column') === ['option_slug', 'option_value_len']);
    echo "wordpress-schema-generated-check-reparse-current-source self-test passed\n";
    return;
}

echo json_encode([
    'operation' => $plan['operation'],
    'requiresReparse' => $plan['requiresReparse'],
    'generatedChecksAdded' => array_map(
        static fn (array $check): array => ['column' => $check['column'], 'check' => $check['check']],
        $plan['generatedChecksAdded'],
    ),
    'tableChecksAdded' => $plan['tableChecksAdded'],
    'wordpressUse' => 'Copied wp_options schemas with generated metadata CHECK changes must expire prepared schema views after sqlite_schema reparses, while CHECK/UNIQUE text inside generated expressions remains literal text.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
