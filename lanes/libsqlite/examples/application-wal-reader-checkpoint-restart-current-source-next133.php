<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalReaderCheckpointRestartCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalReaderCheckpointRestartCurrentSourceNextPlan;

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp schema base')
    . $page('wp option base')
    . $page('wp autoload base')
    . $page('wp cron base')
    . $page('wp transient base');

$makeWal = static function (array $frames, int $checkpoint, int $salt1, int $salt2) use ($pageSize, $page): string {
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

$currentWalBytes = $makeWal([
    [2, 0, 'wp option current reader draft'],
    [3, 5, 'wp autoload current reader commit'],
    [2, 0, 'wp option later draft'],
    [4, 5, 'wp cron checkpoint commit'],
    [2, 5, 'wp option checkpoint tail'],
], 133, 0x13300101, 0x13300102);
$nextWalBytes = $makeWal([
    [2, 0, 'wp option next generation draft'],
    [5, 5, 'wp transient next generation commit'],
    [4, 5, 'wp cron next generation tail'],
], 134, 0x13300102, 0x13300103);

$plan = SQLiteWalReaderCheckpointRestartCurrentSourceNextPlan::plan(
    $databasePath,
    SQLiteWal::parse($currentWalBytes, $pageSize, true),
    $currentWalBytes,
    $databaseBytes,
    $nextWalBytes,
    [1, 2, 3, 4, 5],
    2
);

if (($argv[1] ?? '') === '--self-test') {
    if ($plan['status'] !== 'wal-reader-checkpoint-restart-current-source-next133') {
        throw new RuntimeException('unexpected WAL reader restart status');
    }
    if ($plan['current_reader_preserved_by_source_handle'] !== true || $plan['path_reopen_would_change_current_reader'] !== true) {
        throw new RuntimeException('current Application reader was not protected from reopened WAL path bytes');
    }
    if ($plan['rows'][1]['current_label'] !== 'wp option current reader draft' || $plan['rows'][1]['next_label'] !== 'wp option next generation draft') {
        throw new RuntimeException('wp_options current/next WAL generations were not separated');
    }

    echo "application-wal-reader-checkpoint-restart-current-source-next133 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'current_reader_end_frame' => $plan['current_reader_end_frame'],
    'changed_page_numbers' => $plan['changed_page_numbers'],
    'current_sources' => $plan['current_sources'],
    'next_sources' => $plan['next_sources'],
    'source_transitions' => $plan['source_transitions'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
