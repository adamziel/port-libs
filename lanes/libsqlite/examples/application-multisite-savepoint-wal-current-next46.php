<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteTenantSavepointWalPlan;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$walBytes = static function (array $frames) use ($pageSize, $page): string {
    $salt1 = 0x46464646;
    $salt2 = 0x57575757;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 46, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as [$pageNumber, $commit, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('network_import');
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin_import');
$stack->recordPageImageWrite(2, $page('main-page-2-base'));
$stack->recordWalFrameWrite(3, 2);
$stack->recordWalFrameWrite(4, 3, true);

$mainWalBytes = $walBytes([
    [1, 0, 'main-network-schema-commit'],
    [2, 3, 'main-options-before-plugin'],
    [2, 0, 'main-plugin-options-draft'],
    [3, 3, 'main-plugin-transient-commit'],
]);

$plan = SQLiteTenantSavepointWalPlan::rollbackToAcrossSites([[
    'blog_id' => 1,
    'database_path' => 'wp-content/database/main.sqlite',
    'database_bytes' => $page('main-page-1-base') . $page('main-page-2-base') . $page('main-page-3-base'),
    'wal' => SQLiteWal::parse($mainWalBytes, $pageSize, true),
    'wal_bytes' => $mainWalBytes,
    'savepoints' => $stack,
    'savepoint' => 'plugin_import',
    'page_numbers' => [1, 2, 3],
]], $pageSize);

echo json_encode([
    'scenario' => 'application multisite savepoint wal current next46',
    'status' => $plan['status'],
    'site_count' => $plan['site_count'],
    'rolled_back_site_count' => $plan['rolled_back_site_count'],
    'total_restored_pages' => $plan['total_restored_pages'],
    'total_discarded_wal_frames' => $plan['total_discarded_wal_frames'],
    'current_reader_matrix' => $plan['current_reader_matrix'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT) . PHP_EOL;
