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
    'option_id' => 157,
    'option_name' => 'wp_plugin_hidden_generated_rowid',
    'option_value' => '{"rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":true},{"slug":"forms","priority":4,"enabled":false}],"meta":{"version":1}}',
    'scan_root' => '$.rules',
];
$next = [
    'option_id' => 157,
    'option_name' => 'wp_plugin_hidden_generated_rowid',
    'option_value' => '{"rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":9,"enabled":false},{"slug":"forms","priority":4,"enabled":false},{"slug":"shop","priority":6,"enabled":true}],"meta":{"version":2}}',
    'scan_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourceHiddenGeneratedRowid(
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
    [['column' => 'path'], ['column' => 'id']],
    [
        ['name' => 'slug', 'source' => 'value', 'path' => '$.slug', 'value' => 'cache'],
        ['name' => 'priority', 'source' => 'value', 'path' => '$.priority', 'operator' => 'BETWEEN', 'value' => [5, 9]],
        ['name' => 'enabled', 'source' => 'value', 'path' => '$.enabled', 'operator' => '=', 'value' => 1],
    ],
);

$summary = [
    'scenario' => 'application-json-table-hidden-generated-rowid',
    'applicationUse' => 'Copied wp_options plugin-setting previews can pin a json_tree() hidden path seek, generated-column filters, and a rowid alias lookup to the current source while the next import mutates sibling JSON values.',
    'currentCostClass' => $plan['currentHiddenGeneratedRowid']['costClass'],
    'currentIntersectedRowids' => $plan['currentHiddenGeneratedRowid']['intersectedRowids'],
    'nextCostClass' => $plan['nextHiddenGeneratedRowid']['costClass'],
    'nextIntersectedRowids' => $plan['nextHiddenGeneratedRowid']['intersectedRowids'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'replanReasons' => $plan['hiddenGeneratedRowidReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table hidden path, generated filter, and rowid alias current-source planning',
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['currentCostClass'] === 'json-table-hidden-generated-rowid-point-current-source');
    assert($summary['currentIntersectedRowids'] === [5]);
    assert($summary['nextCostClass'] === 'json-table-hidden-generated-rowid-empty-current-source');
    assert($summary['nextIntersectedRowids'] === []);
    assert(in_array('json-table-hidden-generated-rowid-rowset-changed', $summary['replanReasons'], true));
    echo "application-json-table-hidden-generated-rowid self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
