<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteRecursiveUpsertConflictYieldPlan.php';

use PortLibs\LibSqlite\SQLiteRecursiveUpsertConflictYieldPlan;

$options = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'level' => 0],
    ['setting_id' => 2, 'key_name' => 'landing_page', 'key_value' => 'https://old.test/landing_page', 'load_policy' => 'yes', 'level' => 0],
];

$assignments = [
    'key_value' => static fn (array $old, array $incoming): mixed => $incoming['key_value'],
    'load_policy' => static fn (array $old, array $incoming): mixed => $incoming['load_policy'] ?? $old['load_policy'],
    'level' => static fn (array $old, array $incoming): mixed => $incoming['level'] ?? $old['level'],
];

$plan = SQLiteRecursiveUpsertConflictYieldPlan::execute(
    $options,
    [
        ['setting_id' => 101, 'key_name' => 'base_url', 'key_value' => 'https://new.test', 'load_policy' => 'yes', 'level' => 0],
        ['setting_id' => 102, 'key_name' => 'fresh_module_flag', 'key_value' => 'enabled', 'load_policy' => 'no', 'level' => 0],
    ],
    ['key_name'],
    $assignments,
    [[
        'name' => 'app_settings_au_base_url_home',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'upsert-parent',
        'when' => ['new.key_name', '=', 'base_url'],
        'row' => [
            'setting_id' => 2,
            'key_name' => 'landing_page',
            'key_value' => ['concat' => ['new.key_value', '/landing_page']],
            'load_policy' => 'yes',
            'level' => ['add' => ['new.level', 1]],
        ],
        'values' => ['source' => 'new.key_name', 'target' => 'landing_page'],
    ]],
);

$summary = [
    'changes' => $plan['changes'],
    'optionNames' => array_column($plan['rows'], 'key_name'),
    'optionValues' => array_column($plan['rows'], 'key_value'),
    'yielded' => $plan['yielded'],
    'triggerResults' => array_column($plan['trigger_effects'], 'result'),
    'applicationUse' => 'Preview copied app_settings imports where an UPSERT conflict on base_url recursively UPSERTs the current landing_page row, yielding statement and trigger rows without requiring ext/sqlite.',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['changes'] !== 3 || $summary['optionValues'] !== ['https://new.test', 'https://new.test/landing_page', 'enabled']) {
        fwrite(STDERR, "application-recursive-upsert-conflict-yield-current-next30 self-test failed\n");
        exit(1);
    }

    echo "application-recursive-upsert-conflict-yield-current-next30 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
