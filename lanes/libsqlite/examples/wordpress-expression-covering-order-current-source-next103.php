<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteExpressionCoveringOrderCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $expression, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['expression' => $expression], 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$prepared = [
    'name' => 'prepared-expression-covering-order-next103',
    'schemaCookie' => 101,
    'stat4Generation' => 31,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_name_autoload_value_next103',
        'rootPage' => 10301,
        'estimatedRows' => 120,
        'stat4Samples' => [
            ['neq' => '1 2 2', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => ['plugin alpha', 'yes', 'a:1:{}']],
            ['neq' => '1 4 4', 'nlt' => '2 2 2', 'ndlt' => '1 1 1', 'sample' => ['plugin forms', 'yes', 'a:2:{}']],
            ['neq' => '1 6 6', 'nlt' => '6 6 6', 'ndlt' => '2 2 2', 'sample' => ['plugin seo', 'yes', 'a:3:{}']],
        ],
        'sql' => "CREATE INDEX idx_wp_options_lower_name_autoload_value_next103 ON wp_options(lower(option_name), autoload, option_value) WHERE autoload = 'yes'",
    ]],
];

$current = $prepared;
$current['name'] = 'current-expression-covering-order-next103';
$current['schemaCookie'] = 103;
$current['stat4Generation'] = 33;
$current['indexes'][0]['rootPage'] = 10310;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '1 2 2', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => ['plugin alpha', 'yes', 'a:1:{}']],
    ['neq' => '1 3 3', 'nlt' => '2 2 2', 'ndlt' => '1 1 1', 'sample' => ['plugin cache', 'yes', 'a:2:{}']],
    ['neq' => '1 5 5', 'nlt' => '5 5 5', 'ndlt' => '2 2 2', 'sample' => ['plugin forms', 'yes', 'a:3:{}']],
    ['neq' => '1 4 4', 'nlt' => '10 10 10', 'ndlt' => '3 3 3', 'sample' => ['plugin security', 'yes', 'a:4:{}']],
    ['neq' => '1 2 2', 'nlt' => '14 14 14', 'ndlt' => '4 4 4', 'sample' => ['plugin slider', 'yes', 'a:5:{}']],
];

$plan = SQLiteExpressionCoveringOrderCurrentSourceNextPlan::materialize(
    $prepared,
    $current,
    $and(
        $point('autoload', 'yes'),
        $range('lower(option_name)', '>=', 'plugin '),
        $range('lower(option_name)', '<', 'plugin z'),
    ),
    [['expression' => 'lower(option_name)', 'direction' => 'ASC']],
    ['option_name', 'autoload', 'option_value'],
);

$summary = [
    'scenario' => 'wordpress-expression-covering-order-current-source-next103',
    'selectedSource' => $plan['selectedSource'],
    'selectedIndex' => $plan['cursorTape']['indexName'],
    'rootPage' => $plan['cursorTape']['rootPage'],
    'status' => $plan['status'],
    'expression' => $plan['cursorTape']['expression'],
    'matchedExpressionKeys' => $plan['cursorTape']['matchedExpressionKeys'],
    'seekOpcode' => $plan['cursorTape']['seekOpcode'],
    'stopOpcode' => $plan['cursorTape']['stopOpcode'],
    'deferredSeekOpcode' => $plan['cursorTape']['deferredSeekOpcode'],
    'sorterOpen' => $plan['cursorTape']['sorterOpen'],
    'wordpressUse' => 'Preview copied wp_options autoloaded plugin scans after ANALYZE refresh: lower(option_name) expression index ordering is selected from the current source while the covering cursor avoids table lookups and temp sorting without ext/sqlite.',
];

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['selectedSource'] === 'current');
    assert($summary['status'] === 'expression-covering-order-current-source-ready');
    assert($summary['rootPage'] === 10310);
    assert($summary['matchedExpressionKeys'] === ['plugin alpha', 'plugin cache', 'plugin forms', 'plugin security', 'plugin slider']);
    assert($summary['deferredSeekOpcode'] === null);
    assert($summary['sorterOpen'] === false);
    echo "wordpress-expression-covering-order-current-source-next103 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
