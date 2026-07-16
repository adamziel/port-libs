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
    'option_id' => 142,
    'option_name' => 'wp_plugin_generated_hidden_rowid_cost',
    'option_value' => '{"plugin":{"groups":[{"rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false},{"slug":"forms","priority":4,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$next = [
    'option_id' => 142,
    'option_name' => 'wp_plugin_generated_hidden_rowid_cost',
    'option_value' => '{"plugin":{"groups":[{"rules":[{"slug":"seo","priority":8,"enabled":true},{"slug":"cache","priority":1,"enabled":false},{"slug":"forms","priority":4,"enabled":true},{"slug":"shop","priority":5,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedHiddenRowidCost(
    'json_tree',
    $current,
    $next,
    'option_value',
    'base_root',
    'nested_path',
    [
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
        ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[0].rules[%]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 9],
    ],
    [['column' => 'id']],
    [
        ['name' => 'generated_priority', 'source' => 'value', 'path' => '$.priority', 'operator' => 'BETWEEN', 'value' => [3, 6]],
        ['name' => 'generated_enabled', 'source' => 'value', 'path' => '$.enabled', 'operator' => 'IS', 'value' => 1],
    ],
);

$summary = [
    'scenario' => 'application-json-table-generated-hidden-rowid-cost',
    'applicationUse' => 'Copied wp_options plugin-setting previews can keep a generated priority/enabled filter and a hidden rowid seek pinned to the current json_tree() source while a next import adds sibling rules.',
    'currentCostClass' => $plan['currentGeneratedHiddenRowidCost']['costClass'],
    'currentIntersectedRowids' => $plan['currentGeneratedHiddenRowidCost']['intersectedRowids'],
    'nextIntersectedRowids' => $plan['nextGeneratedHiddenRowidCost']['intersectedRowids'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'replanReasons' => $plan['generatedHiddenRowidCostReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table current-source, generated hidden-cost, and rowid alias constraint planning',
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['currentCostClass'] === 'json-table-generated-hidden-rowid-point');
    assert($summary['currentIntersectedRowids'] === [9]);
    assert($summary['nextIntersectedRowids'] === [9]);
    assert(in_array('source-json-changed', $summary['replanReasons'], true));
    echo "application-json-table-generated-hidden-rowid-cost self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
