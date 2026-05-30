<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$clean = [
    1 => $page('wp next169 clean schema'),
    2 => $page('wp next169 clean active_plugins option'),
    3 => $page('wp next169 clean rewrite_rules option'),
];
$dirtyDatabase = $page('wp next169 dirty schema')
    . $page('wp next169 dirty active_plugins option')
    . $page('wp next169 dirty rewrite_rules option');

$header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($clean), 0x16916901, count($clean), 512, $pageSize);
$journalBytes = str_pad($header, 512, "\0");
foreach ($clean as $pageNumber => $image) {
    $journalBytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, 0x16916901));
}

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 169, 0x16916901, 0x16916902);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([[1, 0, 'wp next169 retained schema draft'], [2, 3, 'wp next169 retained active_plugins commit'], [3, 3, 'wp next169 discarded rewrite_rules']] as [$pageNumber, $commit, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commit, 0x16916901, 0x16916902);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import-next169');
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-import-next169');
$stack->recordWalFrameWrite(3, 3, true);

$completedBeforeCrash = [
    'publish_hot_journal_savepoint_current_checkpoint_database_next165',
    'trim_database_after_current_checkpoint_publish_next165',
    'preserve_retained_wal_for_pinned_reader_next165',
    'sync_current_checkpoint_before_reader_release_next165',
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::planAtomicPublishPreparation(
    '/srv/www/wp-content/database/wp.sqlite',
    $dirtyDatabase,
    $journalBytes,
    $stack,
    'plugin-import-next169',
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    [1, 2, 3],
    $completedBeforeCrash
);

$summary = [
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next169',
    'status' => $plan['status'],
    'resumeAdmitted' => $plan['resume_admitted'],
    'journalDeleteAdmitted' => $plan['journal_delete_admitted'],
    'readerReleaseAdmitted' => $plan['reader_release_admitted'],
    'walResetAdmitted' => $plan['wal_reset_admitted'],
    'nextOperation' => $plan['next_operation_reason'],
    'pendingOperations' => $plan['pending_operation_reasons'],
    'applicationUse' => 'A copied wp_options import crash-resumes after current-source checkpoint bytes and retained WAL bytes are synced, keeping the hot journal until reader release is safe and deferring WAL reset until released checkpoint bytes are published.',
    'dependencyClosure' => $plan['dependency_closure'],
];

if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next169'
    || $summary['resumeAdmitted'] !== true
    || $summary['journalDeleteAdmitted'] !== true
    || $summary['readerReleaseAdmitted'] !== false
    || $summary['walResetAdmitted'] !== false
    || $summary['nextOperation'] !== 'delete_hot_journal_after_current_source_checkpoint_next165'
) {
    fwrite(STDERR, "application-wal-hot-journal-savepoint-checkpoint-current-source-next169 self-test failed\n");
    exit(1);
}

echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next169 self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
