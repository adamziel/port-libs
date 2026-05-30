<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowRowValueUpsertCurrentSourcePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOptions = [
    ['option_name' => 'siteurl', 'version' => 1, 'priority' => 10, 'autoload' => 'yes', 'option_value' => 'https://old.example'],
    ['option_name' => 'home', 'version' => 1, 'priority' => 5, 'autoload' => 'yes', 'option_value' => 'https://old.example'],
    ['option_name' => 'blogname', 'version' => 2, 'priority' => 20, 'autoload' => 'no', 'option_value' => 'Old Site'],
];

$importRows = [
    ['option_name' => 'siteurl', 'version' => 1, 'priority' => 12, 'autoload' => 'yes', 'option_value' => 'https://first.example'],
    ['option_name' => 'siteurl', 'version' => 1, 'priority' => 11, 'autoload' => 'yes', 'option_value' => 'https://stale.example'],
    ['option_name' => 'siteurl', 'version' => 1, 'priority' => 18, 'autoload' => 'yes', 'option_value' => 'https://final.example'],
    ['option_name' => 'plugin_queue', 'version' => 1, 'priority' => 4, 'autoload' => 'no', 'option_value' => 'enabled'],
    ['option_name' => 'home', 'version' => 2, 'priority' => 4, 'autoload' => 'yes', 'option_value' => 'https://home-v2.example'],
];

$plan = SQLiteWindowRowValueUpsertCurrentSourcePlan::execute(
    $currentOptions,
    $importRows,
    ['option_name'],
    ['version', 'priority'],
    '>',
    1,
    1,
);

$summary = [
    'scenario' => 'application-window-rowvalue-upsert-current-source-next108',
    'applicationUse' => 'Copied wp_options imports can apply UPSERT DO UPDATE only when an incoming row-value tuple is newer than the statement current row, then feed accepted RETURNING rows into a bounded window frame for import diagnostics without ext/sqlite.',
    'changes' => $plan['changes'],
    'returningOptionValues' => array_column($plan['returning_rows'], 'option_value'),
    'decisionActions' => array_column($plan['decisions'], 'action'),
    'windowPrioritySums' => array_column($plan['window_rows'], 'frame_priority_sum'),
    'finalSiteurl' => $plan['after'][0]['option_value'],
    'dependency' => 'native PHP row-value UPSERT current-source and window frame execution; no ext/sqlite required',
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    assert($summary['changes'] === 4);
    assert($summary['returningOptionValues'] === ['https://first.example', 'https://final.example', 'enabled', 'https://home-v2.example']);
    assert($summary['decisionActions'] === ['update', 'skip', 'update', 'insert', 'update']);
    assert($summary['windowPrioritySums'] === [30, 34, 26, 8]);
    assert($summary['finalSiteurl'] === 'https://final.example');
    echo "application-window-rowvalue-upsert-current-source-next108 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
