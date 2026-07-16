<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLitePragmaIntegrityPartialIndexCurrentSourceNext;

$rows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'load_policy' => 'yes', 'tenant_id' => 1],
    ['setting_id' => 2, 'key_name' => 'home', 'load_policy' => 'yes', 'tenant_id' => 1],
    ['setting_id' => 3, 'key_name' => '_cache_feed', 'load_policy' => 'no', 'tenant_id' => 1],
    ['setting_id' => 4, 'key_name' => 'module_cache', 'load_policy' => null, 'tenant_id' => 1],
];
$indexEntries = [
    ['rowid' => 1, 'load_policy' => 'yes', 'key_name' => 'base_url'],
    ['rowid' => 3, 'load_policy' => 'no', 'key_name' => '_cache_feed'],
    ['rowid' => 99, 'load_policy' => 'yes', 'key_name' => 'deleted_setting'],
];
$predicate = new SQLiteIndexPredicate('load_policy', SQLiteIndexPredicate::EQUALS, 'yes');

$integrity = SQLitePragmaIntegrityPartialIndexCurrentSourceNext::page(
    $rows,
    $indexEntries,
    $predicate,
    ['load_policy', 'key_name'],
    0,
    126,
    'app_settings',
    'app_settings_load_policy_yes',
    'PRAGMA integrity_check',
);
$quick = SQLitePragmaIntegrityPartialIndexCurrentSourceNext::page(
    $rows,
    $indexEntries,
    $predicate,
    ['load_policy', 'key_name'],
    0,
    126,
    'app_settings',
    'app_settings_load_policy_yes',
    'PRAGMA quick_check',
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $integrity['status'] !== 'blocked'
        || $integrity['current']['missing'] !== 1
        || $integrity['current']['stale'] !== 1
        || $integrity['current']['orphan'] !== 1
        || $quick['current']['stale'] !== 0
    ) {
        fwrite(STDERR, "application-pragma-integrity-partial-index-current-source-next self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-integrity-partial-index-current-source-next self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'application-pragma-integrity-partial-index-current-source-next',
    'applicationUse' => 'Validate copied app_settings partial indexes before import cleanup so load_policy=yes lookups do not miss current rows or retain stale/deleted setting entries.',
    'integrity_status' => $integrity['status'],
    'integrity_current' => $integrity['current'],
    'quick_status' => $quick['status'],
    'quick_current' => $quick['current'],
    'source_id' => $integrity['source_id'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
