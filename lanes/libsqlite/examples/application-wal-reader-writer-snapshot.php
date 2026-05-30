<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalAppendPlan.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalAppendPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$salt1 = 0x26262626;
$salt2 = 0x51515151;
$databasePath = 'wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('database page one before writer') . $page('database page two before writer');

$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 26, $salt1, $salt2);
$headerChecksum = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $headerChecksum[0], $headerChecksum[1]);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $image) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$walBytes = $appendFrame($walBytes, $seed, 1, 0, $page('wp_options root before writer'));
$walBytes = $appendFrame($walBytes, $seed, 2, 2, $page('wp_options active_plugins before writer'));
$wal = SQLiteWal::parse($walBytes, null, true);

$boundary = SQLiteWalAppendPlan::readerWriterSnapshotBoundary(
    $wal,
    $databaseBytes,
    $databasePath,
    [
        [
            'pages' => [
                2 => $page('wp_options active_plugins after writer'),
                3 => $page('wp_options autoload index after writer'),
            ],
            'database_page_count' => 3,
            'commit' => true,
        ],
        [
            'pages' => [
                1 => $page('wp_options draft root not committed'),
                4 => $page('wp_options draft plugin row not committed'),
            ],
            'commit' => false,
        ],
    ],
    [1, 2, 3, 4]
);

$summary = [
    'applicationUse' => 'Show copied wp_options WAL reader/writer snapshot isolation in pure PHP: a current reader pinned before a writer append keeps old committed pages, the next reader sees the new committed option/index pages, and uncommitted writer tail frames stay invisible without ext/sqlite.',
    'currentReaderEndFrame' => $boundary['current_reader_end_frame'],
    'nextReaderEndFrame' => $boundary['next_reader_end_frame'],
    'currentSources' => $boundary['current_reader_sources'],
    'nextSources' => $boundary['next_reader_sources'],
    'currentFrameIndexes' => $boundary['current_reader_frame_indexes'],
    'nextFrameIndexes' => $boundary['next_reader_frame_indexes'],
    'currentPageCount' => $boundary['current_database_page_count'],
    'nextPageCount' => $boundary['next_database_page_count'],
    'uncommittedTailVisible' => $boundary['uncommitted_tail_visible'],
    'nextContainsActivePluginsUpdate' => str_contains($boundary['next_reader'][1]['image'], 'active_plugins after writer'),
    'nextContainsDraftRoot' => str_contains($boundary['next_reader'][0]['image'], 'draft root'),
    'dependencies' => $boundary['dependencies'],
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

return $summary;
