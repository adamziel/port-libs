<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteViewTriggerNameResolution;

$records = [
    new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer, option_name text, option_value text)', 1),
    new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', null, 'CREATE TEMP TABLE wp_options(option_id integer, option_name text, scratch_value text)', 2),
    new SQLiteSchemaRecord('view', 'autoloaded_options', 'autoloaded_options', 0, 'CREATE VIEW autoloaded_options(option_id, option_name) AS SELECT option_id, option_name FROM main.wp_options', 3),
    new SQLiteSchemaRecord('view', 'autoloaded_options', 'autoloaded_options', null, 'CREATE TEMP VIEW autoloaded_options(option_id, temp_name) AS SELECT option_id, option_name FROM temp.wp_options', 4),
    new SQLiteSchemaRecord('trigger', 'temp_stage_option_insert', 'autoloaded_options', null, 'CREATE TEMP TRIGGER temp_stage_option_insert INSTEAD OF INSERT ON autoloaded_options BEGIN SELECT new.option_id, new.temp_name; END', 5),
    new SQLiteSchemaRecord('trigger', 'temp_main_option_insert', 'autoloaded_options', null, 'CREATE TEMP TRIGGER temp_main_option_insert INSTEAD OF INSERT ON main.autoloaded_options BEGIN SELECT new.option_id, new.option_name; END', 6),
];

$tempStage = SQLiteViewTriggerNameResolution::resolveTrigger($records, 'temp_stage_option_insert');
$tempMain = SQLiteViewTriggerNameResolution::resolveTrigger($records, 'temp_main_option_insert');

if (($argv[1] ?? null) === '--self-test') {
    if ($tempStage['targetSchema'] !== 'temp' || $tempMain['targetSchema'] !== 'main' || $tempStage['status'] !== 'resolved' || $tempMain['status'] !== 'resolved') {
        fwrite(STDERR, "application-trigger-view-temp-name-resolution-current-next31 self-test failed\n");
        exit(1);
    }

    echo "application-trigger-view-temp-name-resolution-current-next31 self-test passed\n";
    exit(0);
}

echo json_encode([
    'temp_unqualified_target_schema' => $tempStage['targetSchema'],
    'temp_unqualified_columns' => $tempStage['columns'],
    'temp_main_target_schema' => $tempMain['targetSchema'],
    'temp_main_columns' => $tempMain['columns'],
    'summary' => SQLiteViewTriggerNameResolution::summary($records),
], JSON_PRETTY_PRINT) . "\n";
