<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$eq = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];
$payload = static fn (array $row): array => [
    'rowid' => $row['rowid'],
    'expressionKey' => strtolower((string) $row['key_name']),
    'coveredValues' => [
        'key_name' => $row['key_name'],
        'key_value' => $row['key_value'],
        'updated_at' => $row['updated_at'],
        'tenant_id' => $row['tenant_id'],
        'load_policy' => $row['load_policy'],
    ],
];

$prepared = [
    'name' => 'prepared-app-settings-stat4-peer-cardinality',
    'schemaCookie' => 2270,
    'stat4Generation' => 227,
    'rows' => [
        ['rowid' => 10, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'module_alpha', 'key_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'module_forms', 'key_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'module_seo', 'key_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_app_settings_stat4_peer_cardinality',
        'rootPage' => 22701,
        'expression' => 'lower(key_name)',
        'expressionColumn' => '__expr_lower_key_name',
        'collation' => 'BINARY',
        'descending' => true,
        'partialPredicateTerms' => [
            ['left' => ['expression' => 'lower(key_name)'], 'operator' => '>=', 'right' => 'module_alpha'],
            ['left' => ['expression' => 'lower(key_name)'], 'operator' => '<=', 'right' => 'module_zulu'],
            ['left' => ['column' => 'load_policy'], 'operator' => '=', 'right' => 'eager'],
            ['left' => ['column' => 'key_name'], 'operator' => 'IS NOT NULL'],
        ],
        'partialGroupedOrPredicateArms' => [[
            ['left' => ['column' => 'tenant_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'load_policy'], 'operator' => '=', 'right' => 'eager'],
        ]],
        'partialGroupedLikePredicateArms' => [[
            ['left' => ['column' => 'tenant_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'key_name'], 'operator' => 'LIKE', 'right' => 'module_%'],
        ]],
        'coveringColumns' => ['key_name', 'key_value', 'updated_at', 'load_policy', 'tenant_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['module_alpha', 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['module_forms', 20]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['module_seo', 30]],
        ],
        'stat4ExpressionPayloads' => [],
    ]],
];

$current = $prepared;
$current['name'] = 'current-app-settings-stat4-peer-cardinality';
$current['schemaCookie'] = 2279;
$current['stat4Generation'] = 297;
$current['indexes'][0]['rootPage'] = 22788;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['module_alpha', 10]],
    ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['module_cache', 40]],
    ['neq' => '3 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['module_forms', 20]],
    ['neq' => '1 1', 'nlt' => '5 3', 'ndlt' => '3 3', 'sample' => ['module_mail', 50]],
    ['neq' => '1 1', 'nlt' => '6 4', 'ndlt' => '4 4', 'sample' => ['module_seo', 30]],
    ['neq' => '1 1', 'nlt' => '7 5', 'ndlt' => '5 5', 'sample' => ['module_zulu', 60]],
];
$current['rows'] = [
    ['rowid' => 60, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'module_zulu', 'key_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'module_seo', 'key_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'Module_Mail', 'key_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'module_forms', 'key_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'Module_Forms', 'key_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'MODULE_FORMS', 'key_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 40, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'module_cache', 'key_value' => 'cache', 'updated_at' => 40],
    ['rowid' => 10, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'module_alpha', 'key_value' => 'alpha', 'updated_at' => 10],
];
$current['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload, $current['rows']);

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4ExpressionPartialPeerCardinality(
    $prepared,
    $current,
    [
        $between('LOWER(key_name)', 'module_alpha', 'module_zulu'),
        $eq('load_policy', 'eager'),
        $notNull('key_name'),
        $eq('tenant_id', 1),
        $like('key_name', 'module_%'),
    ],
    ['key_name', 'key_value', 'updated_at', 'tenant_id'],
    5,
    1,
);

if (in_array('--self-test', $argv, true)) {
    assert($plan['status'] === 'stat4-expression-partial-peer-cardinality-ready');
    assert($plan['stat4PeerCardinalityFence']['payloadPeerCounts']['module_forms'] === 3);
    assert($plan['stat4PeerCardinalityFence']['expressionKeysWithStalePeerCounts'] === []);
    echo "application-sqlplanner-stat4-expression-partial-peer-cardinality self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-sqlplanner-stat4-expression-partial-peer-cardinality',
    'applicationUse' => 'Copied app_settings module pagination can reuse a changed STAT4 partial expression-index cursor only when sqlite_stat4 neq peer counts still match the current expression payload stream.',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'selectedIndex' => $plan['selectedPlan']['name'] ?? null,
    'peerCardinalityFence' => $plan['stat4PeerCardinalityFence'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
