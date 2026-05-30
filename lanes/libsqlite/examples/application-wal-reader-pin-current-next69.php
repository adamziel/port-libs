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
$databaseBytes = $page('wp schema before')
    . $page('wp_options siteurl database')
    . $page('wp_options autoload database')
    . $page('wp_transient database');
$databasePath = '/tmp/wp-reader-pin-current-next69.sqlite';
$salt1 = 0x69112233;
$salt2 = 0x69445566;

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 69, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [2, 0, $page('wp_options siteurl old wal')],
    [3, 4, $page('wp_options autoload old wal commit')],
    [2, 4, $page('wp_options siteurl current wal')],
] as [$pageNumber, $commitPageCount, $image]) {
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$wal = SQLiteWal::parse($walBytes, null, true);
$plan = SQLiteWalAppendPlan::readerSlotPinAppendCurrentNext(
    $wal,
    $databaseBytes,
    $databasePath,
    [
        [
            'pages' => [
                2 => $page('wp_options siteurl imported next'),
                4 => $page('wp_transient imported next'),
            ],
            'database_page_count' => 4,
            'commit' => true,
        ],
    ],
    [2, 3, 4],
    [0, null, 3],
    0,
    'restart',
);

$summary = [
    'scenario' => 'application-wal-reader-pin-current-next69',
    'status' => $plan['status'],
    'reason' => $plan['reason'],
    'current_reader_slot' => $plan['current_reader_slot'],
    'current_reader_frame' => $plan['current_reader_end_frame'],
    'next_reader_frame' => $plan['next_reader_end_frame'],
    'next_reader_slot' => $plan['next_reader_slot'],
    'current_sources' => $plan['current_reader_sources'],
    'next_sources' => $plan['next_reader_sources'],
    'checkpoint_with_current_pin_busy' => $plan['checkpoint_with_current_pin']['busy'],
    'checkpoint_after_release_reader_frame' => $plan['checkpoint_after_release']['reader_end_frame'],
    'current_slot_blocks_checkpoint_reset' => $plan['current_slot_blocks_checkpoint_reset'],
    'dependency' => in_array('sqlite-wal-reader-slot-pin-current-next69', $plan['dependencies'], true),
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['status'] === 'current-reader-slot-pinned-next-reader-advanced');
    assert($summary['reason'] === 'database_reader_pin_preserved_across_wal_append');
    assert($summary['current_reader_slot'] === 0);
    assert($summary['current_reader_frame'] === 0);
    assert($summary['next_reader_frame'] === 5);
    assert($summary['next_reader_slot'] === 1);
    assert($summary['current_sources'] === ['database', 'database', 'database']);
    assert($summary['next_sources'] === ['wal', 'wal', 'wal']);
    assert($summary['checkpoint_with_current_pin_busy'] === true);
    assert($summary['dependency'] === true);
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
