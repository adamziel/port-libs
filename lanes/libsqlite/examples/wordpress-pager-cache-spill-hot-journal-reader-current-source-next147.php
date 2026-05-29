<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePagerCacheSpillHotJournalReaderCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/var/www/html/wp-content/database/wp-next147.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('wp next147 hot recovered sqlite header'),
    2 => $page('wp next147 hot recovered wp_options root'),
    3 => $page('wp next147 hot recovered active_plugins'),
    4 => $page('wp next147 hot recovered autoload index'),
    5 => $page('wp next147 hot recovered transient rows'),
];
$dirtyDatabase = $page('wp next147 dirty sqlite header')
    . $page('wp next147 dirty wp_options root')
    . $page('wp next147 dirty active_plugins')
    . $page('wp next147 dirty autoload index')
    . $page('wp next147 dirty transient rows');

$journalBytes = (static function () use ($cleanPages, $sectorSize, $pageSize): string {
    $nonce = 0x2026147;
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($cleanPages), $nonce, count($cleanPages), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($cleanPages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
})();

$makeWalBytes = static function (array $frames, int $checkpoint, int $salt1, int $salt2) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$currentWalBytes = $makeWalBytes([
    [1, 0, 'wp next147 current schema reader frame'],
    [2, 5, 'wp next147 current wp_options reader frame'],
    [3, 0, 'wp next147 current active_plugins frame'],
    [4, 5, 'wp next147 current autoload reader frame'],
], 147, 0x14714711, 0x14714712);
$restartedWalBytes = $makeWalBytes([
    [2, 0, 'wp next147 restarted wp_options next frame'],
    [5, 5, 'wp next147 restarted transient next frame'],
], 148, 0x14714811, 0x14714812);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);

$plan = SQLitePagerCacheSpillHotJournalReaderCurrentSourceNextPlan::plan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $currentWal,
    $currentWalBytes,
    $restartedWalBytes,
    [
        ['page' => 2, 'image' => $page('wp next147 retry options cache spill'), 'current_image' => $currentWal->frames[1]->pageImage, 'walFrame' => 2],
        ['page' => 3, 'image' => $page('wp next147 retry active_plugins spill'), 'current_image' => $currentWal->frames[2]->pageImage, 'walFrame' => 3],
        ['page' => 4, 'image' => $page('wp next147 pinned autoload spill'), 'current_image' => $currentWal->frames[3]->pageImage, 'readerPinned' => true],
        ['page' => 5, 'image' => $page('wp next147 transient cache spill'), 'current_image' => $cleanPages[5], 'journaled' => true],
    ],
    [1, 2, 3, 4, 5],
    4,
    7,
    3
);

if (($argv[1] ?? '') === '--self-test') {
    if ($plan['status'] !== 'pager_cache_spill_hot_journal_reader_current_source_next147') {
        throw new RuntimeException('Unexpected pager hot-journal reader cache-spill status');
    }
    if ($plan['spilled_page_numbers'] !== [2, 3, 5]) {
        throw new RuntimeException('Unexpected pager hot-journal reader cache-spill page set');
    }
    if ($plan['rejected_pages'][4] !== ['reader_pinned_current_source_page']) {
        throw new RuntimeException('Reader-pinned page was not deferred');
    }
    echo "wordpress-pager-cache-spill-hot-journal-reader-current-source-next147 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'reader_end_frame' => $plan['reader_end_frame'],
    'spilled_pages' => $plan['spilled_page_numbers'],
    'rejected_pages' => $plan['rejected_page_numbers'],
    'next_wal_frames' => $plan['appended_wal_frames'],
], JSON_PRETTY_PRINT) . "\n";
