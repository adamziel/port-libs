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
require_once __DIR__ . '/../src/SQLiteJsonTablePlan.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current = [
    'option_id' => 160,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_source',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
];
$next = [
    'option_id' => 160,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_source',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSourcePlan(
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
    'scenario' => 'application-json-table-generated-path-rowid-cost-current-source-next160',
    'applicationUse' => 'Copied wp_options plugin-rule previews can pin a generated JSON path and rowid alias seek to the current json_tree() source, while fingerprinting the next source before a virtual-table filter reset.',
    'currentCostClass' => $plan['currentGeneratedPathRowidCostSource']['costClass'],
    'currentIntersectedRowids' => $plan['currentGeneratedPathRowidCostSource']['intersectedRowids'],
    'nextCostClass' => $plan['nextGeneratedPathRowidCostSource']['costClass'],
    'omitColumns' => $plan['currentGeneratedPathRowidCostSource']['omitColumns'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'replanReasons' => $plan['next160ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table current-source, generated-path rowid-cost, JSON1/JSONB, and path validation helpers',
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['currentCostClass'] === 'json-table-generated-path-rowid-point');
    assert($summary['currentIntersectedRowids'] === [6]);
    assert($summary['nextCostClass'] === 'json-table-generated-path-rowid-empty');
    assert($summary['omitColumns'] === ['path', 'id']);
    assert(in_array('json-table-generated-path-rowid-cost-source-fingerprint-changed', $summary['replanReasons'], true));
    echo "application-json-table-generated-path-rowid-cost-current-source-next160 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
