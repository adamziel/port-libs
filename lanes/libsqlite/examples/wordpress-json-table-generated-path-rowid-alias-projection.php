<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 203,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next203',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}]}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-203-a',
];
$next = [
    'option_id' => 203,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next203',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}]}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-203-b',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidAliasProjection(
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
    ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey'],
);

$payload = [
    'scenario' => 'wordpress-json-table-generated-path-rowid-alias-projection',
    'wordpressUse' => 'Copied wp_options JSON diagnostics can project json_tree rowid aliases from a generated-path xColumn cache without losing the current-source resume guard.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentOpcode' => $plan['currentGeneratedPathRowidAliasProjection203']['aliasOpcode'],
    'currentProjectedColumns' => $plan['currentGeneratedPathRowidAliasProjection203']['aliasTape'][0]['projectedColumns'],
    'nextOpcode' => $plan['nextGeneratedPathRowidAliasProjection203']['aliasOpcode'],
    'nextReusable' => $plan['nextGeneratedPathRowidAliasProjection203']['aliasProjectionReusable'],
    'replanReasons' => $plan['next203ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path rowid xColumn cache materialization',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['currentOpcode'] !== 'OP_JsonTableRowidAliasRangeNext203') {
        fwrite(STDERR, "unexpected next203 current opcode\n");
        exit(1);
    }
    foreach (['rowid', '_rowid_', 'oid'] as $alias) {
        if (($payload['currentProjectedColumns'][$alias] ?? null) !== 9) {
            fwrite(STDERR, "unexpected next203 {$alias} value\n");
            exit(1);
        }
    }
    if (($payload['currentProjectedColumns']['fullkey'] ?? null) !== '$.rules[2].priority') {
        fwrite(STDERR, "unexpected next203 fullkey\n");
        exit(1);
    }
    if ($payload['nextOpcode'] !== 'OP_JsonTableRowidAliasReprepareNext203') {
        fwrite(STDERR, "unexpected next203 next opcode\n");
        exit(1);
    }
    if ($payload['nextReusable'] !== false) {
        fwrite(STDERR, "unexpected next203 next reuse\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-alias-source-changed-next203', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next203 source replan reason\n");
        exit(1);
    }

    echo "wordpress-json-table-generated-path-rowid-alias-projection self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
