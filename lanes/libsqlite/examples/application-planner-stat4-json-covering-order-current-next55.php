<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$jsonChannel = ['function' => 'jsonb_extract', 'column' => 'option_value', 'path' => '$.plugin.channel'];
$predicate = [
    'operator' => 'AND',
    'terms' => [
        ['operator' => '>=', 'left' => $jsonChannel, 'right' => 'beta'],
        ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'],
    ],
];
$index = [
    'name' => 'idx_wp_options_plugin_channel_stat4_cover',
    'rootPage' => 551,
    'estimatedRows' => 10000,
    'stat4Samples' => [
        ['neq' => '4 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['alpha', 'autoload_yes']],
        ['neq' => '7 1', 'nlt' => '4 1', 'ndlt' => '1 1', 'sample' => ['beta', 'autoload_yes']],
        ['neq' => '11 1', 'nlt' => '11 2', 'ndlt' => '2 2', 'sample' => ['delta', 'autoload_no']],
        ['neq' => '13 1', 'nlt' => '22 3', 'ndlt' => '3 3', 'sample' => ['stable', 'autoload_yes']],
        ['neq' => '17 1', 'nlt' => '35 4', 'ndlt' => '4 4', 'sample' => ['theta', 'autoload_yes']],
    ],
    'sql' => "CREATE INDEX idx_wp_options_plugin_channel_stat4_cover ON wp_options(jsonb_extract(option_value, '$.plugin.channel') COLLATE BINARY, autoload, option_id DESC, option_name) WHERE autoload = 'yes'",
];

$plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost(
    [$index],
    $predicate,
    [
        ['function' => 'jsonb_extract', 'column' => 'option_value', 'path' => '$.plugin.channel'],
        ['column' => 'autoload'],
        ['column' => 'option_id', 'direction' => 'DESC'],
    ],
    ['autoload', 'option_id', 'option_name'],
    [$jsonChannel],
);

if ($plan === null) {
    fwrite(STDERR, "No planner candidate selected\n");
    exit(1);
}

printf(
    "application planner stat4 json covering order current-next55: %s rows=%d matched=%d covering=%s order=%s matched_keys=%s\n",
    (string) $plan['name'],
    (int) $plan['estimatedRows'],
    (int) $plan['stat4MatchedSamples'],
    $plan['covering'] ? 'yes' : 'no',
    $plan['orderBySatisfied'] ? 'yes' : 'no',
    implode(',', array_map(static fn (array $pair): string => (string) $pair['current']['key'], $plan['stat4MatchedCurrentNext'])),
);
