<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 346,
    'option_name' => 'active_plugins',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-active-plugins-next346',
];
$next = [
    'option_id' => 346,
    'option_name' => 'active_plugins',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-active-plugins-next346',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    346,
    $current,
    $next,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
        ['column' => '_rowid_', 'operator' => 'BETWEEN', 'value' => [7, 8]],
    ],
    'scan_root',
    [['column' => 'rowid', 'direction' => 'ASC']],
    1,
    null,
    1,
    ['rowid', '_rowid_', 'oid', 'path', 'fullkey', 'value'],
);

$payload = [
    'scenario' => 'application-json-table-generated-path-rowid-cost-current-source-next346',
    'applicationUse' => 'Copied wp_options active_plugins JSON diagnostics can keep a generated-path rowid point on the current source at point cost while forcing the changed imported source through reprepare.',
    'currentPolicy' => $plan['currentReaderPolicy'],
    'nextPolicy' => $plan['nextReaderPolicy'],
    'currentCostClass' => $plan['currentGeneratedPathRowidCurrentSourceCostSelection346']['costClass'],
    'currentEstimatedCost' => $plan['currentGeneratedPathRowidCurrentSourceCostSelection346']['estimatedCost'],
    'nextCostClass' => $plan['nextGeneratedPathRowidCurrentSourceCostSelection346']['costClass'],
    'nextEstimatedCost' => $plan['nextGeneratedPathRowidCurrentSourceCostSelection346']['estimatedCost'],
    'deliveredRowids' => $plan['currentGeneratedPathRowidCurrentSourceCostSelection346']['deliveredRowids'],
    'replanReasons' => $plan['next346ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path, rowid xCurrent/xRowid, and current-source cost profiles',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($payload['currentCostClass'] !== 'json-table-generated-path-rowid-current-source-cost-covering-point-next346') {
        fwrite(STDERR, "unexpected next346 current cost class\n");
        exit(1);
    }
    if ($payload['currentEstimatedCost'] !== 1 || $payload['deliveredRowids'] !== [7]) {
        fwrite(STDERR, "unexpected next346 current point rowid cost\n");
        exit(1);
    }
    if ($payload['nextPolicy'] !== 'reprepare-cost-select-next-json-table-generated-path-rowid-next346') {
        fwrite(STDERR, "unexpected next346 next policy\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next346', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next346 cost replan reason\n");
        exit(1);
    }

    echo "application-json-table-generated-path-rowid-cost-current-source-next346 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
