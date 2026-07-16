<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBusyHandler;
use PortLibs\LibSqlite\SQLiteLockCoordinator;
use PortLibs\LibSqlite\SQLitePagerCheckpointTransactionPlan;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$salt1 = 0x10203040;
$salt2 = 0x50607080;
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

$walHeaderPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 0, $salt1, $salt2);
$checksumSeed = SQLiteWal::checksumPair($walHeaderPrefix, false);
$walBytes = $walHeaderPrefix . pack('N*', $checksumSeed[0], $checksumSeed[1]);
$appendFrame = static function (string $walBytes, array &$checksumSeed, int $pageNumber, int $commit, string $pageImage) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $checksumSeed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $pageImage, false, $checksumSeed[0], $checksumSeed[1]);

    return $walBytes . $framePrefix . pack('N*', $checksumSeed[0], $checksumSeed[1]) . $pageImage;
};

$databaseBytes = $makeFirstPage(3) . str_pad('base-option-page', $pageSize, "\0") . str_pad('base-index-page', $pageSize, "\0");
$walBytes = $appendFrame($walBytes, $checksumSeed, 2, 0, str_pad('siteurl from wal', $pageSize, "\0"));
$walBytes = $appendFrame($walBytes, $checksumSeed, 3, 3, str_pad('autoload index from wal', $pageSize, "\0"));
$wal = SQLiteWal::parse($walBytes, null, true);

$ready = SQLitePagerCheckpointTransactionPlan::plan(
    new SQLiteLockCoordinator(),
    'wp-import',
    $wal,
    $databaseBytes,
    $databasePath,
    'restart'
);

$blocked = SQLitePagerCheckpointTransactionPlan::plan(
    new SQLiteLockCoordinator(['theme-preview-reader' => 'shared']),
    'wp-import',
    $wal,
    $databaseBytes,
    $databasePath,
    'truncate',
    null,
    SQLiteBusyHandler::timeout(50, 10)
);

echo json_encode([
    'scenario' => 'application-wal-checkpoint-transaction',
    'applicationUse' => 'Plan copied wp_options WAL checkpoint admission with SQLite lock escalation before applying durable VFS writes, so import and repair tooling can distinguish restart/truncate-ready checkpoints from reader-blocked busy outcomes without ext/sqlite.',
    'ready' => [
        'status' => $ready['status'],
        'mode' => $ready['mode'],
        'lockSequence' => array_column($ready['lock_sequence'], 'requested'),
        'walAction' => $ready['write_plan']['wal_action'],
        'databaseBytes' => $ready['write_plan']['database_bytes'],
        'walBytes' => $ready['write_plan']['wal_bytes'],
        'operationReasons' => array_column($ready['write_plan']['operations'], 'reason'),
    ],
    'blocked' => [
        'status' => $blocked['status'],
        'canCheckpoint' => $blocked['can_checkpoint'],
        'reason' => $blocked['reason'],
        'blockingConnection' => $blocked['lock_sequence'][3]['blocking'][0]['connection'],
        'blockingReason' => $blocked['lock_sequence'][3]['blocking'][0]['reason'],
    ],
    'dependencies' => $ready['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
