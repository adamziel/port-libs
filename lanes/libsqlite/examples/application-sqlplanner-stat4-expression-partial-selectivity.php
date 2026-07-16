<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

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

$source = [
    'name' => 'prepared-app-settings-stat4-selectivity',
    'schemaCookie' => 2290,
    'stat4Generation' => 229,
    'rows' => [
        ['rowid' => 10, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'module_alpha', 'key_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'module_forms', 'key_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'module_seo', 'key_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_app_settings_stat4_selectivity',
        'rootPage' => 22901,
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
        'partialGroupedOrPredicateArms' => [
            [
                ['left' => ['column' => 'tenant_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'load_policy'], 'operator' => '=', 'right' => 'eager'],
            ],
        ],
        'partialGroupedLikePredicateArms' => [
            [
                ['left' => ['column' => 'tenant_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'key_name'], 'operator' => 'LIKE', 'right' => 'module_%'],
            ],
        ],
        'coveringColumns' => ['key_name', 'key_value', 'updated_at', 'load_policy', 'tenant_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['module_alpha', 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['module_forms', 20]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['module_seo', 30]],
        ],
        'stat4ExpressionPayloads' => [],
    ]],
];

$current = $source;
$current['name'] = 'current-app-settings-stat4-selectivity';
$current['schemaCookie'] = 2299;
$current['stat4Generation'] = 309;
$current['indexes'][0]['rootPage'] = 22988;
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

$terms = [
    ['left' => ['expression' => 'LOWER(key_name)'], 'operator' => 'BETWEEN', 'lower' => 'module_alpha', 'upper' => 'module_zulu'],
    ['left' => ['column' => 'load_policy'], 'operator' => '=', 'right' => 'eager'],
    ['left' => ['column' => 'key_name'], 'operator' => 'IS NOT NULL'],
    ['left' => ['column' => 'tenant_id'], 'operator' => '=', 'right' => 1],
    ['left' => ['column' => 'key_name'], 'operator' => 'LIKE', 'right' => 'module_%'],
];
$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4ExpressionPartialSelectivity(
    $source,
    $current,
    $terms,
    ['key_name', 'key_value', 'updated_at', 'tenant_id'],
    5,
    1,
);

if (($argv[1] ?? null) === '--self-test') {
    if (($plan['status'] ?? null) !== 'stat4-expression-partial-selectivity-ready') {
        throw new RuntimeException('Expected selectivity STAT4 selectivity plan to be ready');
    }
    if (($plan['stat4SelectivityFence']['estimatedRows'] ?? null) !== 9) {
        throw new RuntimeException('Expected current STAT4 counters to estimate nine rows');
    }
    echo "application-sqlplanner-stat4-expression-partial-selectivity self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-sqlplanner-stat4-expression-partial-selectivity',
    'status' => $plan['status'],
    'matchedRowids' => $plan['matchedRowids'],
    'estimatedRows' => $plan['stat4SelectivityFence']['estimatedRows'],
    'pageWindow' => $plan['stat4SelectivityFence']['pageWindow'],
    'applicationUse' => 'Copied app_settings module scans can reuse a current-source partial lower(key_name) expression index only when current sqlite_stat4 counters still cover the selected page and duplicate peer counts.',
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
