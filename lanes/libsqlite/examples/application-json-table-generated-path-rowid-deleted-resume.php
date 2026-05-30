<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 188,
    'option_name' => 'active_plugins',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}]}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-active-plugins-next188',
];
$next = [
    'option_id' => 188,
    'option_name' => 'active_plugins',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}]}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-active-plugins-next188',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidDeletedResume(
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
    assert($plan['generatedPathRowidDeletedResume188']['deletedRowids'] === [5, 6, 7, 8]);
    assert($plan['generatedPathRowidDeletedResume188']['restartRequired'] === true);
    assert($plan['generatedPathRowidDeletedResume188']['costClass'] === 'json-table-generated-path-rowid-deleted-resume-restart-next188');
    echo "application-json-table-generated-path-rowid-deleted-resume self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-json-table-generated-path-rowid-deleted-resume',
    'applicationUse' => 'Copied wp_options active_plugins diagnostics can preserve the current json_tree resume checkpoint while forcing a restart when generated-path rowids disappear from the next source.',
    'currentPolicy' => $plan['currentReaderPolicy'],
    'nextPolicy' => $plan['nextReaderPolicy'],
    'deliveredRowids' => $plan['currentGeneratedPathRowidCurrentSourceResume185']['deliveredRowids'],
    'deletedRowids' => $plan['generatedPathRowidDeletedResume188']['deletedRowids'],
    'retainedRowids' => $plan['generatedPathRowidDeletedResume188']['retainedRowids'],
    'insertedRowids' => $plan['generatedPathRowidDeletedResume188']['insertedRowids'],
    'restartRequired' => $plan['generatedPathRowidDeletedResume188']['restartRequired'],
    'costClass' => $plan['generatedPathRowidDeletedResume188']['costClass'],
    'replanReasons' => $plan['next188ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table row generation, generated-path rowid resume checkpoints, JSON path validation, and current-source fingerprints',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
