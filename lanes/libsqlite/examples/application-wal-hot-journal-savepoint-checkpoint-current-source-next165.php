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
    1 => $page('wp next165 clean schema'),
    2 => $page('wp next165 clean active_plugins option'),
    3 => $page('wp next165 clean plugin settings'),
];
$dirtyDatabase = $page('wp next165 dirty schema')
    . $page('wp next165 dirty active_plugins option')
    . $page('wp next165 dirty plugin settings');

$header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($clean), 0x16516501, count($clean), 512, $pageSize);
$journalBytes = str_pad($header, 512, "\0");
foreach ($clean as $pageNumber => $image) {
    $journalBytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, 0x16516501));
}

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 165, 0x16516501, 0x16516502);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([[1, 0, 'wp next165 retained schema draft'], [2, 3, 'wp next165 retained active_plugins commit'], [3, 3, 'wp next165 discarded settings']] as [$pageNumber, $commit, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commit, 0x16516501, 0x16516502);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import-next165');
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-import-next165');
$stack->recordWalFrameWrite(3, 3, true);

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next165Plan(
    '/srv/www/wp-content/database/wp.sqlite',
    $dirtyDatabase,
    $journalBytes,
    $stack,
    'plugin-import-next165',
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    [1, 2, 3]
);

$summary = [
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next165',
    'status' => $plan['status'],
    'publishAdmitted' => $plan['publish_admitted'],
    'pinnedReaderPages' => $plan['pinned_reader_page_numbers'],
    'stalePublishBlockedPages' => $plan['stale_publish_blocked_page_numbers'],
    'currentWalAction' => $plan['current_checkpoint_wal_action'],
    'releasedWalAction' => $plan['released_checkpoint_wal_action'],
    'operationReasons' => $plan['operation_reasons'],
    'applicationUse' => 'A copied wp_options import recovers a hot rollback journal, rolls back failed savepoint WAL frames, publishes the checkpoint from that current source, and only then resets the WAL generation for the next reader.',
    'dependencyClosure' => $plan['dependency_closure'],
];

if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next165'
    || $summary['publishAdmitted'] !== true
    || $summary['pinnedReaderPages'] !== [1, 2]
    || $summary['stalePublishBlockedPages'] !== [3]
    || $summary['currentWalAction'] !== 'preserve_wal'
    || $summary['releasedWalAction'] !== 'restart_wal'
) {
    fwrite(STDERR, "application-wal-hot-journal-savepoint-checkpoint-current-source-next165 self-test failed\n");
    exit(1);
}

echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next165 self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
