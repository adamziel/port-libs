<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAlterTableColumnCorpus;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$table = new SQLiteSchemaRecord(
    'table',
    'wp_options',
    'wp_options',
    2,
    'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes")',
    1,
);

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'transient_timeout_feed', 'option_value' => '1700000000', 'autoload' => 'no'],
];

$accepted = SQLiteAlterTableColumnCorpus::addColumn(
    $table,
    "ALTER TABLE wp_options ADD COLUMN option_name_lower TEXT AS (lower(option_name)) VIRTUAL CHECK(option_name_lower <> '')",
    $rows,
);

$rejected = null;
try {
    SQLiteAlterTableColumnCorpus::addColumn(
        $table,
        "ALTER TABLE wp_options ADD COLUMN option_value_len INTEGER AS (length(option_value)) VIRTUAL CHECK(option_value_len > 3)",
        [['option_id' => 4, 'option_name' => 'short_option', 'option_value' => 'abc', 'autoload' => 'no']],
    );
} catch (InvalidArgumentException $error) {
    $rejected = $error->getMessage();
}

echo json_encode([
    'acceptedColumn' => $accepted['column'],
    'acceptedGenerated' => $accepted['generated'],
    'checkedRows' => $accepted['checked_rows'],
    'rejectedCurrentRow' => $rejected,
    'applicationUse' => 'Validate ALTER TABLE ADD COLUMN generated CHECK constraints against copied wp_options rows before rewriting schema SQL, without requiring ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
