<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

$indexes = [
    [
        'name' => 'wp_options_plugin_lower_partial',
        'rootPage' => 301,
        'estimatedRows' => 900,
        'coveringColumns' => ['option_name', 'autoload', 'option_value'],
        'sql' => "CREATE INDEX wp_options_plugin_lower_partial ON wp_options(lower(option_name), autoload) WHERE lower(option_name) >= 'plugin_'",
    ],
    [
        'name' => 'wp_options_plugin_window_partial',
        'rootPage' => 305,
        'estimatedRows' => 620,
        'coveringColumns' => ['option_name', 'autoload'],
        'sql' => "CREATE INDEX wp_options_plugin_window_partial ON wp_options(lower(option_name)) WHERE lower(option_name) >= 'plugin_' AND lower(option_name) < 'plugin`'",
    ],
];

$predicate = [
    'operator' => 'AND',
    'terms' => [
        ['operator' => '>=', 'left' => ['function' => 'lower', 'column' => 'option_name'], 'right' => 'plugin_cache'],
        ['operator' => '<', 'left' => ['function' => 'lower', 'column' => 'option_name'], 'right' => 'plugin_z'],
    ],
];

$plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes, $predicate, [], ['option_name', 'autoload']);

if (($argv[1] ?? null) === '--self-test') {
    if (($plan['name'] ?? null) !== 'wp_options_plugin_window_partial') {
        fwrite(STDERR, 'Expected wp_options_plugin_window_partial' . PHP_EOL);
        exit(1);
    }
    if (($plan['partial'] ?? null) !== true || ($plan['operator'] ?? null) !== 'range->=') {
        fwrite(STDERR, 'Expected partial expression range proof' . PHP_EOL);
        exit(1);
    }

    echo 'application-planner-expression-partial-index-current-next30 self-test passed' . PHP_EOL;
    exit(0);
}

echo json_encode([
    'scenario' => 'application-planner-expression-partial-index-current-next30',
    'selected_index' => $plan['name'] ?? null,
    'root_page' => $plan['rootPage'] ?? null,
    'partial' => $plan['partial'] ?? null,
    'operator' => $plan['operator'] ?? null,
    'estimated_rows' => $plan['estimatedRows'] ?? null,
    'without_ext_sqlite' => true,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
