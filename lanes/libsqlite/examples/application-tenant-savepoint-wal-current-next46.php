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
$stack->beginTransaction('tenant_import');
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('settings_import');
$stack->recordPageImageWrite(2, $page('main-page-2-base'));
$stack->recordWalFrameWrite(3, 2);
$stack->recordWalFrameWrite(4, 3, true);

$mainWalBytes = $walBytes([
    [1, 0, 'main-network-schema-commit'],
    [2, 3, 'main-settings-before-plugin'],
    [2, 0, 'main-plugin-settings-draft'],
    [3, 3, 'main-plugin-cache-commit'],
]);

$plan = SQLiteTenantSavepointWalPlan::rollbackToAcrossTenants([[
    'tenant_id' => 1,
    'database_path' => '/tmp/app-main.sqlite',
    'database_bytes' => $page('main-page-1-base') . $page('main-page-2-base') . $page('main-page-3-base'),
    'wal' => SQLiteWal::parse($mainWalBytes, $pageSize, true),
    'wal_bytes' => $mainWalBytes,
    'savepoints' => $stack,
    'savepoint' => 'settings_import',
    'page_numbers' => [1, 2, 3],
]], $pageSize);

echo json_encode([
    'scenario' => 'application tenant savepoint wal current next46',
    'status' => $plan['status'],
    'tenant_count' => $plan['tenant_count'],
    'rolled_back_tenant_count' => $plan['rolled_back_tenant_count'],
    'total_restored_pages' => $plan['total_restored_pages'],
    'total_discarded_wal_frames' => $plan['total_discarded_wal_frames'],
    'current_reader_matrix' => $plan['current_reader_matrix'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT) . PHP_EOL;
