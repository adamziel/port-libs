<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = implode('', [
    $page('wp schema dirty before hot journal recovery'),
    $page('wp_options dirty before hot journal recovery'),
    $page('active_plugins dirty during failed savepoint'),
]);
$hotJournal = [2 => $page('wp_options clean page from hot journal')];
$savepointBefore = [3 => $page('active_plugins before failed savepoint')];

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

$walBytes = $makeWalBytes([
    [1, 0, 'wp schema draft in wal'],
    [3, 3, 'active_plugins committed in wal'],
], 170, 0x17000101, 0x17000102);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$generation = [
    'checkpoint_sequence' => $wal->header->checkpointSequence,
    'salt' => [$wal->header->salt1, $wal->header->salt2],
    'frame_count' => $wal->frameCount(),
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::planHotJournalCheckpointSource(
    '/srv/www/wp-content/database/wp.sqlite',
    $databaseBytes,
    $pageSize,
    'plugin-import',
    $hotJournal,
    $savepointBefore,
    $wal,
    $walBytes,
    [
        1 => ['image' => $page('wp schema draft in wal'), 'checkpoint_sequence' => 170, 'salt' => $generation['salt'], 'frame_count' => 2],
        2 => ['image' => $page('wp_options clean page from hot journal'), 'checkpoint_sequence' => 170, 'salt' => $generation['salt'], 'frame_count' => 2],
        3 => ['image' => $page('active_plugins committed in wal'), 'checkpoint_sequence' => 170, 'salt' => $generation['salt'], 'frame_count' => 2],
    ],
    [1, 2, 3],
    'restart',
);

echo json_encode([
    'status' => $plan['status'],
    'walAction' => $plan['wal_action'],
    'generationChanged' => $plan['generation_changed'],
    'invalidatedCachePages' => $plan['invalidated_cache_page_numbers'],
    'requiresReaderReopen' => $plan['requires_reader_reopen'],
], JSON_PRETTY_PRINT) . PHP_EOL;
