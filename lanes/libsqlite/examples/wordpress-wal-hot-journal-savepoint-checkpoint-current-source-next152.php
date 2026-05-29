<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next152.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('wp next152 clean schema before hot journal'),
    2 => $page('wp next152 clean wp_options before hot journal'),
    3 => $page('wp next152 clean active_plugins before hot journal'),
    4 => $page('wp next152 clean plugin setting before hot journal'),
];
$databaseBytes = $page('wp next152 dirty schema interrupted copy')
    . $page('wp next152 dirty wp_options interrupted copy')
    . $page('wp next152 dirty active_plugins interrupted copy')
    . $page('wp next152 dirty plugin setting interrupted copy');

$nonce = 0x2026152;
$journalBytes = str_pad(
    SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($cleanPages), $nonce, count($cleanPages), $sectorSize, $pageSize),
    $sectorSize,
    "\0"
);
foreach ($cleanPages as $pageNumber => $image) {
    $journalBytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
}

$salt1 = 0x15215201;
$salt2 = 0x15215202;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 152, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [1, 0, 'wp next152 retained schema draft'],
    [2, 4, 'wp next152 retained wp_options commit'],
    [3, 0, 'wp next152 rolled active_plugins draft'],
    [4, 4, 'wp next152 rolled plugin setting commit'],
] as [$pageNumber, $commit, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp-import-next152');
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin-batch-next152');
$savepoints->recordWalFrameWrite(3, 3);
$savepoints->recordWalFrameWrite(4, 4, true);

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next152Plan(
    $databasePath,
    $databaseBytes,
    $journalBytes,
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    $savepoints,
    'plugin-batch-next152',
    [1, 2, 3, 4],
    'restart'
);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next152',
    'wordpressUse' => 'A copied WordPress options import first recovers an interrupted rollback journal, then rolls back a plugin-batch WAL savepoint and checkpoints only the retained current WAL prefix.',
    'status' => $plan['status'],
    'journalAction' => $plan['journal_action'],
    'walAction' => $plan['wal_action'],
    'retainedFrames' => $plan['retained_frame_count'],
    'discardedFrames' => $plan['discarded_frame_count'],
    'hotRestoredPages' => $plan['hot_restored_page_numbers'],
    'savepointRestoredPages' => $plan['savepoint_restored_page_numbers'],
    'currentSources' => $plan['current_sources'],
    'nextSources' => $plan['next_sources'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next152'
    || $summary['journalAction'] !== 'delete_journal_after_recovery'
    || $summary['walAction'] !== 'restart_wal'
    || $summary['retainedFrames'] !== 2
    || $summary['discardedFrames'] !== 2
) {
    fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next152 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next152 self-test passed\n";
