<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOption = [
    'option_id' => 953968,
    'option_name' => 'wp_plugin_generated_rule_lookup_next953968',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"version":1}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-953968',
];
$nextOption = array_replace($currentOption, [
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"version":2}}',
    'generated_path' => '$.rules[1]',
    'source_generation' => 'next-953968',
]);

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    968,
    $currentOption,
    $nextOption,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7]],
        ['column' => '_rowid_', 'operator' => 'BETWEEN', 'value' => [6, 7]],
    ],
    'scan_root',
    [['column' => 'rowid', 'direction' => 'ASC']],
    1,
    null,
    1,
    ['rowid', '_rowid_', 'oid', 'path', 'fullkey', 'value'],
);

$payload = [
    'scenario' => 'wordpress-json-table-generated-path-rowid-cost-current-source-next953-968',
    'wordpressUse' => 'Generated wp_options JSON path scans keep rowid point-cost admission stable across the next953-968 follow-on while changed copied source rows force a next reader reprepare.',
    'dependency' => 'sqlite-json-table-generated-path-rowid-cost-current-source-next968',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentCostClass' => $plan['currentGeneratedPathRowidCurrentSourceCostSelection968']['costClass'],
    'currentEstimatedCost' => $plan['currentGeneratedPathRowidCurrentSourceCostSelection968']['estimatedCost'],
    'nextCostClass' => $plan['nextGeneratedPathRowidCurrentSourceCostSelection968']['costClass'],
    'replanReasons' => $plan['next968ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses current-source generated-path rowid yield guard and cost selection aliases',
];

if (($argv[1] ?? null) === '--self-test') {
    if (!in_array($payload['dependency'], $plan['dependencies'], true)) {
        fwrite(STDERR, "missing next968 dependency\n");
        exit(1);
    }
    if ($payload['currentEstimatedCost'] !== 1) {
        fwrite(STDERR, "unexpected current generated-path rowid cost\n");
        exit(1);
    }
    if ($payload['nextReaderPolicy'] !== 'reprepare-cost-select-next-json-table-generated-path-rowid-next968') {
        fwrite(STDERR, "unexpected next968 reader policy\n");
        exit(1);
    }

    echo "wordpress-json-table-generated-path-rowid-cost-current-source-next953-968 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
