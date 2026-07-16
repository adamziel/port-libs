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
    'option_id' => 178,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next178',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 17,
];
$next = [
    'option_id' => 178,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next178',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 18,
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceResumeYieldPlan(
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
    null,
    6,
);

$summary = [
    'scenario' => 'application-json-table-generated-path-rowid-cost-current-source-next178',
    'applicationUse' => 'Copied wp_options plugin-rule import previews can resume a pinned generated-path rowid json_tree cursor after a yielded rowid, while a changed next-source cache fence restarts xFilter instead of reusing stale rows.',
    'lastYieldedRowid' => $plan['currentGeneratedPathRowidCurrentSourceYield178']['lastYieldedRowid'],
    'resumeMode' => $plan['currentGeneratedPathRowidCurrentSourceYield178']['resumeMode'],
    'remainingRowids' => $plan['currentGeneratedPathRowidCurrentSourceYield178']['remainingRowids'],
    'yieldCost' => $plan['currentGeneratedPathRowidCurrentSourceYield178']['yieldCost'],
    'nextResumeMode' => $plan['nextGeneratedPathRowidCurrentSourceYield178']['resumeMode'],
    'nextReplanFence' => $plan['nextGeneratedPathRowidCurrentSourceYield178']['replanFence'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'replanReasons' => $plan['next178ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path, rowid-cost, current-source cache, and xFilter/xNext planner metadata',
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['lastYieldedRowid'] === 6);
    assert($summary['resumeMode'] === 'resume-xnext-from-pinned-current-source');
    assert($summary['remainingRowids'] === [5]);
    assert($summary['yieldCost'] === 1);
    assert($summary['nextResumeMode'] === 'restart-xfilter-for-next-source');
    assert($summary['nextReplanFence'] === 'next-source-generated-path-rowid-cache-fence');
    assert($summary['nextReaderPolicy'] === 'restart-next-json-table-generated-path-rowid-cost-current-source-next178-filter');
    assert(in_array('json-table-generated-path-rowid-yield-source-fence-changed', $summary['replanReasons'], true));
    echo "application-json-table-generated-path-rowid-cost-current-source-next178 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
