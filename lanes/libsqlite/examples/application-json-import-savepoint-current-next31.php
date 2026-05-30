<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonImportSavepointPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$plan = SQLiteJsonImportSavepointPlan::plan([
    [
        'setting_id' => 1,
        'key_name' => 'plugin_settings',
        'key_value' => '{"enabled":false,"modules":["core"]}',
        'load_policy' => 'yes',
        'page_number' => 2,
    ],
    [
        'setting_id' => 65,
        'key_name' => 'theme_mods_twenty',
        'key_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['colors' => ['accent' => 'blue']])),
        'load_policy' => 'yes',
        'page_number' => 3,
    ],
    [
        'setting_id' => 130,
        'key_name' => 'broken_plugin_settings',
        'key_value' => '{"enabled":',
        'load_policy' => 'no',
        'page_number' => 4,
    ],
], [
    [
        'statement' => 'enable_plugin',
        'key_name' => 'plugin_settings',
        'path' => '$.enabled',
        'value' => true,
    ],
    [
        'statement' => 'theme_accent',
        'key_name' => 'theme_mods_twenty',
        'function' => 'jsonb_set',
        'path' => '$.colors.accent',
        'value' => new SQLiteJsonSubtypeValue('{"name":"green","contrast":7}'),
    ],
    [
        'statement' => 'broken_payload',
        'key_name' => 'broken_plugin_settings',
        'path' => '$.enabled',
        'value' => true,
    ],
]);

echo json_encode([
    'scenario' => 'application-json-import-savepoint',
    'applicationUse' => 'Apply copied app_settings JSON key mutations with SQLite statement-journal savepoint rollback semantics so one malformed JSON key rolls back without discarding the surrounding plugin-settings import.',
    'status' => $plan['status'],
    'appliedStatements' => array_column($plan['applied'], 'statement'),
    'failedStatements' => array_column($plan['failed'], 'statement'),
    'failedRollbackPages' => $plan['failed'][0]['rollback']['restored_page_numbers'] ?? [],
    'commitPages' => $plan['commit']['committed_page_numbers'],
    'savepointPages' => $plan['savepoint_state'][1]['page_numbers'] ?? [],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
