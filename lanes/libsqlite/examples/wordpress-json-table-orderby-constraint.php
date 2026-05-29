<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteDatabase.php';
require __DIR__ . '/../src/SQLiteBlobValue.php';
require __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require __DIR__ . '/../src/SQLiteJson5Parser.php';
require __DIR__ . '/../src/SQLiteJsonValidity.php';
require __DIR__ . '/../src/SQLiteJsonPath.php';
require __DIR__ . '/../src/SQLiteJsonB.php';
require __DIR__ . '/../src/SQLiteJsonCanonical.php';
require __DIR__ . '/../src/SQLiteJsonInspection.php';
require __DIR__ . '/../src/SQLiteJsonEach.php';
require __DIR__ . '/../src/SQLiteJsonTree.php';
require __DIR__ . '/../src/SQLiteJsonTablePlan.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current = [
    'option_id' => 401,
    'option_name' => 'wp_plugin_rule_order',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]}',
    'scan_root' => '$.rules',
];
$next = array_replace($current, [
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4},{"slug":"shop","priority":5}]}',
]);

$plan = SQLiteJsonTablePlan::currentSourceOrderByConstraint(
    'json_tree',
    $current,
    $next,
    'option_value',
    [
        ['column' => 'key', 'operator' => '=', 'value' => 'priority'],
        ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
    ],
    'scan_root',
    [['column' => 'key', 'direction' => 'DESC'], ['column' => 'id', 'direction' => 'ASC']],
);

assert($plan['current']['orderByConsumed'] === true);
assert($plan['next']['orderByConsumed'] === true);
assert($plan['currentCostOrder']['requiresSorter'] === false);
assert($plan['nextCostOrder']['requiresSorter'] === false);
assert($plan['currentOrderConstraintCoverage'][0]['reason'] === 'constant-visible-constraint');

echo json_encode([
    'scenario' => 'wordpress-json-table-orderby-constraint',
    'currentRows' => count($plan['currentRows']),
    'nextRows' => count($plan['nextRows']),
    'currentOrderByConsumed' => $plan['current']['orderByConsumed'],
    'nextOrderByConsumed' => $plan['next']['orderByConsumed'],
    'currentRequiresSorter' => $plan['currentCostOrder']['requiresSorter'],
    'nextRequiresSorter' => $plan['nextCostOrder']['requiresSorter'],
    'coverage' => $plan['currentOrderConstraintCoverage'],
    'wordpressUse' => 'Copied wp_options JSON diagnostics can skip a redundant sorter when ORDER BY terms are fixed by pushed json_tree() visible constraints, while the current source remains pinned until cursor reset.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
