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
    1 => $page('wp next162 clean schema'),
    2 => $page('wp next162 clean active_plugins option'),
    3 => $page('wp next162 clean plugin settings'),
];
$dirtyDatabase = $page('wp next162 dirty schema')
    . $page('wp next162 dirty active_plugins option')
    . $page('wp next162 dirty plugin settings');

$header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($clean), 0x16216201, count($clean), 512, $pageSize);
$journalBytes = str_pad($header, 512, "\0");
foreach ($clean as $pageNumber => $image) {
    $journalBytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, 0x16216201));
}

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 162, 0x16216201, 0x16216202);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([[1, 0, 'wp next162 retained schema draft'], [2, 3, 'wp next162 retained active_plugins commit'], [3, 3, 'wp next162 discarded plugin settings']] as [$pageNumber, $commit, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commit, 0x16216201, 0x16216202);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import-next162');
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-import-next162');
$stack->recordWalFrameWrite(3, 3, true);

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::hotJournalSavepointCheckpointPlan(
    '/srv/www/wp-content/database/wp.sqlite',
    $dirtyDatabase,
    $journalBytes,
    $stack,
    'plugin-import-next162',
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    [1, 2, 3]
);

echo json_encode([
    'status' => $plan['status'],
    'stale_checkpoint_rejected' => $plan['stale_checkpoint_rejected'],
    'stale_checkpoint_dirty_page_numbers' => $plan['stale_checkpoint_dirty_page_numbers'],
    'current_checkpoint_wal_action' => $plan['current_checkpoint_wal_action'],
    'released_checkpoint_wal_action' => $plan['released_checkpoint_wal_action'],
], JSON_PRETTY_PRINT) . "\n";
