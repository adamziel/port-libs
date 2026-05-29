<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOption = [
    'option_id' => 873888,
    'option_name' => 'wp_plugin_generated_rule_lookup_next873888',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"version":1}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-873888',
];
$nextOption = array_replace($currentOption, [
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"version":2}}',
    'generated_path' => '$.rules[1]',
    'source_generation' => 'next-873888',
]);

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    888,
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
    'scenario' => 'wordpress-json-table-generated-path-rowid-cost-current-source-next873-888',
    'wordpressUse' => 'Generated wp_options JSON path scans keep rowid point-cost admission stable across the next873-888 follow-on while changed copied source rows force a next reader reprepare.',
    'dependency' => 'sqlite-json-table-generated-path-rowid-cost-current-source-next888',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentCostClass' => $plan['currentGeneratedPathRowidCurrentSourceCostSelection888']['costClass'],
    'currentEstimatedCost' => $plan['currentGeneratedPathRowidCurrentSourceCostSelection888']['estimatedCost'],
    'nextCostClass' => $plan['nextGeneratedPathRowidCurrentSourceCostSelection888']['costClass'],
    'replanReasons' => $plan['next888ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses current-source generated-path rowid yield guard and cost selection aliases',
];

if (($argv[1] ?? null) === '--self-test') {
    if (!in_array($payload['dependency'], $plan['dependencies'], true)) {
        fwrite(STDERR, "missing next888 dependency\n");
        exit(1);
    }
    if ($payload['currentEstimatedCost'] !== 1) {
        fwrite(STDERR, "unexpected current generated-path rowid cost\n");
        exit(1);
    }
    if ($payload['nextReaderPolicy'] !== 'reprepare-cost-select-next-json-table-generated-path-rowid-next888') {
        fwrite(STDERR, "unexpected next888 reader policy\n");
        exit(1);
    }

    echo "wordpress-json-table-generated-path-rowid-cost-current-source-next873-888 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
