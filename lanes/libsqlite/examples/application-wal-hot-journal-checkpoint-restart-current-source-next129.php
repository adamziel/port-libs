<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalCheckpointRestartCurrentSourceNextPlan;

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-options-next129.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('wp next129 clean sqlite header'),
    2 => $page('wp next129 clean wp_options root'),
    3 => $page('wp next129 clean active_plugins option'),
    4 => $page('wp next129 clean autoload index'),
];
$dirtyDatabase = $page('wp next129 dirty sqlite header')
    . $page('wp next129 dirty wp_options root')
    . $page('wp next129 dirty active_plugins option')
    . $page('wp next129 dirty autoload index');

$nonce = 0x2026129;
$journalBytes = str_pad(
    SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($cleanPages), $nonce, count($cleanPages), $sectorSize, $pageSize),
    $sectorSize,
    "\0"
);
foreach ($cleanPages as $pageNumber => $pageImage) {
    $journalBytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
}

$salt1 = 0x12912901;
$salt2 = 0x12912902;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 129, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [1, 0, 'wp next129 wal schema retained draft'],
    [2, 4, 'wp next129 wal options retained commit'],
    [3, 0, 'wp next129 wal active_plugins reader draft'],
    [4, 4, 'wp next129 wal autoload reader commit'],
] as [$pageNumber, $commit, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$plan = SQLiteWalHotJournalCheckpointRestartCurrentSourceNextPlan::plan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $wal,
    $walBytes,
    [1, 2, 3, 4],
    4
);

$summary = [
    'scenario' => 'application-wal-hot-journal-checkpoint-restart-current-source-next129',
    'applicationUse' => 'After a copied wp_options import leaves both a hot rollback journal and WAL frames, native PHP tooling recovers the hot journal, preserves the current WAL while a reader is pinned, then writes a released restart checkpoint generation for next readers.',
    'status' => $plan['status'],
    'pinnedCheckpointBusy' => $plan['pinned_checkpoint_busy'],
    'releasedCheckpointBusy' => $plan['released_checkpoint_busy'],
    'pinnedWalAction' => $plan['pinned_wal_action'],
    'releasedWalAction' => $plan['released_wal_action'],
    'releasedWalBytesLength' => $plan['released_wal_bytes_length'],
    'sourceTransitions' => $plan['source_transitions'],
    'operationReasons' => $plan['operation_reasons'],
    'dependencyClosure' => 'no new support component needed; this reuses native rollback-journal recovery and WAL restart checkpoint primitives',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    assert($summary['status'] === 'wal-hot-journal-checkpoint-restart-current-source-next129');
    assert($summary['pinnedCheckpointBusy'] === true);
    assert($summary['releasedCheckpointBusy'] === false);
    assert($summary['pinnedWalAction'] === 'preserve_wal');
    assert($summary['releasedWalAction'] === 'restart_wal');
    assert($summary['releasedWalBytesLength'] === 32);
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

return $summary;
