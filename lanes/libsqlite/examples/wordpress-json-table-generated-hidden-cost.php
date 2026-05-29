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
    'option_id' => 136,
    'option_name' => 'wp_plugin_generated_hidden_cost',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false},{"slug":"forms","priority":4,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$next = [
    'option_id' => 136,
    'option_name' => 'wp_plugin_generated_hidden_cost',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":8,"enabled":true},{"slug":"cache","priority":1,"enabled":false},{"slug":"forms","priority":4,"enabled":true},{"slug":"shop","priority":5,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedHiddenCost(
    'json_tree',
    $current,
    $next,
    'option_value',
    'base_root',
    'nested_path',
    [
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
        ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[0].rules[%]'],
    ],
    [['column' => 'id']],
    [
        ['name' => 'generated_priority', 'source' => 'value', 'path' => '$.priority', 'operator' => 'BETWEEN', 'value' => [3, 6]],
        ['name' => 'generated_enabled', 'source' => 'value', 'path' => '$.enabled', 'operator' => 'IS', 'value' => 1],
    ],
);

$summary = [
    'operation' => 'json-table-generated-hidden-cost-current-source-next136',
    'optionName' => $current['option_name'],
    'currentFilteredRowids' => $plan['currentGeneratedHiddenCost']['filteredRowids'],
    'nextFilteredRowids' => $plan['nextGeneratedHiddenCost']['filteredRowids'],
    'currentCostClass' => $plan['currentGeneratedHiddenCost']['costClass'],
    'nextCostClass' => $plan['nextGeneratedHiddenCost']['costClass'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'replanReasons' => $plan['next136ReplanReasons'],
    'dependencies' => $plan['dependencies'],
    'wordpressUse' => 'Copied wp_options plugin settings can re-cost json_tree() hidden-source scans when generated priority/enabled columns filter nested JSON rules between current and next source rows.',
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if ($summary['currentFilteredRowids'] !== [9]) {
        fwrite(STDERR, "expected current generated hidden cost rowid filter\n");
        exit(1);
    }
    if ($summary['nextFilteredRowids'] !== [9, 13]) {
        fwrite(STDERR, "expected next generated hidden cost rowid filter\n");
        exit(1);
    }
    if (!in_array('sqlite-json-table-generated-hidden-cost-current-source-next136', $summary['dependencies'], true)) {
        fwrite(STDERR, "expected next136 dependency marker\n");
        exit(1);
    }
}
