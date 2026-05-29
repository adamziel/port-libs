<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 200,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next200',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-200-a',
];
$next = [
    'option_id' => 200,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next200',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}]}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-200-b',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidXFilterArguments(
    'json_tree',
    $current,
    $next,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 42]],
    ],
    'scan_root',
    [['column' => 'rowid', 'direction' => 'DESC']],
    null,
    null,
    ['id', 'fullkey', 'atom', 'value', 'type'],
    6,
);

$payload = [
    'scenario' => 'wordpress-json-table-generated-path-rowid-cost-current-source-next200',
    'wordpressUse' => 'Copied wp_options plugin-rule diagnostics can carry generated-path and rowid constraints into a stable json_tree xFilter argv tape only while the current-source pin and emitted rowids remain reusable.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'argvOrder' => $plan['currentGeneratedPathRowidXFilterArgv200']['argvOrder'],
    'currentDisposition' => $plan['currentGeneratedPathRowidXFilterArgv200']['xFilterDisposition'],
    'currentOpcode' => $plan['currentGeneratedPathRowidXFilterArgv200']['xFilterOpcode'],
    'currentAcceptedRowids' => $plan['currentGeneratedPathRowidXFilterArgv200']['acceptedRowids'],
    'currentCostClass' => $plan['currentGeneratedPathRowidXFilterArgv200']['costClass'],
    'nextDisposition' => $plan['nextGeneratedPathRowidXFilterArgv200']['xFilterDisposition'],
    'nextCostClass' => $plan['nextGeneratedPathRowidXFilterArgv200']['costClass'],
    'replanReasons' => $plan['next200ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table current-source pinning, generated-path rowid cost, and xFilter argument planning',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['argvOrder'] !== ['json', 'root', 'generated_path', 'rowid']) {
        fwrite(STDERR, "unexpected next200 argv order\n");
        exit(1);
    }
    if ($payload['currentDisposition'] !== 'reuse-current-source-generated-path-rowid-xfilter-argv-next200') {
        fwrite(STDERR, "unexpected next200 current disposition\n");
        exit(1);
    }
    if ($payload['currentAcceptedRowids'] !== [6, 5]) {
        fwrite(STDERR, "unexpected next200 accepted rowids\n");
        exit(1);
    }
    if ($payload['nextCostClass'] !== 'json-table-generated-path-rowid-xfilter-argv-reprepare-next200') {
        fwrite(STDERR, "unexpected next200 next cost class\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-xfilter-argv-changed-next200', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next200 argv replan reason\n");
        exit(1);
    }

    echo "wordpress-json-table-generated-path-rowid-cost-current-source-next200 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
