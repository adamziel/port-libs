<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$pageSize = 1024;
$checkpointFrame = 64;
$walDigest = $digest('next237 durable wal sidecar after hot journal savepoint checkpoint');
$durablePlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next234',
    'database_path' => '/srv/www/wp-content/database/wp-next237.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next237.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next237.sqlite-journal',
    'current_source_token' => ['id' => 'wp-hot-journal-current-source-next237', 'epoch' => 237],
    'source_epoch' => 237,
    'next_source_epoch' => 238,
    'checkpoint_frame' => $checkpointFrame,
    'checkpoint_cookie' => 90237,
    'schema_cookie' => 1237,
    'expected_wal_digest' => $walDigest,
    'can_serve_durable_current_source' => true,
    'operation_names' => ['serve_durable_reopened_checkpoint_source_next234'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next234'],
];
$readerPins = [
    'wp-options-import' => 64,
    'wp-plugin-cache' => 63,
];
$sidecar = [
    'name' => 'wp-options-wal-sidecar-next237',
    'source_token_id' => 'wp-hot-journal-current-source-next237',
    'source_epoch' => 237,
    'next_source_epoch' => 238,
    'checkpoint_frame' => $checkpointFrame,
    'checkpoint_cookie' => 90237,
    'schema_cookie' => 1237,
    'wal_digest' => $walDigest,
    'salt_1' => 23701,
    'salt_2' => 23702,
    'page_size' => $pageSize,
    'frame_count' => $checkpointFrame,
    'byte_length' => 32 + ($checkpointFrame * (24 + $pageSize)),
    'last_commit_frame' => $checkpointFrame,
    'checksum_digest' => hash('sha256', json_encode([23701, 23702, $checkpointFrame, $readerPins, $walDigest], JSON_THROW_ON_ERROR)),
    'hot_journal_visible' => false,
    'savepoint_depth' => 0,
    'writer_generation' => 238,
    'directory_synced' => true,
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next237VerifySidecarBoundary(
    $durablePlan,
    [$sidecar],
    $readerPins,
    $pageSize
);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next237',
    'wordpressUse' => 'A copied WordPress plugin import reuses a durable WAL sidecar after hot-journal/savepoint checkpoint only when the byte boundary, salts, checksum digest, and reader pins match the published current source.',
    'status' => $plan['status'],
    'walAction' => $plan['wal_action'],
    'expectedWalSidecarLength' => $plan['expected_wal_sidecar_length'],
    'admittedSidecars' => $plan['admitted_sidecar_names'],
    'blockedReasons' => $plan['blocked_reasons'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv ?? [], true)) {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next237'
        || $summary['walAction'] !== 'reuse_durable_wal_sidecar_boundary'
        || $summary['blockedReasons'] !== []
    ) {
        fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next237 self-test failed\n");
        exit(1);
    }
    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next237 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
