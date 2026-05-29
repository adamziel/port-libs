<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointReaderHotJournalCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-options-next132.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('wp next132 clean sqlite header'),
    2 => $page('wp next132 clean wp_options root'),
    3 => $page('wp next132 clean active_plugins option'),
];
$dirtyDatabase = $page('wp next132 dirty sqlite header')
    . $page('wp next132 dirty wp_options root')
    . $page('wp next132 dirty active_plugins option');

$nonce = 0x2026132;
$journalBytes = str_pad(
    SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($cleanPages), $nonce, count($cleanPages), $sectorSize, $pageSize),
    $sectorSize,
    "\0"
);
foreach ($cleanPages as $pageNumber => $pageImage) {
    $journalBytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
}

$makeWalBytes = static function (array $frames, int $checkpoint, int $salt1, int $salt2) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as [$pageNumber, $commit, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$currentWalBytes = $makeWalBytes([
    [1, 0, 'wp next132 current wal schema draft'],
    [2, 3, 'wp next132 current wal options commit'],
    [3, 3, 'wp next132 current wal active_plugins commit'],
], 132, 0x13213201, 0x13213202);
$staleReaderWalBytes = $makeWalBytes([
    [1, 0, 'wp next132 stale reader schema draft'],
    [2, 3, 'wp next132 stale reader options commit'],
    [3, 3, 'wp next132 stale reader active_plugins commit'],
], 131, 0x13113101, 0x13113102);

$plan = SQLiteWalCheckpointReaderHotJournalCurrentSourceNextPlan::plan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    SQLiteWal::parse($currentWalBytes, $pageSize, true),
    $currentWalBytes,
    $staleReaderWalBytes,
    [1, 2, 3],
    3
);

$summary = [
    'scenario' => 'wordpress-wal-checkpoint-reader-hot-journal-current-source-next132',
    'wordpressUse' => 'After a copied wp_options import leaves a hot rollback journal and a reader still holds an older WAL source, native PHP tooling restores the hot journal but defers restart checkpoint reset until the reader reopens on the current WAL source.',
    'status' => $plan['status'],
    'readerSourceMatchesCurrent' => $plan['reader_source_matches_current'],
    'checkpointAllowed' => $plan['checkpoint_allowed'],
    'readerReopenRequired' => $plan['reader_reopen_required'],
    'sourceTransitions' => $plan['source_transitions'],
    'operationReasons' => $plan['operation_reasons'],
    'dependencyClosure' => 'no new support component needed; this reuses native rollback-journal recovery and WAL reader current-source validation',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    assert($summary['status'] === 'wal-checkpoint-reader-hot-journal-current-source-stale-reader-next132');
    assert($summary['readerSourceMatchesCurrent'] === false);
    assert($summary['checkpointAllowed'] === false);
    assert($summary['readerReopenRequired'] === true);
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

return $summary;
