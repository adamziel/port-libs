<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteCreateIndex.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLiteIndexPredicate.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJsonExtractIndexExpression.php';
require_once __DIR__ . '/../src/SQLiteSelectExpressionIndexPlan.php';

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

$indexes = [
    [
        'name' => 'idx_wp_option_mode_plugin_stat4',
        'rootPage' => 5103,
        'estimatedRows' => 21,
        'coveringColumns' => ['option_name'],
        'coveringExpressions' => [
            ['function' => 'json_text_operator', 'column' => 'option_value', 'path' => '$.plugin'],
            ['function' => 'json_value_operator', 'column' => 'option_value', 'path' => '$.enabled'],
        ],
        'stat4Samples' => [
            ['neq' => '3 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['enabled', 'plugin_alpha']],
            ['neq' => '7 2', 'nlt' => '3 1', 'ndlt' => '1 1', 'sample' => ['network', 'plugin_beta']],
            ['neq' => '2 1', 'nlt' => '10 3', 'ndlt' => '2 2', 'sample' => ['private', 'plugin_delta']],
            ['neq' => '9 3', 'nlt' => '12 4', 'ndlt' => '3 3', 'sample' => ['public', 'plugin_gamma']],
        ],
        'sql' => "CREATE INDEX idx_wp_option_mode_plugin_stat4 ON wp_options(json_extract(option_value,'$.mode'), option_name)",
    ],
];

$plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost(
    $indexes,
    [
        'operator' => '=',
        'left' => ['function' => 'json_extract', 'column' => 'option_value', 'path' => '$.mode'],
        'right' => 'network',
    ],
    [],
    ['option_name'],
    [
        ['function' => 'json_text_operator', 'column' => 'option_value', 'path' => '$.plugin'],
        ['function' => 'json_value_operator', 'column' => 'option_value', 'path' => '$.enabled'],
    ],
);

$summary = [
    'applicationScenario' => 'Copied wp_options plugin settings can satisfy json_extract(option_value,$.mode) lookup and projected JSON operator expressions from the same STAT4-estimated expression index without decoding the table row.',
    'index' => $plan['name'] ?? null,
    'rootPage' => $plan['rootPage'] ?? null,
    'covering' => $plan['covering'] ?? false,
    'coveringExpressions' => $plan['coveringExpressions'] ?? [],
    'stat4Used' => $plan['stat4Used'] ?? false,
    'estimatedRows' => $plan['estimatedRows'] ?? null,
    'estimatedCost' => $plan['estimatedCost'] ?? null,
    'firstCurrentNext' => $plan['stat4CurrentNext'][0] ?? null,
];

if (in_array('--self-test', $argv, true)) {
    if (
        $summary['index'] !== 'idx_wp_option_mode_plugin_stat4'
        || $summary['covering'] !== true
        || $summary['coveringExpressions'] !== ['json_text_operator(option_value,$.plugin)', 'json_value_operator(option_value,$.enabled)']
        || $summary['stat4Used'] !== true
        || $summary['estimatedRows'] !== 7
    ) {
        fwrite(STDERR, "application-planner-stat4-covering-expression-current-next51 self-test failed\n");
        exit(1);
    }

    echo "application-planner-stat4-covering-expression-current-next51 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
