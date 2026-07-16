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
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('wp schema') . $page('wp_options siteurl before') . $page('wp_options autoload before') . $page('wp_postmeta before');
$databasePath = '/tmp/wp-reader-pin-current-next67.sqlite';
$salt1 = 0x67112233;
$salt2 = 0x67445566;

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 67, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [2, 0, $page('wp_options current reader siteurl')],
    [3, 4, $page('wp_options autoload committed')],
    [2, 4, $page('wp_options previous writer siteurl')],
] as [$pageNumber, $commitPageCount, $image]) {
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$wal = SQLiteWal::parse($walBytes, null, true);
$plan = SQLiteWalAppendPlan::readerPinAppendCurrentNext(
    $wal,
    $databaseBytes,
    $databasePath,
    [
        [
            'pages' => [
                2 => $page('wp_options next import siteurl'),
                4 => $page('wp_postmeta next import'),
            ],
            'database_page_count' => 4,
            'commit' => true,
        ],
        [
            'pages' => [
                3 => $page('wp_options uncommitted autoload'),
            ],
            'database_page_count' => 4,
            'commit' => false,
        ],
    ],
    [2, 3, 4],
    [0, 2, null, null],
    'restart',
);

$summary = [
    'scenario' => 'application-wal-reader-pin-append-current-next67',
    'status' => $plan['status'],
    'current_reader_frame' => $plan['current_reader_end_frame'],
    'next_reader_frame' => $plan['next_reader_end_frame'],
    'next_reader_slot' => $plan['next_reader_slot'],
    'next_read_marks' => $plan['next_read_marks'],
    'current_sources' => $plan['current_reader_sources'],
    'next_sources' => $plan['next_reader_sources'],
    'checkpoint_before_release_busy' => $plan['checkpoint_before_release']['busy'],
    'checkpoint_after_release_reason' => $plan['checkpoint_after_release']['reason'],
    'current_pin_blocks_checkpoint' => $plan['current_pin_blocks_checkpoint'],
    'release_allows_checkpoint_reset' => $plan['release_allows_checkpoint_reset'],
    'dependency' => in_array('sqlite-wal-reader-pin-append-current-next67', $plan['dependencies'], true),
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['status'] === 'current-reader-pinned-next-reader-advanced');
    assert($summary['current_reader_frame'] === 2);
    assert($summary['next_reader_frame'] === 5);
    assert($summary['next_reader_slot'] === 2);
    assert($summary['checkpoint_before_release_busy'] === true);
    assert($summary['current_pin_blocks_checkpoint'] === true);
    assert($summary['dependency'] === true);
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
