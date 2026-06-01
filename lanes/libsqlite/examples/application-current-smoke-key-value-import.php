<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCurrentSmokePlan;

$currentRows = [
    ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://old.example', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'home', 'key_value' => 'https://old.example', 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => 'rewrite_rules', 'key_value' => 'a:0:{}', 'load_policy' => 'no'],
];

$stagedRows = [
    ['key_name' => 'siteurl', 'key_value' => 'https://current.example', 'load_policy' => 'yes'],
    ['key_name' => 'tenant_public', 'key_value' => '1', 'load_policy' => 'yes'],
    ['key_name' => 'home', 'key_value' => 'https://duplicate.example', 'load_policy' => 'yes', 'setting_id' => 8],
    ['key_name' => 'stylesheet', 'key_value' => 'twentytwentyfive', 'load_policy' => 'yes'],
];

$smoke = SQLiteCurrentSmokePlan::keyValueImport($currentRows, $stagedRows, [
    'fail_on_error' => false,
    'statement_prefix' => 'app_current_smoke',
]);

if (in_array('--self-test', $argv, true)) {
    if ($smoke['status'] !== 'partial_errors' || $smoke['applied_count'] !== 3 || $smoke['error_codes'] !== ['sqlite_constraint']) {
        fwrite(STDERR, "application-current-smoke-key-value-import self-test failed\n");
        exit(1);
    }

    echo "application-current-smoke-key-value-import self-test passed\n";
    exit(0);
}

echo json_encode([
    'applicationUse' => 'Smoke the current pure-PHP SQLite key-value settings import path without ext/sqlite: BEGIN IMMEDIATE admission, per-row yields, SQLite-style error shaping, and statement-only rollback for a duplicate key_name.',
    'status' => $smoke['status'],
    'appliedCount' => $smoke['applied_count'],
    'errorCodes' => $smoke['error_codes'],
    'finalKeyNames' => $smoke['final_key_names'],
    'dirtyPages' => $smoke['dirty_pages'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
