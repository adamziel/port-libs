<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];
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

$source = [
    'name' => 'current-wp-options-stat4-trailing-payload-next237',
    'schemaCookie' => 2379,
    'stat4Generation' => 337,
    'rows' => [
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
        ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
        ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
        ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache', 'updated_at' => 40],
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 10],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_stat4_trailing_payload_next237',
        'rootPage' => 23788,
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
        'partialGroupedOrPredicateArms' => [[
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ]],
        'partialGroupedLikePredicateArms' => [[
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'plugin_%'],
        ]],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1 1 1', 'nlt' => '0 0 0 0', 'ndlt' => '0 0 0 0', 'sample' => ['plugin_alpha', 10, 'yes', 1]],
            ['neq' => '1 1 1 1', 'nlt' => '1 1 1 1', 'ndlt' => '1 1 1 1', 'sample' => ['plugin_cache', 40, 'yes', 1]],
            ['neq' => '3 1 1 1', 'nlt' => '2 2 2 2', 'ndlt' => '2 2 2 2', 'sample' => ['plugin_forms', 20, 'yes', 1]],
            ['neq' => '1 1 1 1', 'nlt' => '5 3 3 3', 'ndlt' => '3 3 3 3', 'sample' => ['plugin_mail', 50, 'yes', 1]],
            ['neq' => '1 1 1 1', 'nlt' => '6 4 4 4', 'ndlt' => '4 4 4 4', 'sample' => ['plugin_seo', 30, 'yes', 1]],
            ['neq' => '1 1 1 1', 'nlt' => '7 5 5 5', 'ndlt' => '5 5 5 5', 'sample' => ['plugin_zulu', 60, 'yes', 1]],
        ],
        'stat4ExpressionPayloads' => [],
    ]],
];
$source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload, $source['rows']);

$prepared = $source;
$prepared['name'] = 'prepared-wp-options-stat4-trailing-payload-next237';
$prepared['schemaCookie'] = 2370;
$prepared['stat4Generation'] = 237;
$prepared['indexes'][0]['rootPage'] = 23701;
$prepared['indexes'][0]['stat4Samples'] = array_slice($source['indexes'][0]['stat4Samples'], 0, 3);
$prepared['rows'] = array_slice($source['rows'], 3, 3);

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceTrailingPayloadValidation(
    $prepared,
    $source,
    [
        $between('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
        $eq('autoload', 'yes'),
        $notNull('option_name'),
        $eq('blog_id', 1),
        ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'plugin_%'],
    ],
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    ['autoload', 'blog_id'],
    5,
    1,
);

if (($argv[1] ?? null) === '--self-test' && $plan['status'] !== 'stat4-expression-partial-current-source-next237-ready') {
    throw new RuntimeException('Expected next237 STAT4 trailing payload plan to be ready');
}

printf(
    "application sqlplanner stat4 expression partial current-source next237: %s trailing=%s matched=%s signature=%s\n",
    $plan['status'],
    implode(',', $plan['stat4TrailingPayloadFence']['trailingColumns']),
    implode(',', $plan['stat4TrailingPayloadFence']['matchedTrailingRowids']),
    substr($plan['stat4TrailingPayloadFence']['proofSignature'], 0, 12),
);

return [
    'scenario' => 'application-sqlplanner-stat4-expression-partial-current-source-next237',
    'status' => $plan['status'],
    'matchedTrailingRowids' => $plan['stat4TrailingPayloadFence']['matchedTrailingRowids'],
    'applicationUse' => 'Copied wp_options plugin preload scans can reuse a current-source partial lower(option_name) covering index only when sqlite_stat4 trailing autoload/blog_id payloads still match current rows after ANALYZE or source refresh.',
];
