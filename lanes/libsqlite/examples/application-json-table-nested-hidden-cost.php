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
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteJsonTablePlan.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current = [
    'option_id' => 129,
    'option_name' => 'wp_plugin_nested_hidden_cost',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false}]},{"name":"forms","rules":[{"slug":"forms","priority":4,"enabled":true},{"slug":"lead","priority":6,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$next = [
    'option_id' => 129,
    'option_name' => 'wp_plugin_nested_hidden_cost',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":3,"enabled":true},{"slug":"cache","priority":8,"enabled":false}]},{"name":"forms","rules":[{"slug":"forms","priority":4,"enabled":true},{"slug":"lead","priority":6,"enabled":true},{"slug":"spam","priority":1,"enabled":false}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[1].rules',
];

$plan = SQLiteJsonTablePlan::currentSourceNestedHiddenCost(
    'json_tree',
    $current,
    $next,
    'option_value',
    'base_root',
    'nested_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.plugin.groups[0].rules[0]'],
        ['column' => 'id', 'operator' => '=', 'value' => 2],
    ],
    [['column' => 'id']],
);

$summary = [
    'operation' => 'json-table-nested-hidden-cost-current-source-next129',
    'optionName' => $current['option_name'],
    'currentRoot' => $plan['currentNestedHiddenCost']['root'],
    'nextRoot' => $plan['nextNestedHiddenCost']['root'],
    'currentHiddenArguments' => $plan['currentNestedHiddenCost']['hiddenArguments'],
    'nextHiddenArguments' => $plan['nextNestedHiddenCost']['hiddenArguments'],
    'scanStrategy' => $plan['currentNestedHiddenCost']['scanStrategy'],
    'compositeSignature' => $plan['currentNestedHiddenCost']['compositeSignature'],
    'currentHiddenCost' => $plan['currentNestedHiddenCost']['hiddenEstimatedCost'],
    'nextHiddenCost' => $plan['nextNestedHiddenCost']['hiddenEstimatedCost'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'replanReasons' => $plan['next129ReplanReasons'],
    'dependencies' => $plan['dependencies'],
    'applicationUse' => 'Copied wp_options plugin settings can keep a hidden json/root cursor open while a nested group fragment changes; this previews when the next json_tree() plan must be costed and prepared instead of reusing the current hidden-argument tape.',
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if ($summary['currentRoot'] !== '$.plugin.groups[0].rules' || $summary['nextRoot'] !== '$.plugin.groups[1].rules') {
        fwrite(STDERR, "expected nested hidden roots\n");
        exit(1);
    }
    if ($summary['scanStrategy'] !== 'path-rowid-intersection') {
        fwrite(STDERR, "expected path-rowid intersection\n");
        exit(1);
    }
    if (!in_array('sqlite-json-table-nested-hidden-cost-current-source-next129', $summary['dependencies'], true)) {
        fwrite(STDERR, "expected next129 dependency marker\n");
        exit(1);
    }
}
