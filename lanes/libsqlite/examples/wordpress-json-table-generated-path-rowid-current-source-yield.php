<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOption = [
    'option_id' => 168,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next168',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules',
];
$nextOption = [
    'option_id' => 168,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next168',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4},{"slug":"search","priority":5}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.missing',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceBatchYieldPlan(
    'json_tree',
    $currentOption,
    $nextOption,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 9, 11]],
    ],
    'scan_root',
    [['column' => 'id', 'direction' => 'ASC']],
    null,
    2,
    0,
);

$payload = [
    'scenario' => 'wordpress-json-table-generated-path-rowid-cost-current-source-next168',
    'wordpressUse' => 'Copied wp_options JSON rule diagnostics can yield a pinned json_tree current-source rowid batch with a stable resume token while forcing reprepare when the next imported option changes generated path coverage.',
    'currentYieldRowids' => $plan['currentGeneratedPathRowidCurrentSourceYield']['yieldRowids'],
    'currentResumeOffset' => $plan['currentGeneratedPathRowidCurrentSourceYield']['resumeOffset'],
    'currentCursorDisposition' => $plan['currentGeneratedPathRowidCurrentSourceYield']['cursorDisposition'],
    'nextCursorDisposition' => $plan['nextGeneratedPathRowidCurrentSourceYield']['cursorDisposition'],
    'currentCostClass' => $plan['currentGeneratedPathRowidCurrentSourceYield']['costClass'],
    'replanReasons' => $plan['next168ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path, rowid seek, current-source admission, and ORDER BY profiles',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($payload['currentYieldRowids'] !== [5, 6]) {
        fwrite(STDERR, "unexpected next168 yielded rowids\n");
        exit(1);
    }
    if ($payload['currentResumeOffset'] !== 2) {
        fwrite(STDERR, "unexpected next168 resume offset\n");
        exit(1);
    }
    if ($payload['currentCursorDisposition'] !== 'yield-current-source-resumable-batch') {
        fwrite(STDERR, "unexpected next168 current disposition\n");
        exit(1);
    }
    if ($payload['nextCursorDisposition'] !== 'reprepare-json-table-cursor-before-yield') {
        fwrite(STDERR, "unexpected next168 next disposition\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-current-source-yield-rowset-changed', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next168 yield rowset replan reason\n");
        exit(1);
    }

    echo "wordpress-json-table-generated-path-rowid-cost-current-source-next168 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
