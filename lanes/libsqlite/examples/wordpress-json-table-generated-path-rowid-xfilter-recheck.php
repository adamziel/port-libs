<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 191,
    'option_name' => 'active_plugins',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}]}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-active-plugins-next191',
];
$next = [
    'option_id' => 191,
    'option_name' => 'active_plugins',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}]}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-active-plugins-next191',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidXFilterRecheck(
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
    assert($plan['currentGeneratedPathRowidXFilterRecheck191']['acceptedRowids'] === [8]);
    assert($plan['nextGeneratedPathRowidXFilterRecheck191']['rejectedRowids'] === [8]);
    assert($plan['nextGeneratedPathRowidXFilterRecheck191']['checkpointReusable'] === false);
    assert($plan['nextReaderPolicy'] === 'restart-xfilter-next-json-table-generated-path-rowid-next191');
    echo "wordpress-json-table-generated-path-rowid-xfilter-recheck self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-json-table-generated-path-rowid-xfilter-recheck',
    'wordpressUse' => 'Copied wp_options active_plugins diagnostics recheck generated-path rowid checkpoint rows before reusing a json_tree cursor after plugin settings change.',
    'currentPolicy' => $plan['currentReaderPolicy'],
    'nextPolicy' => $plan['nextReaderPolicy'],
    'checkpointRowids' => $plan['currentGeneratedPathRowidXFilterRecheck191']['checkpointRowids'],
    'acceptedRowids' => $plan['currentGeneratedPathRowidXFilterRecheck191']['acceptedRowids'],
    'rejectedNextRowids' => $plan['nextGeneratedPathRowidXFilterRecheck191']['rejectedRowids'],
    'xFilterOpcode' => $plan['nextGeneratedPathRowidXFilterRecheck191']['xFilterOpcode'],
    'costClass' => $plan['nextGeneratedPathRowidXFilterRecheck191']['costClass'],
    'replanReasons' => $plan['next191ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table row generation, generated-path rowid resume checkpoints, JSON path validation, and current-source xFilter rechecks',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
