<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$records = [
    new SQLiteSchemaRecord(
        'table',
        'wp_options',
        'wp_options',
        2,
        "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT 'yes')",
        1,
    ),
    new SQLiteSchemaRecord(
        'view',
        'wp_autoloaded_options',
        'wp_autoloaded_options',
        0,
        "CREATE VIEW wp_autoloaded_options AS SELECT option_id, option_name FROM wp_options WHERE autoload = 'yes'",
        2,
    ),
    new SQLiteSchemaRecord(
        'trigger',
        'wp_options_ai',
        'wp_options',
        0,
        "CREATE TRIGGER wp_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(label) VALUES('wp_options'); SELECT count(*) FROM wp_options; END",
        3,
    ),
];

$plan = SQLiteSchemaDdlReparsePlan::apply(
    $records,
    ['ALTER TABLE wp_options RENAME TO wp_site_options'],
    125,
    'main',
    [
        ['id' => 'wp-options-autoload-query', 'schema_cookie' => 125, 'sql' => 'SELECT option_name FROM wp_options WHERE autoload = ?'],
    ],
);

$summary = [
    'scenario' => 'application-schema-alter-reparse-view-trigger-current-source-next125',
    'applicationUse' => 'Reparse copied Application views/triggers after ALTER TABLE RENAME so stale sqlite_schema SQL does not target the old options table.',
    'schemaCookie' => [
        'before' => $plan['before_schema_cookie'],
        'after' => $plan['after_schema_cookie'],
    ],
    'operation' => $plan['operations'][0]['kind'],
    'renamedTo' => $plan['operations'][0]['new_name'],
    'rewrittenRecords' => $plan['operations'][0]['rewritten_records'],
    'dependentReparseCount' => $plan['operations'][0]['dependent_reparse_count'],
    'invalidatedPrepared' => $plan['invalidated_prepared'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['schemaCookie']['after'] === 126);
    assert($summary['dependentReparseCount'] === 2);
    assert(in_array('view:wp_autoloaded_options', $summary['rewrittenRecords'], true));
    assert(in_array('trigger:wp_options_ai', $summary['rewrittenRecords'], true));
    assert($summary['invalidatedPrepared'] === ['wp-options-autoload-query']);
    echo "application-schema-alter-reparse-view-trigger-current-source-next125 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
