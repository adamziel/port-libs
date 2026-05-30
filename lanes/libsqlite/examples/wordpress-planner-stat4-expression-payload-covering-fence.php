<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$row = static fn (int $rowid, string $name, string $value, string $autoload = 'yes'): array => [
    'rowid' => $rowid,
    'blog_id' => 1,
    'autoload' => $autoload,
    'option_name' => $name,
    'option_value' => $value,
    'updated_at' => $rowid,
];
$payload = static fn (array $row): array => [
    'rowid' => $row['rowid'],
    'expressionKey' => strtolower((string) $row['option_name']),
    'coveredValues' => [
        'option_name' => $row['option_name'],
        'option_value' => $row['option_value'],
        'updated_at' => $row['updated_at'],
        'blog_id' => $row['blog_id'],
        'autoload' => $row['autoload'],
    ],
];

$prepared = [
    'name' => 'prepared-wp-options-next218',
    'schemaCookie' => 2180,
    'stat4Generation' => 218,
    'rows' => [
        $row(10, 'plugin_alpha', 'old-alpha'),
        $row(20, 'plugin_forms', 'old-forms'),
        $row(30, 'plugin_seo', 'old-seo'),
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_expr_payload_stat4_next218',
        'rootPage' => 21801,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'descending' => true,
        'partialPredicateTerms' => [
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_alpha'],
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<=', 'right' => 'plugin_zulu'],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ],
        'partialGroupedOrPredicateArms' => [
            [
                ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ],
        ],
        'partialGroupedLikePredicateArms' => [
            [
                ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'plugin_%'],
            ],
        ],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
        ],
        'stat4ExpressionPayloads' => [],
    ]],
];

$current = $prepared;
$current['name'] = 'current-wp-options-next218';
$current['schemaCookie'] = 2189;
$current['stat4Generation'] = 286;
$current['indexes'][0]['rootPage'] = 21888;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
    ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 40]],
    ['neq' => '2 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 20]],
    ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 50]],
    ['neq' => '1 1', 'nlt' => '5 4', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 30]],
    ['neq' => '1 1', 'nlt' => '6 5', 'ndlt' => '5 5', 'sample' => ['plugin_zulu', 60]],
];
$current['rows'] = [
    $row(60, 'plugin_zulu', 'zulu-current'),
    $row(30, 'plugin_seo', 'seo-current'),
    $row(50, 'Plugin_Mail', 'mail-current'),
    $row(20, 'plugin_forms', 'forms-current'),
    $row(21, 'Plugin_Forms', 'forms-copy-current'),
    $row(40, 'plugin_cache', 'cache-current'),
    $row(10, 'plugin_alpha', 'alpha-current'),
    $row(70, 'plugin_forms', 'lazy', 'no'),
];
$current['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload, array_slice($current['rows'], 0, 7));

$where = [
    ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => 'BETWEEN', 'lower' => 'plugin_alpha', 'upper' => 'plugin_zulu'],
    ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
    ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
    ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
    ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'plugin_%'],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeExpressionPayloadCoveringFence(
    $prepared,
    $current,
    $where,
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    4,
    0,
);

echo json_encode([
    'status' => $plan['status'],
    'selected_index' => $plan['selectedPlan']['name'],
    'matched_rowids' => $plan['matchedRowids'],
    'payload_ready' => $plan['expressionPayloadFence']['allMatchedRowsHaveCurrentExpressionPayload'],
    'sample_payload_ready' => $plan['expressionPayloadFence']['allStat4SamplePayloadsResolveToCurrentRows'],
    'projected_values' => array_column($plan['projectedRows'], 'option_value'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
