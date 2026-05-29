<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4PartialExpressionCoveringCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$lowerName = ['function' => 'lower', 'column' => 'option_name'];
$predicate = [
    'operator' => 'AND',
    'terms' => [
        ['operator' => 'IN', 'left' => $lowerName, 'values' => ['plugin_cache', 'plugin_forms', 'plugin_seo']],
        ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'],
    ],
];
$prepared = [
    'name' => 'prepared-wp-options-plugin-covering-next118',
    'schemaCookie' => 1180,
    'stat4Generation' => 17,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_plugin_covering_next118',
        'rootPage' => 11801,
        'coveringColumns' => ['option_name', 'autoload', 'option_value', 'blog_id'],
        'stat4Samples' => [
            ['neq' => 1, 'nlt' => 0, 'ndlt' => 0, 'sample' => ['plugin_cache']],
            ['neq' => 2, 'nlt' => 1, 'ndlt' => 1, 'sample' => ['plugin_forms']],
            ['neq' => 3, 'nlt' => 3, 'ndlt' => 2, 'sample' => ['plugin_seo']],
        ],
        'sql' => "CREATE INDEX idx_wp_options_lower_plugin_covering_next118 ON wp_options(lower(option_name), autoload, option_value, blog_id) WHERE autoload = 'yes'",
    ]],
];
$current = $prepared;
$current['name'] = 'current-wp-options-plugin-covering-next118';
$current['schemaCookie'] = 1184;
$current['stat4Generation'] = 21;
$current['indexes'][0]['rootPage'] = 11844;
$current['indexes'][0]['stat4Samples'][] = ['neq' => 1, 'nlt' => 6, 'ndlt' => 3, 'sample' => ['plugin_mail']];

$rows = [
    ['rowid' => 11, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'cache-enabled', 'blog_id' => 1],
    ['rowid' => 31, 'option_name' => 'Plugin_Forms', 'autoload' => 'yes', 'option_value' => 'forms-enabled', 'blog_id' => 1],
    ['rowid' => 21, 'option_name' => 'plugin_seo', 'autoload' => 'yes', 'option_value' => 'seo-enabled', 'blog_id' => 1],
    ['rowid' => 41, 'option_name' => 'plugin_mail', 'autoload' => 'yes', 'option_value' => 'mail-enabled', 'blog_id' => 2],
    ['rowid' => 51, 'option_name' => 'plugin_cache', 'autoload' => 'no', 'option_value' => 'cache-disabled', 'blog_id' => 3],
];

$plan = SQLitePlannerStat4PartialExpressionCoveringCurrentSourceNextPlan::materialize(
    $prepared,
    $current,
    $predicate,
    $rows,
    [$lowerName, ['column' => 'autoload']],
    ['option_name', 'autoload', 'option_value', 'blog_id'],
    [$lowerName],
);

if (($plan['status'] ?? null) !== 'stat4-partial-expression-covering-current-source-ready') {
    fwrite(STDERR, "No current-source STAT4 partial covering plan selected\n");
    exit(1);
}

printf(
    "wordpress stat4 partial expression covering current-source next118: source=%s index=%s rows=%d names=%s\n",
    (string) $plan['selectedSource'],
    (string) $plan['selectedPlan']['name'],
    (int) $plan['selectedPlan']['coveredRowCount'],
    implode(',', array_map(static fn (array $pair): string => (string) $pair['current']['covering']['option_name'], $plan['selectedPlan']['currentNextRows'])),
);
