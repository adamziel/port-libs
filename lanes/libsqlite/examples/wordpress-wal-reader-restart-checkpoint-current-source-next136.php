<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalReaderRestartCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalReaderRestartCheckpointCurrentSourceNextPlan;

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp schema base')
    . $page('wp option base')
    . $page('wp autoload base')
    . $page('wp cron base')
    . $page('wp transient base')
    . $page('wp rewrite base');

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
    [3, 6, 'wp autoload current reader commit'],
    [4, 0, 'wp cron current reader draft'],
    [5, 6, 'wp transient current reader commit'],
], 136, 0x13600101, 0x13600102);
$firstRestartWalBytes = $makeWal([
    [2, 0, 'wp option first restart draft'],
    [4, 6, 'wp cron first restart commit'],
    [6, 6, 'wp rewrite first restart tail'],
], 137, 0x13600102, 0x13600103);
$secondRestartWalBytes = $makeWal([
    [2, 0, 'wp option second restart draft'],
    [5, 0, 'wp transient second restart draft'],
    [6, 6, 'wp rewrite second restart commit'],
    [4, 6, 'wp cron second restart tail'],
], 138, 0x13600103, 0x13600104);

$plan = SQLiteWalReaderRestartCheckpointCurrentSourceNextPlan::plan(
    $databasePath,
    SQLiteWal::parse($currentWalBytes, $pageSize, true),
    $currentWalBytes,
    $databaseBytes,
    $firstRestartWalBytes,
    $secondRestartWalBytes,
    [1, 2, 3, 4, 5, 6],
    2
);

if (($argv[1] ?? '') === '--self-test') {
    if ($plan['status'] !== 'wal-reader-restart-checkpoint-current-source-next136') {
        throw new RuntimeException('unexpected WAL reader consecutive restart status');
    }
    if ($plan['current_reader_preserved_by_source_handle'] !== true || $plan['changed_page_numbers'] !== [2, 4, 5, 6]) {
        throw new RuntimeException('current WordPress reader was not protected across consecutive restart checkpoints');
    }
    if ($plan['rows'][1]['current_label'] !== 'wp option current reader draft' || $plan['rows'][1]['second_restart_label'] !== 'wp option second restart draft') {
        throw new RuntimeException('wp_options current and second restart generations were not separated');
    }

    echo "wordpress-wal-reader-restart-checkpoint-current-source-next136 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'current_reader_end_frame' => $plan['current_reader_end_frame'],
    'changed_page_numbers' => $plan['changed_page_numbers'],
    'current_sources' => $plan['current_sources'],
    'second_restart_sources' => $plan['second_restart_sources'],
    'source_transitions' => $plan['source_transitions'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
