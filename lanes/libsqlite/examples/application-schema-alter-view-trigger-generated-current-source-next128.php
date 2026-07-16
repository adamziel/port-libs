<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$records = [
    new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes")', 1),
    new SQLiteSchemaRecord('index', 'wp_options_autoload', 'wp_options', 3, 'CREATE INDEX wp_options_autoload ON wp_options(autoload)', 2),
    new SQLiteSchemaRecord('view', 'wp_options_export', 'wp_options_export', 0, 'CREATE VIEW wp_options_export AS SELECT * FROM wp_options WHERE autoload = "yes"', 3),
    new SQLiteSchemaRecord('view', 'wp_options_names', 'wp_options_names', 0, 'CREATE VIEW wp_options_names AS SELECT option_name FROM wp_options WHERE autoload = "yes"', 4),
    new SQLiteSchemaRecord('trigger', 'wp_options_ai', 'wp_options', 0, 'CREATE TRIGGER wp_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name) VALUES(new.option_id, new.option_name); SELECT * FROM wp_options WHERE option_id = new.option_id; END', 5),
    new SQLiteSchemaRecord('view', 'wp_postmeta_names', 'wp_postmeta_names', 0, 'CREATE VIEW wp_postmeta_names AS SELECT meta_key FROM wp_postmeta', 6),
];

$plan = SQLiteSchemaDdlReparsePlan::apply(
    $records,
    ['ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL CHECK(option_name_lc <> "")'],
    128,
    'main',
    [
        ['id' => 'wp-options-export-star', 'schema_cookie' => 128, 'sql' => 'SELECT * FROM wp_options_export'],
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
    'scenario' => 'application-schema-alter-view-trigger-generated-current-source-next128',
    'applicationUse' => 'Preview copied Application ALTER TABLE ADD generated-column migrations where dependent views/triggers must be reparsed from the current sqlite_schema source, especially SELECT * view/trigger bodies whose expanded columns change after the generated column is admitted.',
    'schemaCookie' => [
        'before' => $plan['before_schema_cookie'],
        'after' => $plan['after_schema_cookie'],
    ],
    'operation' => $operation['kind'],
    'addedColumn' => $operation['column'],
    'checkedRows' => $operation['checked_rows'],
    'dependentReparseRecords' => $operation['dependent_reparse_records'],
    'starExpansionRecords' => $operation['star_expansion_records'],
    'invalidatedPrepared' => $plan['invalidated_prepared'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['schemaCookie']['after'] === 129);
    assert($summary['checkedRows'] === 2);
    assert(in_array('view:wp_options_export', $summary['dependentReparseRecords'], true));
    assert(in_array('trigger:wp_options_ai', $summary['dependentReparseRecords'], true));
    assert(in_array('view:wp_options_export', $summary['starExpansionRecords'], true));
    assert(in_array('trigger:wp_options_ai', $summary['starExpansionRecords'], true));
    assert(!in_array('view:wp_postmeta_names', $summary['dependentReparseRecords'], true));
    assert($summary['invalidatedPrepared'] === ['wp-options-export-star']);
    echo "application-schema-alter-view-trigger-generated-current-source-next128 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
