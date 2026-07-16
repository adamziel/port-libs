<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalCheckpointCrashRecoveryPlan.php';
require_once __DIR__ . '/../src/SQLiteWalFileWritePlan.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointCrashRecoveryPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$databasePath = 'wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('db schema before wp checkpoint recovery') . $page('db option before wp checkpoint recovery') . $page('db autoload before wp checkpoint recovery');
$salt1 = 0x71717171;
$salt2 = 0x27272727;

$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 71, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $label) use ($pageSize, $salt1, $salt2): string {
    $image = str_pad($label, $pageSize, '.');
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$walBytes = $appendFrame($walBytes, $seed, 1, 0, 'wal schema page for recovered plugin import');
$walBytes = $appendFrame($walBytes, $seed, 2, 3, 'wal active_plugins committed before crash');
$walBytes = $appendFrame($walBytes, $seed, 3, 0, 'wal transient draft valid but uncommitted');
$walBytes = $appendFrame($walBytes, $seed, 2, 3, 'wal corrupt committed replacement must not replay');
$walBytes = substr_replace($walBytes, 'X', 32 + ((24 + $pageSize) * 3) + 120, 1);

$plan = SQLiteWalCheckpointCrashRecoveryPlan::recoverFromWalBytes(
    $walBytes,
    $databaseBytes,
    $databasePath,
    [1, 2, 3],
    'restart',
    'after_database_sync',
    $pageSize
);

$summary = [
    'scenario' => 'application-wal-checkpoint-recovery-current-next71',
    'applicationUse' => 'Recover copied wp_options checkpoint state when a crash leaves durable database pages beside an old WAL containing a valid uncommitted tail and a corrupt frame; current readers keep their prefix snapshot while the next opener checkpoints only the committed prefix.',
    'status' => $plan['status'],
    'recoveryReason' => $plan['recovery']['reason'],
    'validFrames' => $plan['recovery']['valid_frame_count'],
    'committedFrames' => $plan['recovery']['committed_frame_count'],
    'discardedValidTailFrames' => $plan['discarded_valid_tail_frame_count'],
    'discardedCorruptTailFrames' => $plan['discarded_corrupt_tail_frame_count'],
    'currentReaderSources' => $plan['current_reader_sources'],
    'nextReaderSources' => $plan['next_reader_sources'],
    'currentReaderFrames' => $plan['current_reader_frame_indexes'],
    'nextReaderFrames' => $plan['next_reader_frame_indexes'],
    'nextUsesCheckpointDatabase' => $plan['next_uses_checkpoint_database'],
    'nextReplaysPersistedWal' => $plan['next_replays_persisted_wal'],
    'persistedWalAction' => $plan['persisted_wal_action'],
    'appliedOperations' => array_column($plan['operations_applied'], 'reason'),
    'pendingOperations' => array_column($plan['operations_pending'], 'reason'),
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    $expected = [
        'status' => 'recovered',
        'recoveryReason' => 'uncommitted_valid_tail_before_corrupt_frame',
        'validFrames' => 3,
        'committedFrames' => 2,
        'discardedValidTailFrames' => 1,
        'discardedCorruptTailFrames' => 1,
    'currentReaderSources' => ['wal', 'wal', 'database'],
        'nextReaderSources' => ['wal', 'wal', 'database'],
    'currentReaderFrames' => [1, 2, null],
        'nextReaderFrames' => [1, 2, null],
        'nextUsesCheckpointDatabase' => true,
        'nextReplaysPersistedWal' => true,
        'persistedWalAction' => 'preserve_pre_recovery_wal',
        'appliedOperations' => ['checkpoint_recovered_committed_wal_prefix', 'sync_checkpoint_database_image'],
        'pendingOperations' => ['restart_recovered_wal_header', 'sync_recovered_wal_sidecar', 'persist_recovered_checkpoint_directory_entries'],
    ];

    foreach ($expected as $key => $value) {
        if ($summary[$key] !== $value) {
            fwrite(STDERR, "application-wal-checkpoint-recovery-current-next71 self-test failed for {$key}\n");
            exit(1);
        }
    }

    fwrite(STDOUT, "application-wal-checkpoint-recovery-current-next71 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
