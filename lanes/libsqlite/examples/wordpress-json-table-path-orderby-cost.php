<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteBlobValue.php';
require __DIR__ . '/../src/SQLiteDatabase.php';
require __DIR__ . '/../src/SQLiteJson5Parser.php';
require __DIR__ . '/../src/SQLiteJsonB.php';
require __DIR__ . '/../src/SQLiteJsonCanonical.php';
require __DIR__ . '/../src/SQLiteJsonConstructor.php';
require __DIR__ . '/../src/SQLiteJsonInspection.php';
require __DIR__ . '/../src/SQLiteJsonPath.php';
require __DIR__ . '/../src/SQLiteJsonQuote.php';
require __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require __DIR__ . '/../src/SQLiteJsonValidity.php';
require __DIR__ . '/../src/SQLiteJsonEach.php';
require __DIR__ . '/../src/SQLiteJsonTree.php';
require __DIR__ . '/../src/SQLiteJsonTablePlan.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current = [
    'option_id' => 131,
    'option_name' => 'wp_plugin_rule_path_order',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]}',
    'scan_root' => '$.rules',
];
$next = [
    'option_id' => 131,
    'option_name' => 'wp_plugin_rule_path_order',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4},{"slug":"shop","priority":5}]}',
    'scan_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourcePathOrderByCost(
    'json_tree',
    $current,
    $next,
    'option_value',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'key', 'operator' => '=', 'value' => 'priority'],
        ['column' => 'atom', 'operator' => '>=', 'value' => 3],
    ],
    'scan_root',
    [['column' => 'key'], ['column' => 'atom', 'direction' => 'DESC']],
);

echo json_encode([
    'scenario' => 'wordpress-json-table-path-orderby-cost',
    'planned' => [
        'currentCostClass' => $plan['currentPathOrderByCost']['costClass'],
        'nextCostClass' => $plan['nextPathOrderByCost']['costClass'],
        'currentEffectiveCost' => $plan['currentPathOrderByCost']['effectiveEstimatedCost'],
        'nextEffectiveCost' => $plan['nextPathOrderByCost']['effectiveEstimatedCost'],
        'currentOrderedPathTape' => $plan['currentPathOrderByCost']['orderedPathTape'],
        'nextOrderedPathTape' => $plan['nextPathOrderByCost']['orderedPathTape'],
        'nextReaderPolicy' => $plan['nextReaderPolicy'],
        'replanReasons' => $plan['next131ReplanReasons'],
    ],
    'wordpressUse' => 'Copied wp_options plugin JSON can combine path pushdown with ORDER BY cost planning so migration previews know when a json_tree() scan needs a suffix sorter after settings rows change, without ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
