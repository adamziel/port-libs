<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteJsonCanonical.php';
require_once __DIR__ . '/../src/SQLiteJsonInspection.php';
require_once __DIR__ . '/../src/SQLiteJsonValidity.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require_once __DIR__ . '/../src/SQLiteJsonExtract.php';
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteJsonTablePlan.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current = [
    'option_name' => 'wp_plugin_generated_hidden_path',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false},{"slug":"forms","priority":4,"enabled":true}]},{"name":"commerce","rules":[{"slug":"shop","priority":5,"enabled":true},{"slug":"cart","priority":6,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'active_path' => '[0].rules',
];
$next = [
    'option_name' => 'wp_plugin_generated_hidden_path',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false},{"slug":"forms","priority":4,"enabled":true}]},{"name":"commerce","rules":[{"slug":"shop","priority":5,"enabled":true},{"slug":"cart","priority":6,"enabled":true},{"slug":"coupons","priority":9,"enabled":false}]}]}}',
    'base_root' => '$.plugin.groups',
    'active_path' => '[1].rules',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedHiddenPath(
    'json_tree',
    $current,
    $next,
    'option_value',
    'base_root',
    'active_path',
    [
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
        ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[%].rules[%]'],
    ],
    [['column' => 'id']],
    [
        ['name' => 'generated_priority', 'source' => 'value', 'path' => '$.priority', 'operator' => 'BETWEEN', 'value' => [3, 6]],
        ['name' => 'generated_enabled', 'source' => 'value', 'path' => '$.enabled', 'operator' => 'IS', 'value' => 1],
        ['name' => 'generated_slug', 'source' => 'value', 'path' => '$.slug', 'operator' => 'IN', 'value' => ['forms', 'shop', 'cart'], 'usable' => false],
    ],
);

$summary = [
    'scenario' => 'application-json-table-generated-hidden-path',
    'applicationUse' => 'Copied wp_options plugin settings can store a generated hidden JSON path that moves a json_tree scan from one rules subtree to another while preserving usable generated predicates and residual slug checks until the next statement is prepared.',
    'currentRoot' => $plan['currentGeneratedHiddenPath']['composedRoot'],
    'nextRoot' => $plan['nextGeneratedHiddenPath']['composedRoot'],
    'currentCostClass' => $plan['currentGeneratedHiddenPath']['costClass'],
    'nextCostClass' => $plan['nextGeneratedHiddenPath']['costClass'],
    'currentMatchedFullkeys' => $plan['currentGeneratedHiddenPath']['matchedFullkeys'],
    'nextMatchedFullkeys' => $plan['nextGeneratedHiddenPath']['matchedFullkeys'],
    'replanReasons' => $plan['generatedHiddenPathReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON path composition, json_tree current-source rows, and generated hidden residual costing',
];

if (($argv[1] ?? '') === '--self-test') {
    if (
        $summary['currentRoot'] !== '$.plugin.groups[0].rules'
        || $summary['nextRoot'] !== '$.plugin.groups[1].rules'
        || $summary['currentCostClass'] !== 'json-table-generated-hidden-path-point'
        || $summary['nextCostClass'] !== 'json-table-generated-hidden-path-subtree'
        || $summary['nextMatchedFullkeys'] !== ['$.plugin.groups[1].rules[0]', '$.plugin.groups[1].rules[1]']
        || !in_array('json-table-generated-hidden-path-root-changed', $summary['replanReasons'], true)
    ) {
        fwrite(STDERR, "application-json-table-generated-hidden-path self-test failed\n");
        exit(1);
    }

    echo "application-json-table-generated-hidden-path self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
