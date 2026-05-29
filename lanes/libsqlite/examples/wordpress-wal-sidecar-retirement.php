<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$databasePath = '/srv/www/wp-content/database/wp-next221.sqlite';
$token = ['id' => 'wp-import-hot-journal-source-next221', 'epoch' => 221];
$sha = static fn (string $value): string => hash('sha256', $value);

$admission = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next217',
    'database_path' => $databasePath,
    'wal_path' => $databasePath . '-wal',
    'journal_path' => $databasePath . '-journal',
    'shm_path' => $databasePath . '-shm',
    'current_source_token' => $token,
    'checkpoint_frame' => 44,
    'checkpoint_cookie' => 22144,
    'checkpoint_admitted' => true,
    'next_source_epoch' => 222,
    'blocked_reader_names' => [],
    'operation_names' => ['admit_checkpoint_next_source_after_durable_receipts_next217'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next217'],
];

$receipt = static fn (string $name, string $kind, string $action, string $path): array => [
    'name' => $name,
    'kind' => $kind,
    'path' => $path,
    'action' => $action,
    'source_id' => $token['id'],
    'next_epoch' => 222,
    'checkpoint_frame' => 44,
    'checkpoint_cookie' => 22144,
    'receipt_sha256' => $sha($name . $path),
    'synced' => true,
    'directory_synced' => true,
    'savepoint_closed' => true,
    'exclusive_lock_receipt' => true,
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::sidecarRetirementPlan($admission, [
    $receipt('delete-hot-journal-after-plugin-import', 'hot-journal', 'delete', $databasePath . '-journal'),
    $receipt('restart-wal-after-plugin-import', 'wal', 'restart-header', $databasePath . '-wal'),
    $receipt('reset-shm-readmarks-after-plugin-import', 'shm', 'reset-read-marks', $databasePath . '-shm'),
]);

$summary = [
    'scenario' => 'wordpress-wal-sidecar-retirement',
    'wordpressUse' => 'A copied WordPress plugin import publishes the next current source only after the recovered hot journal is deleted, the restarted WAL generation is durable, SHM read marks are reset, and the sidecar directory entry is synced.',
    'status' => $plan['status'],
    'nextSourceEpoch' => $plan['next_source_token']['epoch'],
    'admittedSidecars' => $plan['admitted_sidecar_names'],
    'blockedSidecars' => $plan['blocked_sidecar_names'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next221'
        || $summary['nextSourceEpoch'] !== 222
        || $summary['admittedSidecars'] !== ['delete-hot-journal-after-plugin-import', 'restart-wal-after-plugin-import', 'reset-shm-readmarks-after-plugin-import']
        || $summary['blockedSidecars'] !== []
    ) {
        fwrite(STDERR, "wordpress-wal-sidecar-retirement self-test failed\n");
        exit(1);
    }

    echo "wordpress-wal-sidecar-retirement self-test passed\n";
}

return $summary;
