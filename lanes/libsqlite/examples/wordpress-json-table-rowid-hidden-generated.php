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
    'option_id' => 149,
    'option_name' => 'wp_plugin_rowid_hidden_generated',
    'option_value' => '{"plugin":{"groups":[{"rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false},{"slug":"forms","priority":4,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$next = [
    'option_id' => 149,
    'option_name' => 'wp_plugin_rowid_hidden_generated',
    'option_value' => '{"plugin":{"groups":[{"rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false},{"slug":"forms","priority":6,"enabled":true},{"slug":"shop","priority":5,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];

$plan = SQLiteJsonTablePlan::currentSourceRowidHiddenGenerated(
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
        ['name' => 'generated_slug', 'source' => 'value', 'path' => '$.slug', 'operator' => 'IN', 'value' => ['forms', 'shop']],
    ],
    ['generated_slug', 'generated_priority', 'generated_enabled'],
);

$summary = [
    'scenario' => 'wordpress-json-table-rowid-hidden-generated',
    'wordpressUse' => 'Copied wp_options plugin-setting previews can pin a json_tree rowid while generated hidden columns expose slug, priority, and enabled values across a next import.',
    'currentCostClass' => $plan['currentRowidHiddenGenerated']['costClass'],
    'currentRows' => $plan['currentRowidHiddenGenerated']['generatedRows'],
    'nextRows' => $plan['nextRowidHiddenGenerated']['generatedRows'],
    'fingerprintChanged' => $plan['currentRowidHiddenGenerated']['combinedGeneratedFingerprint'] !== $plan['nextRowidHiddenGenerated']['combinedGeneratedFingerprint'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'replanReasons' => $plan['rowidHiddenGeneratedReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table hidden rowid and generated hidden-value planning',
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['currentCostClass'] === 'json-table-rowid-hidden-generated-covering-point');
    assert($summary['currentRows'][0]['rowid'] === 9);
    assert($summary['currentRows'][0]['values']['generated_priority'] === 4);
    assert($summary['nextRows'][0]['values']['generated_priority'] === 6);
    assert($summary['fingerprintChanged'] === true);
    assert(in_array('json-table-rowid-hidden-generated-values-changed', $summary['replanReasons'], true));
    echo "wordpress-json-table-rowid-hidden-generated self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
