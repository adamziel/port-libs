<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 202,
    'option_name' => 'active_plugins',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-active-plugins-next202',
];
$next = [
    'option_id' => 202,
    'option_name' => 'active_plugins',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}]}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-active-plugins-next202',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidBatchAdvancePlan(
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
    null,
    ['id', 'fullkey', 'atom', 'value', 'type'],
    6,
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentGeneratedPathRowidXNext202']['sourcePinned'] === true);
    assert($plan['currentGeneratedPathRowidXNext202']['previousRowid'] === 6);
    assert($plan['currentGeneratedPathRowidXNext202']['emittedRowids'] === [5]);
    assert($plan['currentGeneratedPathRowidXNext202']['xNextDisposition'] === 'advance-current-source-generated-path-rowid-xnext-next202');
    assert($plan['nextGeneratedPathRowidXNext202']['xNextReusable'] === false);
    echo "wordpress-json-table-generated-path-rowid-cost-current-source-next202 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-json-table-generated-path-rowid-cost-current-source-next202',
    'wordpressUse' => 'Copied wp_options active_plugins diagnostics can continue a pinned generated-path rowid json_tree cursor through xNext only while the source fingerprint remains reusable.',
    'currentPolicy' => $plan['currentReaderPolicy'],
    'nextPolicy' => $plan['nextReaderPolicy'],
    'previousRowid' => $plan['currentGeneratedPathRowidXNext202']['previousRowid'],
    'emittedRowids' => $plan['currentGeneratedPathRowidXNext202']['emittedRowids'],
    'blockedRowids' => $plan['currentGeneratedPathRowidXNext202']['blockedRowidsAfterXNext'],
    'xNextDisposition' => $plan['currentGeneratedPathRowidXNext202']['xNextDisposition'],
    'nextDisposition' => $plan['nextGeneratedPathRowidXNext202']['xNextDisposition'],
    'replanReasons' => $plan['next202ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table rows, generated-path rowid costing, pinned source fingerprints, and xColumn snapshots',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
