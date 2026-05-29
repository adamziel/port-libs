<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteStat4ExpressionCoveringCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$jsonChannel = ['function' => 'jsonb_extract', 'column' => 'option_value', 'path' => '$.plugin.channel'];
$predicate = [
    'operator' => 'AND',
    'terms' => [
        ['operator' => 'IN', 'left' => $jsonChannel, 'values' => ['alpha', 'beta', 'stable']],
        ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'],
    ],
];

$preparedSource = [
    'name' => 'prepared-wp-options-next117',
    'schemaCookie' => 50,
    'stat4Generation' => 12,
    'indexes' => [[
        'name' => 'idx_wp_options_channel_covering_stat4_old_next117',
        'rootPage' => 1170,
        'estimatedRows' => 220,
        'stat4Samples' => [
            ['neq' => '3 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['alpha', 'yes']],
            ['neq' => '9 1', 'nlt' => '3 1', 'ndlt' => '1 1', 'sample' => ['beta', 'yes']],
        ],
        'coveringColumns' => ['option_name', 'autoload', 'option_id', 'blog_id'],
        'sql' => "CREATE INDEX idx_wp_options_channel_covering_stat4_old_next117 ON wp_options(jsonb_extract(option_value, '$.plugin.channel'), autoload, option_id) WHERE autoload = 'yes'",
    ]],
    'rows' => [
        ['rowid' => 1, 'option_id' => 1, 'option_name' => 'plugin_alpha_old', 'autoload' => 'yes', 'blog_id' => 1, 'option_value' => '{"plugin":{"channel":"alpha"}}'],
    ],
];

$currentSource = [
    'name' => 'current-wp-options-next117',
    'schemaCookie' => 51,
    'stat4Generation' => 13,
    'indexes' => [[
        'name' => 'idx_wp_options_channel_covering_stat4_current_next117',
        'rootPage' => 1171,
        'estimatedRows' => 180,
        'stat4Samples' => [
            ['neq' => '3 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['alpha', 'yes']],
            ['neq' => '7 1', 'nlt' => '3 1', 'ndlt' => '1 1', 'sample' => ['beta', 'yes']],
            ['neq' => '5 1', 'nlt' => '10 2', 'ndlt' => '2 2', 'sample' => ['stable', 'yes']],
        ],
        'coveringColumns' => ['option_name', 'autoload', 'option_id', 'blog_id'],
        'sql' => "CREATE INDEX idx_wp_options_channel_covering_stat4_current_next117 ON wp_options(jsonb_extract(option_value, '$.plugin.channel'), autoload, option_id) WHERE autoload = 'yes'",
    ]],
    'rows' => [
        ['rowid' => 10, 'option_id' => 10, 'option_name' => 'plugin_beta_a', 'autoload' => 'yes', 'blog_id' => 1, 'option_value' => '{"plugin":{"channel":"beta"}}'],
        ['rowid' => 11, 'option_id' => 11, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'blog_id' => 1, 'option_value' => '{"plugin":{"channel":"alpha"}}'],
        ['rowid' => 12, 'option_id' => 12, 'option_name' => 'plugin_stable', 'autoload' => 'yes', 'blog_id' => 2, 'option_value' => '{"plugin":{"channel":"stable"}}'],
        ['rowid' => 13, 'option_id' => 13, 'option_name' => 'plugin_beta_b', 'autoload' => 'yes', 'blog_id' => 3, 'option_value' => '{"plugin":{"channel":"beta"}}'],
    ],
];

$plan = SQLiteStat4ExpressionCoveringCurrentSourceNextPlan::materializeNext117(
    $preparedSource,
    $currentSource,
    $predicate,
    [$jsonChannel, ['column' => 'autoload']],
    ['option_name', 'autoload', 'option_id', 'blog_id'],
    [$jsonChannel],
);

if (($plan['status'] ?? null) !== 'stat4-expression-covering-current-source-ready') {
    fwrite(STDERR, "STAT4 expression covering current-source plan was not ready\n");
    exit(1);
}

printf(
    "wordpress planner expression-index stat4 covering current-source next117: source=%s index=%s rows=%d keys=%s names=%s\n",
    (string) $plan['selectedSource'],
    (string) ($plan['cursorTape']['indexName'] ?? ''),
    (int) ($plan['cursorTape']['coveredRowCount'] ?? 0),
    implode(',', array_map(static fn (mixed $key): string => (string) $key, $plan['cursorTape']['expressionKeys'] ?? [])),
    implode(',', array_map(static fn (array $pair): string => (string) $pair['current']['covering']['option_name'], $plan['selectedPlan']['currentNextRows'] ?? [])),
);
