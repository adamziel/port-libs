<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$source = [
    'name' => 'wp-options-current-source',
    'schemaCookie' => 1818,
    'stat4Generation' => 46,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-current'],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-current'],
        ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_mail', 'option_value' => 'mail-current'],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-current'],
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_lazy', 'option_value' => 'lazy-current'],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_partial_or_next181',
        'rootPage' => 18188,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'direction' => 'ASC',
        'collation' => 'BINARY',
        'partialPredicateTerms' => [
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_'],
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<', 'right' => 'plugin`'],
        ],
        'partialPredicateAnyTerms' => [
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => '=', 'right' => 'siteurl'],
        ],
        'coveringColumns' => ['option_name', 'option_value', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 40]],
            ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 30]],
        ],
    ]],
];

$prepared = $source;
$prepared['name'] = 'wp-options-prepared-source';
$prepared['schemaCookie'] = 1810;
$prepared['stat4Generation'] = 31;
$prepared['indexes'][0]['rootPage'] = 18101;
$prepared['rows'] = array_values(array_filter(
    $prepared['rows'],
    static fn (array $row): bool => ($row['rowid'] ?? null) !== 40,
));

$terms = [
    ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => '>=', 'right' => 'plugin_'],
    ['left' => ['expression' => 'lower( option_name )'], 'operator' => '<', 'right' => 'plugin_t'],
    ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
    ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
    ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4PartialOrOrderFence(
    $prepared,
    $source,
    $terms,
    ['option_name', 'option_value', 'autoload'],
    ['expression' => 'lower(option_name)', 'direction' => 'ASC', 'collation' => 'BINARY'],
);

echo json_encode([
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'matchedRowids' => $plan['matchedRowids'],
    'matchedOrTerm' => $plan['selectedPlan']['next181MatchedOrTerm'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
