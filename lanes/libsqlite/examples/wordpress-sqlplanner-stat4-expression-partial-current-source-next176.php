<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$eq = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$range = static fn (string $expression, string $operator, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => $operator, 'right' => $right];

$prepared = [
    'name' => 'prepared-wp-options-stat4-exact-boundary-next176',
    'schemaCookie' => 1760,
    'stat4Generation' => 41,
    'rows' => [
        ['rowid' => 10, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'stale-cache'],
        ['rowid' => 20, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms'],
        ['rowid' => 30, 'autoload' => 'yes', 'option_name' => 'plugin_mail', 'option_value' => 'mail'],
        ['rowid' => 40, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo'],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_exact_boundary_next176',
        'rootPage' => 17601,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'partialPredicateTerms' => [
            $range('lower(option_name)', '>=', 'plugin_'),
            $range('lower(option_name)', '<', 'plugin`'),
            $eq('autoload', 'yes'),
            $notNull('option_name'),
        ],
        'coveringColumns' => ['option_name', 'option_value', 'autoload'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 30]],
            ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 40]],
            ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '4 4', 'sample' => ['plugin_t', 90]],
        ],
    ]],
];

$current = $prepared;
$current['name'] = 'current-wp-options-stat4-exact-boundary-next176';
$current['schemaCookie'] = 1768;
$current['stat4Generation'] = 57;
$current['indexes'][0]['rootPage'] = 17688;
$current['rows'] = [
    ['rowid' => 10, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'excluded-lower-edge'],
    ['rowid' => 20, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms'],
    ['rowid' => 30, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail'],
    ['rowid' => 40, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo'],
    ['rowid' => 90, 'autoload' => 'yes', 'option_name' => 'plugin_t', 'option_value' => 'included-upper-edge'],
    ['rowid' => 100, 'autoload' => 'no', 'option_name' => 'plugin_tail', 'option_value' => 'lazy'],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext176(
    $prepared,
    $current,
    [
        $range('LOWER( option_name )', '>', 'plugin_cache'),
        $range('lower(option_name)', '<=', 'plugin_t'),
        $eq('autoload', 'yes'),
        $notNull('option_name'),
    ],
    ['option_name', 'option_value'],
);

if (in_array('--self-test', $argv, true)) {
    assert($plan['status'] === 'stat4-expression-partial-current-source-next176-ready');
    assert($plan['rangeBoundary']['lowerSeekOpcode'] === 'SeekGT');
    assert($plan['rangeBoundary']['upperFenceOpcode'] === 'IdxLE');
    assert($plan['matchedRowids'] === [20, 30, 40, 90]);
    echo "wordpress-sqlplanner-stat4-expression-partial-current-source-next176 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-sqlplanner-stat4-expression-partial-current-source-next176',
    'wordpressUse' => 'Preview copied wp_options plugin scans where an ANALYZE-refresh partial expression index must use exact exclusive/inclusive STAT4 cursor boundaries.',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'selectedIndex' => $plan['selectedPlan']['name'] ?? null,
    'rangeBoundary' => $plan['rangeBoundary'],
    'matchedRowids' => $plan['matchedRowids'],
    'boundaryRowAudit' => $plan['boundaryRowAudit'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
