<?php

declare(strict_types=1);

foreach (glob(__DIR__ . '/../src/*.php') ?: [] as $sourceFile) {
    require_once $sourceFile;
}

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_alpha',
        'option_value' => '{"flags":["network","beta"]}',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_empty',
        'option_value' => '{"flags":[]}',
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_jsonb',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['flags' => ['jsonb', 'fast']])),
    ],
];

$rows = SQLiteSelectSql::execute(
    "SELECT o.option_name AS option_name, f.rowid AS flag_rowid, f.atom AS flag
       FROM wp_options AS o
       LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid = 2
      ORDER BY option_name",
    ['wp_options' => $options],
);

echo json_encode([
    'scenario' => 'application-json-table-left-join-rowid-current-next77',
    'rows' => $rows,
    'matchedOptions' => array_values(array_filter(array_column($rows, 'option_name'), static fn (string $name): bool => $name !== 'plugin_empty')),
    'applicationUse' => 'Copied wp_options plugin JSON settings can LEFT JOIN json_each() by rowid/_rowid_/oid aliases while preserving NULL extension for empty option arrays and JSONB-backed option values without requiring ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
