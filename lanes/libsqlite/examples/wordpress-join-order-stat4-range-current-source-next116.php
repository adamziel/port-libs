<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJoinOrderStat4RangeCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteCreateIndex.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLiteMultiColumnRangePlan.php';
require_once __DIR__ . '/../src/SQLiteJoinOrderStat4RangeCurrentSourceNextPlan.php';

$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];
$join = static fn (string $leftTable, string $leftColumn, string $rightTable, string $rightColumn): array => [
    'leftTable' => $leftTable,
    'leftColumn' => $leftColumn,
    'rightTable' => $rightTable,
    'rightColumn' => $rightColumn,
];

$source = [
    'name' => 'wp-import-before-analyze',
    'schemaCookie' => 1160,
    'stat4Generation' => 50,
    'tables' => ['wp_posts', 'wp_postmeta', 'wp_options'],
    'tableRows' => ['wp_posts' => 30000, 'wp_postmeta' => 240000, 'wp_options' => 12000],
    'joinTerms' => [
        $join('wp_posts', 'ID', 'wp_postmeta', 'post_id'),
        $join('wp_postmeta', 'meta_value', 'wp_options', 'option_name'),
    ],
    'predicates' => [
        'wp_posts' => $and($range('post_date', '>=', '2026-01-01'), $range('post_date', '<', '2027-01-01')),
        'wp_postmeta' => $and($range('meta_key', '>=', '_plugin_'), $range('meta_key', '<', '_plugin_z')),
        'wp_options' => $and($range('option_name', '>=', 'plugin_'), $range('option_name', '<', 'plugin_z')),
    ],
    'neededColumns' => [
        'wp_posts' => ['post_date', 'ID'],
        'wp_postmeta' => ['meta_key', 'post_id', 'meta_value'],
        'wp_options' => ['option_name', 'autoload'],
    ],
    'indexes' => [
        'wp_posts' => [[
            'name' => 'idx_posts_date_id_next116',
            'estimatedRows' => 30000,
            'rootPage' => 310,
            'stat4Samples' => [
                ['neq' => '1200 1200', 'nlt' => '300 300', 'ndlt' => '1 1', 'sample' => ['2026-01-01', 200]],
                ['neq' => '3600 3600', 'nlt' => '1500 1500', 'ndlt' => '2 2', 'sample' => ['2026-06-01', 900]],
                ['neq' => '4200 4200', 'nlt' => '5100 5100', 'ndlt' => '3 3', 'sample' => ['2026-12-01', 1800]],
                ['neq' => '800 800', 'nlt' => '9300 9300', 'ndlt' => '4 4', 'sample' => ['2027-01-01', 2400]],
            ],
            'sql' => 'CREATE INDEX idx_posts_date_id_next116 ON wp_posts(post_date, ID)',
        ]],
        'wp_postmeta' => [[
            'name' => 'idx_postmeta_key_post_value_next116',
            'estimatedRows' => 240000,
            'rootPage' => 420,
            'stat4Samples' => [
                ['neq' => '900 900 900', 'nlt' => '1000 1000 1000', 'ndlt' => '1 1 1', 'sample' => ['_plugin_alpha', 20, 'plugin_alpha']],
                ['neq' => '1400 1400 1400', 'nlt' => '1900 1900 1900', 'ndlt' => '2 2 2', 'sample' => ['_plugin_beta', 30, 'plugin_beta']],
                ['neq' => '2200 2200 2200', 'nlt' => '3300 3300 3300', 'ndlt' => '3 3 3', 'sample' => ['_plugin_omega', 40, 'plugin_omega']],
            ],
            'sql' => 'CREATE INDEX idx_postmeta_key_post_value_next116 ON wp_postmeta(meta_key, post_id, meta_value)',
        ]],
        'wp_options' => [[
            'name' => 'idx_options_name_autoload_next116_old',
            'estimatedRows' => 12000,
            'rootPage' => 510,
            'stat4Samples' => [
                ['neq' => '10 10', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_alpha', 'yes']],
                ['neq' => '13 13', 'nlt' => '11 11', 'ndlt' => '2 2', 'sample' => ['plugin_omega', 'no']],
            ],
            'sql' => 'CREATE INDEX idx_options_name_autoload_next116_old ON wp_options(autoload, option_name)',
        ]],
    ],
];

$current = $source;
$current['name'] = 'wp-import-after-plugin-analyze';
$current['schemaCookie'] = 1161;
$current['stat4Generation'] = 51;
$current['indexes']['wp_options'][0]['name'] = 'idx_options_name_autoload_next116';
$current['indexes']['wp_options'][0]['rootPage'] = 512;
$current['indexes']['wp_options'][0]['sql'] = 'CREATE INDEX idx_options_name_autoload_next116 ON wp_options(option_name, autoload)';
$current['indexes']['wp_options'][0]['stat4Samples'] = [
    ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_alpha', 'yes']],
    ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_beta', 'yes']],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_gamma', 'no']],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '4 4', 'sample' => ['plugin_omega', 'no']],
];

$plan = SQLiteJoinOrderStat4RangeCurrentSourceNextPlan::materialize($source, $current);
$output = [
    'scenario' => 'wordpress-join-order-stat4-range-current-source-next116',
    'selectedSource' => $plan['selectedSource'],
    'reprepareRequired' => $plan['reprepareRequired'],
    'joinOrder' => $plan['selectedPlan']['tables'],
    'firstLoop' => $plan['selectedPlan']['loops'][0]['index'],
    'estimatedRows' => $plan['selectedPlan']['loops'][0]['estimatedRows'],
    'wordpressUse' => 'Preview copied WordPress plugin-import joins where ANALYZE refreshes sqlite_stat4 enough to start from wp_options before probing postmeta/posts.',
];

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if (($output['selectedSource'] ?? null) !== 'current') {
    fwrite(STDERR, "expected current source\n");
    exit(1);
}
if (($output['joinOrder'][0] ?? null) !== 'wp_options') {
    fwrite(STDERR, "expected wp_options outer loop\n");
    exit(1);
}

echo "wordpress-join-order-stat4-range-current-source-next116 self-test passed\n";
