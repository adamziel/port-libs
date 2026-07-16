<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

$indexes = [
    [
        'name' => 'wp_options_transient_lookup',
        'rootPage' => 81,
        'estimatedRows' => 420,
        'coveringColumns' => ['option_name', 'autoload', 'option_value'],
        'sql' => "CREATE INDEX wp_options_transient_lookup ON wp_options(lower(option_name)) WHERE option_name BETWEEN '_transient_' AND '_transient_timeout_zzzz'",
    ],
];

$predicate = [
    'operator' => 'AND',
    'terms' => [
        ['operator' => '=', 'left' => ['function' => 'lower', 'column' => 'option_name'], 'right' => '_transient_feed'],
        ['operator' => '>=', 'left' => ['column' => 'option_name'], 'right' => '_transient_'],
        ['operator' => '<=', 'left' => ['column' => 'option_name'], 'right' => '_transient_timeout_zzzz'],
    ],
];

$plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes, $predicate, [], ['option_name', 'option_value']);

echo json_encode([
    'selectedIndex' => $plan['name'] ?? null,
    'partialProof' => $plan['partial'] ?? false,
    'covering' => $plan['covering'] ?? false,
    'rootPage' => $plan['rootPage'] ?? null,
], JSON_PRETTY_PRINT) . PHP_EOL;
