<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$tables = [
    'app_settings' => [
        ['setting_id' => 1, 'tenant_id' => 10, 'key_name' => 'alpha', 'state' => 'queued', 'value_size' => 10],
        ['setting_id' => 2, 'tenant_id' => 10, 'key_name' => 'beta', 'state' => 'queued', 'value_size' => 20],
        ['setting_id' => 3, 'tenant_id' => 20, 'key_name' => 'alpha', 'state' => 'queued', 'value_size' => 30],
        ['setting_id' => 4, 'tenant_id' => 20, 'key_name' => 'beta', 'state' => 'queued', 'value_size' => 40],
    ],
    'app_setting_targets' => [
        ['target_id' => 101, 'tenant_id' => 10, 'key_name' => 'alpha', 'batch_name' => 'retire', 'priority' => 40],
        ['target_id' => 102, 'tenant_id' => 10, 'key_name' => 'beta', 'batch_name' => 'retire', 'priority' => 30],
        ['target_id' => 103, 'tenant_id' => 20, 'key_name' => 'alpha', 'batch_name' => 'retire', 'priority' => 20],
        ['target_id' => 104, 'tenant_id' => 20, 'key_name' => 'beta', 'batch_name' => 'retire', 'priority' => 10],
    ],
];

$updateSql = "UPDATE app_settings SET state = 'active' WHERE state = 'queued' RETURNING setting_id, state ORDER BY value_size ASC LIMIT changes()+2 OFFSET total_changes()";
$deleteSql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets WHERE batch_name = 'retire' ORDER BY priority DESC LIMIT last_insert_rowid()+1, changes()+2) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id";

$update = SQLiteUpdateDeleteReturningSql::execute($updateSql, $tables, 'setting_id', [['tenant_id', 'key_name']]);
$delete = SQLiteUpdateDeleteReturningSql::execute($deleteSql, $tables, 'setting_id', [['tenant_id', 'key_name']]);

$payload = [
    'status' => 'rowvalue-limit-connection-status-ready',
    'updateSelectedIds' => $update['plan']->selectedIds,
    'updateReturningIds' => array_column($update['returning'], 'setting_id'),
    'deleteSelectedIds' => $delete['plan']->selectedIds,
    'deleteRemainingIds' => array_column($delete['tables']['app_settings'], 'setting_id'),
    'connectionStatusLimitDefaults' => [
        'changes' => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT changes()')['limit'],
        'total_changes' => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT total_changes()')['limit'],
        'last_insert_rowid' => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT last_insert_rowid()')['limit'],
    ],
];

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        'status' => 'rowvalue-limit-connection-status-ready',
        'updateSelectedIds' => [1, 2],
        'updateReturningIds' => [1, 2],
        'deleteSelectedIds' => [2, 3],
        'deleteRemainingIds' => [1, 4],
        'connectionStatusLimitDefaults' => [
            'changes' => 0,
            'total_changes' => 0,
            'last_insert_rowid' => 0,
        ],
    ];

    if ($payload !== $expected) {
        fwrite(STDERR, "application-rowvalue-limit-connection-status self-test failed\n");
        fwrite(STDERR, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        exit(1);
    }

    echo "application-rowvalue-limit-connection-status self-test passed\n";
    exit(0);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
