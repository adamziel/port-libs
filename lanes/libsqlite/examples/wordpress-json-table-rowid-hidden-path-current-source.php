<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current = [
    'option_id' => 138,
    'option_name' => 'wp_plugin_rowid_hidden_path',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$next = [
    'option_id' => 138,
    'option_name' => 'wp_plugin_rowid_hidden_path',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":8},{"slug":"forms","priority":4}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];

$plan = SQLiteJsonTablePlan::currentSourceRowidHiddenPathPlan(
    'json_tree',
    $current,
    $next,
    'option_value',
    'base_root',
    'nested_path',
    [
        ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[0].rules[%].priority'],
        ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [2, 14]],
        ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
    ],
    [['column' => 'id']],
);

if (($argv[1] ?? '') === '--self-test') {
    assert(in_array('sqlite-json-table-rowid-hidden-path-current-source', $plan['dependencies'], true));
    assert($plan['currentRowidHiddenPath']['intersectedRowids'] === [3, 7, 11]);
    assert($plan['currentRowidHiddenPath']['relativeFullkeys'] === ['$[0].priority', '$[1].priority', '$[2].priority']);
    assert($plan['currentRowidHiddenPath']['costClass'] === 'json-table-hidden-path-scan');
    assert(in_array('source-json-changed', $plan['rowidHiddenPathReplanReasons'], true));
    echo "wordpress-json-table-rowid-hidden-path-current-source self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-json-table-rowid-hidden-path-current-source',
    'option' => $current['option_name'],
    'currentRowids' => $plan['currentRowidHiddenPath']['intersectedRowids'],
    'relativeFullkeys' => $plan['currentRowidHiddenPath']['relativeFullkeys'],
    'costClass' => $plan['currentRowidHiddenPath']['costClass'],
    'nextPolicy' => $plan['nextReaderPolicy'],
    'replanReasons' => $plan['rowidHiddenPathReplanReasons'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
