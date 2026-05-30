<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalAppendPlan.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalAppendPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp_options schema base')
    . $page('siteurl base')
    . $page('autoload index base')
    . $page('theme mods base');
$salt1 = 0x61616161;
$salt2 = 0x62626262;

$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 61, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [2, 0, $page('siteurl before pinned reader')],
    [3, 4, $page('autoload index first commit')],
    [2, 0, $page('siteurl draft after pin')],
    [4, 4, $page('theme mods committed before append')],
] as [$pageNumber, $commitPageCount, $image]) {
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$report = SQLiteWalAppendPlan::pinnedCheckpointAppendCurrentNext(
    $wal,
    $databaseBytes,
    '/tmp/wp-content/database/.ht.sqlite',
    [[
        'pages' => [
            2 => $page('siteurl committed writer append'),
            3 => $page('autoload index committed writer append'),
        ],
        'database_page_count' => 4,
        'commit' => true,
    ], [
        'pages' => [
            4 => $page('uncommitted theme mods writer tail'),
        ],
        'commit' => false,
    ]],
    [2, 3, 4],
    'restart',
    2
);

echo json_encode([
    'status' => $report['status'],
    'checkpointReason' => $report['checkpoint']['reason'],
    'walAction' => $report['checkpoint']['wal_action'],
    'currentReaderFrames' => $report['current_reader_frame_indexes'],
    'nextReaderFrames' => $report['next_reader_frame_indexes'],
    'currentStable' => $report['current_reader_stable'],
    'nextSeesCommittedAppend' => $report['next_reader_sees_committed_append'],
    'nextHidesUncommittedTail' => $report['next_reader_hides_uncommitted_tail'],
    'appendFrames' => [$report['append']['start_frame'], $report['append']['end_frame']],
    'dependencies' => $report['dependencies'],
], JSON_PRETTY_PRINT) . PHP_EOL;
