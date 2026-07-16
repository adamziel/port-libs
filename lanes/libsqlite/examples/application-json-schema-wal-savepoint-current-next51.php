<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonSchemaWalSavepointPlan;

$plan = SQLiteJsonSchemaWalSavepointPlan::plan(
    [
        [
            'name' => 'wp_options',
            'type' => 'table',
            'rootpage' => 2,
            'sql' => 'CREATE TABLE wp_options (option_id integer primary key, option_name text unique, option_value text, autoload text)',
        ],
    ],
    [
        [
            'name' => 'wp_json_schema',
            'type' => 'table',
            'rootpage' => 8,
            'page_number' => 1,
            'wal_frame_index' => 1,
            'sql' => 'CREATE TABLE wp_json_schema (option_name text primary key, schema_json text not null)',
        ],
        [
            'name' => 'wp_json_schema_validate',
            'type' => 'trigger',
            'page_number' => 1,
            'wal_frame_index' => 2,
            'sql' => 'CREATE TRIGGER wp_json_schema_validate BEFORE INSERT ON wp_options BEGIN SELECT json_valid(NEW.option_value); END',
        ],
    ],
    ['schema_cookie' => 7, 'data_version' => 3]
);

echo json_encode([
    'status' => $plan['status'],
    'schemaCookie' => $plan['final_schema_cookie'],
    'dataVersion' => $plan['final_data_version'],
    'schemaNames' => $plan['final_schema_names'],
    'walFrames' => array_column($plan['wal_rollback_to_savepoint']['discarded_wal_frames'], 'frame_index'),
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
