<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaBulkImportPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$dump = <<<'SQL'
CREATE TABLE wp_options (
  option_id INTEGER PRIMARY KEY AUTOINCREMENT,
  option_name TEXT NOT NULL UNIQUE COLLATE NOCASE,
  option_value TEXT NOT NULL DEFAULT '',
  autoload TEXT NOT NULL DEFAULT 'yes'
);
CREATE UNIQUE INDEX wp_options_option_name ON wp_options(option_name COLLATE NOCASE);
CREATE VIEW wp_autoloaded_options AS SELECT option_name, option_value FROM wp_options WHERE autoload = 'yes';
CREATE TRIGGER wp_options_ai AFTER INSERT ON wp_options BEGIN
  SELECT 'inserted; option';
END;
SQL;

$plan = SQLiteSchemaBulkImportPlan::plan($dump, [], [
    'schema_version' => 41,
    'data_version' => 9,
    'next_rootpage' => 5,
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'ok');
    assert($plan['applied_count'] === 4);
    assert($plan['schema_version_after'] === 45);
    assert($plan['ordered_names'] === ['wp_options', 'wp_options_option_name', 'wp_autoloaded_options', 'wp_options_ai']);
}

echo json_encode([
    'status' => $plan['status'],
    'applied_count' => $plan['applied_count'],
    'schema_version_after' => $plan['schema_version_after'],
    'ordered_names' => $plan['ordered_names'],
], JSON_PRETTY_PRINT) . "\n";
