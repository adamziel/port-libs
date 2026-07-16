<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$jsonChannel = ['function' => 'jsonb_extract', 'column' => 'option_value', 'path' => '$.plugin.channel'];
$predicate = [
    'operator' => 'AND',
    'terms' => [
        ['operator' => 'IN', 'left' => $jsonChannel, 'values' => ['beta', 'stable']],
        ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'],
    ],
];
$index = [
    'name' => 'idx_wp_options_channel_covering_stat4_next109',
    'rootPage' => 1091,
    'estimatedRows' => 200,
    'stat4Samples' => [
        ['neq' => '2 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['alpha', 'autoload_yes']],
        ['neq' => '4 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['beta', 'autoload_yes']],
        ['neq' => '6 1', 'nlt' => '6 2', 'ndlt' => '2 2', 'sample' => ['delta', 'autoload_yes']],
        ['neq' => '8 1', 'nlt' => '12 3', 'ndlt' => '3 3', 'sample' => ['stable', 'autoload_yes']],
    ],
    'coveringColumns' => ['option_name', 'autoload', 'option_id', 'blog_id'],
    'sql' => "CREATE INDEX idx_wp_options_channel_covering_stat4_next109 ON wp_options(jsonb_extract(option_value, '$.plugin.channel'), autoload, option_id) WHERE autoload = 'yes'",
];
$rows = [
    ['rowid' => 5, 'option_id' => 5, 'option_name' => 'plugin_beta_a', 'autoload' => 'yes', 'blog_id' => 1, 'option_value' => '{"plugin":{"channel":"beta"}}'],
    ['rowid' => 7, 'option_id' => 7, 'option_name' => 'plugin_stable', 'autoload' => 'yes', 'blog_id' => 1, 'option_value' => '{"plugin":{"channel":"stable"}}'],
    ['rowid' => 11, 'option_id' => 11, 'option_name' => 'plugin_beta_b', 'autoload' => 'yes', 'blog_id' => 2, 'option_value' => '{"plugin":{"channel":"beta"}}'],
    ['rowid' => 13, 'option_id' => 13, 'option_name' => 'plugin_theta', 'autoload' => 'no', 'blog_id' => 1, 'option_value' => '{"plugin":{"channel":"theta"}}'],
];

$plan = SQLiteSelectExpressionIndexPlan::stat4ExpressionCoveringCurrentSourcePlan(
    [$index],
    $predicate,
    $rows,
    [$jsonChannel, ['column' => 'autoload']],
    ['option_name', 'autoload', 'option_id', 'blog_id'],
    [$jsonChannel],
);

if ($plan === null) {
    fwrite(STDERR, "No STAT4 current-source covering plan selected\n");
    exit(1);
}

printf(
    "application planner stat4 expression covering current-source next109: %s rows=%d matched=%d keys=%s names=%s\n",
    (string) $plan['name'],
    (int) $plan['coveredRowCount'],
    (int) $plan['stat4MatchedSamples'],
    implode(',', array_map(static fn (array $pair): string => (string) $pair['current']['key'], $plan['currentNextRows'])),
    implode(',', array_map(static fn (array $pair): string => (string) $pair['current']['covering']['option_name'], $plan['currentNextRows'])),
);
