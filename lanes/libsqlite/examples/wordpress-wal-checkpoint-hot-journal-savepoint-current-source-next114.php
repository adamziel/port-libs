<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointHotJournalSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next114.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('wp next114 clean sqlite header'),
    2 => $page('wp next114 clean wp_options root'),
    3 => $page('wp next114 clean plugin settings'),
    4 => $page('wp next114 clean autoload index'),
    5 => $page('wp next114 clean transient page'),
];
$dirtyDatabase = $page('wp next114 dirty sqlite header')
    . $page('wp next114 dirty wp_options root')
    . $page('wp next114 dirty plugin settings')
    . $page('wp next114 dirty autoload index')
    . $page('wp next114 dirty transient page');

$nonce = 0x2026114;
$journalHeader = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($cleanPages), $nonce, count($cleanPages), $sectorSize, $pageSize);
$journalBytes = str_pad($journalHeader, $sectorSize, "\0");
foreach ($cleanPages as $pageNumber => $image) {
    $journalBytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
}
$journal = SQLiteRollbackJournal::parse($journalBytes, true);

$salt1 = 0x20260528;
$salt2 = 0x20261140;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 114, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [1, 0, 'wp next114 wal schema draft'],
    [2, 5, 'wp next114 wal retained options commit'],
    [3, 0, 'wp next114 wal plugin draft'],
    [4, 5, 'wp next114 wal autoload commit'],
    [5, 0, 'wp next114 wal uncommitted transient tail'],
] as [$pageNumber, $commit, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import-next114');
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-settings-next114');
$stack->recordWalFrameWrite(3, 3);
$stack->recordWalFrameWrite(4, 4, true);

$plan = SQLiteWalCheckpointHotJournalSavepointCurrentSourceNextPlan::plan(
    $journal,
    $dirtyDatabase,
    $journalBytes,
    $stack,
    'plugin-settings-next114',
    $wal,
    $walBytes,
    $databasePath,
    [1, 2, 3, 4, 5],
    'restart',
    null,
    $pageSize
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'hot-journal-savepoint-checkpoint-ready-next114');
    assert($plan['hot_recovered'] === true);
    assert($plan['retained_frame_count'] === 2);
    assert($plan['savepoint_discarded_frame_count'] === 2);
    assert($plan['next_uses_checkpoint_database'] === true);
    assert($plan['source_transitions'][2] === 'wal>wal>database>database');
    echo "wordpress-wal-checkpoint-hot-journal-savepoint-current-source-next114 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'reason' => $plan['reason'],
    'hotRecovered' => $plan['hot_recovered'],
    'committedFrameCount' => $plan['committed_frame_count'],
    'retainedFrameCount' => $plan['retained_frame_count'],
    'discardedSavepointFrames' => $plan['savepoint_discarded_frame_count'],
    'walAction' => $plan['wal_action'],
    'nextUsesCheckpointDatabase' => $plan['next_uses_checkpoint_database'],
    'sourceTransitions' => $plan['source_transitions'],
    'operations' => $plan['operation_reasons'],
], JSON_PRETTY_PRINT) . "\n";
