<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowRowValueUpsertCurrentSourcePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOptions = [
    ['key_name' => 'base_url', 'version' => 1, 'priority' => 10, 'load_policy' => 'yes', 'key_value' => 'https://old.example'],
    ['key_name' => 'landing_url', 'version' => 1, 'priority' => 5, 'load_policy' => 'yes', 'key_value' => 'https://old.example'],
    ['key_name' => 'site_title', 'version' => 2, 'priority' => 20, 'load_policy' => 'no', 'key_value' => 'Old Site'],
];

$importRows = [
    ['key_name' => 'base_url', 'version' => 1, 'priority' => 12, 'load_policy' => 'yes', 'key_value' => 'https://first.example'],
    ['key_name' => 'base_url', 'version' => 1, 'priority' => 11, 'load_policy' => 'yes', 'key_value' => 'https://stale.example'],
    ['key_name' => 'base_url', 'version' => 1, 'priority' => 18, 'load_policy' => 'yes', 'key_value' => 'https://final.example'],
    ['key_name' => 'module_queue', 'version' => 1, 'priority' => 4, 'load_policy' => 'no', 'key_value' => 'enabled'],
    ['key_name' => 'landing_url', 'version' => 2, 'priority' => 4, 'load_policy' => 'yes', 'key_value' => 'https://landing_url-v2.example'],
];

$plan = SQLiteWindowRowValueUpsertCurrentSourcePlan::execute(
    $currentOptions,
    $importRows,
    ['key_name'],
    ['version', 'priority'],
    '>',
    1,
    1,
);

$summary = [
    'scenario' => 'application-window-rowvalue-upsert-source-neutral-defaults',
    'applicationUse' => 'Copied app_settings imports can apply UPSERT DO UPDATE only when an incoming row-value tuple is newer than the statement current row, then feed accepted RETURNING rows into a bounded window frame for import diagnostics without ext/sqlite.',
    'changes' => $plan['changes'],
    'returningKeyValues' => array_column($plan['returning_rows'], 'key_value'),
    'decisionActions' => array_column($plan['decisions'], 'action'),
    'windowPrioritySums' => array_column($plan['window_rows'], 'frame_priority_sum'),
    'finalBaseUrl' => $plan['after'][0]['key_value'],
    'dependency' => 'native PHP row-value UPSERT current-source and window frame execution; no ext/sqlite required',
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    assert($summary['changes'] === 4);
    assert($summary['returningKeyValues'] === ['https://first.example', 'https://final.example', 'enabled', 'https://landing_url-v2.example']);
    assert($summary['decisionActions'] === ['update', 'skip', 'update', 'insert', 'update']);
    assert($summary['windowPrioritySums'] === [30, 34, 26, 8]);
    assert($summary['finalBaseUrl'] === 'https://final.example');
    echo "application-window-rowvalue-upsert-source-neutral-defaults self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
