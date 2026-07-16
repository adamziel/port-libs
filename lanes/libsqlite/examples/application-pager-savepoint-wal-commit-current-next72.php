<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSavepointStack.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalSavepointCheckpointPlan.php';
require_once __DIR__ . '/../src/SQLiteVfsFileWriter.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databasePath = '/wp-content/database/.ht.sqlite';
$databaseBytes = $page('wp-options-header-before') . $page('wp-siteurl-before') . $page('wp-plugin-before');

$salt1 = 0x72817281;
$salt2 = 0x90919091;
$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 72, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
foreach ([[1, 0, 'retained-schema-frame'], [2, 3, 'retained-siteurl-commit'], [3, 3, 'rolled-back-plugin-commit']] as $frame) {
    [$pageNumber, $commitPageCount, $label] = $frame;
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp-import');
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin-settings');
$savepoints->recordWalFrameWrite(3, 3, true);

$root = sys_get_temp_dir() . '/port-libsqlite-application-commit-current72-' . bin2hex(random_bytes(4));
$localDatabase = $root . $databasePath;
if (!is_dir(dirname($localDatabase)) && !mkdir(dirname($localDatabase), 0777, true) && !is_dir(dirname($localDatabase))) {
    throw new RuntimeException('Unable to create Application savepoint commit-current fixture');
}
file_put_contents($localDatabase, $databaseBytes);
file_put_contents($localDatabase . '-wal', $walBytes . 'stale-tail');

$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$applied = (new SQLiteVfsFileWriter($root))->applySavepointCommitCurrent(
    $savepoints,
    'plugin-settings',
    $wal,
    $walBytes,
    $databaseBytes,
    $databasePath,
    [1, 2, 3],
    'restart'
);

echo json_encode([
    'scenario' => 'application-pager-savepoint-wal-commit-current-next72',
    'applicationUse' => 'Copied wp_options import commits the retained WAL prefix after ROLLBACK TO plugin savepoint, discards the failed plugin frame, checkpoints retained pages, and resets the WAL sidecar through bounded native PHP VFS writes.',
    'status' => $applied['status'],
    'walAction' => $applied['commit_current']['wal_action'],
    'committedPages' => $applied['commit_current']['committed_page_numbers'],
    'discardedFrames' => array_column($applied['commit_current']['discarded_wal_frames'], 'frame_index'),
    'databaseContainsRetainedSiteurl' => str_contains((string) file_get_contents($localDatabase), 'retained-siteurl-commit'),
    'databaseContainsRolledBackPlugin' => str_contains((string) file_get_contents($localDatabase), 'rolled-back-plugin-commit'),
    'walBytes' => strlen((string) file_get_contents($localDatabase . '-wal')),
    'dependencies' => $applied['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
