<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalReaderTruncateCurrentSourceNextPlan;

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-options-next137.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('wp next137 clean sqlite header'),
    2 => $page('wp next137 clean wp_options root'),
    3 => $page('wp next137 clean active_plugins option'),
];
$dirtyDatabase = $page('wp next137 dirty sqlite header')
    . $page('wp next137 dirty wp_options root')
    . $page('wp next137 dirty active_plugins option');

$nonce = 0x2026137;
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
    [1, 0, 'wp next137 current reader schema draft'],
    [2, 3, 'wp next137 current reader wp_options commit'],
    [3, 3, 'wp next137 current reader active_plugins commit'],
], 137, 0x13713701, 0x13713702);

$plan = SQLiteWalHotJournalReaderTruncateCurrentSourceNextPlan::plan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    SQLiteWal::parse($currentWalBytes, $pageSize, true),
    $currentWalBytes,
    $currentWalBytes,
    [[
        'pages' => [
            2 => $page('wp next137 next generation wp_options commit'),
            3 => $page('wp next137 next generation active_plugins commit'),
        ],
        'database_page_count' => 3,
        'commit' => true,
    ]],
    [1, 2, 3],
    3
);

$summary = [
    'scenario' => 'application-wal-hot-journal-reader-truncate-current-source-next137',
    'applicationUse' => 'After a copied wp_options import recovers a hot rollback journal, native PHP WAL handling feeds the recovered database image into a truncate checkpoint while the current reader remains pinned and the next writer starts a fresh WAL generation.',
    'status' => $plan['status'],
    'hotRecovered' => $plan['hot_recovered'],
    'readerSourceMatchesCurrent' => $plan['reader_source_matches_current'],
    'truncateRemovedOldWalSidecar' => $plan['truncate_removed_old_wal_sidecar'],
    'nextReaderUsesFreshWalGeneration' => $plan['next_reader_uses_fresh_wal_generation'],
    'nextGenerationChangedPageNumbers' => $plan['next_generation_changed_page_numbers'],
    'sourceTransitions' => $plan['source_transitions'],
    'operationReasons' => $plan['operation_reasons'],
    'dependencyClosure' => 'no new support component needed; this reuses native hot rollback-journal recovery, WAL reader current-source validation, truncate checkpoint planning, and WAL append planning',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    assert($summary['status'] === 'wal-hot-journal-reader-truncate-current-source-next137');
    assert($summary['hotRecovered'] === true);
    assert($summary['readerSourceMatchesCurrent'] === true);
    assert($summary['truncateRemovedOldWalSidecar'] === true);
    assert($summary['nextReaderUsesFreshWalGeneration'] === true);
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

return $summary;
