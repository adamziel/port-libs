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
    'option_id' => 166,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next166',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
];
$next = [
    'option_id' => 166,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next166',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidYieldPlan(
    'json_tree',
    $current,
    $next,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => '_rowid_', 'operator' => '=', 'value' => 6],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'rowid']],
);

$payload = [
    'scenario' => 'wordpress-json-table-generated-path-rowid-cost-current-source-next166',
    'wordpressUse' => 'Copied wp_options plugin-rule previews can keep yielding the current json_tree generated-path rowid point cursor while a shifted next source is prepared separately.',
    'currentYieldDecision' => $plan['currentGeneratedPathRowidYield']['yieldDecision'],
    'currentCostClass' => $plan['currentGeneratedPathRowidYield']['costClass'],
    'currentYieldRowids' => $plan['currentGeneratedPathRowidYield']['yieldRowids'],
    'nextYieldDecision' => $plan['nextGeneratedPathRowidYield']['yieldDecision'],
    'nextCostClass' => $plan['nextGeneratedPathRowidYield']['costClass'],
    'replanReasons' => $plan['next166ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path, rowid, xBestIndex, and current-source planner profiles',
];

if (in_array('--self-test', $argv, true)) {
    if ($payload['currentYieldDecision'] !== 'yield-current-source-generated-path-rowid-covering') {
        fwrite(STDERR, "unexpected current yield decision\n");
        exit(1);
    }
    if ($payload['currentYieldRowids'] !== [6]) {
        fwrite(STDERR, "unexpected current yield rowids\n");
        exit(1);
    }
    if ($payload['nextYieldDecision'] !== 'prepare-fresh-json-table-yield') {
        fwrite(STDERR, "unexpected next yield decision\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-yield-rowset-changed', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next166 rowset replan reason\n");
        exit(1);
    }
    echo "wordpress-json-table-generated-path-rowid-cost-current-source-next166 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
