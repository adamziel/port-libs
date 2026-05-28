<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNext241Plan;

foreach (glob(__DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNext*Plan.php') ?: [] as $plannerFile) {
    require_once $plannerFile;
}

$prepared = [
    'name' => 'prepared-wp-options-stat4-residual-next241',
    'schemaCookie' => 2410,
    'stat4Generation' => 241,
    'rows' => [],
    'indexes' => [[
        'name' => 'idx_wp_options_stat4_residual_partial_next241',
        'rootPage' => 24101,
        'expression' => 'lower(option_name)',
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
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 1, 10]],
            ['neq' => '3 3', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 1, 20]],
            ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 1, 30]],
        ],
        'stat4ExpressionPayloads' => [],
    ]],
];
$current = $prepared;
$current['name'] = 'current-wp-options-stat4-residual-next241';
$current['schemaCookie'] = 2419;
$current['stat4Generation'] = 641;
$current['indexes'][0]['rootPage'] = 24188;
$current['rows'] = [
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy', 'updated_at' => 21],
    ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 10],
];
$current['indexes'][0]['stat4ExpressionPayloads'] = array_map(static fn (array $row): array => [
    'rowid' => $row['rowid'],
    'expressionKey' => strtolower((string) $row['option_name']),
    'coveredValues' => [
        'option_name' => $row['option_name'],
        'option_value' => $row['option_value'],
        'updated_at' => $row['updated_at'],
        'autoload' => $row['autoload'],
        'blog_id' => $row['blog_id'],
    ],
], $current['rows']);
$terms = [
    ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => 'BETWEEN', 'lower' => 'plugin_alpha', 'upper' => 'plugin_zulu'],
    ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
    ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
    ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
    ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'plugin_%'],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNext241Plan::materialize(
    $prepared,
    $current,
    $terms,
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    3,
    0,
);

$summary = [
    'scenario' => 'wordpress-sqlplanner-stat4-expression-partial-current-source-next241',
    'status' => $plan['status'],
    'acceptedRowids' => $plan['stat4ResidualWhereFence']['residualAcceptedRowids'],
    'rejectedRowids' => $plan['stat4ResidualWhereFence']['residualRejectedRowids'],
    'cursorOpcode' => $plan['cursorProgram'][array_key_last($plan['cursorProgram'])]['opcode'],
    'wordpressUse' => 'Copied wp_options plugin screens can keep a STAT4 partial expression covering scan only after current-source rowids still satisfy blog/autoload/LIKE residual predicates, avoiding stale payloads after plugin-option churn without requiring ext/sqlite.',
];

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if ($summary['status'] !== 'stat4-expression-partial-current-source-next241-ready' || $summary['rejectedRowids'] !== []) {
        fwrite(STDERR, "wordpress-sqlplanner-stat4-expression-partial-current-source-next241 self-test failed\n");
        exit(1);
    }
    echo "wordpress-sqlplanner-stat4-expression-partial-current-source-next241 self-test passed\n";
}

return $summary;
