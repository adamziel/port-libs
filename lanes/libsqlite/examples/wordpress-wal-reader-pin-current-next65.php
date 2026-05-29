<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalAppendPlan.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalAppendPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$salt1 = 0x65656565;
$salt2 = 0x25252525;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0", STR_PAD_RIGHT);
$database = $page('db page one schema before reader')
    . $page('db page two siteurl before reader')
    . $page('db page three autoload index before reader');

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 65, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [2, 0, $page('wal frame one siteurl draft before pin')],
    [3, 3, $page('wal frame two autoload commit current pin')],
    [2, 0, $page('wal frame three later draft hidden')],
] as [$pageNumber, $commit, $image]) {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$wal = SQLiteWal::parse($walBytes, null, true);
$plan = SQLiteWalAppendPlan::readerPinCurrentNext(
    $wal,
    $database,
    'wp-content/database/.ht.sqlite',
    [[
        'pages' => [
            2 => $page('writer committed siteurl next reader'),
            4 => $page('writer committed plugin option page'),
        ],
        'database_page_count' => 4,
        'commit' => true,
    ]],
    [2, 3, 4],
    [0, 2, null]
);

echo json_encode([
    'status' => $plan['status'],
    'currentReaderEndFrame' => $plan['current_reader_end_frame'],
    'nextReaderEndFrame' => $plan['next_reader_end_frame'],
    'currentSources' => $plan['current_reader_sources'],
    'nextSources' => $plan['next_reader_sources'],
    'currentFrames' => $plan['current_reader_frame_indexes'],
    'nextFrames' => $plan['next_reader_frame_indexes'],
    'nextReadMarks' => $plan['next_read_marks'],
    'currentReaderPinsOldSnapshot' => $plan['current_reader_pins_old_snapshot'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT) . PHP_EOL;
