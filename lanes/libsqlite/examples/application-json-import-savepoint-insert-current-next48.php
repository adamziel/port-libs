<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonImportSavepointPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$plan = SQLiteJsonImportSavepointPlan::plan([
    [
        'option_id' => 1,
        'option_name' => 'plugin_settings',
        'option_value' => '{"enabled":false,"imports":0}',
        'autoload' => 'yes',
        'page_number' => 2,
    ],
], [
    [
        'statement' => 'insert_plugin_catalog',
        'option_name' => 'plugin_catalog',
        'on_missing' => 'insert',
        'insert_option_id' => 130,
        'insert_autoload' => 'no',
        'page_number' => 5,
        'initial_value' => '{}',
        'path' => '$.plugins',
        'value' => new SQLiteJsonSubtypeValue('["seo","cache"]'),
        'wal_frame_index' => 1,
    ],
    [
        'statement' => 'insert_palette',
        'option_name' => 'theme_palette',
        'on_missing' => 'insert',
        'insert_option_id' => 141,
        'insert_autoload' => 'yes',
        'page_number' => 6,
        'function' => 'jsonb_set',
        'initial_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['colors' => ['accent' => 'blue']])),
        'path' => '$.colors',
        'value' => new SQLiteJsonSubtypeValue('{"accent":{"name":"green","contrast":7}}'),
        'wal_frame_index' => 2,
    ],
    [
        'statement' => 'insert_broken_catalog',
        'option_name' => 'broken_catalog',
        'on_missing' => 'insert',
        'insert_option_id' => 142,
        'page_number' => 7,
        'initial_value' => '{"broken":',
        'path' => '$.enabled',
        'value' => true,
        'wal_frame_index' => 3,
    ],
]);

echo json_encode([
    'scenario' => 'application-json-import-savepoint-insert-current-next48',
    'applicationUse' => 'Insert missing copied wp_options JSON rows inside the active import savepoint while statement rollback removes a malformed inserted row without discarding earlier JSON imports.',
    'status' => $plan['status'],
    'appliedStatements' => array_column($plan['applied'], 'statement'),
    'insertedStatements' => array_values(array_map(
        static fn (array $row): string => $row['statement'],
        array_filter($plan['applied'], static fn (array $row): bool => $row['inserted_option'])
    )),
    'failedStatements' => array_column($plan['failed'], 'statement'),
    'finalOptionNames' => array_column($plan['final_rows'], 'option_name'),
    'savepointPages' => $plan['savepoint_state'][1]['page_numbers'] ?? [],
    'rollbackPages' => $plan['rollback_to_savepoint']['restored_page_numbers'],
    'walRollbackFrames' => array_column($plan['wal_rollback_to_savepoint']['discarded_wal_frames'], 'frame_index'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
