<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteLockCoordinator;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$root = sys_get_temp_dir() . '/port-libsqlite-application-pager-checkpoint-' . bin2hex(random_bytes(4));
$databasePath = '/srv/www/wp-content/database/.ht.sqlite';

$makeFirstPage = static function (int $databaseSizePages) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $databaseSizePages), 28, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$databaseBytes = $makeFirstPage(3)
    . str_pad('base-wp-options-table', $pageSize, "\0")
    . str_pad('base-autoload-index', $pageSize, "\0");

$salt1 = 0x51617181;
$salt2 = 0x91a1b1c1;
$walHeaderPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 0, $salt1, $salt2);
$checksumSeed = SQLiteWal::checksumPair($walHeaderPrefix, false);
$walBytes = $walHeaderPrefix . pack('N*', $checksumSeed[0], $checksumSeed[1]);
$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $pageImage) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $pageImage, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $pageImage;
};
$walBytes = $appendFrame($walBytes, $checksumSeed, 2, 0, str_pad('checkpointed-wp-options-siteurl', $pageSize, "\0"));
$walBytes = $appendFrame($walBytes, $checksumSeed, 3, 3, str_pad('checkpointed-autoload-index', $pageSize, "\0"));
$wal = SQLiteWal::parse($walBytes, null, true);

$locks = new SQLiteLockCoordinator();
$applied = (new SQLiteVfsFileWriter($root))->applyPagerCheckpointTransaction(
    $locks,
    'wp-import-checkpoint',
    $wal,
    $databaseBytes,
    $databasePath,
    'truncate'
);

$localDatabase = $root . $databasePath;
$localWal = $localDatabase . '-wal';
$databaseAfter = file_get_contents($localDatabase);
$walAfter = file_get_contents($localWal);

echo json_encode([
    'scenario' => 'application-pager-checkpoint-atomic-apply',
    'applicationUse' => 'Apply copied wp_options WAL checkpoint transactions through bounded native PHP file handles only after SQLite lock escalation, rolling back partial file writes if a later sidecar operation fails, without requiring ext/sqlite.',
    'root' => $root,
    'databasePath' => $databasePath,
    'status' => $applied['status'],
    'atomic' => $applied['atomic'],
    'locksReleased' => $applied['locks_released'],
    'appliedOperations' => $applied['applied'],
    'durableSyncs' => $applied['durable_syncs'],
    'directorySyncs' => $applied['directory_syncs'],
    'lockSequence' => array_column($applied['transaction']['lock_sequence'], 'requested'),
    'walAction' => $applied['transaction']['write_plan']['wal_action'],
    'operationReasons' => array_column($applied['operations'], 'reason'),
    'pagePrefixes' => [
        'page2' => rtrim(substr($databaseAfter, $pageSize, 64), "\0"),
        'page3' => rtrim(substr($databaseAfter, $pageSize * 2, 64), "\0"),
    ],
    'walBytesAfter' => strlen($walAfter),
    'dependencies' => $applied['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
