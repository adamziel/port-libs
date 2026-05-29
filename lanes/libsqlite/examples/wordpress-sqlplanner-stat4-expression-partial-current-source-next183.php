<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$prepared = [
    'name' => 'prepared-wp-options-stat4-expression-partial-in-next183',
    'schemaCookie' => 1830,
    'stat4Generation' => 71,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-stale'],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_in_partial_stat4_next183',
        'rootPage' => 18301,
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
            ['neq' => '1 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 2]],
            ['neq' => '1 1', 'nlt' => '4 2', 'ndlt' => '2 2', 'sample' => ['plugin_security', 3]],
            ['neq' => '1 1', 'nlt' => '6 3', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 4]],
        ],
    ]],
];

$current = $prepared;
$current['name'] = 'current-wp-options-stat4-expression-partial-in-next183';
$current['schemaCookie'] = 1838;
$current['stat4Generation'] = 94;
$current['indexes'][0]['rootPage'] = 18388;
$current['rows'] = [
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_shop', 'option_value' => 'shop-current'],
    ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'cache-current'],
    ['rowid' => 11, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-copy'],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_mail', 'option_value' => 'mail-current'],
    ['rowid' => 40, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_shop', 'option_value' => 'network-shop'],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_mail', 'option_value' => 'lazy-mail'],
];

$where = [
    ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => 'IN', 'values' => [['literal' => 'plugin_shop'], ['literal' => 'plugin_cache'], ['literal' => 'plugin_mail']]],
    ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
    ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
    ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
    ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>', 'right' => 'plugin_'],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext183(
    $prepared,
    $current,
    $where,
    ['option_name', 'option_value', 'autoload', 'blog_id'],
);

if (($argv[1] ?? '') === '--self-test') {
    if (($plan['status'] ?? null) !== 'stat4-expression-partial-current-source-next183-ready') {
        throw new RuntimeException('Expected next183 STAT4 expression partial IN plan to be ready');
    }
    if (($plan['matchedRowids'] ?? []) !== [30, 10, 11, 20]) {
        throw new RuntimeException('Expected WordPress plugin IN-list rowids to follow current-source probe order');
    }
    echo "wordpress-sqlplanner-stat4-expression-partial-current-source-next183 self-test passed\n";

    return;
}

echo json_encode([
    'scenario' => 'wordpress-sqlplanner-stat4-expression-partial-current-source-next183',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'inValues' => $plan['inValues'],
    'matchedRowids' => $plan['matchedRowids'],
    'cursorOpcodes' => array_column($plan['cursorProgram'], 'opcode'),
    'wordpressUse' => 'Copied wp_options plugin preload queries can reprepare stale STAT4 partial lower(option_name) IN-list plans, probe the current source once per requested plugin key, deduplicate rowids, and avoid falling back to a table scan without requiring ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
