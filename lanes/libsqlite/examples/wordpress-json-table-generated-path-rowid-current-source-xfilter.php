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
    'option_id' => 176,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next176',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
];
$next = [
    'option_id' => 176,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next176',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceXFilterPlan(
    'json_tree',
    $current,
    $next,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'IN', 'value' => [5, 6, 42]],
    ],
    'scan_root',
    [['column' => '_rowid_', 'direction' => 'DESC']],
);

$payload = [
    'scenario' => 'wordpress-json-table-generated-path-rowid-cost-current-source-next176',
    'wordpressUse' => 'Copied wp_options JSON rule previews can keep a generated-path and rowid xFilter pinned to the current json_tree source while a next import reprepare blocks stale row output.',
    'currentOpcode' => $plan['currentGeneratedPathRowidCurrentSourceXFilter176']['cursorOpcode'],
    'currentYieldRowids' => $plan['currentGeneratedPathRowidCurrentSourceXFilter176']['yieldRowids'],
    'currentCostClass' => $plan['currentGeneratedPathRowidCurrentSourceXFilter176']['costClass'],
    'nextOpcode' => $plan['nextGeneratedPathRowidCurrentSourceXFilter176']['cursorOpcode'],
    'nextStaleOutputBlocked' => $plan['nextGeneratedPathRowidCurrentSourceXFilter176']['staleOutputBlocked'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path rowid current-source planning and xFilter tapes',
];

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if ($payload['currentYieldRowids'] !== [6, 5]) {
        fwrite(STDERR, "unexpected next176 current rowids\n");
        exit(1);
    }
    if ($payload['nextStaleOutputBlocked'] !== true) {
        fwrite(STDERR, "expected next176 stale output block\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-xfilter-rowset-changed', $plan['next176ReplanReasons'], true)) {
        fwrite(STDERR, "missing next176 xfilter rowset replan reason\n");
        exit(1);
    }

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    echo "wordpress-json-table-generated-path-rowid-cost-current-source-next176 self-test passed\n";
}

return $payload;
