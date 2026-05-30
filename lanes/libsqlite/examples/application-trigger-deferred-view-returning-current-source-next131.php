<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerDeferredViewReturningCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$plan = SQLiteTriggerDeferredViewReturningCurrentSourceNextPlan::execute(
    [
        ['option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'parent_name' => null, 'revision' => 1],
        ['option_name' => 'theme_mods', 'option_value' => 'old-theme', 'autoload' => 'yes', 'parent_name' => 'siteurl', 'revision' => 2],
        ['option_name' => 'plugin_cache', 'option_value' => 'old-cache', 'autoload' => 'no', 'parent_name' => 'siteurl', 'revision' => 3],
    ],
    [
        ['option_name' => 'theme_mods', 'option_value' => 'new-theme', 'autoload' => 'yes', 'parent_name' => 'missing-theme-parent', 'revision' => 4],
        ['option_name' => 'seo_settings', 'option_value' => 'enabled', 'autoload' => 'yes', 'parent_name' => 'siteurl', 'revision' => 1],
    ],
    [
        ['option_name' => 'plugin_cache', 'option_value' => 'primed', 'autoload' => 'yes', 'parent_name' => 'siteurl', 'revision' => 5],
        ['option_name' => 'rewrite_rules', 'option_value' => 'cached', 'autoload' => 'no', 'parent_name' => 'siteurl', 'revision' => 1],
    ],
    ['parent_key' => 'option_name', 'child_key' => 'parent_name', 'deferred' => true],
    [
        'name' => 'wp_autoloaded_options_131',
        'columns' => ['option_name', 'option_value', 'parent_name', 'autoload'],
        'where' => static fn (array $row): bool => ($row['autoload'] ?? null) === 'yes',
        'order_by' => 'option_name',
    ],
    [
        'option_name',
        ['expr' => 'new.option_value', 'as' => 'value'],
        ['expr' => 'old.option_value', 'as' => 'old_value'],
        ['expr' => 'event', 'as' => 'event_name'],
    ],
    [
        'key' => 'option_name',
        'trigger' => 'wp_options_view_io_update_131',
        'current_source' => 'main@cookie-131-current',
        'next_source' => 'main@cookie-131-next',
    ],
);

$summary = [
    'scenario' => 'application-trigger-deferred-view-returning-current-source-next131',
    'applicationUse' => 'A copied wp_options INSTEAD OF view-trigger import can drain current-source RETURNING rows, materialize the autoloaded-options view, then reject the next source when deferred parent-option references fail.',
    'status' => $plan['status'],
    'currentViewRows' => array_column($plan['current_view_rows'], 'option_name'),
    'currentReturning' => array_column(array_column($plan['current_returning_rows'], 'returning'), 'option_name'),
    'attemptedNextReturning' => array_column(array_column($plan['attempted_next_returning_rows'], 'returning'), 'option_name'),
    'visibleSource' => $plan['visible_source'],
    'deferredViolations' => $plan['deferred_violations'],
    'dependencyClosure' => 'no new support component needed; reuses native PHP row-array trigger RETURNING, view projection, and deferred foreign-key admission primitives',
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'deferred-view-returning-current-source-rolled-back'
        || $summary['currentViewRows'] !== ['seo_settings', 'siteurl', 'theme_mods']
        || $summary['currentReturning'] !== ['theme_mods', 'seo_settings']
        || count($summary['deferredViolations']) !== 1
        || $summary['visibleSource'] !== 'main@cookie-131-current'
    ) {
        fwrite(STDERR, "application-trigger-deferred-view-returning-current-source-next131 self-test failed\n");
        exit(1);
    }

    echo "application-trigger-deferred-view-returning-current-source-next131 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
