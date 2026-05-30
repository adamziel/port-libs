<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$terms = [
    ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => 'BETWEEN', 'lower' => 'plugin_cache', 'upper' => 'plugin_zulu'],
    ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
    ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
    ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
    ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'plugin_%'],
];

$prepared = [
    'name' => 'prepared-wp-options-stat4-duplicate-run-next248',
    'schemaCookie' => 2480,
    'stat4Generation' => 248,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_duplicate_run_next248',
        'rootPage' => 24801,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'partialPredicateTerms' => array_slice($terms, 0, 3),
        'partialGroupedOrPredicateArms' => [[
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ]],
        'partialGroupedLikePredicateArms' => [[
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'plugin_%'],
        ]],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_seo', 30, 1]],
        ],
    ]],
];

$current = $prepared;
$current['name'] = 'current-wp-options-stat4-duplicate-run-next248';
$current['schemaCookie'] = 2488;
$current['stat4Generation'] = 648;
$current['rows'] = [
    ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-a', 'updated_at' => 40],
    ['rowid' => 41, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'cache-b', 'updated_at' => 41],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-a', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-b', 'updated_at' => 21],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
];
$current['indexes'][0]['rootPage'] = 24888;
$current['indexes'][0]['stat1'] = ['rows' => '5 2 1'];
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '2 2', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 40, 1]],
    ['neq' => '2 2', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 41, 1]],
    ['neq' => '2 2', 'nlt' => '2 2', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '2 2', 'nlt' => '2 2', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 21, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceDuplicateRunValidation(
    $prepared,
    $current,
    $terms,
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    5,
);

echo json_encode([
    'scenario' => 'application-stat4-duplicate-run-current-source-next248',
    'status' => $plan['status'],
    'selectedIndex' => $plan['selectedPlan']['name'] ?? null,
    'duplicateKeys' => $plan['stat4DuplicateRunFence']['duplicateKeys'] ?? [],
    'partialRowidsInIndexOrder' => $plan['stat4DuplicateRunFence']['partialRowidsInIndexOrder'] ?? [],
    'cursorOpcode' => $plan['cursorProgram'][array_key_last($plan['cursorProgram'])]['opcode'] ?? null,
    'applicationUse' => 'Prevents a copied wp_options partial expression-index cursor from reusing stale STAT4 duplicate-key sample runs after plugin option rows are duplicated with different case.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
