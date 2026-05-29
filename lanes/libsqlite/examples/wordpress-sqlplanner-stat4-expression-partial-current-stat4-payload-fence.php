<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$between = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared = [
    'name' => 'wp-options-prepared-next253',
    'schemaCookie' => 2530,
    'stat4Generation' => 253,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'old', 'updated_at' => 10],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_stat4_payload_next253',
        'rootPage' => 25301,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'descending' => true,
        'partialPredicateTerms' => [
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_alpha'],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
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
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 1, 10]],
            ['neq' => '3 3', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 1, 20]],
            ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 1, 30]],
        ],
        'stat4ExpressionPayloads' => [],
    ]],
];

$current = $prepared;
$current['name'] = 'wp-options-current-next253';
$current['schemaCookie'] = 2539;
$current['stat4Generation'] = 953;
$current['indexes'][0]['rootPage'] = 25388;
$current['indexes'][0]['partialPredicateTerms'] = [
    ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => '>=', 'right' => 'plugin_alpha'],
    ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => '<=', 'right' => 'plugin_zulu'],
    ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
];
$current['rows'] = [
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy', 'updated_at' => 21],
    ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 10],
];
$current['indexes'][0]['stat4ExpressionPayloads'] = array_map(
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
    $current['rows'],
);

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentStat4PayloadFence(
    $prepared,
    $current,
    [
        $between('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
        $eq('autoload', 'yes'),
        $eq('blog_id', 1),
        $like('option_name', 'plugin_%'),
    ],
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    3,
    0,
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'stat4-expression-partial-current-source-next253-ready');
    assert($plan['stat4CurrentPayloadFence']['payloadMatchedRowids'] === [10, 20, 21]);
    assert($plan['selectedPlan']['next253Ready'] === true);
    echo "wordpress-sqlplanner-stat4-expression-partial-current-stat4-payload-fence self-test passed\n";
}

return [
    'scenario' => 'wordpress-sqlplanner-stat4-expression-partial-current-stat4-payload-fence',
    'status' => $plan['status'],
    'payloadMatchedRowids' => $plan['stat4CurrentPayloadFence']['payloadMatchedRowids'],
    'payloadSignature' => $plan['stat4CurrentPayloadFence']['payloadSignature'],
    'wordpressUse' => 'Copied wp_options plugin preload pagination can reuse a current-source partial lower(option_name) STAT4 index only after STAT4 expression payloads for yielded rowids match the current row image.',
];
