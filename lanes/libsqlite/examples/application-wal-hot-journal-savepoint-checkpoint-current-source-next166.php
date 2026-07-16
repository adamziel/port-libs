<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next166.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$database = [
    1 => $page('next166 dirty schema page after plugin import'),
    2 => $page('next166 dirty wp_options root page'),
    3 => $page('next166 dirty active_plugins payload'),
    4 => $page('next166 dirty autoload index page'),
    5 => $page('next166 dirty cron option page'),
    6 => $page('next166 dirty transient timeout page'),
];
$hot = [
    2 => $page('next166 hot journal clean wp_options root'),
    4 => $page('next166 hot journal clean autoload index'),
];
$before = [
    3 => $page('next166 savepoint before active_plugins retry'),
    5 => $page('next166 savepoint before cron retry'),
];
$databaseBytes = implode('', $database);

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
    [1, 0, 'next166 current wal schema draft'],
    [2, 6, 'next166 current wal wp_options commit'],
    [4, 0, 'next166 current wal autoload draft'],
    [5, 6, 'next166 current wal cron commit'],
    [6, 6, 'next166 current wal transient timeout commit'],
], 166, 0x16600101, 0x16600102);
$nextWalBytes = $makeWalBytes([
    [3, 0, 'next166 next wal active_plugins retry draft'],
    [5, 6, 'next166 next wal cron commit'],
    [6, 6, 'next166 next wal transient timeout commit'],
], 167, 0x16700101, 0x16700102);

$rolledBack = $database;
$rolledBack[2] = $hot[2];
$rolledBack[4] = $hot[4];
$rolledBack[3] = $before[3];
$rolledBack[5] = $before[5];
ksort($rolledBack, SORT_NUMERIC);
$rolledBackBytes = implode('', $rolledBack);
$sourceId = 'wal-hot-journal-savepoint-checkpoint-next161:current:' . substr(hash('sha256', $databasePath . '|plugin-import-inner-next166|restart|5|' . $currentWalBytes . '|' . $rolledBackBytes), 0, 24);

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::planSourceTokenHandoff(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-inner-next166',
    'plugin-import-outer-next166',
    $hot,
    $before,
    SQLiteWal::parse($currentWalBytes, $pageSize, true),
    $currentWalBytes,
    SQLiteWal::parse($nextWalBytes, $pageSize, true),
    $nextWalBytes,
    [
        1 => ['image' => $page('next166 current wal schema draft'), 'source_id' => $sourceId, 'epoch' => 167, 'label' => 'schema cache current'],
        2 => ['image' => $page('next166 current wal wp_options commit'), 'source_id' => 'old-source-token', 'epoch' => 167, 'label' => 'wp_options stale token'],
        3 => ['image' => $before[3], 'source_id' => $sourceId, 'epoch' => 166, 'label' => 'active_plugins stale epoch'],
        4 => ['image' => $page('next166 stale autoload cache image'), 'source_id' => $sourceId, 'epoch' => 167, 'label' => 'autoload stale image'],
        5 => ['image' => $page('next166 current wal cron commit'), 'source_id' => $sourceId, 'epoch' => 167, 'dirty' => true, 'label' => 'cron dirty failed savepoint'],
        6 => ['image' => $page('next166 current wal transient timeout commit'), 'source_id' => $sourceId, 'epoch' => 167, 'label' => 'transient timeout current'],
    ],
    [1, 2, 3, 4, 5, 6],
    ['plugin-import-inner-next166' => [3, 5]],
    'restart',
    5,
    166,
);

echo json_encode([
    'status' => $plan['status'],
    'release_complete' => $plan['release_complete'],
    'released_inner_page_numbers' => $plan['released_inner_page_numbers'],
    'retained_cache_page_numbers' => $plan['retained_cache_page_numbers'],
    'invalidated_cache_page_numbers' => $plan['invalidated_cache_page_numbers'],
    'writer_barrier_page_order' => $plan['writer_barrier_page_order'],
], JSON_PRETTY_PRINT) . PHP_EOL;
