<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteRecursiveUpsertConflictYieldPlan.php';

use PortLibs\LibSqlite\SQLiteRecursiveUpsertConflictYieldPlan;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'level' => 0],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://old.test/home', 'autoload' => 'yes', 'level' => 0],
];

$assignments = [
    'option_value' => static fn (array $old, array $incoming): mixed => $incoming['option_value'],
    'autoload' => static fn (array $old, array $incoming): mixed => $incoming['autoload'] ?? $old['autoload'],
    'level' => static fn (array $old, array $incoming): mixed => $incoming['level'] ?? $old['level'],
];

$plan = SQLiteRecursiveUpsertConflictYieldPlan::execute(
    $options,
    [
        ['option_id' => 101, 'option_name' => 'siteurl', 'option_value' => 'https://new.test', 'autoload' => 'yes', 'level' => 0],
        ['option_id' => 102, 'option_name' => 'fresh_plugin_flag', 'option_value' => 'enabled', 'autoload' => 'no', 'level' => 0],
    ],
    ['option_name'],
    $assignments,
    [[
        'name' => 'wp_options_au_siteurl_home',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'upsert-parent',
        'when' => ['new.option_name', '=', 'siteurl'],
        'row' => [
            'option_id' => 2,
            'option_name' => 'home',
            'option_value' => ['concat' => ['new.option_value', '/home']],
            'autoload' => 'yes',
            'level' => ['add' => ['new.level', 1]],
        ],
        'values' => ['source' => 'new.option_name', 'target' => 'home'],
    ]],
);

$summary = [
    'changes' => $plan['changes'],
    'optionNames' => array_column($plan['rows'], 'option_name'),
    'optionValues' => array_column($plan['rows'], 'option_value'),
    'yielded' => $plan['yielded'],
    'triggerResults' => array_column($plan['trigger_effects'], 'result'),
    'applicationUse' => 'Preview copied wp_options imports where an UPSERT conflict on siteurl recursively UPSERTs the current home row, yielding statement and trigger rows without requiring ext/sqlite.',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['changes'] !== 3 || $summary['optionValues'] !== ['https://new.test', 'https://new.test/home', 'enabled']) {
        fwrite(STDERR, "application-recursive-upsert-conflict-yield-current-next30 self-test failed\n");
        exit(1);
    }

    echo "application-recursive-upsert-conflict-yield-current-next30 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
