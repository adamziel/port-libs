<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext158(
    [
        ['option_name' => 'siteurl', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
        ['option_name' => 'home', 'option_value' => 'https://old-home.example', 'autoload' => 'yes'],
        ['option_name' => 'rewrite_rules', 'option_value' => 'old-rules', 'autoload' => 'no'],
    ],
    [
        ['name' => 'siteurl', 'value' => 'https://current.example', 'autoload_flag' => 'yes'],
        ['name' => 'blogdescription', 'value' => 'Current Tagline', 'autoload_flag' => 'yes'],
    ],
    [
        ['name' => 'siteurl', 'value' => 'https://next.example', 'autoload_flag' => 'yes'],
        ['name' => 'fresh_plugin', 'value' => 'enabled', 'autoload_flag' => 'no'],
    ],
    [
        'name' => 'wp_option_import_view',
        'current_source' => 'main@trigger158-current',
        'next_source' => 'main@trigger158-next',
        'mapping' => ['name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    ],
    ['option_name'],
    [
        ['name' => 'wp_options_ai_home', 'when' => 'siteurl', 'target' => 'home', 'value' => '{value}/home'],
        ['name' => 'wp_options_au_rewrite', 'when' => 'home', 'target' => 'rewrite_rules', 'value' => 'flushed:{value}'],
    ],
    [
        ['expr' => 'new.option_name', 'as' => 'name'],
        ['expr' => 'new.option_value', 'as' => 'value'],
        ['expr' => 'depth', 'as' => 'trigger_depth'],
        ['expr' => 'source', 'as' => 'view_source'],
    ],
    ['savepoint' => 'wp_recursive_view_returning_158']
);

$result = [
    'scenario' => 'wordpress-trigger-recursive-view-returning-current-source-next158',
    'wordpressUse' => 'Preview copied wp_options imports through an INSTEAD OF view trigger where recursive trigger side effects yield RETURNING rows from the current source, roll back before the next source is admitted, and keep the final option rows aligned with the visible source without requiring ext/sqlite.',
    'status' => $summary['status'],
    'visibleSource' => $summary['visible_source'],
    'admittedReturningNames' => array_column(array_column($summary['returning_rows'], 'returning'), 'name'),
    'suppressedReturningNames' => array_column(array_column($summary['suppressed_returning_rows'], 'returning'), 'name'),
    'finalOptionNames' => array_column($summary['after_savepoint'], 'option_name'),
    'dependencyClosure' => $summary['dependency_closure'],
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    assert($result['status'] === 'trigger-recursive-view-returning-current-source-retained-next158');
    assert($result['visibleSource'] === 'main@trigger158-next');
    assert($result['admittedReturningNames'] === ['siteurl', 'home', 'rewrite_rules', 'fresh_plugin']);
    assert($result['suppressedReturningNames'] === ['siteurl', 'home', 'rewrite_rules', 'blogdescription']);
    assert($result['finalOptionNames'] === ['siteurl', 'home', 'rewrite_rules', 'fresh_plugin']);
    assert($result['dependencyClosure'] === 'reuses-native-recursive-trigger-returning-view-current-source-plans');
    echo "wordpress-trigger-recursive-view-returning-current-source-next158 self-test passed\n";
}

return $result;
