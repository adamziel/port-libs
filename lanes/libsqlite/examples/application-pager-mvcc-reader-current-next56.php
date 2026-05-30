<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalAppendPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$salt1 = 0x56565656;
$salt2 = 0x78787878;
$databasePath = 'wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp schema') . $page('wp_options base') . $page('autoload base');

$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 56, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
$appendFrame = static function (int $pageNumber, int $commit, string $image) use (&$walBytes, &$seed, $salt1, $salt2): void {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$appendFrame(2, 0, $page('reader sees active_plugins before import'));
$appendFrame(3, 3, $page('reader sees autoload before import'));
$appendFrame(2, 0, $page('newer writer before import'));
$appendFrame(4, 4, $page('newer plugin before import'));

$plan = SQLiteWalAppendPlan::mvccReaderCurrentNext(
    SQLiteWal::parse($walBytes, null, true),
    $databaseBytes,
    $databasePath,
    [[
        'pages' => [
            2 => $page('next reader sees imported active_plugins'),
            5 => $page('next reader sees imported transient cache'),
        ],
        'database_page_count' => 5,
        'commit' => true,
    ], [
        'pages' => [
            6 => $page('draft import remains invisible'),
        ],
        'commit' => false,
    ]],
    [2, 5, 6],
    2,
);

if (($argv[1] ?? '') === '--self-test') {
    $checks = [
        $plan['status'] === 'planned',
        $plan['current_reader_frame_indexes'] === [1, null, null],
        $plan['next_reader_frame_indexes'] === [5, 6, null],
        str_contains($plan['current_reader'][0]['image'], 'before import'),
        str_contains($plan['next_reader'][0]['image'], 'imported active_plugins'),
        str_contains($plan['next_reader'][1]['image'], 'transient cache'),
        $plan['next_reader'][2]['source'] === 'error',
        $plan['uncommitted_tail_visible'] === false,
    ];
    if (in_array(false, $checks, true)) {
        fwrite(STDERR, "application-pager-mvcc-reader-current-next56 self-test failed\n");
        exit(1);
    }

    echo "application-pager-mvcc-reader-current-next56 self-test passed\n";
    exit(0);
}

echo json_encode([
    'status' => $plan['status'],
    'reason' => $plan['reason'],
    'current_reader_end_frame' => $plan['current_reader_end_frame'],
    'next_reader_end_frame' => $plan['next_reader_end_frame'],
    'current_reader_frame_indexes' => $plan['current_reader_frame_indexes'],
    'next_reader_frame_indexes' => $plan['next_reader_frame_indexes'],
    'current_reader_sources' => $plan['current_reader_sources'],
    'next_reader_sources' => $plan['next_reader_sources'],
    'uncommitted_tail_visible' => $plan['uncommitted_tail_visible'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
