<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteScopedKeyValueWalPlan;

$pageSize = 512;
$salt1 = 0x42004200;
$salt2 = 0x20260527;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('sqlite header scoped settings before import')
    . $page('app_tenant_settings current display_name before import')
    . $page('app_tenant_2_settings current primary_url before import')
    . $page('app_tenant_3_settings current dashboard_url before import')
    . $page('app_tenant_settings current enabled_modules before import');

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 42, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([[2, 0, 'draft scoped display_name before import'], [3, 5, 'committed tenant 2 primary_url before import']] as [$pageNumber, $commit, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$plan = SQLiteScopedKeyValueWalPlan::currentNext(
    SQLiteWal::parse($walBytes, $pageSize, true),
    $databaseBytes,
    'app-data/database/scoped-settings.sqlite',
    [
        ['scope' => 'global', 'setting_id' => 1, 'key_name' => 'display_name', 'key_value' => 'Old Registry', 'load_policy' => 'yes'],
        ['scope' => 'global', 'setting_id' => 2, 'key_name' => 'enabled_modules', 'key_value' => '[]', 'load_policy' => 'yes'],
        ['scope' => 'tenant', 'tenant_id' => 2, 'setting_id' => 1, 'key_name' => 'primary_url', 'key_value' => 'https://old.example/tenant-two', 'load_policy' => 'yes'],
        ['scope' => 'tenant', 'tenant_id' => 2, 'setting_id' => 2, 'key_name' => 'scope_public', 'key_value' => '1', 'load_policy' => 'no'],
        ['scope' => 'tenant', 'tenant_id' => 3, 'setting_id' => 1, 'key_name' => 'dashboard_url', 'key_value' => 'https://old.example/tenant-three/dashboard', 'load_policy' => 'yes'],
    ],
    [
        ['scope' => 'global', 'key_name' => 'enabled_modules', 'key_value' => '["search","cache"]', 'load_policy' => 'yes'],
        ['scope' => 'tenant', 'tenant_id' => 2, 'key_name' => 'primary_url', 'key_value' => 'https://new.example/tenant-two', 'load_policy' => 'yes'],
        ['scope' => 'tenant', 'tenant_id' => 2, 'key_name' => 'route_map', 'key_value' => '{"entry":"index.php?id="}', 'load_policy' => 'no'],
        ['scope' => 'tenant', 'tenant_id' => 3, 'key_name' => 'primary_url', 'key_value' => 'https://new.example/tenant-three', 'load_policy' => 'yes'],
        ['scope' => 'global', 'key_name' => 'registration', 'key_value' => 'none', 'load_policy' => 'no'],
    ],
    range(2, 12),
);

if (in_array('--self-test', $argv, true)) {
    if (
        $plan['status'] !== 'planned'
        || $plan['reason'] !== 'application_scoped_settings_wal_commit_current_next_visibility'
        || $plan['tables'] !== ['app_tenant_2_settings', 'app_tenant_3_settings', 'app_tenant_settings']
        || $plan['append']['last_commit_frame'] !== 13
    ) {
        fwrite(STDERR, "application-scoped-settings-wal-current-next42 self-test failed\n");
        exit(1);
    }

    echo "application-scoped-settings-wal-current-next42 self-test passed\n";
    exit(0);
}

echo json_encode([
    'applicationUse' => 'Preview scoped application settings WAL current/next visibility for global and tenant rows using neutral key/value/load-policy column names.',
    'status' => $plan['status'],
    'tables' => $plan['tables'],
    'inserted' => $plan['inserted_keys'],
    'updated' => $plan['updated_keys'],
    'last_commit_frame' => $plan['append']['last_commit_frame'],
    'database_page_count' => $plan['database_page_count'],
    'next_reader_sources' => $plan['next_reader_sources'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
