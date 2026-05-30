<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current = [
    'option_id' => 174,
    'option_name' => 'wp_plugin_generated_path_rowid_alias',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
];
$next = array_replace($current, [
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
]);

$sameAlias = SQLiteJsonTablePlan::generatedPathRowidAliasPlan(
    'json_tree',
    $current,
    $next,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
        ['column' => '_rowid_', 'operator' => '=', 'value' => '6'],
        ['column' => 'oid', 'operator' => '=', 'value' => 6],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'rowid']],
);

$conflictingAlias = SQLiteJsonTablePlan::generatedPathRowidAliasPlan(
    'json_tree',
    $current,
    $current,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
        ['column' => '_rowid_', 'operator' => '=', 'value' => 7],
        ['column' => 'oid', 'operator' => '=', 'value' => 6],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'rowid']],
);

echo json_encode([
    'scenario' => 'wordpress-json-table-rowid-alias',
    'wordpressUse' => 'Copied wp_options JSON diagnostics can safely treat json_tree rowid, _rowid_, and oid constraints as the same virtual-table rowid, deduping identical aliases and short-circuiting contradictory aliases before current-source cursor reuse.',
    'sameAlias' => $sameAlias['currentGeneratedPathRowidAliasProfile'],
    'nextPolicy' => $sameAlias['nextReaderPolicy'],
    'conflictingAlias' => $conflictingAlias['currentGeneratedPathRowidAliasProfile'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
