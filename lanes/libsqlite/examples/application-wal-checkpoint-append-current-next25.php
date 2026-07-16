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
$salt1 = 0x25252525;
$salt2 = 0x67676767;
$databasePath = 'wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('database page 1 original schema') . $page('database page 2 original options');
$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 25, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $image) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};
$walBytes = $appendFrame($walBytes, $seed, 1, 0, $page('wal page 1 schema before checkpoint'));
$walBytes = $appendFrame($walBytes, $seed, 2, 2, $page('wal page 2 options before checkpoint'));
$wal = SQLiteWal::parse($walBytes, null, true);

$transactions = [[
    'pages' => [
        2 => $page('next writer page 2 updated active_plugins'),
        3 => $page('next writer page 3 new autoload index'),
    ],
    'database_page_count' => 3,
    'commit' => true,
], [
    'pages' => [
        3 => $page('next writer page 3 draft not committed'),
    ],
    'commit' => false,
]];

$plan = SQLiteWalAppendPlan::checkpointAppendCurrentNext(
    $wal,
    $databaseBytes,
    $databasePath,
    $transactions,
    [1, 2, 3],
    'restart'
);

$report = [
    'scenario' => 'application-wal-checkpoint-append-current-next25',
    'applicationUse' => 'After a copied wp_options WAL checkpoint restarts the sidecar, append the next writer transaction to the fresh WAL while preserving current-reader visibility on the old frame set.',
    'status' => $plan['status'],
    'checkpointAction' => $plan['checkpoint']['wal_action'],
    'appendStartOffset' => $plan['append']['start_offset'],
    'appendedFrames' => $plan['append']['appended_frame_count'],
    'currentReaderSources' => $plan['current_reader_sources'],
    'nextReaderSources' => $plan['next_reader_sources'],
    'nextCommitFrame' => $plan['append']['last_commit_frame'],
    'nextUsesCheckpointDatabase' => $plan['next_uses_checkpoint_database'],
    'nextUsesAppendedWal' => $plan['next_uses_appended_wal'],
    'dependencies' => $plan['dependencies'],
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
