<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 185,
    'option_name' => 'active_plugins',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}]}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-active-plugins-next185',
];
$next = [
    'option_id' => 185,
    'option_name' => 'active_plugins',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4},{"slug":"audit","priority":5}]}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-active-plugins-next185',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidResumeCheckpointPlan(
    'json_tree',
    $current,
    $next,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ],
    'scan_root',
    [['column' => '_rowid_', 'direction' => 'DESC']],
    5,
    9,
    1,
    ['key', 'value', 'type', 'id', 'fullkey', 'path'],
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentGeneratedPathRowidCurrentSourceResume185']['deliveredRowids'] === [8]);
    assert($plan['currentGeneratedPathRowidCurrentSourceResume185']['blockedRowids'] === [7, 6, 5]);
    assert($plan['currentGeneratedPathRowidCurrentSourceResume185']['lastDeliveredRowid'] === 8);
    assert($plan['currentGeneratedPathRowidCurrentSourceResume185']['projectedRows'][0]['value'] === 'forms');
    assert($plan['nextGeneratedPathRowidCurrentSourceResume185']['costClass'] === 'json-table-generated-path-rowid-resume-restart-next-source-next185');
    echo "application-json-table-generated-path-rowid-resume-checkpoint self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-json-table-generated-path-rowid-resume-checkpoint',
    'applicationUse' => 'Copied wp_options active_plugins diagnostics can checkpoint a generated-path rowid json_tree xNext batch, expose the last delivered rowid for resume, and restart safely when the next source changes.',
    'currentPolicy' => $plan['currentReaderPolicy'],
    'nextPolicy' => $plan['nextReaderPolicy'],
    'deliveredRowids' => $plan['currentGeneratedPathRowidCurrentSourceResume185']['deliveredRowids'],
    'blockedRowids' => $plan['currentGeneratedPathRowidCurrentSourceResume185']['blockedRowids'],
    'lastDeliveredRowid' => $plan['currentGeneratedPathRowidCurrentSourceResume185']['lastDeliveredRowid'],
    'projectedRows' => $plan['currentGeneratedPathRowidCurrentSourceResume185']['projectedRows'],
    'nextCostClass' => $plan['nextGeneratedPathRowidCurrentSourceResume185']['costClass'],
    'replanReasons' => $plan['next185ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table row generation, generated-path rowid xNext admission, JSON path validation, and current-source cache fingerprints',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
