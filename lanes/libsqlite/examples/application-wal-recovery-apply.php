<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalRecoveryPlan.php';
require_once __DIR__ . '/../src/SQLiteVfsFileWriter.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalRecoveryPlan;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;

$pageSize = 512;
$salt1 = 0x51525354;
$salt2 = 0x61626364;
$databasePath = '/srv/www/wp-content/database/.ht.sqlite';

$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 3), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$databaseBytes = $firstPage
    . str_pad('wp_options siteurl before WAL', $pageSize, '.')
    . str_pad('wp_options home before WAL', $pageSize, '.');

$walHeaderPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 91, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($walHeaderPrefix, false);
$walBytes = $walHeaderPrefix . pack('N*', $seed[0], $seed[1]);
$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $pageImage) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $pageImage, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $pageImage;
};

$walBytes = $appendFrame($walBytes, $seed, 2, 0, str_pad('wp_options siteurl stale frame', $pageSize, 's'));
$walBytes = $appendFrame($walBytes, $seed, 2, 3, str_pad('wp_options siteurl committed frame', $pageSize, 'u'));
$walBytes = $appendFrame($walBytes, $seed, 3, 3, str_pad('wp_options home committed frame', $pageSize, 'h'));
$walBytes = $appendFrame($walBytes, $seed, 4, 0, str_pad('wp_options transient uncommitted tail', $pageSize, 't'));

$wal = SQLiteWal::parse($walBytes, null, true);
$plan = SQLiteWalRecoveryPlan::recover($wal, $databaseBytes, $databasePath);
$root = sys_get_temp_dir() . '/port-libsqlite-wp-wal-recovery-' . bin2hex(random_bytes(4));
$writer = new SQLiteVfsFileWriter($root);
$applied = $writer->applyWalRecovery($wal, $databaseBytes, $databasePath);

echo json_encode([
    'status' => $applied['status'],
    'reason' => $applied['recovery']['reason'],
    'committed_transactions' => $plan['committed_transaction_count'],
    'applied_pages' => $plan['applied_page_count'],
    'uncommitted_tail_frames' => $plan['uncommitted_frame_count'],
    'operations' => array_column($plan['operations'], 'reason'),
    'database_bytes' => filesize($root . $databasePath),
    'wal_bytes' => filesize($root . $databasePath . '-wal'),
    'dependencies' => $applied['dependencies'],
], JSON_PRETTY_PRINT) . PHP_EOL;
