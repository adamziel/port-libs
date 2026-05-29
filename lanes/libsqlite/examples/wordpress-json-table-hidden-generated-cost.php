<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteJsonCanonical.php';
require_once __DIR__ . '/../src/SQLiteJsonInspection.php';
require_once __DIR__ . '/../src/SQLiteJsonValidity.php';
require_once __DIR__ . '/../src/SQLiteJsonExtract.php';
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require_once __DIR__ . '/../src/SQLiteJsonTablePlan.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current = [
    'option_id' => 148,
    'option_name' => 'wp_plugin_hidden_generated_cost',
    'option_value' => '{"rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":true},{"slug":"forms","priority":4,"enabled":false}],"meta":{"version":1}}',
    'scan_root' => '$.rules',
];
$next = [
    'option_id' => 148,
    'option_name' => 'wp_plugin_hidden_generated_cost',
    'option_value' => '{"rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":8,"enabled":false},{"slug":"forms","priority":4,"enabled":false},{"slug":"shop","priority":8,"enabled":true}],"meta":{"version":2}}',
    'scan_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourceHiddenGeneratedCost(
    'json_tree',
    $current,
    $next,
    'option_value',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 5],
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'rowid']],
    [
        ['name' => 'slug', 'path' => '$.slug', 'value' => 'cache'],
        ['name' => 'priority', 'path' => '$.priority', 'operator' => 'BETWEEN', 'value' => [5, 9]],
        ['name' => 'enabled', 'path' => '$.enabled', 'operator' => '=', 'value' => 1],
    ],
);

$summary = [
    'scenario' => 'wordpress-json-table-hidden-generated-cost',
    'wordpressUse' => 'Copied wp_options plugin-setting previews can keep json_tree hidden path/rowid constraints and generated JSON filters costed as a single xBestIndex-style seek while the next import mutates generated values.',
    'currentCostClass' => $plan['currentHiddenGeneratedCost']['costClass'],
    'nextCostClass' => $plan['nextHiddenGeneratedCost']['costClass'],
    'currentEstimatedRows' => $plan['currentHiddenGeneratedCost']['estimatedRows'],
    'nextEstimatedRows' => $plan['nextHiddenGeneratedCost']['estimatedRows'],
    'omitColumns' => $plan['currentHiddenGeneratedCost']['omitColumns'],
    'replanReasons' => $plan['hiddenGeneratedCostReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table hidden path/rowid source seeks and generated JSON value extraction',
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['currentCostClass'] === 'json-table-hidden-generated-cost-covering-point');
    assert($summary['nextCostClass'] === 'json-table-hidden-generated-cost-filtered');
    assert($summary['currentEstimatedRows'] === 1);
    assert($summary['nextEstimatedRows'] === 0);
    assert($summary['omitColumns'] === ['path', 'id']);
    assert(in_array('json-table-hidden-generated-cost-values-changed', $summary['replanReasons'], true));
    echo "wordpress-json-table-hidden-generated-cost self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
