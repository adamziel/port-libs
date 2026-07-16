<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows = [
    ['key_name' => 'base_url', 'key_value' => 'https://example.test', 'load_policy' => 'yes', 'parent_name' => null, 'priority' => 0],
    ['key_name' => 'module_alpha', 'key_value' => 'enabled', 'load_policy' => 'yes', 'parent_name' => 'base_url', 'priority' => 10],
    ['key_name' => 'module_beta', 'key_value' => 'disabled', 'load_policy' => 'yes', 'parent_name' => 'base_url', 'priority' => 20],
];

$view = [
    'name' => 'app_recursive_load_policy_view',
    'source' => 'main@view-cookie-166-current',
    'trigger' => 'app_recursive_load_policy_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-166-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['key_name', 'key_value', 'load_policy', 'parent_name', 'priority'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 1 && str_starts_with((string) $row['key_name'], 'module_'),
    'order_by' => 'priority',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-166-next';
$nextView['trigger_source'] = 'main@trigger-cookie-166-next';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeStagedSourceReleaseAfterDrain(
    $rows,
    [['root_name' => 'base_url']],
    [['root_name' => 'audit:current:base_url:module_alpha']],
    $view,
    $nextView,
    ['key_name', ['expr' => 'root', 'as' => 'root_name'], ['expr' => 'trigger_source', 'as' => 'trigger_cookie']],
    [
        'release_next_source' => true,
        'savepoint' => 'app_recursive_view_next166',
        'current_generation' => 'app-import-current-166',
        'next_generation' => 'app-import-next-166',
        'trigger_child_prefix' => 'module_generated',
    ],
);

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (($summary['status'] ?? null) !== 'trigger-recursive-view-returning-current-drain-before-next-source-next166') {
        fwrite(STDERR, "unexpected recursive view RETURNING drain status\n");
        exit(1);
    }
    if (($summary['returning_drain']['next_after_current_drain'] ?? null) !== true) {
        fwrite(STDERR, "next-source RETURNING was admitted before current RETURNING drained\n");
        exit(1);
    }
    if (($summary['returning_drain']['next_visible_count'] ?? 0) !== 1) {
        fwrite(STDERR, "released next source did not expose the trigger-generated seed row\n");
        exit(1);
    }
    echo "application-trigger-recursive-view-returning-current-source-next166 self-test passed\n";
}

return [
    'scenario' => 'application-trigger-recursive-view-returning-current-source-next166',
    'applicationUse' => 'Copied app_settings imports through recursive INSTEAD OF view triggers drain current RETURNING rows before trigger-generated audit rows seed a released next view source.',
    'dependencyClosure' => 'no new support component needed; reuses native PHP recursive view RETURNING barriers and trigger row projection',
    'summary' => [
        'status' => $summary['status'],
        'currentVisible' => $summary['returning_drain']['current_visible_count'],
        'nextVisible' => $summary['returning_drain']['next_visible_count'],
        'nextAfterCurrentDrain' => $summary['returning_drain']['next_after_current_drain'],
        'visibleReturningKeys' => $summary['returning_drain']['visible_keys'],
    ],
];
