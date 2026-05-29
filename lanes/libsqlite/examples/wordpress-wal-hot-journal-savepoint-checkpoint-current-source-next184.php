<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next184.sqlite';
$walPath = $databasePath . '-wal';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$makeWal = static function (array $frames, int $checkpoint, int $salt1, int $salt2) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$currentWalBytes = $makeWal([
    [1, 0, 'wp next184 schema before retry'],
    [2, 4, 'wp next184 option_value before retry'],
], 184, 0x18400201, 0x18400202);
$nextWalBytes = $makeWal([
    [2, 0, 'wp next184 option_value retry draft'],
    [3, 4, 'wp next184 active_plugins retry commit'],
], 185, 0x18500201, 0x18500202);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);
$reopen = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next181',
    'database_path' => $databasePath,
    'wal_path' => $walPath,
    'can_reopen_publish' => true,
    'wal_checksums_validated' => true,
    'wal_checkpoint_sequence' => $nextWal->header->checkpointSequence,
    'wal_frame_count' => count($nextWal->frames),
    'reopen_digest' => hash('sha256', 'wp-next184-reopen'),
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next181'],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next184Plan(
    $reopen,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1, 2, 3]
);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next184',
    'wordpressUse' => 'A copied WordPress plugin import reopens after hot-journal recovery and savepoint checkpoint publication, then fences old reader marks before a retry WAL source is reused.',
    'status' => $plan['status'],
    'canReuseReaderMarks' => $plan['can_reuse_reader_marks'],
    'currentCheckpoint' => $plan['current_checkpoint_sequence'],
    'nextCheckpoint' => $plan['next_checkpoint_sequence'],
    'saltRotated' => $plan['salt_pair_rotated'],
    'readerNextSources' => $plan['reader_next_sources'],
    'blockedReasons' => $plan['blocked_reasons'],
    'transitionDigest' => $plan['source_transition_digest'],
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next184'
        || $summary['canReuseReaderMarks'] !== true
        || $summary['saltRotated'] !== true
        || $summary['readerNextSources'] !== ['checkpoint-database', 'next-wal', 'next-wal']
    ) {
        fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next184 self-test failed\n");
        exit(1);
    }

    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next184 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
