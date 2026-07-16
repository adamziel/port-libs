<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSchemaRecord.php';
require_once __DIR__ . '/../src/SQLitePragmaSchemaCatalog.php';
require_once __DIR__ . '/../src/SQLiteAlterTableRenamePlan.php';
require_once __DIR__ . '/../src/SQLiteAlterTableColumnCorpus.php';
require_once __DIR__ . '/../src/SQLiteSchemaDdlReparsePlan.php';

use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$records = [
    new SQLiteSchemaRecord(
        'table',
        'wp_options',
        'wp_options',
        2,
        'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes")',
        1,
    ),
    new SQLiteSchemaRecord('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 3, null, 2),
    new SQLiteSchemaRecord('index', 'wp_options_autoload', 'wp_options', 4, 'CREATE INDEX wp_options_autoload ON wp_options(autoload)', 3),
];

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'transient_timeout_feed', 'option_value' => '1700000000', 'autoload' => 'no'],
];

$plan = SQLiteSchemaDdlReparsePlan::apply(
    $records,
    ['ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL CHECK(option_name_lc <> "")'],
    126,
    'main',
    [
        ['id' => 'wp-options-import-current', 'schema_cookie' => 126, 'sql' => 'SELECT option_name FROM wp_options'],
        ['id' => 'already-reparsed', 'schema_cookie' => 127, 'sql' => 'SELECT option_name_lc FROM wp_options'],
    ],
    ['wp_options' => $rows],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'ok');
    assert($plan['after_schema_cookie'] === 127);
    assert($plan['operations'][0]['kind'] === 'alter_table_add_column');
    assert($plan['operations'][0]['checked_rows'] === 3);
    assert($plan['operations'][0]['generated'] === true);
    assert($plan['invalidated_prepared'] === ['wp-options-import-current']);
    assert($plan['pragma_samples']['table_xinfo:wp_options']['rows'][4]['name'] === 'option_name_lc');
    echo "application-schema-alter-generated-check-reparse-current-source-next126 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'copied wp_options ALTER ADD generated CHECK reparse current-source next126',
    'applicationUse' => 'Plugin import code can add a virtual generated option-name column, scan current copied rows for CHECK compatibility, refresh sqlite_schema text, and reprepare stale statements without ext/sqlite.',
    'afterSchemaCookie' => $plan['after_schema_cookie'],
    'operation' => $plan['operations'][0],
    'invalidatedPrepared' => $plan['invalidated_prepared'],
    'generatedColumn' => $plan['pragma_samples']['table_xinfo:wp_options']['rows'][4],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
