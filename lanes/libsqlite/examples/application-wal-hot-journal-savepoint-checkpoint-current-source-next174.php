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
$databasePath = '/srv/www/wp-content/database/wp-next174.sqlite';
$journalPath = $databasePath . '-journal';
$walPath = $databasePath . '-wal';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$cleanPages = [
    1 => $page('wp next174 clean schema before failed import'),
    2 => $page('wp next174 clean wp_options root before failed import'),
    3 => $page('wp next174 clean active_plugins before failed import'),
    4 => $page('wp next174 clean rewrite_rules before failed import'),
];
$dirtyDatabase = $page('wp next174 dirty schema from failed import')
    . $page('wp next174 dirty wp_options root from failed import')
    . $page('wp next174 dirty active_plugins from failed import')
    . $page('wp next174 dirty rewrite_rules from failed import');

$nonce = 0x17417402;
$journalBytes = str_pad(SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($cleanPages), $nonce, count($cleanPages), $sectorSize, $pageSize), $sectorSize, "\0");
foreach ($cleanPages as $pageNumber => $image) {
    $journalBytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
}

$salt1 = 0x17427401;
$salt2 = 0x17427402;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 174, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [1, 0, 'wp next174 retained schema draft before publish'],
    [2, 4, 'wp next174 retained wp_options commit before publish'],
    [3, 0, 'wp next174 discarded active_plugins draft'],
    [4, 4, 'wp next174 discarded rewrite_rules commit'],
] as [$pageNumber, $commitPageCount, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$stack = static function (): SQLiteSavepointStack {
    $savepoints = new SQLiteSavepointStack();
    $savepoints->beginTransaction('wp-import-next174');
    $savepoints->recordWalFrameWrite(1, 1);
    $savepoints->recordWalFrameWrite(2, 2, true);
    $savepoints->savepoint('plugin-batch-next174');
    $savepoints->recordWalFrameWrite(3, 3);
    $savepoints->recordWalFrameWrite(4, 4, true);

    return $savepoints;
};

$completed = [
    'publish_hot_journal_savepoint_current_checkpoint_database_next165',
    'trim_database_after_current_checkpoint_publish_next165',
    'preserve_retained_wal_for_pinned_reader_next165',
    'sync_current_checkpoint_before_reader_release_next165',
];
$base = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::planAtomicPublishPreparation(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $stack(),
    'plugin-batch-next174',
    $wal,
    $walBytes,
    [1, 2, 3, 4],
    $completed
);
$payloads = $base['base_plan']['payloads'];
$files = [
    $databasePath => (string) $payloads[$databasePath . '#next165-current-checkpoint'],
    $journalPath => $journalBytes,
    $walPath => (string) $payloads[$walPath . '#next165-current-reader'],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::planAtomicPublishApply(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $stack(),
    'plugin-batch-next174',
    $wal,
    $walBytes,
    [1, 2, 3, 4],
    $completed,
    $files
);

$summary = [
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next174',
    'applicationUse' => 'A copied Application plugin import resumes after a crash between hot-journal recovery, WAL savepoint rollback, and checkpoint publication; file bytes are verified before deleting the hot journal or releasing readers.',
    'status' => $plan['status'],
    'hotJournalDeleteAdmitted' => $plan['hot_journal_delete_admitted'],
    'readerReleaseAdmitted' => $plan['reader_release_admitted'],
    'needsReplay' => $plan['needs_replay'],
    'fileRoles' => $plan['file_roles'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next174'
    || $summary['hotJournalDeleteAdmitted'] !== true
    || $summary['readerReleaseAdmitted'] !== false
    || $summary['needsReplay'] !== false
) {
    fwrite(STDERR, "application-wal-hot-journal-savepoint-checkpoint-current-source-next174 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
