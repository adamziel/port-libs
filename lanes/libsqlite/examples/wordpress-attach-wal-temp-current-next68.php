<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempCurrentNextPlan;

$schemas = [
    'main' => ['journal_mode' => 'wal', 'page_count' => 5, 'change_counter' => 77, 'wal_frame_count' => 20, 'tables' => ['wp_options', 'wp_posts']],
    'temp' => ['journal_mode' => 'delete', 'page_count' => 2, 'change_counter' => 4, 'tables' => ['wp_options_stage']],
    'archive' => ['journal_mode' => 'wal', 'page_count' => 3, 'change_counter' => 8, 'wal_frame_count' => 40, 'tables' => ['wp_options_archive']],
];

$plan = SQLiteAttachWalTempCurrentNextPlan::rollbackPlan($schemas, [
    ['table' => 'wp_options_stage', 'page' => 2, 'bytes' => 128],
    ['table' => 'wp_options', 'page' => 6, 'bytes' => 512],
    ['schema' => 'archive', 'table' => 'wp_options_archive', 'page' => 4, 'bytes' => 256],
]);

if (($argv[1] ?? '') === '--self-test') {
    if (
        $plan['status'] !== 'rolled_back'
        || $plan['wal_schemas'] !== ['main', 'archive']
        || $plan['rollback_schemas'] !== ['temp']
        || $plan['next']['schemas']['main']['wal_frame_count'] !== 20
        || $plan['next']['schemas']['archive']['wal_frame_count'] !== 40
        || $plan['operations'][1]['restore_frame_count'] !== 20
    ) {
        fwrite(STDERR, "wordpress-attach-wal-temp-current-next68 self-test failed\n");
        exit(1);
    }

    echo "wordpress-attach-wal-temp-current-next68 self-test passed\n";
    exit(0);
}

echo json_encode([
    'scenario' => 'wordpress-attach-wal-temp-current-next68',
    'status' => $plan['status'],
    'search_order' => $plan['search_order'],
    'wal_schemas' => $plan['wal_schemas'],
    'rollback_schemas' => $plan['rollback_schemas'],
    'main_restore_frame_count' => $plan['operations'][1]['restore_frame_count'],
    'archive_restore_frame_count' => $plan['operations'][2]['restore_frame_count'],
    'temp_restored_pages' => $plan['operations'][0]['pages'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT) . "\n";
