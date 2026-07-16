<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteShmIndex.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalAppendPlan.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalAppendPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$salt1 = 0x73112233;
$salt2 = 0x73445566;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp schema baseline')
    . $page('wp_options siteurl baseline')
    . $page('wp_options autoload baseline')
    . $page('wp plugin settings baseline');
$databasePath = '/tmp/wp-reader-pin-restart-current-next73.sqlite';

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 73, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [2, 0, $page('wp_options siteurl before reader')],
    [3, 3, $page('wp_options autoload committed before reader')],
    [2, 0, $page('wp_options siteurl after reader')],
    [4, 4, $page('wp plugin settings committed before restart')],
] as [$pageNumber, $commitPageCount, $image]) {
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$makeShm = static function (array $readMarks, array $readLocks, int $backfill, int $attempted) use ($pageSize): string {
    $pageSizeField = (1 << 24) | $pageSize;
    $header = pack('V*', 3007000, $backfill, 173, $pageSizeField, 4, 4, 1, 2, 3, 4, 5, 6);
    $marks = array_map(static fn ($value): int => $value === null ? 0xffffffff : $value, array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, null));
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");
    $checkpoint = pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);

    return $header . $header . $checkpoint;
};

$wal = SQLiteWal::parse($walBytes, null, true);
$plan = SQLiteWalAppendPlan::checkpointRestartAppendReaderCurrentNext(
    $wal,
    $databaseBytes,
    $databasePath,
    [[
        'pages' => [
            2 => $page('wp_options siteurl next import'),
            4 => $page('wp plugin settings next import'),
        ],
        'database_page_count' => 4,
        'commit' => true,
    ]],
    [2, 3, 4],
    SQLiteShmIndex::parse($makeShm([0, 2, 4, null, null], [false, true, true, false, false], 1, 3)),
    SQLiteShmIndex::parse($makeShm([0, 4, null, null, null], [false, false, false, false, false], 4, 4)),
    'restart',
);

$summary = [
    'scenario' => 'application-wal-reader-pin-restart-current-next73',
    'status' => $plan['status'],
    'current_reader_frame' => $plan['current_reader_end_frame'],
    'next_reader_frame' => $plan['next_reader_end_frame'],
    'first_checkpoint_busy' => $plan['first']['checkpoint']['busy'],
    'retry_checkpoint_action' => $plan['retry']['checkpoint']['wal_action'],
    'append_start_frame' => $plan['append']['start_frame'],
    'append_end_frame' => $plan['append']['end_frame'],
    'current_sources' => $plan['current_reader_sources'],
    'next_sources' => $plan['next_reader_sources'],
    'dependency' => in_array('sqlite-wal-reader-pin-restart-snapshot-current-next73', $plan['dependencies'], true),
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['status'] === 'reader-pin-restart-append-current-next');
    assert($summary['current_reader_frame'] === 2);
    assert($summary['next_reader_frame'] === 2);
    assert($summary['first_checkpoint_busy'] === true);
    assert($summary['retry_checkpoint_action'] === 'restart_wal');
    assert($summary['append_start_frame'] === 1);
    assert($summary['append_end_frame'] === 2);
    assert($summary['current_sources'] === ['wal', 'wal', 'error']);
    assert($summary['next_sources'] === ['wal', 'database', 'wal']);
    assert($summary['dependency'] === true);
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
