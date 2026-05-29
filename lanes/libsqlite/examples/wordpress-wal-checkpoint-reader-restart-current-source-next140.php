<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalCheckpointReaderRestartCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointReaderRestartCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp schema base')
    . $page('wp option base')
    . $page('wp autoload base')
    . $page('wp cron base')
    . $page('wp transient base')
    . $page('wp rewrite base')
    . $page('wp session base');

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
    [2, 0, 'wp option first reader draft'],
    [3, 7, 'wp autoload first reader commit'],
    [2, 0, 'wp option restarted reader draft'],
    [4, 0, 'wp cron restarted reader draft'],
    [5, 7, 'wp transient restarted reader commit'],
    [7, 7, 'wp session restarted reader tail'],
], 140, 0x14000101, 0x14000102);
$pathRestartWalBytes = $makeWal([
    [2, 0, 'wp option path restart draft'],
    [4, 0, 'wp cron path restart draft'],
    [6, 7, 'wp rewrite path restart commit'],
    [7, 7, 'wp session path restart tail'],
], 141, 0x14000102, 0x14000103);

$plan = SQLiteWalCheckpointReaderRestartCurrentSourceNextPlan::plan(
    $databasePath,
    SQLiteWal::parse($currentWalBytes, $pageSize, true),
    $currentWalBytes,
    $databaseBytes,
    $pathRestartWalBytes,
    [1, 2, 3, 4, 5, 6, 7],
    2,
    6
);

if (($argv[1] ?? '') === '--self-test') {
    if ($plan['status'] !== 'wal-checkpoint-reader-restart-current-source-next140') {
        throw new RuntimeException('unexpected WAL checkpoint reader restart current-source status');
    }
    if ($plan['current_restart_advanced_pages'] !== [2, 4, 5, 7]) {
        throw new RuntimeException('current-source reader restart did not advance within the original WAL source');
    }
    if ($plan['path_restart_separated_pages'] !== [2, 4, 6, 7]) {
        throw new RuntimeException('fresh path reader did not bind to the restarted WAL generation');
    }

    echo "wordpress-wal-checkpoint-reader-restart-current-source-next140 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'old_reader_end_frame' => $plan['old_reader_end_frame'],
    'restarted_current_reader_end_frame' => $plan['restarted_current_reader_end_frame'],
    'current_restart_advanced_pages' => $plan['current_restart_advanced_pages'],
    'path_restart_separated_pages' => $plan['path_restart_separated_pages'],
    'source_transitions' => $plan['source_transitions'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
