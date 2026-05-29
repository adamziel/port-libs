<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempTransactionCurrentNextPlan;

$schemas = [
    'main' => ['schema_cookie' => 40, 'wal_schema_cookie' => 41, 'file' => '/srv/wp/current.sqlite'],
    'temp' => ['schema_cookie' => 6, 'temp' => true, 'file' => ''],
    'archive' => [
        'schema_cookie' => 10,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 11, 'commit' => true],
        ],
        'file' => '/srv/wp/archive.sqlite',
    ],
];

$operations = [
    ['op' => 'schema_write', 'schema' => 'main', 'object' => 'wp_options_autoload_idx'],
    ['op' => 'savepoint', 'savepoint' => 'plugin_import'],
    ['op' => 'schema_write', 'schema' => 'temp', 'object' => 'wp_options_stage'],
    ['op' => 'schema_write', 'schema' => 'archive', 'object' => 'wp_archive_options_idx'],
    ['op' => 'rollback_to', 'savepoint' => 'plugin_import'],
    ['op' => 'release', 'savepoint' => 'plugin_import'],
];

$committed = SQLiteAttachWalTempTransactionCurrentNextPlan::plan($schemas, $operations);
$rolledBack = SQLiteAttachWalTempTransactionCurrentNextPlan::plan($schemas, $operations, 'rollback');

echo json_encode([
    'commit_status' => $committed['status'],
    'commit_reprepare_schemas' => $committed['reprepare_schemas'],
    'main_cookie_after_commit' => $committed['schemas']['main']['post_transaction_cookie'],
    'temp_cookie_after_savepoint_rollback' => $committed['schemas']['temp']['post_transaction_cookie'],
    'full_rollback_status' => $rolledBack['status'],
    'main_cookie_after_full_rollback' => $rolledBack['schemas']['main']['post_transaction_cookie'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
