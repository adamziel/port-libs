<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalAppendPlan.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteVfsFileWriter.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalAppendPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;

$pageSize = 512;
$salt1 = 0x23232323;
$salt2 = 0x45454545;
$databasePath = 'wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 11, $salt1, $salt2);
$checksum = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $checksum[0], $checksum[1]);
$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $image) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};
$walBytes = $appendFrame($walBytes, $checksum, 1, 0, $page('wp_options schema before appended import'));
$walBytes = $appendFrame($walBytes, $checksum, 2, 2, $page('wp_options data before appended import'));
$wal = SQLiteWal::parse($walBytes, null, true);

$transactions = [[
    'pages' => [
        2 => $page('wp_options active_plugins updated in append'),
        3 => $page('wp_options autoload index updated append'),
    ],
    'database_page_count' => 3,
    'commit' => true,
], [
    'pages' => [
        4 => $page('wp_options plugin draft left uncommitted'),
    ],
    'commit' => false,
]];

$root = sys_get_temp_dir() . '/port-libsqlite-application-wal-append-' . bin2hex(random_bytes(4));
$localWal = $root . '/' . $databasePath . '-wal';
if (!is_dir(dirname($localWal)) && !mkdir(dirname($localWal), 0777, true) && !is_dir(dirname($localWal))) {
    throw new RuntimeException('Unable to create Application WAL append smoke directory');
}
file_put_contents($localWal, $walBytes);

$plan = SQLiteWalAppendPlan::appendTransactions($wal, $databasePath, $transactions);
$applied = (new SQLiteVfsFileWriter($root))->applyWalAppendTransactions($wal, $databasePath, $transactions);
$parsed = SQLiteWal::parse((string) file_get_contents($localWal), null, true);

$report = [
    'scenario' => 'application-wal-append-transaction',
    'applicationUse' => 'Append copied wp_options import pages to a WAL sidecar with SQLite-compatible chained checksums, commit markers, and native PHP VFS persistence, so shared-hosting repair/import tools can stage committed option rows while preserving an uncommitted draft tail without ext/sqlite.',
    'status' => $applied['status'],
    'appendBytes' => $plan['append_bytes_length'],
    'appendedFrames' => $plan['appended_frame_count'],
    'lastCommitFrame' => $parsed->lastCommitFrame()?->index,
    'uncommittedTailFrames' => $parsed->uncommittedFrameCount(),
    'durableSyncs' => $applied['durable_syncs'],
    'directorySyncs' => $applied['directory_syncs'],
    'containsCommittedOption' => str_contains((string) file_get_contents($localWal), 'active_plugins updated'),
    'containsUncommittedDraft' => str_contains((string) file_get_contents($localWal), 'draft left uncommitted'),
    'dependencies' => $applied['dependencies'],
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
