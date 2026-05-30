<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheTransactionPlan;

$schemas = [
    'main' => [
        'schema_cookie' => 20,
        'wal_schema_cookie' => 21,
        'wal_frames' => [['page' => 1, 'schema_cookie' => 21, 'commit' => true]],
        'tables' => ['wp_options', 'wp_posts'],
        'next_tables' => ['wp_options', 'wp_posts', 'wp_plugin_state'],
        'indexes' => ['wp_options_name'],
        'next_indexes' => ['wp_options_name', 'wp_plugin_state_name'],
        'file' => 'wp-content/database/.ht.sqlite',
        'cache' => 'shared',
    ],
    'temp' => [
        'schema_cookie' => 4,
        'tables' => ['wp_options_stage'],
        'next_tables' => ['wp_options_stage', 'wp_options'],
        'indexes' => ['wp_options_stage_name'],
        'next_indexes' => ['wp_options_stage_name', 'wp_options_temp_name'],
        'file' => '',
    ],
    'archive' => [
        'schema_cookie' => 7,
        'wal_frames' => [['page' => 1, 'schema_cookie' => 8, 'commit' => true]],
        'tables' => ['wp_options', 'wp_optionmeta'],
        'next_tables' => ['wp_options', 'wp_optionmeta', 'wp_archive_state'],
        'indexes' => ['wp_options_name'],
        'next_indexes' => ['wp_options_name', 'wp_archive_state_name'],
        'file' => 'wp-content/database/archive.sqlite',
        'cache' => 'shared',
    ],
];

$operations = [
    ['op' => 'schema_write', 'schema' => 'main', 'object' => 'wp_plugin_state'],
    ['op' => 'schema_write', 'schema' => 'temp', 'object' => 'wp_options'],
    ['op' => 'schema_write', 'schema' => 'archive', 'object' => 'wp_archive_state'],
];

$statements = [
    ['name' => 'main-options-reader', 'sql' => 'SELECT option_value FROM [main].[wp_options] WHERE option_name = ?'],
    ['name' => 'temp-stage-insert', 'sql' => 'INSERT INTO [temp].[wp_options_stage](option_name, option_value) VALUES (?, ?)'],
    ['name' => 'archive-options-delete', 'sql' => 'DELETE FROM [archive].[wp_options] WHERE option_name = ?'],
    ['name' => 'unqualified-options-reader', 'sql' => 'SELECT option_value FROM [wp_options] WHERE option_name = ?', 'active' => true],
];

$plan = SQLiteAttachWalTempSchemaCacheTransactionPlan::plan($schemas, $operations, $statements);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'schema_cache_expired');
    assert($plan['changed_schemas'] === ['temp', 'main', 'archive']);
    assert($plan['statements']['0']['tables'] === ['main.wp_options']);
    assert($plan['statements']['1']['next_step_action'] === 'sqlite_schema_before_write_retry');
    assert($plan['statements']['3']['schema_transitions']['0']['current_schema'] === 'main');
    assert($plan['statements']['3']['schema_transitions']['0']['next_schema'] === 'temp');
    assert($plan['statements']['3']['next_step_action'] === 'finish_current_snapshot_then_sqlite_schema_on_reset');

    echo "application-attach-temp-wal-schema-cache-current-transaction-source self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-attach-temp-wal-schema-cache-current-transaction-source',
    'status' => $plan['status'],
    'changed_schemas' => $plan['changed_schemas'],
    'expired_statements' => $plan['expired_statements'],
    'blocked_writes' => $plan['write_statements_blocked_before_retry'],
    'unqualified_transition' => $plan['statements']['3']['schema_transitions']['0'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
