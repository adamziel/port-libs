<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next161.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$database = [
    1 => $page('next161 dirty schema page after interrupted import'),
    2 => $page('next161 dirty wp_options root after interrupted import'),
    3 => $page('next161 dirty active_plugins after interrupted import'),
    4 => $page('next161 dirty autoload index after interrupted import'),
    5 => $page('next161 dirty cron option after interrupted import'),
];
$hot = [
    2 => $page('next161 hot journal clean wp_options root'),
    4 => $page('next161 hot journal clean autoload index'),
];
$savepointBefore = [
    3 => $page('next161 savepoint before active_plugins retry'),
    5 => $page('next161 savepoint before cron retry'),
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
    [1, 0, 'next161 current wal schema draft'],
    [2, 5, 'next161 current wal wp_options commit'],
    [4, 0, 'next161 current wal autoload draft'],
    [5, 5, 'next161 current wal cron commit'],
], 161, 0x16100101, 0x16100102);
$nextWalBytes = $makeWalBytes([
    [3, 0, 'next161 next wal active_plugins retry draft'],
    [5, 5, 'next161 next wal cron commit'],
], 162, 0x16200101, 0x16200102);

$rolledBack = $database;
$rolledBack[2] = $hot[2];
$rolledBack[4] = $hot[4];
$rolledBack[3] = $savepointBefore[3];
$rolledBack[5] = $savepointBefore[5];
ksort($rolledBack, SORT_NUMERIC);
$rolledBackBytes = implode('', $rolledBack);
$sourceId = 'wal-hot-journal-savepoint-checkpoint-next161:current:' . substr(hash('sha256', $databasePath . '|plugin-import-next161|restart|4|' . $currentWalBytes . '|' . $rolledBackBytes), 0, 24);

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next161Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next161',
    $hot,
    $savepointBefore,
    SQLiteWal::parse($currentWalBytes, $pageSize, true),
    $currentWalBytes,
    SQLiteWal::parse($nextWalBytes, $pageSize, true),
    $nextWalBytes,
    [
        1 => ['image' => $page('next161 current wal schema draft'), 'source_id' => $sourceId, 'epoch' => 162, 'label' => 'schema cache current'],
        2 => ['image' => $page('next161 current wal wp_options commit'), 'source_id' => 'old-source-token', 'epoch' => 162, 'label' => 'wp_options stale token'],
        3 => ['image' => $savepointBefore[3], 'source_id' => $sourceId, 'epoch' => 161, 'label' => 'active_plugins stale epoch'],
        4 => ['image' => $page('next161 stale autoload cache image'), 'source_id' => $sourceId, 'epoch' => 162, 'label' => 'autoload stale image'],
        5 => ['image' => $page('next161 current wal cron commit'), 'source_id' => $sourceId, 'epoch' => 162, 'dirty' => true, 'label' => 'cron dirty failed savepoint'],
    ],
    [1, 2, 3, 4, 5],
    'restart',
    4,
    161,
);

echo json_encode([
    'status' => $plan['status'],
    'checkpoint_action' => $plan['current_durable']['wal_action'],
    'retained_cache_page_numbers' => $plan['retained_cache_page_numbers'],
    'invalidated_cache_page_numbers' => $plan['invalidated_cache_page_numbers'],
    'requires_reader_reopen' => $plan['requires_reader_reopen'],
    'current_sources' => $plan['current_sources'],
    'checkpoint_sources' => $plan['checkpoint_sources'],
    'next_sources' => $plan['next_sources'],
], JSON_PRETTY_PRINT) . PHP_EOL;
