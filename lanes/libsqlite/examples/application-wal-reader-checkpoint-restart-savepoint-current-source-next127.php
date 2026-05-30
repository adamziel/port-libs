<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';
require_once __DIR__ . '/../src/SQLiteWalAppendPlan.php';
require_once __DIR__ . '/../src/SQLiteWalReaderCheckpointRestartSavepointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalReaderCheckpointRestartSavepointCurrentSourceNextPlan;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp schema base')
    . $page('wp active_plugins base')
    . $page('wp autoload base')
    . $page('wp cron base')
    . $page('wp transient base')
    . $page('wp rewrite_rules base');

$makeWalBytes = static function (array $frames) use ($pageSize, $page): string {
    $salt1 = 0x12712701;
    $salt2 = 0x12712702;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 127, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$walBytes = $makeWalBytes([
    [2, 0, 'wp active_plugins retained draft'],
    [3, 6, 'wp autoload retained commit'],
    [4, 0, 'wp cron stale draft'],
    [5, 6, 'wp transient stale commit'],
    [2, 6, 'wp active_plugins stale tail'],
    [6, 6, 'wp rewrite_rules stale commit'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import-next127');
$stack->recordWalFrameWrite(1, 2);
$stack->recordWalFrameWrite(2, 3, true);
$stack->savepoint('plugin-settings-next127');
$stack->recordWalFrameWrite(3, 4);
$stack->recordWalFrameWrite(4, 5, true);
$stack->recordWalFrameWrite(5, 2, true);
$stack->recordWalFrameWrite(6, 6, true);

$plan = SQLiteWalReaderCheckpointRestartSavepointCurrentSourceNextPlan::plan(
    $stack,
    'plugin-settings-next127',
    $wal,
    $walBytes,
    $walBytes,
    $databaseBytes,
    '/srv/www/wp-content/database/.ht.sqlite',
    [[
        'pages' => [
            2 => $page('wp active_plugins restarted generation'),
            5 => $page('wp transient restarted generation'),
            6 => $page('wp rewrite_rules restarted generation'),
        ],
        'database_page_count' => 6,
    ]],
    [2, 5, 6],
    6
);

echo json_encode([
    'status' => $plan['status'],
    'reader_release_unblocked_restart' => $plan['reader_release_unblocked_restart'],
    'rollback_changed_pages' => $plan['rollback_changed_pages'],
    'released_next_sources' => $plan['released_next_sources'],
    'operation_sequence' => array_column($plan['operations'], 'op'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
