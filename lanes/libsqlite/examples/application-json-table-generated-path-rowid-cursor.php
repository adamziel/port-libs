<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 186,
    'option_name' => 'active_plugins',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
    'source_generation' => 'current-active-plugins-cursor',
];
$next = [
    'option_id' => 186,
    'option_name' => 'active_plugins',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}]}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-active-plugins-cursor',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCursor(
    'json_tree',
    $current,
    $next,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6]],
    ],
    'scan_root',
    [['column' => 'rowid']],
    1,
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentGeneratedPathRowidCurrentSourceCursor186']['xNextOpcode'] === 'refill-current-source-generated-path-rowid-cursor-next186');
    assert($plan['currentGeneratedPathRowidCurrentSourceCursor186']['activeRowid'] === 5);
    assert($plan['currentGeneratedPathRowidCurrentSourceCursor186']['pendingRowids'] === [5, 6]);
    assert($plan['nextGeneratedPathRowidCurrentSourceCursor186']['xNextOpcode'] === 'restart-next-source-generated-path-rowid-cursor-next186');
    assert(in_array('json-table-generated-path-rowid-cursor-next186-rowset-changed', $plan['next186ReplanReasons'], true));
    echo "application-json-table-generated-path-rowid-cursor self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-json-table-generated-path-rowid-cursor',
    'applicationUse' => 'Copied wp_options active_plugins diagnostics can advance a pinned generated-path rowid json_tree cursor after a yielded current-source batch while changed next-source JSON forces a restart fence.',
    'currentOpcode' => $plan['currentGeneratedPathRowidCurrentSourceCursor186']['xNextOpcode'],
    'activeRowid' => $plan['currentGeneratedPathRowidCurrentSourceCursor186']['activeRowid'],
    'pendingRowids' => $plan['currentGeneratedPathRowidCurrentSourceCursor186']['pendingRowids'],
    'nextOpcode' => $plan['nextGeneratedPathRowidCurrentSourceCursor186']['xNextOpcode'],
    'replanReasons' => $plan['next186ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path rowid batch materialization and current-source cursor profiles',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
