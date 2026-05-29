<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalAppendPlan.php';
require_once __DIR__ . '/../src/SQLiteWalCheckpointTruncateReaderCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointTruncateReaderCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp schema base')
    . $page('wp option base')
    . $page('wp autoload base')
    . $page('wp transient base')
    . $page('wp plugin base');

$salt1 = 0x13413401;
$salt2 = 0x13413402;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 134, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [1, 0, 'wp schema draft current'],
    [2, 5, 'wp option commit current'],
    [3, 0, 'wp autoload draft current'],
    [4, 5, 'wp transient commit current'],
] as [$pageNumber, $commitPageCount, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$plan = SQLiteWalCheckpointTruncateReaderCurrentSourceNextPlan::plan(
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    $walBytes,
    $databaseBytes,
    '/srv/www/wp-content/database/wp-next134.sqlite',
    [[
        'pages' => [
            2 => $page('wp option commit next generation'),
            5 => $page('wp plugin commit next generation'),
        ],
        'database_page_count' => 5,
        'commit' => true,
    ]],
    [1, 2, 3, 4, 5],
    4
);

if (($argv[1] ?? '') === '--self-test') {
    if ($plan['status'] !== 'wal-checkpoint-truncate-reader-current-source-next134') {
        throw new RuntimeException('unexpected WAL checkpoint truncate current-source status');
    }
    if ($plan['truncate_removed_old_wal_sidecar'] !== true || $plan['next_reader_uses_fresh_wal_generation'] !== true) {
        throw new RuntimeException('next WordPress reader did not move to the fresh WAL generation');
    }
    if ($plan['rows'][1]['next_label'] !== 'wp option commit next generation') {
        throw new RuntimeException('next wp_options page was not read from the fresh WAL generation');
    }

    echo "wordpress-wal-checkpoint-truncate-reader-current-source-next134 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'reader_source_matches_current' => $plan['reader_source_matches_current'],
    'truncate_removed_old_wal_sidecar' => $plan['truncate_removed_old_wal_sidecar'],
    'next_reader_uses_fresh_wal_generation' => $plan['next_reader_uses_fresh_wal_generation'],
    'source_transitions' => $plan['source_transitions'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
