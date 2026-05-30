<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOption = [
    'option_id' => 164,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next164',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules',
];
$nextOption = [
    'option_id' => 164,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next164',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules[2]',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceOrderPlan(
    'json_tree',
    $currentOption,
    $nextOption,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'IN', 'value' => [5, 6, 42]],
    ],
    'scan_root',
    [['column' => '_rowid_', 'direction' => 'DESC']],
    1,
);

$payload = [
    'scenario' => 'application-json-table-generated-path-rowid-cost-current-source-next164',
    'applicationUse' => 'Copied wp_options JSON rule diagnostics can satisfy rowid ORDER BY from the pinned current-source json_tree seek tape and cap LIMIT cost without allocating a temp sorter.',
    'currentIdxStr' => $plan['currentGeneratedPathRowidCurrentSourceOrder']['idxStr'],
    'currentOrderByConsumed' => $plan['currentGeneratedPathRowidCurrentSourceOrder']['orderByConsumed'],
    'currentScanDirection' => $plan['currentGeneratedPathRowidCurrentSourceOrder']['scanDirection'],
    'currentOrderedSeekRowids' => $plan['currentGeneratedPathRowidCurrentSourceOrder']['orderedSeekRowids'],
    'currentEstimatedCost' => $plan['currentGeneratedPathRowidCurrentSourceOrder']['estimatedCost'],
    'nextRequiresSorter' => $plan['nextGeneratedPathRowidCurrentSourceOrder']['requiresSorter'],
    'replanReasons' => $plan['next164ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path, rowid seek, current-source admission, and ORDER BY planner profiles',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($payload['currentIdxStr'] !== 'omit:path:LIKE|omit:id:IN|orderby:consumed') {
        fwrite(STDERR, "unexpected next164 current idx string\n");
        exit(1);
    }
    if ($payload['currentOrderByConsumed'] !== true || $payload['currentScanDirection'] !== 'reverse') {
        fwrite(STDERR, "unexpected next164 rowid ORDER BY plan\n");
        exit(1);
    }
    if ($payload['currentOrderedSeekRowids'] !== [6]) {
        fwrite(STDERR, "unexpected next164 limited rowids\n");
        exit(1);
    }
    if ($payload['currentEstimatedCost'] !== 1) {
        fwrite(STDERR, "unexpected next164 LIMIT cost\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-current-source-order-usage-changed', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next164 order replan reason\n");
        exit(1);
    }

    echo "application-json-table-generated-path-rowid-cost-current-source-next164 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
