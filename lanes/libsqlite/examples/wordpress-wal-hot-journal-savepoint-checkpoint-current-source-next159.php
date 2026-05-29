<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next159.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$dirty = [
    1 => $page('next159 dirty sqlite schema after crashed import'),
    2 => $page('next159 dirty wp_options root after crashed import'),
    3 => $page('next159 dirty active_plugins after crashed import'),
    4 => $page('next159 dirty autoload index after crashed import'),
    5 => $page('next159 dirty transient rows after crashed import'),
    6 => $page('next159 dirty cron array after crashed import'),
];
$clean = [
    2 => $page('next159 clean wp_options root before crashed import'),
    4 => $page('next159 clean autoload index before crashed import'),
    6 => $page('next159 clean cron array before crashed import'),
];
$databaseBytes = implode('', $dirty);

$makeWalBytes = static function (array $frames, int $checkpoint, int $salt1, int $salt2) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
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

$currentWalBytes = $makeWalBytes([
    [1, 0, 'next159 current wal schema draft'],
    [2, 6, 'next159 current wal wp_options commit'],
    [3, 0, 'next159 current wal active_plugins draft'],
    [4, 6, 'next159 current wal autoload commit'],
    [5, 0, 'next159 current wal transient draft'],
], 159, 0x15900101, 0x15900102);
$nextWalBytes = $makeWalBytes([
    [2, 0, 'next159 next wal wp_options retry draft'],
    [5, 0, 'next159 next wal transient retry draft'],
    [6, 6, 'next159 next wal cron retry commit'],
], 160, 0x16000101, 0x16000102);

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next159Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next159',
    $clean,
    [2 => $dirty[2], 3 => $dirty[3], 4 => $dirty[4], 5 => $dirty[5], 6 => $dirty[6]],
    [
        2 => $page('next159 current savepoint wp_options draft'),
        4 => $page('next159 current savepoint autoload draft'),
        6 => $page('next159 current savepoint cron draft'),
    ],
    [
        3 => $page('next159 next savepoint active_plugins retry'),
        5 => $page('next159 next savepoint transient retry'),
    ],
    SQLiteWal::parse($currentWalBytes, $pageSize, true),
    $currentWalBytes,
    SQLiteWal::parse($nextWalBytes, $pageSize, true),
    $nextWalBytes,
    [1, 2, 3, 4, 5, 6],
    5,
    'restart',
    159,
    false,
    true,
    true,
);

echo json_encode([
    'status' => $plan['status'],
    'checkpoint_action' => $plan['checkpoint_wal_action'],
    'next_checkpoint_action' => $plan['next_checkpoint_wal_action'],
    'current_sources' => $plan['current_sources'],
    'checkpoint_sources' => $plan['checkpoint_sources'],
    'next_sources' => $plan['next_sources'],
    'operation_reasons' => $plan['operation_reasons'],
], JSON_PRETTY_PRINT) . PHP_EOL;
