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
    'option_id' => 158,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules[1]',
];
$next = [
    'option_id' => 158,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules[2]',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSourceProfilePlan(
    'json_tree',
    $current,
    $next,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'id']],
);

$summary = [
    'scenario' => 'wordpress-json-table-generated-path-rowid-cost-current-source-next158',
    'wordpressUse' => 'Copied wp_options plugin-rule previews can keep the current json_tree() cursor pinned when a generated path and rowid seek are stable, but prepare the next cursor when the incoming option JSON shifts the path/rowid source.',
    'currentReuseDecision' => $plan['currentGeneratedPathRowidCurrentSource']['reuseDecision'],
    'currentPinnedCost' => $plan['currentGeneratedPathRowidCurrentSource']['pinnedEstimatedCost'],
    'currentPinKey' => $plan['currentGeneratedPathRowidCurrentSource']['sourcePinKey'],
    'nextReuseDecision' => $plan['nextGeneratedPathRowidCurrentSource']['reuseDecision'],
    'nextPinnedCost' => $plan['nextGeneratedPathRowidCurrentSource']['pinnedEstimatedCost'],
    'replanReasons' => $plan['next158ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table current-source planning, generated path rowid costing, JSON validation, and source fingerprinting',
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['currentReuseDecision'] === 'pin-current-source-json-table-cursor');
    assert($summary['currentPinnedCost'] === 1);
    assert($summary['nextReuseDecision'] === 'prepare-fresh-json-table-cursor');
    assert($summary['nextPinnedCost'] === 1000000);
    assert(in_array('json-table-generated-path-rowid-current-source-pin-key-changed', $summary['replanReasons'], true));
    echo "wordpress-json-table-generated-path-rowid-cost-current-source-next158 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
