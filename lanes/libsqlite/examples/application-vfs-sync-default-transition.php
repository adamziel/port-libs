<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$checkpoint = SQLiteVfsIoDynamicPlan::syncPragmaTrafficProfile(
    'sync2-1.12-default-wal-checkpoint-normal.application',
    'wal',
    'normal',
    false,
    false,
    false,
    true,
    2,
    true
);
$afterCheckpoint = SQLiteVfsIoDynamicPlan::syncPragmaTrafficProfile(
    'sync2-1.13.1-default-wal-after-checkpoint.application',
    'wal',
    'normal',
    false,
    false,
    false,
    false,
    2,
    true,
    true
);
$reopenedDelete = SQLiteVfsIoDynamicPlan::syncPragmaTrafficProfile(
    'sync2-1.20.1-reopen-delete-default-full.application',
    'delete',
    'full',
    false,
    false,
    false,
    false,
    2,
    true
);

if (in_array('--self-test', $argv, true)) {
    $require = static function (bool $condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    $require($checkpoint['status'] === 'ok', 'checkpoint profile must be ok');
    $require($checkpoint['sync_count'] === 2, 'checkpoint must sync WAL and database');
    $require($checkpoint['sync_targets'] === ['wal', 'database'], 'checkpoint targets must match sync2.test 1.12');
    $require($checkpoint['wal_checkpoint_result'] === [0, 2, 2], 'checkpoint result must expose frame counts');
    $require($afterCheckpoint['sync_count'] === 1, 'first transaction after checkpoint must sync WAL frames');
    $require($afterCheckpoint['sync_targets'] === ['wal_frames'], 'after-checkpoint target must be WAL frames');
    $require($afterCheckpoint['wal_restart_after_checkpoint'] === true, 'after-checkpoint flag must be preserved');
    $require($reopenedDelete['pragma_synchronous_value'] === 2, 'reopened delete mode must restore FULL synchronous');
    $require($reopenedDelete['sync_count'] === 4, 'reopened delete mode must use four syncs');
    $require(in_array('vfs-sync-count-pragmas', $afterCheckpoint['dependencies'], true), 'sync dependency marker missing');
    echo "application-vfs-sync-default-transition self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-vfs-sync-default-transition',
    'applicationUse' => 'Track SQLite default synchronous behavior as an application database moves through WAL checkpoints, WAL frame appends after checkpoint, delete-journal mode, and reopened default FULL sync handling without requiring ext/sqlite.',
    'source' => '/home/claude/port-libs/.upstream-cache/libsqlite/test/sync2.test',
    'profiles' => [
        'walCheckpoint' => [
            'status' => $checkpoint['status'],
            'upstream' => $checkpoint['upstream'],
            'syncCount' => $checkpoint['sync_count'],
            'syncTargets' => $checkpoint['sync_targets'],
            'walCheckpointResult' => $checkpoint['wal_checkpoint_result'],
            'reason' => $checkpoint['reason'],
        ],
        'walAfterCheckpoint' => [
            'status' => $afterCheckpoint['status'],
            'upstream' => $afterCheckpoint['upstream'],
            'syncCount' => $afterCheckpoint['sync_count'],
            'syncTargets' => $afterCheckpoint['sync_targets'],
            'restartAfterCheckpoint' => $afterCheckpoint['wal_restart_after_checkpoint'],
            'reason' => $afterCheckpoint['reason'],
        ],
        'reopenedDeleteDefault' => [
            'status' => $reopenedDelete['status'],
            'upstream' => $reopenedDelete['upstream'],
            'pragmaSynchronousValue' => $reopenedDelete['pragma_synchronous_value'],
            'syncCount' => $reopenedDelete['sync_count'],
            'syncTargets' => $reopenedDelete['sync_targets'],
            'reason' => $reopenedDelete['reason'],
        ],
    ],
    'dependencies' => $afterCheckpoint['dependencies'],
    'dependencyClosure' => $afterCheckpoint['dependency_closure'],
    'nonOverlap' => $afterCheckpoint['non_overlap'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
