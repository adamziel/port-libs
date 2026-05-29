<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempPlan;

$schemas = [
    'main' => ['journal_mode' => 'wal', 'page_count' => 4, 'change_counter' => 31, 'wal_frame_count' => 12, 'tables' => ['wp_options']],
    'temp' => ['journal_mode' => 'delete', 'page_count' => 2, 'change_counter' => 4, 'tables' => ['wp_options_stage']],
    'archive' => ['journal_mode' => 'wal', 'page_count' => 3, 'change_counter' => 8, 'wal_frame_count' => 40, 'tables' => ['wp_options_archive']],
];

$plan = SQLiteAttachWalTempPlan::plan($schemas, [
    ['table' => 'wp_options_stage', 'page' => 2, 'bytes' => 256],
    ['table' => 'wp_options', 'page' => 5, 'bytes' => 512],
    ['schema' => 'archive', 'table' => 'wp_options_archive', 'page' => 4, 'bytes' => 512],
]);

if (($argv[1] ?? '') === '--self-test') {
    if (
        $plan['status'] !== 'committed'
        || $plan['wal_schemas'] !== ['main', 'archive']
        || $plan['rollback_schemas'] !== ['temp']
        || $plan['next']['schemas']['main']['wal_frame_count'] !== 13
        || $plan['next']['schemas']['archive']['wal_frame_count'] !== 41
    ) {
        fwrite(STDERR, "wordpress-attach-wal-temp-commit-plan self-test failed\n");
        exit(1);
    }

    echo "wordpress-attach-wal-temp-commit-plan self-test passed\n";
    exit(0);
}

echo json_encode([
    'scenario' => 'wordpress-attach-wal-temp-commit-plan',
    'status' => $plan['status'],
    'search_order' => $plan['search_order'],
    'wal_schemas' => $plan['wal_schemas'],
    'rollback_schemas' => $plan['rollback_schemas'],
    'main_next_frames' => $plan['next']['schemas']['main']['wal_frame_count'],
    'archive_next_frames' => $plan['next']['schemas']['archive']['wal_frame_count'],
    'temp_change_counter' => $plan['next']['schemas']['temp']['change_counter'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT) . "\n";
