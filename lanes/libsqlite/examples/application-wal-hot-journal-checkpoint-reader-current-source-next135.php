<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalCheckpointReaderCurrentSourceNextPlan;

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-options-next135.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('wp next135 clean sqlite header'),
    2 => $page('wp next135 clean wp_options root'),
    3 => $page('wp next135 clean active_plugins option'),
];
$dirtyDatabase = $page('wp next135 dirty sqlite header')
    . $page('wp next135 dirty wp_options root')
    . $page('wp next135 dirty active_plugins option');

$nonce = 0x2026135;
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
    [1, 0, 'wp next135 current reader schema draft'],
    [2, 3, 'wp next135 current reader wp_options commit'],
    [3, 3, 'wp next135 current reader active_plugins commit'],
], 135, 0x13513501, 0x13513502);
$nextWalBytes = $makeWalBytes([
    [1, 0, 'wp next135 current reader schema draft'],
    [2, 3, 'wp next135 next generation wp_options commit'],
    [3, 3, 'wp next135 next generation active_plugins commit'],
], 136, 0x13613601, 0x13613602);

$plan = SQLiteWalHotJournalCheckpointReaderCurrentSourceNextPlan::hotJournalCheckpointReaderSeparatedWalPlan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    SQLiteWal::parse($currentWalBytes, $pageSize, true),
    $currentWalBytes,
    $nextWalBytes,
    [1, 2, 3],
    3
);

$summary = [
    'scenario' => 'application-wal-hot-journal-checkpoint-reader-current-source-next135',
    'applicationUse' => 'After a copied wp_options import recovers a hot rollback journal, a current WAL reader stays pinned to its source while a later writer opens the next WAL generation for new option rows.',
    'status' => $plan['status'],
    'checkpointAllowed' => $plan['checkpoint_allowed'],
    'readerSourceMatchesCurrent' => $plan['reader_source_matches_current'],
    'nextSourceSeparated' => $plan['next_source_separated'],
    'nextChangedPageNumbers' => $plan['next_changed_page_numbers'],
    'sourceTransitions' => $plan['source_transitions'],
    'operationReasons' => $plan['operation_reasons'],
    'dependencyClosure' => 'no new support component needed; this reuses native WAL parsing, hot rollback-journal recovery, and reader current-source validation',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    assert($summary['status'] === 'wal-hot-journal-checkpoint-reader-current-source-next135');
    assert($summary['checkpointAllowed'] === true);
    assert($summary['readerSourceMatchesCurrent'] === true);
    assert($summary['nextSourceSeparated'] === true);
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

return $summary;
