<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$rows = [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$index = [
    'name' => 'idx_wp_options_lower_histogram_next242',
    'rootPage' => 24288,
    'expression' => 'lower(option_name)',
    'expressionColumn' => '__expr_lower_option_name',
    'collation' => 'BINARY',
    'descending' => true,
    'stat1' => ['rows' => '6 2 1'],
    'partialPredicateTerms' => [
        ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_forms'],
        ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<=', 'right' => 'plugin_zulu'],
        ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
    ],
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
        ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
        ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
        ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
        ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
    ],
];

$prepared = [
    'name' => 'prepared-wp-options-stat4-histogram-next242',
    'schemaCookie' => 2420,
    'stat4Generation' => 242,
    'rows' => array_slice($rows, 0, 3),
    'indexes' => [array_replace($index, [
        'rootPage' => 24201,
        'estimatedPartialRows' => 3,
        'stat4Samples' => array_slice($index['stat4Samples'], 0, 3),
    ])],
];
$current = [
    'name' => 'current-wp-options-stat4-histogram-next242',
    'schemaCookie' => 2428,
    'stat4Generation' => 428,
    'rows' => $rows,
    'indexes' => [$index],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceHistogramValidation(
    $prepared,
    $current,
    [
        ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => 'BETWEEN', 'lower' => 'plugin_forms', 'upper' => 'plugin_zulu'],
        ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
        ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'plugin_%'],
    ],
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
);

if (($argv[1] ?? null) === '--self-test' && $plan['status'] !== 'stat4-expression-partial-current-source-next242-ready') {
    throw new RuntimeException('Expected next242 STAT4 histogram fence to be ready');
}

printf(
    "wordpress sqlplanner stat4 expression partial current-source next242: %s samples=%d rejected=%d signature=%s\n",
    $plan['status'],
    $plan['stat4HistogramFence']['sampleCount'],
    count($plan['stat4HistogramFence']['rejectedSamples']),
    substr($plan['stat4HistogramFence']['proofSignature'], 0, 12),
);
