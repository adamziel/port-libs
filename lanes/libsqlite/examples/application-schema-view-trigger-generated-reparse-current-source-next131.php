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
        'wp_autoloaded_option_names',
        'wp_autoloaded_option_names',
        0,
        "CREATE VIEW wp_autoloaded_option_names AS SELECT * FROM wp_options WHERE autoload = 'yes'",
        2,
    ),
    new SQLiteSchemaRecord(
        'trigger',
        'wp_options_generated_audit',
        'wp_options',
        0,
        'CREATE TRIGGER wp_options_generated_audit AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(label) VALUES(new.option_name_lc); END',
        3,
    ),
];

$plan = SQLiteSchemaDdlReparsePlan::apply(
    $records,
    ['ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL CHECK(option_name_lc <> "")'],
    131,
    'main',
    [
        ['id' => 'wp-autoloaded-options-star', 'schema_cookie' => 131, 'sql' => 'SELECT * FROM wp_autoloaded_option_names'],
    ],
    [
        'wp_options' => [
            ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
            ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes'],
        ],
    ],
);

$operation = $plan['operations'][0];
$summary = [
    'scenario' => 'application-schema-view-trigger-generated-reparse-current-source-next131',
    'applicationUse' => 'After a copied Application schema import adds a generated option-name column, dependent SELECT-star views and triggers are marked for current-source reparse before prepared statements are reused.',
    'schemaCookie' => [
        'before' => $plan['before_schema_cookie'],
        'after' => $plan['after_schema_cookie'],
    ],
    'generatedColumn' => $operation['column'],
    'dependentReparseRecords' => $operation['dependent_reparse_records'],
    'starExpansionRecords' => $operation['star_expansion_records'],
    'resolvedTriggerRecords' => $operation['resolved_trigger_records'],
    'unresolvedTriggerRecords' => $operation['unresolved_trigger_records'],
    'invalidatedPrepared' => $plan['invalidated_prepared'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['schemaCookie']['after'] === 132);
    assert($summary['generatedColumn'] === 'option_name_lc');
    assert($summary['starExpansionRecords'] === ['view:wp_autoloaded_option_names']);
    assert($summary['resolvedTriggerRecords'] === ['trigger:wp_options_generated_audit']);
    assert($summary['unresolvedTriggerRecords'] === []);
    assert($summary['invalidatedPrepared'] === ['wp-autoloaded-options-star']);
    echo "application-schema-view-trigger-generated-reparse-current-source-next131 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
