<?php

declare(strict_types=1);

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
    'option_id' => 121,
    'option_name' => 'wp_plugin_nested_rules',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7}]},{"name":"forms","rules":[{"slug":"forms","priority":4}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$next = [
    'option_id' => 121,
    'option_name' => 'wp_plugin_nested_rules',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":3},{"slug":"cache","priority":8}]},{"name":"forms","rules":[{"slug":"forms","priority":4},{"slug":"lead","priority":6}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[1].rules',
];

$plan = SQLiteJsonTablePlan::currentSourceNestedPathPlanner(
    'json_tree',
    $current,
    $next,
    'option_value',
    'base_root',
    'nested_path',
    [
        ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
        ['column' => 'key', 'operator' => '=', 'value' => 'priority'],
    ],
    [['column' => 'atom', 'direction' => 'DESC']],
);

$summary = [
    'operation' => 'json-table-nested-path-planner-current-source-next121',
    'optionName' => $current['option_name'],
    'currentRoot' => $plan['currentNestedPath']['root'],
    'nextRoot' => $plan['nextNestedPath']['root'],
    'currentPriorities' => array_column($plan['currentRows'], 'atom'),
    'nextPriorities' => array_column($plan['nextRows'], 'atom'),
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'replanReasons' => $plan['next121ReplanReasons'],
    'dependencies' => $plan['dependencies'],
    'applicationUse' => 'Copied wp_options plugin settings can store a base JSON root and per-request nested path fragment; native JSON table planning composes the current and next roots before scanning json_tree() priority leaves.',
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if ($summary['currentRoot'] !== '$.plugin.groups[0].rules' || $summary['nextRoot'] !== '$.plugin.groups[1].rules') {
        fwrite(STDERR, "expected composed nested roots\n");
        exit(1);
    }
    if ($summary['currentPriorities'] !== [7, 2] || $summary['nextPriorities'] !== [6, 4]) {
        fwrite(STDERR, "expected ordered priority leaves\n");
        exit(1);
    }
    if (!in_array('sqlite-json-table-nested-path-planner-current-source-next121', $summary['dependencies'], true)) {
        fwrite(STDERR, "expected nested path dependency marker\n");
        exit(1);
    }
}
