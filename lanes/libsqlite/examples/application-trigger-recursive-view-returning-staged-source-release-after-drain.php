<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows = [
    ['option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'parent_name' => null, 'priority' => 0],
    ['option_name' => 'plugin_alpha', 'option_value' => 'enabled', 'autoload' => 'yes', 'parent_name' => 'siteurl', 'priority' => 10],
    ['option_name' => 'plugin_beta', 'option_value' => 'disabled', 'autoload' => 'yes', 'parent_name' => 'siteurl', 'priority' => 20],
];

$view = [
    'name' => 'wp_recursive_autoload_view',
    'source' => 'main@view-cookie-166-current',
    'trigger' => 'wp_recursive_autoload_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-166-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['option_name', 'option_value', 'autoload', 'parent_name', 'priority'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 1 && str_starts_with((string) $row['option_name'], 'plugin_'),
    'order_by' => 'priority',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-166-next';
$nextView['trigger_source'] = 'main@trigger-cookie-166-next';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeStagedSourceReleaseAfterDrain(
    $rows,
    [['root_name' => 'siteurl']],
    [['root_name' => 'audit:current:siteurl:plugin_alpha']],
    $view,
    $nextView,
    ['option_name', ['expr' => 'root', 'as' => 'root_name'], ['expr' => 'trigger_source', 'as' => 'trigger_cookie']],
    [
        'release_next_source' => true,
        'savepoint' => 'wp_recursive_view_next166',
        'current_generation' => 'wp-import-current-166',
        'next_generation' => 'wp-import-next-166',
        'trigger_child_prefix' => 'plugin_generated',
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
    'applicationUse' => 'Copied wp_options imports through recursive INSTEAD OF view triggers drain current RETURNING rows before trigger-generated audit rows seed a released next view source.',
    'dependencyClosure' => 'no new support component needed; reuses native PHP recursive view RETURNING barriers and trigger row projection',
    'summary' => [
        'status' => $summary['status'],
        'currentVisible' => $summary['returning_drain']['current_visible_count'],
        'nextVisible' => $summary['returning_drain']['next_visible_count'],
        'nextAfterCurrentDrain' => $summary['returning_drain']['next_after_current_drain'],
        'visibleReturningKeys' => $summary['returning_drain']['visible_keys'],
    ],
];
