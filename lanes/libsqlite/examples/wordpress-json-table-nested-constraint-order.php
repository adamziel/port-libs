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
    'option_id' => 127,
    'option_name' => 'wp_plugin_nested_rule_order',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]},{"name":"forms","rules":[{"slug":"forms","priority":4},{"slug":"lead","priority":6}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$next = [
    'option_id' => 127,
    'option_name' => 'wp_plugin_nested_rule_order',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":3},{"slug":"cache","priority":8},{"slug":"shop","priority":5}]},{"name":"forms","rules":[{"slug":"forms","priority":4},{"slug":"lead","priority":6},{"slug":"mail","priority":1}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[1].rules',
];

$plan = SQLiteJsonTablePlan::currentSourceNestedConstraintOrder(
    'json_tree',
    $current,
    $next,
    'option_value',
    'base_root',
    'nested_path',
    [
        ['column' => 'key', 'operator' => '=', 'value' => 'priority'],
        ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
    ],
    [['column' => 'key'], ['column' => 'atom', 'direction' => 'DESC']],
);

$summary = [
    'operation' => 'json-table-nested-constraint-order-current-source-next127',
    'optionName' => $current['option_name'],
    'currentRoot' => $plan['currentNestedConstraintOrder']['root'],
    'nextRoot' => $plan['nextNestedConstraintOrder']['root'],
    'currentPrefix' => $plan['currentNestedConstraintOrder']['consumedPrefixColumns'],
    'nextPrefix' => $plan['nextNestedConstraintOrder']['consumedPrefixColumns'],
    'currentSuffix' => $plan['currentNestedConstraintOrder']['suffixColumns'],
    'nextSuffix' => $plan['nextNestedConstraintOrder']['suffixColumns'],
    'currentPriorities' => array_column($plan['currentRows'], 'atom'),
    'nextPriorities' => array_column($plan['nextRows'], 'atom'),
    'currentRootOrderKey' => $plan['currentNestedConstraintOrder']['rootOrderKey'],
    'nextRootOrderKey' => $plan['nextNestedConstraintOrder']['rootOrderKey'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'replanReasons' => $plan['next127ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table path composition, constraint pushdown, and partial ORDER BY cost planners',
    'wordpressUse' => 'Copied wp_options plugin settings can keep a base JSON path and per-request nested path while the JSON virtual-table planner still recognizes constant key constraints and charges only the priority suffix sort.',
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if ($summary['currentRoot'] !== '$.plugin.groups[0].rules' || $summary['nextRoot'] !== '$.plugin.groups[1].rules') {
        fwrite(STDERR, "expected composed nested JSON table roots\n");
        exit(1);
    }
    if ($summary['currentPrefix'] !== ['key'] || $summary['currentSuffix'] !== ['atom']) {
        fwrite(STDERR, "expected key prefix and atom suffix ORDER BY coverage\n");
        exit(1);
    }
    if ($summary['currentPriorities'] !== [7, 4, 2] || $summary['nextPriorities'] !== [6, 4, 1]) {
        fwrite(STDERR, "expected nested priority rows ordered by atom DESC\n");
        exit(1);
    }
}
