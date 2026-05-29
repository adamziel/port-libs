<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$lowerName = ['function' => 'lower', 'column' => 'option_name'];
$predicate = [
    'operator' => 'AND',
    'terms' => [
        ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'],
        ['operator' => 'IN', 'left' => $lowerName, 'values' => ['plugin_beta', 'plugin_stable']],
    ],
];
$indexSql = "CREATE INDEX idx_wp_options_lower_name_autoload_stat4_partial_next157 ON wp_options(lower(option_name), autoload, site_id, option_value) WHERE autoload = 'yes'";
$prepared = [
    'name' => 'prepared-main.wp_options@next157',
    'schemaCookie' => 1570,
    'stat4Generation' => 70,
    'rows' => [
        ['rowid' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Alpha', 'option_value' => 'alpha-old', 'site_id' => 1],
        ['rowid' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_beta', 'option_value' => 'beta-old', 'site_id' => 1],
        ['rowid' => 5, 'autoload' => 'yes', 'option_name' => 'plugin_stable', 'option_value' => 'stable-old', 'site_id' => 1],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_name_autoload_stat4_partial_next157',
        'rootPage' => 15701,
        'estimatedRows' => 240,
        'coveringColumns' => ['option_name', 'option_value', 'autoload', 'site_id'],
        'coveringExpressions' => [$lowerName],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 'yes']],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_beta', 'yes']],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_stable', 'yes']],
        ],
        'sql' => $indexSql,
    ]],
];
$current = [
    'name' => 'current-main.wp_options@next157',
    'schemaCookie' => 1571,
    'stat4Generation' => 71,
    'rows' => [
        ['rowid' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Alpha', 'option_value' => 'alpha-old', 'site_id' => 1],
        ['rowid' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_beta', 'option_value' => 'beta-old', 'site_id' => 1],
        ['rowid' => 3, 'autoload' => 'no', 'option_name' => 'plugin_beta', 'option_value' => 'beta-lazy', 'site_id' => 1],
        ['rowid' => 5, 'autoload' => 'yes', 'option_name' => 'plugin_stable', 'option_value' => 'stable-old', 'site_id' => 1],
        ['rowid' => 7, 'autoload' => 'yes', 'option_name' => 'PLUGIN_BETA', 'option_value' => 'beta-new', 'site_id' => 2],
        ['rowid' => 8, 'autoload' => 'yes', 'option_name' => 'plugin_stable', 'option_value' => 'stable-new', 'site_id' => 2],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_name_autoload_stat4_partial_next157',
        'rootPage' => 15711,
        'estimatedRows' => 180,
        'coveringColumns' => ['option_name', 'option_value', 'autoload', 'site_id'],
        'coveringExpressions' => [$lowerName],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 'yes']],
            ['neq' => '2 2', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_beta', 'yes']],
            ['neq' => '2 2', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_stable', 'yes']],
        ],
        'sql' => $indexSql,
    ]],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext157(
    $prepared,
    $current,
    $predicate,
    [$lowerName, ['column' => 'site_id']],
    ['option_name', 'option_value', 'autoload', 'site_id'],
    [$lowerName],
);

$rowids = $plan['selectedPlan']['next157Rowids'] ?? [];
$names = $plan['selectedPlan']['next157CoveringNames'] ?? [];
if ($plan['status'] !== 'stat4-expression-partial-current-source-next157-ready' || $rowids !== [2, 7, 5, 8]) {
    fwrite(STDERR, "wordpress planner stat4 expression partial current-source next157 failed\n");
    exit(1);
}

printf(
    "wordpress planner stat4 expression partial current-source next157: %s rows=%d rowids=%s names=%s\n",
    (string) ($plan['selectedPlan']['name'] ?? 'no-index'),
    count($rowids),
    implode(',', $rowids),
    implode(',', array_map(static fn (mixed $name): string => (string) $name, $names)),
);
