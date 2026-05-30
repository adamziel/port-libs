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
$databasePath = '/srv/www/wp-content/database/wp-next177.sqlite';
$journalPath = $databasePath . '-journal';
$walPath = $databasePath . '-wal';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$dirtyDatabase = $page('wp next177 dirty schema')
    . $page('wp next177 dirty wp_options root')
    . $page('wp next177 dirty active_plugins')
    . $page('wp next177 dirty rewrite_rules');
$cleanPages = [
    1 => $page('wp next177 clean schema'),
    2 => $page('wp next177 clean wp_options root'),
    4 => $page('wp next177 clean rewrite_rules'),
];

$header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($cleanPages), 0x17717701, 4, $sectorSize, $pageSize);
$journalBytes = str_pad($header, $sectorSize, "\0");
foreach ($cleanPages as $pageNumber => $image) {
    $journalBytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, 0x17717701));
}

$salt1 = 0x17717701;
$salt2 = 0x17717702;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 177, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([[1, 0, 'wp next177 schema wal'], [2, 4, 'wp next177 options wal'], [3, 0, 'wp next177 plugin draft'], [4, 4, 'wp next177 rewrite wal']] as [$pageNumber, $commitPageCount, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import-next177');
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-batch-next177');
$stack->recordWalFrameWrite(3, 3);
$stack->recordWalFrameWrite(4, 4, true);

$checkpointComplete = [
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
    'plugin-batch-next177',
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    [1, 2, 3, 4],
    $checkpointComplete,
    []
);
$payloads = $probe['base_plan']['base_plan']['payloads'];
$resume = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next174Plan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $stack,
    'plugin-batch-next177',
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    [1, 2, 3, 4],
    $checkpointComplete,
    [
        $journalPath => $journalBytes,
        $walPath => (string) $payloads[$walPath . '#next165-current-reader'],
    ]
);
$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next177Plan($resume);

$summary = [
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next177',
    'applicationUse' => 'A copied Application plugin import resumes after a crash between hot-journal recovery and checkpoint publication, rewriting the missing current-source database image before deleting the hot journal.',
    'status' => $plan['status'],
    'operationNames' => $plan['operation_names'],
    'payloadPaths' => $plan['payload_paths'],
    'blockedReasons' => $plan['blocked_reasons'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next177'
    || $summary['operationNames'] !== ['write', 'truncate', 'sync', 'sync_directory']
    || $summary['payloadPaths'] !== [$databasePath]
) {
    fwrite(STDERR, "application-wal-hot-journal-savepoint-checkpoint-current-source-next177 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
