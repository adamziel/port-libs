<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 195,
    'option_name' => 'active_plugins',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$',
    'source_generation' => 'active-plugins-anchor-remap',
];
$next = [
    'option_id' => 195,
    'option_name' => 'active_plugins',
    'option_value' => '{"meta":{"autoload":"no"},"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]}',
    'generated_path' => '$.rules',
    'scan_root' => '$',
    'source_generation' => 'active-plugins-anchor-remap',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidAnchorRemap(
    'json_tree',
    $current,
    $next,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'IN', 'value' => [6, 7, 8, 9, 10]],
    ],
    'scan_root',
    [['column' => '_rowid_', 'direction' => 'DESC']],
    5,
    10,
    1,
    ['key', 'value', 'type', 'id', 'fullkey', 'path'],
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentGeneratedPathRowidAnchorRemap195']['checkpointRowids'] === [9]);
    assert($plan['nextGeneratedPathRowidAnchorRemap195']['remappedRowids'] === [11]);
    assert($plan['nextGeneratedPathRowidAnchorRemap195']['resumeByFullkey'] === true);
    assert($plan['nextReaderPolicy'] === 'reseek-fullkey-anchor-json-table-generated-path-rowid-next195');
    echo "application-json-table-generated-path-rowid-anchor-remap self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-json-table-generated-path-rowid-anchor-remap',
    'applicationUse' => 'Copied wp_options active_plugins diagnostics can detect when a json_tree rowid checkpoint still points at the same fullkey after object layout changes shifted rowids.',
    'currentPolicy' => $plan['currentReaderPolicy'],
    'nextPolicy' => $plan['nextReaderPolicy'],
    'checkpointRowids' => $plan['currentGeneratedPathRowidAnchorRemap195']['checkpointRowids'],
    'remappedRowids' => $plan['nextGeneratedPathRowidAnchorRemap195']['remappedRowids'],
    'anchorTape' => $plan['nextGeneratedPathRowidAnchorRemap195']['anchorTape'],
    'replanReasons' => $plan['next195ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table row generation, generated-path rowid xFilter checkpoints, and JSON fullkey anchors',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
