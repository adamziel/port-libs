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
$databasePath = '/srv/www/wp-content/database/wp-next183.sqlite';
$journalPath = $databasePath . '-journal';
$walPath = $databasePath . '-wal';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$dirtyDatabase = $page('wp next183 dirty schema')
    . $page('wp next183 dirty wp_options root')
    . $page('wp next183 dirty active_plugins')
    . $page('wp next183 dirty rewrite_rules');
$cleanPages = [
    1 => $page('wp next183 clean schema'),
    2 => $page('wp next183 clean wp_options root'),
    4 => $page('wp next183 clean rewrite_rules'),
];

$header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($cleanPages), 0x18318301, 4, $sectorSize, $pageSize);
$journalBytes = str_pad($header, $sectorSize, "\0");
foreach ($cleanPages as $pageNumber => $image) {
    $journalBytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, 0x18318301));
}

$salt1 = 0x18318301;
$salt2 = 0x18318302;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 183, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([[1, 0, 'wp next183 schema wal'], [2, 4, 'wp next183 options wal'], [3, 0, 'wp next183 plugin draft'], [4, 4, 'wp next183 rewrite wal']] as [$pageNumber, $commitPageCount, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import-next183');
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-batch-next183');
$stack->recordWalFrameWrite(3, 3);
$stack->recordWalFrameWrite(4, 4, true);

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
    'plugin-batch-next183',
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    [1, 2, 3, 4],
    $completed,
    []
);
$payloads = $probe['base_plan']['base_plan']['payloads'];
$databasePayload = (string) $payloads[$databasePath . '#next165-current-checkpoint'];
$walPayload = (string) $payloads[$walPath . '#next165-current-reader'];
$resume = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next174Plan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $stack,
    'plugin-batch-next183',
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    [1, 2, 3, 4],
    $completed,
    [
        $databasePath => $databasePayload,
        $journalPath => $journalBytes,
        $walPath => $walPayload,
    ]
);
$apply = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next180Apply(
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next177Plan($resume),
    [
        $databasePath => $databasePayload,
        $journalPath => $journalBytes,
        $walPath => $walPayload,
    ],
    [
        $databasePath => $databasePayload,
        $journalPath => $journalBytes,
        $walPath => $walPayload,
    ]
);
$verify = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next183Verify($apply, $apply['files'], [], 183);

$summary = [
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next183',
    'applicationUse' => 'A copied Application plugin import restarts after hot-journal/checkpoint resume, verifies the post-apply file map and admits a fresh WAL reader current-source token only after durable directory sync evidence.',
    'status' => $verify['status'],
    'verifiedRoles' => $verify['verified_roles'],
    'hotJournalDeleted' => $verify['hot_journal_deleted'],
    'directorySyncVerified' => $verify['directory_sync_verified'],
    'readerTokenPrefix' => substr($verify['reader_source_token'], 0, 53),
    'dependencyClosure' => $verify['dependency_closure'],
];

if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next183'
    || $summary['verifiedRoles'] !== ['hot-journal']
    || $summary['hotJournalDeleted'] !== true
    || $summary['directorySyncVerified'] !== true
) {
    fwrite(STDERR, "application-wal-hot-journal-savepoint-checkpoint-current-source-next183 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
