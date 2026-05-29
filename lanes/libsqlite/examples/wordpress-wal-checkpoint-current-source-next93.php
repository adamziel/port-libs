<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$salt1 = 0x93112233;
$salt2 = 0x93445566;
$page = static fn (string $label): string => str_pad($label, $pageSize, ' ', STR_PAD_RIGHT);
$databaseBytes = $page('wp93 schema baseline')
    . $page('wp93 active_plugins baseline')
    . $page('wp93 autoload index baseline')
    . $page('wp93 plugin cache baseline')
    . $page('wp93 cron baseline');

$makeWal = static function (array $frames) use ($pageSize, $salt1, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 93, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as [$pageNumber, $commitPageCount, $image]) {
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$makeShm = static function (array $readMarks, array $readLocks, int $backfill, int $attempted) use ($pageSize, $salt1, $salt2): string {
    $pageSizeField = (1 << 24) | $pageSize;
    $header = pack('V*', 3007000, $backfill, 193, $pageSizeField, 6, 8, 1, 2, $salt1, $salt2, 5, 6);
    $marks = array_map(static fn ($value): int => $value === null ? 0xffffffff : $value, array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, null));
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");

    return $header . $header . pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);
};

$walBytes = $makeWal([
    [2, 0, $page('wp93 active_plugins current reader')],
    [3, 3, $page('wp93 autoload first commit')],
    [2, 0, $page('wp93 active_plugins after import')],
    [4, 0, $page('wp93 plugin cache draft')],
    [5, 5, $page('wp93 cron committed')],
    [4, 5, $page('wp93 plugin cache committed')],
]);
$wal = SQLiteWal::parse($walBytes, null, true);

$plan = $wal->checkpointRestartTruncateReaderCurrentSourceNext(
    $databaseBytes,
    $walBytes,
    SQLiteShmIndex::parse($makeShm([0, 2, null, null, null], [false, true, false, false, false], 1, 4)),
    SQLiteShmIndex::parse($makeShm([0, 2, 6, null, null], [false, true, true, false, false], 1, 5)),
    SQLiteShmIndex::parse($makeShm([0, null, 6, null, null], [false, false, true, false, false], 2, 6)),
    SQLiteShmIndex::parse($makeShm([0, null, null, null, null], [false, false, false, false, false], 6, 6)),
    [2, 3, 4, 5],
    'restart'
);

echo json_encode([
    'scenario' => 'wordpress-wal-checkpoint-current-source-next93',
    'wordpressUse' => 'Report copied wp_options WAL checkpoint source transitions so import tooling keeps an active reader on the verified current sidecar while later readers use checkpointed database pages and final readers use the reset WAL generation without ext/sqlite.',
    'status' => $plan['status'],
    'currentSource' => $plan['current_source_names_next93'],
    'nextSource' => $plan['next_source_names_next93'],
    'finalSource' => $plan['final_source_names_next93'],
    'sourceGeneration' => $plan['source_generation'],
    'transitions' => $plan['current_to_final_source_transition'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
