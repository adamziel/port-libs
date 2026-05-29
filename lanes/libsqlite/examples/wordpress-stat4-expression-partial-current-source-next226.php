<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prepared = [
    'name' => 'prepared-wp-options-sample-window-stat4-expression-next226',
    'schemaCookie' => 2260,
    'stat4Generation' => 226,
    'rows' => [],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_sample_window_next226',
        'rootPage' => 22601,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'descending' => true,
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
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
            ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60]],
        ],
    ]],
];
$current = $prepared;
$current['name'] = 'current-wp-options-sample-window-stat4-expression-next226';
$current['schemaCookie'] = 2268;
$current['stat4Generation'] = 286;
$current['indexes'][0]['rootPage'] = 22688;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
    ['neq' => '3 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
    ['neq' => '1 1', 'nlt' => '4 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 50]],
    ['neq' => '1 1', 'nlt' => '5 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 30]],
    ['neq' => '1 1', 'nlt' => '6 4', 'ndlt' => '4 4', 'sample' => ['plugin_zulu', 60]],
    ['neq' => '1 1', 'nlt' => '7 5', 'ndlt' => '5 5', 'sample' => ['theme_mods_current', 90]],
];
$current['rows'] = [
    ['rowid' => 90, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods_current', 'option_value' => 'theme', 'updated_at' => 90],
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 10],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];
$where = [
    ['left' => ['expression' => 'LOWER( option_name )'], 'operator' => 'BETWEEN', 'lower' => 'plugin_forms', 'upper' => 'plugin_zulu'],
    ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
    ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
    ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
    ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'plugin_%'],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext226(
    $prepared,
    $current,
    $where,
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    4,
    0,
);

$summary = [
    'scenario' => 'wordpress-stat4-expression-partial-current-source-next226',
    'status' => $plan['status'],
    'selectedIndex' => $plan['selectedPlan']['name'],
    'windowRowids' => $plan['stat4SampleWindowFence']['currentWindowRowids'],
    'outsideWindowRowids' => $plan['stat4SampleWindowFence']['currentOutsideWindowRowids'],
    'matchedRowids' => $plan['matchedRowids'],
    'cursorOpcode' => $plan['cursorProgram'][array_key_last($plan['cursorProgram'])]['opcode'],
    'wordpressUse' => 'Keeps copied wp_options plugin-option range scans on a current partial expression index when STAT4 samples outside the range churn during import.',
];

if ($summary['status'] !== 'stat4-expression-partial-current-source-next226-ready'
    || $summary['windowRowids'] !== [20, 50, 30, 60]
    || $summary['outsideWindowRowids'] !== [10, 90]
    || $summary['cursorOpcode'] !== 'RecheckCurrentSourceStat4SampleWindow'
) {
    fwrite(STDERR, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    fwrite(STDERR, "wordpress-stat4-expression-partial-current-source-next226 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
