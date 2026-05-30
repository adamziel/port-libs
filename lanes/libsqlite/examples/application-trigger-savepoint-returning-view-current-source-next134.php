<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteTriggerSavepointReturningViewCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerSavepointReturningViewCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'revision' => 1, 'source' => 'seed'],
    ['option_id' => 2, 'option_name' => 'theme_mods', 'option_value' => 'a:0:{}', 'autoload' => 'no', 'revision' => 2, 'source' => 'seed'],
];
$current = [
    ['option_id' => 2, 'option_name' => 'theme_mods', 'option_value' => 'broken-view-trigger', 'autoload' => 'yes', 'revision' => 3, 'source' => 'current-import'],
];
$next = [
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'option_value' => 'cached', 'autoload' => 'yes', 'revision' => 1, 'source' => 'next-import'],
];
$currentView = [
    'name' => 'wp_autoload_options_view',
    'source' => 'main@view-cookie-134-current',
    'columns' => ['option_name', 'option_value', 'autoload', 'revision'],
    'where' => static fn (array $row): bool => ($row['autoload'] ?? null) === 'yes',
    'order_by' => 'option_name',
];
$nextView = [
    'name' => 'wp_autoload_options_view',
    'source' => 'main@view-cookie-134-next',
    'columns' => ['option_name', 'option_value', 'autoload', 'revision', 'source'],
    'where' => static fn (array $row): bool => ($row['autoload'] ?? null) === 'yes',
    'order_by' => 'option_name',
];
$triggers = [
    [
        'name' => 'wp_options_view_after_update_guard',
        'phase' => 'current',
        'event' => 'update',
        'when' => ['new.option_name', '=', 'theme_mods'],
        'raise' => 'rollback',
        'reason' => 'theme view trigger rollback',
        'values' => ['name' => 'new.option_name', 'source_token' => 'source'],
    ],
];
$returning = [
    'option_name',
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'source', 'as' => 'source_token'],
    ['expr' => 'event', 'as' => 'event_name'],
];

$plan = SQLiteTriggerSavepointReturningViewCurrentSourceNextPlan::executeViewSavepointReturningRollback(
    $rows,
    $current,
    $next,
    $currentView,
    $nextView,
    $triggers,
    $returning,
    ['savepoint' => 'wp_import_view_batch', 'trigger' => 'wp_options_view_io_update_134'],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['rolled_back_to_savepoint'] === true);
    assert($plan['next_source_admitted'] === false);
    assert($plan['visible_view']['source'] === 'main@view-cookie-134-current');
    assert($plan['current_returning_rows'][0]['returning']['option_name'] === 'theme_mods');
    assert($plan['yield_stream'][0]['rolled_back_after_yield'] === true);
    assert(array_column($plan['after_savepoint'], 'option_name') === ['siteurl', 'theme_mods']);
    echo "application-trigger-savepoint-returning-view-current-source-next134 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
