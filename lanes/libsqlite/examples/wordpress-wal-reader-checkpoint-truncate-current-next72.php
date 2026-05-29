<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('wp72-schema-before') . $page('wp72-siteurl-before') . $page('wp72-autoload-index-before') . $page('wp72-active-plugins-before');
$salt1 = 0x72aa1001;
$salt2 = 0x72aa1002;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 72, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [2, 0, $page('wp72-siteurl-during-import')],
    [3, 0, $page('wp72-autoload-index-during-import')],
    [4, 4, $page('wp72-active-plugins-final')],
] as [$pageNumber, $commitPageCount, $image]) {
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$plan = SQLiteWal::parse($walBytes, null, true)->checkpointTruncateCurrentNext($databaseBytes, [2, 3, 4]);

if (($argv[1] ?? '') === '--self-test') {
    foreach ([
        'status' => 'truncate-checkpoint-drained-reader-next-database',
        'wal_action' => 'truncate_wal',
        'wal_bytes_length' => 0,
        'images_match' => true,
    ] as $key => $expected) {
        if ($plan[$key] !== $expected) {
            throw new RuntimeException("Unexpected {$key} in WAL truncate smoke");
        }
    }
    echo "wordpress-wal-reader-checkpoint-truncate-current-next72 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'wal_action' => $plan['wal_action'],
    'wal_bytes_length' => $plan['wal_bytes_length'],
    'current_sources' => $plan['current_sources'],
    'next_sources' => $plan['next_sources'],
    'images_match' => $plan['images_match'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
