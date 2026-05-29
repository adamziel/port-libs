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
    'option_id' => 134,
    'option_name' => 'wp_plugin_generated_path',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules[1]',
];
$next = [
    'option_id' => 134,
    'option_name' => 'wp_plugin_generated_path',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}]}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules[2]',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathCost(
    'json_tree',
    $current,
    $next,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'key', 'operator' => 'IN', 'value' => ['slug', 'priority']],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'id']],
);

echo json_encode([
    'scenario' => 'wordpress-json-table-generated-path-cost',
    'planned' => [
        'currentGeneratedPath' => $plan['currentGeneratedPathCost']['generatedPath'],
        'nextGeneratedPath' => $plan['nextGeneratedPathCost']['generatedPath'],
        'currentCostClass' => $plan['currentGeneratedPathCost']['costClass'],
        'nextCostClass' => $plan['nextGeneratedPathCost']['costClass'],
        'currentCoveredPathTape' => $plan['currentGeneratedPathCost']['coveredPathTape'],
        'nextCoveredPathTape' => $plan['nextGeneratedPathCost']['coveredPathTape'],
        'nextReaderPolicy' => $plan['nextReaderPolicy'],
        'replanReasons' => $plan['next134ReplanReasons'],
    ],
    'wordpressUse' => 'Copied wp_options rows with a generated JSON path column can feed json_tree() path-cost planning so migration previews detect when a generated path no longer covers the selected path after plugin settings are rewritten, without ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
