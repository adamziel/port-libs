<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteExpressionIndexPartialCollationStat4CurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $expression, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['expression' => $expression], 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$prepared = [
    'name' => 'prepared-partial-collation-stat4-next114',
    'schemaCookie' => 1140,
    'stat4Generation' => 41,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_name_autoload_nocase_next114',
        'rootPage' => 11401,
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['Plugin Alpha', 11]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin cache', 12]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['Plugin Forms', 13]],
            ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin SEO', 14]],
        ],
        'sql' => "CREATE INDEX idx_wp_options_lower_name_autoload_nocase_next114 ON wp_options(lower(option_name) COLLATE NOCASE, option_id) WHERE autoload = 'yes'",
    ]],
];

$current = $prepared;
$current['name'] = 'current-partial-collation-stat4-next114';
$current['schemaCookie'] = 1144;
$current['stat4Generation'] = 44;
$current['indexes'][0]['rootPage'] = 11410;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['Plugin Alpha', 21]],
    ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin cache', 22]],
    ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['Plugin Forms', 23]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin Security', 24]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '4 4', 'sample' => ['Plugin SEO', 25]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '5 5', 'sample' => ['plugin Slider', 26]],
];

$plan = SQLiteExpressionIndexPartialCollationStat4CurrentSourceNextPlan::materialize(
    $prepared,
    $current,
    $and(
        $point('autoload', 'yes'),
        $range('lower(option_name)', '>=', 'plugin cache'),
        $range('lower(option_name)', '<', 'plugin t'),
    ),
    [['expression' => 'lower(option_name)', 'direction' => 'ASC', 'collation' => 'NOCASE']],
);

$summary = [
    'scenario' => 'wordpress-expression-index-partial-collation-stat4-current-source-next114',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'selectedIndex' => $plan['cursorTape']['indexName'],
    'rootPage' => $plan['cursorTape']['rootPage'],
    'collation' => $plan['cursorTape']['collation'],
    'matchedKeys' => $plan['cursorTape']['matchedKeys'],
    'matchedRowids' => $plan['cursorTape']['matchedRowids'],
    'seekOpcode' => $plan['cursorTape']['seekOpcode'],
    'stopOpcode' => $plan['cursorTape']['stopOpcode'],
    'wordpressUse' => 'Preview copied wp_options autoloaded plugin scans after ANALYZE: a partial lower(option_name) COLLATE NOCASE expression index is selected from the current source, using STAT4 boundaries without requiring ext/sqlite.',
];

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['status'] === 'expression-index-partial-collation-stat4-current-source-ready');
    assert($summary['selectedSource'] === 'current');
    assert($summary['rootPage'] === 11410);
    assert($summary['collation'] === 'NOCASE');
    assert($summary['matchedRowids'] === [22, 23, 24, 25, 26]);
    echo "wordpress-expression-index-partial-collation-stat4-current-source-next114 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
