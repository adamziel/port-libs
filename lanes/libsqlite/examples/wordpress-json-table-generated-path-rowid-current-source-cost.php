<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current = [
    'option_id' => 170,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next170',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
];
$next = [
    'option_id' => 170,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next170',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceCost(
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
    'scenario' => 'wordpress-json-table-generated-path-rowid-current-source-cost',
    'wordpressUse' => 'Copied wp_options JSON rule diagnostics can keep a generated-path and rowid-constrained json_tree cursor pinned to the current source until xFilter reset, while a changed generated path prepares a fresh virtual-table filter.',
    'currentMode' => $plan['currentGeneratedPathRowidCurrentSourceNext170']['cursorMode'],
    'currentIdxStr' => $plan['currentGeneratedPathRowidCurrentSourceNext170']['idxStr'],
    'currentRowids' => $plan['currentGeneratedPathRowidCurrentSourceNext170']['rowidTape'],
    'currentPaths' => $plan['currentGeneratedPathRowidCurrentSourceNext170']['pathTape'],
    'nextMode' => $plan['nextGeneratedPathRowidCurrentSourceNext170']['cursorMode'],
    'nextCostClass' => $plan['nextGeneratedPathRowidCurrentSourceNext170']['costClass'],
    'replanReasons' => $plan['next170ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path, rowid seek, best-index, and current-source cursor profiles',
];

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if ($payload['currentMode'] !== 'pinned-current-source-point') {
        fwrite(STDERR, "unexpected next170 current cursor mode\n");
        exit(1);
    }
    if ($payload['currentRowids'] !== [6] || $payload['currentPaths'] !== ['$.rules[1]']) {
        fwrite(STDERR, "unexpected next170 current cursor rowset\n");
        exit(1);
    }
    if ($payload['nextMode'] !== 'fresh-json-table-xfilter') {
        fwrite(STDERR, "unexpected next170 next cursor mode\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-current-source-next170-xfilter-changed', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next170 xfilter replan reason\n");
        exit(1);
    }

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    echo "wordpress-json-table-generated-path-rowid-current-source-cost self-test passed\n";
}

return $payload;
