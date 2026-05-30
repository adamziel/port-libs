<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOption = [
    'option_id' => 146,
    'option_name' => 'wp_plugin_hidden_rowid_path',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]},{"name":"commerce","rules":[{"slug":"shop","priority":6}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$nextOption = [
    'option_id' => 146,
    'option_name' => 'wp_plugin_hidden_rowid_path',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":8},{"slug":"forms","priority":4},{"slug":"shop","priority":9}]},{"name":"commerce","rules":[{"slug":"shop","priority":6}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];

$plan = SQLiteJsonTablePlan::currentSourceHiddenRowidPathPlan(
    'json_tree',
    $currentOption,
    $nextOption,
    'option_value',
    'base_root',
    'nested_path',
    [
        ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [2, 12]],
        ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[0].rules[%].priority'],
        ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
    ],
    [['column' => 'id']],
);

$payload = [
    'scenario' => 'application-json-table-hidden-rowid-path-current-source',
    'applicationUse' => 'Copied wp_options plugin-rule diagnostics can pin hidden rowid aliases with path constraints while the next JSON source adds a rule.',
    'currentSourceToken' => $plan['currentHiddenRowidPathSource']['sourceToken'],
    'aliasColumns' => $plan['currentHiddenRowidPathSource']['aliasColumns'],
    'currentPinnedRowids' => $plan['currentHiddenRowidPathSource']['pinnedRowids'],
    'nextPinnedRowids' => $plan['nextHiddenRowidPathSource']['pinnedRowids'],
    'currentCostClass' => $plan['currentHiddenRowidPathSource']['costClass'],
    'nextCostClass' => $plan['nextHiddenRowidPathSource']['costClass'],
    'replanReasons' => $plan['hiddenRowidPathReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table nested path/rowid planner and residual constraint matching',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($payload['aliasColumns'] !== ['id', 'fullkey']) {
        fwrite(STDERR, "unexpected hidden rowid/path aliases\n");
        exit(1);
    }
    if ($payload['currentPinnedRowids'] !== [3, 6, 9]) {
        fwrite(STDERR, "unexpected current pinned rowids\n");
        exit(1);
    }
    if ($payload['nextPinnedRowids'] !== [3, 6, 9, 12]) {
        fwrite(STDERR, "unexpected next pinned rowids\n");
        exit(1);
    }
    if (!in_array('json-table-hidden-rowid-path-tape-changed', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing hidden rowid path tape replan reason\n");
        exit(1);
    }

    echo "application-json-table-hidden-rowid-path-current-source self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
