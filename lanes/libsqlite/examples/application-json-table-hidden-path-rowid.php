<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current = [
    'option_id' => 140,
    'option_name' => 'wp_plugin_hidden_path_rowid',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"version":1}}',
    'scan_root' => '$.rules',
];
$next = [
    'option_id' => 140,
    'option_name' => 'wp_plugin_hidden_path_rowid',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":8},{"slug":"forms","priority":4},{"slug":"shop","priority":8}],"meta":{"version":2}}',
    'scan_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourceHiddenPathRowid(
    'json_tree',
    $current,
    $next,
    'option_value',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
        ['column' => 'key', 'operator' => '=', 'value' => 'priority'],
    ],
    'scan_root',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentHiddenPathRowidSource']['matchedAtom'] === 7);
    assert($plan['nextHiddenPathRowidSource']['matchedAtom'] === 8);
    assert($plan['currentHiddenPathRowidSource']['seekSignature'] === '2:path:=:"$.rules[1]"&&3:id:=:6');
    assert(in_array('json-table-hidden-path-rowid-current-source-value-changed', $plan['hiddenPathRowidReplanReasons'], true));
    echo "application-json-table-hidden-path-rowid self-test passed\n";
    return;
}

echo json_encode([
    'currentMatchedAtom' => $plan['currentHiddenPathRowidSource']['matchedAtom'],
    'nextMatchedAtom' => $plan['nextHiddenPathRowidSource']['matchedAtom'],
    'seekSignature' => $plan['currentHiddenPathRowidSource']['seekSignature'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'replanReasons' => $plan['hiddenPathRowidReplanReasons'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
