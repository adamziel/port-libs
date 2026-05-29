<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$source = [
    'name' => 'wp-options-current-next228',
    'schemaCookie' => 2289,
    'stat4Generation' => 308,
    'rows' => [
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
        ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
        ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
        ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache', 'updated_at' => 40],
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 10],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_stat4_partial_sample_next228',
        'rootPage' => 22888,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'descending' => true,
        'partialPredicateTerms' => [
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_alpha'],
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
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 40]],
            ['neq' => '3 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '5 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 50]],
            ['neq' => '1 1', 'nlt' => '6 4', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 30]],
            ['neq' => '1 1', 'nlt' => '7 5', 'ndlt' => '5 5', 'sample' => ['plugin_zulu', 60]],
        ],
        'stat4ExpressionPayloads' => [],
    ]],
];
$source['indexes'][0]['stat4ExpressionPayloads'] = array_map(
    static fn (array $row): array => [
        'rowid' => $row['rowid'],
        'expressionKey' => strtolower((string) $row['option_name']),
        'coveredValues' => [
            'option_name' => $row['option_name'],
            'option_value' => $row['option_value'],
            'updated_at' => $row['updated_at'],
            'blog_id' => $row['blog_id'],
            'autoload' => $row['autoload'],
        ],
    ],
    $source['rows'],
);
$prepared = $source;
$prepared['name'] = 'wp-options-prepared-next228';
$prepared['schemaCookie'] = 2280;
$prepared['stat4Generation'] = 228;
$prepared['indexes'][0]['rootPage'] = 22801;
$prepared['indexes'][0]['stat4Samples'] = array_slice($source['indexes'][0]['stat4Samples'], 0, 3);
$where = [
    ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => 'BETWEEN', 'lower' => 'plugin_alpha', 'upper' => 'plugin_zulu'],
    ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
    ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
    ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
    ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'plugin_%'],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext228(
    $prepared,
    $source,
    $where,
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    5,
    0,
);

if (($argv[1] ?? null) === '--self-test' && $plan['status'] !== 'stat4-expression-partial-current-source-next228-ready') {
    throw new RuntimeException('Expected next228 STAT4 partial sample fence to be ready');
}

printf(
    "wordpress sqlplanner stat4 expression partial current-source next228: %s samples=%d rowids=%s signature=%s\n",
    $plan['status'],
    $plan['stat4SamplePartialPredicateFence']['sampleRowCount'],
    implode(',', $plan['cursorProgram'][array_key_last($plan['cursorProgram'])]['sampleRowids']),
    substr($plan['stat4SamplePartialPredicateFence']['proofSignature'], 0, 12),
);
