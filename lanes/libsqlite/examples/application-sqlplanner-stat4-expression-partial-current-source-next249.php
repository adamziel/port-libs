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
];
$source = [
    'name' => 'wp-options-current-next249',
    'schemaCookie' => 2498,
    'stat4Generation' => 948,
    'rows' => $rows,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_peers_next249',
        'rootPage' => 24988,
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
    ]],
];
$prepared = $source;
$prepared['name'] = 'wp-options-prepared-next249';
$prepared['schemaCookie'] = 2490;
$prepared['stat4Generation'] = 249;
$prepared['indexes'][0]['rootPage'] = 24901;
$prepared['indexes'][0]['stat4Samples'][0]['neq'] = '1 1';

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeDuplicatePeerFence(
    $prepared,
    $source,
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

if (($argv[1] ?? null) === '--self-test' && $plan['status'] !== 'stat4-expression-partial-current-source-next249-ready') {
    throw new RuntimeException('Expected next249 STAT4 duplicate peer fence to be ready');
}

printf(
    "application sqlplanner stat4 expression partial current-source next249: %s peers=%s signature=%s\n",
    $plan['status'],
    json_encode($plan['stat4DuplicatePeerFence']['currentPeerCounts'], JSON_THROW_ON_ERROR),
    substr($plan['stat4DuplicatePeerFence']['proofSignature'], 0, 12),
);
