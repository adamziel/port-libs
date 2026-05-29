<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan;

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-options-next143.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$cleanPages = [
    1 => $page('wp next143 hot recovered sqlite header'),
    2 => $page('wp next143 hot recovered wp_options root'),
    3 => $page('wp next143 hot recovered active_plugins'),
    4 => $page('wp next143 hot recovered autoload index'),
];
$dirtyDatabase = $page('wp next143 dirty sqlite header')
    . $page('wp next143 dirty wp_options root')
    . $page('wp next143 dirty active_plugins')
    . $page('wp next143 dirty autoload index');

$nonce = 0x2026143;
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
    [1, 0, 'wp next143 reader schema draft'],
    [2, 4, 'wp next143 reader wp_options commit'],
    [3, 4, 'wp next143 reader active_plugins commit'],
], 143, 0x14314301, 0x14314302);
$restartedWalBytes = $makeWalBytes([
    [2, 0, 'wp next143 restarted wp_options draft'],
    [4, 4, 'wp next143 restarted autoload commit'],
], 144, 0x14314401, 0x14314402);

$plan = SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan::next143Plan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    SQLiteWal::parse($currentWalBytes, $pageSize, true),
    $currentWalBytes,
    $restartedWalBytes,
    [1, 2, 3, 4],
    3
);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-reader-restart-current-source-next143',
    'wordpressUse' => 'A copied WordPress SQLite database recovers hot rollback-journal pages, keeps an existing WAL reader pinned to the recovered current source, then admits the next reader on a restarted WAL generation for later wp_options writes.',
    'status' => $plan['status'],
    'hotRecovered' => $plan['hot_recovered'],
    'currentReaderPreserved' => $plan['current_reader_preserved'],
    'nextSourceSeparated' => $plan['next_source_separated'],
    'currentSources' => $plan['current_sources'],
    'nextSources' => $plan['next_sources'],
    'nextSeparatedPages' => $plan['next_separated_page_numbers'],
    'operationReasons' => $plan['operation_reasons'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    assert($summary['status'] === 'wal-hot-journal-reader-restart-current-source-next143');
    assert($summary['hotRecovered'] === true);
    assert($summary['currentReaderPreserved'] === true);
    assert($summary['nextSourceSeparated'] === true);
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

return $summary;
