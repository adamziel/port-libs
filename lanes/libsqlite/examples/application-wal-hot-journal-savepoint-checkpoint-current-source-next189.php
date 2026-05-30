<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteRollbackJournalHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalPage.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournal.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next189.sqlite';
$journalPath = $databasePath . '-journal';
$walPath = $databasePath . '-wal';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databasePages = [
    1 => $page('wp next189 schema checkpoint page'),
    2 => $page('wp next189 options checkpoint page'),
    3 => $page('wp next189 active_plugins checkpoint page'),
];
$dirtyDatabase = $page('wp next189 dirty schema')
    . $page('wp next189 dirty options')
    . $page('wp next189 dirty active_plugins');
$journalHeader = SQLiteRollbackJournalHeader::MAGIC . pack('N*', 2, 0x18918901, 3, $sectorSize, $pageSize);
$journalBytes = str_pad($journalHeader, $sectorSize, "\0");
foreach ([1 => $databasePages[1], 2 => $databasePages[2]] as $pageNumber => $image) {
    $journalBytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, 0x18918901));
}

$salt1 = 0x18918901;
$salt2 = 0x18918902;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 189, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([[1, 0, 'wp next189 schema wal'], [2, 3, 'wp next189 options wal'], [3, 0, 'wp next189 plugin draft']] as [$pageNumber, $commitPageCount, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import-next189');
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-batch-next189');
$stack->recordWalFrameWrite(3, 3);

$completed = [
    'publish_hot_journal_savepoint_current_checkpoint_database_next165',
    'trim_database_after_current_checkpoint_publish_next165',
    'preserve_retained_wal_for_pinned_reader_next165',
    'sync_current_checkpoint_before_reader_release_next165',
];

$probe = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next174Plan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $stack,
    'plugin-batch-next189',
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    [1, 2, 3],
    $completed,
    []
);
$payloads = $probe['base_plan']['base_plan']['payloads'];
$files = [
    $databasePath => (string) $payloads[$databasePath . '#next165-current-checkpoint'],
    $journalPath => $probe['file_rows'][1]['required'] ? $journalBytes : null,
    $walPath => (string) $payloads[$walPath . '#next165-current-reader'],
];
$apply = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next180Apply(
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next177Plan(SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next174Plan(
        $databasePath,
        $dirtyDatabase,
        $journalBytes,
        $stack,
        'plugin-batch-next189',
        SQLiteWal::parse($walBytes, $pageSize, true),
        $walBytes,
        [1, 2, 3],
        $completed,
        $files
    )),
    $files,
    [
        $databasePath => (string) $payloads[$databasePath . '#next165-current-checkpoint'],
        $journalPath => $journalBytes,
        $walPath => (string) $payloads[$walPath . '#next165-current-reader'],
    ]
);

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next189ReaderSnapshotPlan(
    $apply,
    $apply['files'],
    189,
    $pageSize,
    2,
    [1, 2, 3],
    [],
    $databasePages,
    189
);

$summary = [
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next189',
    'applicationUse' => 'A copied Application plugin import reopens after hot-journal/checkpoint recovery and admits a retained WAL reader only when its snapshot stops at the last committed retained frame and falls back to the checkpoint database for later pages.',
    'status' => $plan['status'],
    'readerSources' => $plan['reader_sources'],
    'readerFrames' => $plan['reader_frame_indexes'],
    'lastCommitFrame' => $plan['last_commit_frame'],
    'blockedReasons' => $plan['blocked_reasons'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next189'
    || $summary['readerSources'] !== ['retained-wal', 'retained-wal', 'checkpoint-database']
    || $summary['lastCommitFrame'] !== 2
) {
    fwrite(STDERR, "application-wal-hot-journal-savepoint-checkpoint-current-source-next189 self-test failed\n");
    fwrite(STDERR, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    exit(1);
}

echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next189 self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
