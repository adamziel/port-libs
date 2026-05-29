<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$prepared = [
    'name' => 'prepared-wp-options-stat4-expression-partial-in-limit-next186',
    'schemaCookie' => 1860,
    'stat4Generation' => 80,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-stale'],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_in_limit_partial_stat4_next186',
        'rootPage' => 18601,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'partialPredicateTerms' => [
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>', 'right' => 'plugin_'],
        ],
        'coveringColumns' => ['option_name', 'option_value', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_admin', 1]],
            ['neq' => '1 1', 'nlt' => '3 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 2]],
            ['neq' => '1 1', 'nlt' => '6 2', 'ndlt' => '2 2', 'sample' => ['plugin_security', 3]],
            ['neq' => '1 1', 'nlt' => '9 3', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 4]],
        ],
    ]],
];

$current = $prepared;
$current['name'] = 'current-wp-options-stat4-expression-partial-in-limit-next186';
$current['schemaCookie'] = 1867;
$current['stat4Generation'] = 99;
$current['indexes'][0]['rootPage'] = 18688;
$current['rows'] = [
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_shop', 'option_value' => 'shop-current'],
    ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'cache-current'],
    ['rowid' => 11, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-copy'],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_mail', 'option_value' => 'mail-current'],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_MAIL', 'option_value' => 'mail-uppercase'],
    ['rowid' => 40, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_shop', 'option_value' => 'network-shop'],
];

$where = [
    ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => 'IN', 'values' => [['literal' => 'plugin_shop'], ['literal' => 'plugin_cache'], ['literal' => 'plugin_mail']]],
    ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
    ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
    ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
    ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>', 'right' => 'plugin_'],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext186(
    $prepared,
    $current,
    $where,
    ['option_name', 'option_value', 'autoload', 'blog_id'],
    3,
    1,
);

if (($argv[1] ?? '') === '--self-test') {
    if (($plan['status'] ?? null) !== 'stat4-expression-partial-current-source-next186-ready') {
        throw new RuntimeException('Expected next186 STAT4 expression partial IN limit plan to be ready');
    }
    if (($plan['matchedRowids'] ?? []) !== [10, 11, 20]) {
        throw new RuntimeException('Expected LIMIT/OFFSET to window copied WordPress plugin preload rows');
    }
    if (($plan['projectedRows'][0]['option_value'] ?? null) !== 'cache-current') {
        throw new RuntimeException('Expected covering payload projection from the current source');
    }
    echo "wordpress-sqlplanner-stat4-expression-partial-current-source-next186 self-test passed\n";

    return;
}

echo json_encode([
    'scenario' => 'wordpress-sqlplanner-stat4-expression-partial-current-source-next186',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'limitWindow' => $plan['limitWindow'],
    'matchedRowids' => $plan['matchedRowids'],
    'projectedValues' => array_column($plan['projectedRows'], 'option_value'),
    'cursorOpcodes' => array_column($plan['cursorProgram'], 'opcode'),
    'wordpressUse' => 'Copied wp_options plugin preload queries can reprepare stale STAT4 partial lower(option_name) IN-list plans, apply LIMIT/OFFSET to the current-source probe stream, and return covering option payloads without table lookups.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
