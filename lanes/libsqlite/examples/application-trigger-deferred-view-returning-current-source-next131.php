<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerDeferredViewReturningCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$plan = SQLiteTriggerDeferredViewReturningCurrentSourceNextPlan::execute(
    [
        ['key_name' => 'siteurl', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'parent_key_name' => null, 'revision' => 1],
        ['key_name' => 'theme_mods', 'key_value' => 'old-theme', 'load_policy' => 'yes', 'parent_key_name' => 'siteurl', 'revision' => 2],
        ['key_name' => 'plugin_cache', 'key_value' => 'old-cache', 'load_policy' => 'no', 'parent_key_name' => 'siteurl', 'revision' => 3],
    ],
    [
        ['key_name' => 'theme_mods', 'key_value' => 'new-theme', 'load_policy' => 'yes', 'parent_key_name' => 'missing-theme-parent', 'revision' => 4],
        ['key_name' => 'seo_settings', 'key_value' => 'enabled', 'load_policy' => 'yes', 'parent_key_name' => 'siteurl', 'revision' => 1],
    ],
    [
        ['key_name' => 'plugin_cache', 'key_value' => 'primed', 'load_policy' => 'yes', 'parent_key_name' => 'siteurl', 'revision' => 5],
        ['key_name' => 'rewrite_rules', 'key_value' => 'cached', 'load_policy' => 'no', 'parent_key_name' => 'siteurl', 'revision' => 1],
    ],
    ['parent_key' => 'key_name', 'child_key' => 'parent_key_name', 'deferred' => true],
    [
        'name' => 'app_loadable_settings_131',
        'columns' => ['key_name', 'key_value', 'parent_key_name', 'load_policy'],
        'where' => static fn (array $row): bool => ($row['load_policy'] ?? null) === 'yes',
        'order_by' => 'key_name',
    ],
    [
        'key_name',
        ['expr' => 'new.key_value', 'as' => 'value'],
        ['expr' => 'old.key_value', 'as' => 'old_value'],
        ['expr' => 'event', 'as' => 'event_name'],
    ],
    [
        'key' => 'key_name',
        'trigger' => 'app_settings_view_io_update_131',
        'current_source' => 'main@cookie-131-current',
        'next_source' => 'main@cookie-131-next',
    ],
);

$summary = [
    'scenario' => 'application-trigger-deferred-view-returning-current-source-next131',
    'applicationUse' => 'A copied app_settings INSTEAD OF view-trigger import can drain current-source RETURNING rows, materialize the eager settings view, then reject the next source when deferred parent-setting references fail.',
    'status' => $plan['status'],
    'currentViewRows' => array_column($plan['current_view_rows'], 'key_name'),
    'currentReturning' => array_column(array_column($plan['current_returning_rows'], 'returning'), 'key_name'),
    'attemptedNextReturning' => array_column(array_column($plan['attempted_next_returning_rows'], 'returning'), 'key_name'),
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
