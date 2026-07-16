<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current = [
    'option_id' => 170,
    'option_name' => 'wp_plugin_generated_path_rowid_current_source_cost',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
];
$next = [
    'option_id' => 170,
    'option_name' => 'wp_plugin_generated_path_rowid_current_source_cost',
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
    'scenario' => 'application-json-table-generated-path-rowid-current-source-cost',
    'applicationUse' => 'Copied wp_options JSON rule diagnostics can keep a generated-path and rowid-constrained json_tree cursor pinned to the current source until xFilter reset, while a changed generated path prepares a fresh virtual-table filter.',
    'currentMode' => $plan['currentGeneratedPathRowidCurrentSource']['cursorMode'],
    'currentIdxStr' => $plan['currentGeneratedPathRowidCurrentSource']['idxStr'],
    'currentRowids' => $plan['currentGeneratedPathRowidCurrentSource']['rowidTape'],
    'currentPaths' => $plan['currentGeneratedPathRowidCurrentSource']['pathTape'],
    'nextMode' => $plan['nextGeneratedPathRowidCurrentSource']['cursorMode'],
    'nextCostClass' => $plan['nextGeneratedPathRowidCurrentSource']['costClass'],
    'replanReasons' => $plan['generatedPathRowidCurrentSourceReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path, rowid seek, best-index, and current-source cursor profiles',
];

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if ($payload['currentMode'] !== 'pinned-current-source-point') {
        fwrite(STDERR, "unexpected current-source current cursor mode\n");
        exit(1);
    }
    if ($payload['currentRowids'] !== [6] || $payload['currentPaths'] !== ['$.rules[1]']) {
        fwrite(STDERR, "unexpected current-source current cursor rowset\n");
        exit(1);
    }
    if ($payload['nextMode'] !== 'fresh-json-table-xfilter') {
        fwrite(STDERR, "unexpected current-source next cursor mode\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-current-source-xfilter-changed', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing current-source xfilter replan reason\n");
        exit(1);
    }

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    echo "application-json-table-generated-path-rowid-current-source-cost self-test passed\n";
}

return $payload;
