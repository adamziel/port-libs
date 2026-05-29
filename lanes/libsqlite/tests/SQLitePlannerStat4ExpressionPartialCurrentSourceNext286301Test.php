<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan;

$eq286301 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like286301 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull286301 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between286301 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows286301 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples286301 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload286301 = static fn (array $row): array => [
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

$prepared286301 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next286301',
    'schemaCookie' => 2860,
    'stat4Generation' => 286,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next286301',
        'rootPage' => 28601,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'descending' => true,
        'partialPredicateTerms' => [
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_forms'],
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
        'stat4ExpressionPayloads' => [],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_seo', 30, 1]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_zulu', 60, 1]],
        ],
    ]],
];

$current286301 = static function (?array $rows = null, ?array $samples = null) use ($prepared286301, $rows286301, $samples286301, $payload286301): array {
    $source = $prepared286301();
    $source['name'] = 'current-wp-options-stat4-handoff-next286301';
    $source['schemaCookie'] = 2868;
    $source['stat4Generation'] = 886;
    $source['rows'] = $rows ?? $rows286301();
    $source['indexes'][0]['rootPage'] = 28688;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples286301();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload286301, $source['rows']);

    return $source;
};

$terms286301 = static fn (): array => [
    $between286301('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq286301('autoload', 'yes'),
    $notNull286301('option_name'),
    $eq286301('blog_id', 1),
    $like286301('option_name', 'plugin_%'),
];

$plan286301 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materializeNext286301(
    $prepared286301(),
    $current286301($rows, $samples),
    $terms286301(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next286301 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next286-301-prepared', $plan286301()['status']),
    'planner stat4 expression partial current source next286301 inherits next270285' => static fn (TestRunner $t) => $t->same(true, $plan286301()['selectedPlan']['next270285Prepared']),
    'planner stat4 expression partial current source next286301 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan286301()['selectedPlan']['next286301Prepared']),
    'planner stat4 expression partial current source next286301 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan286301()['stat4Next286301PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next286301 slice range' => static fn (TestRunner $t) => $t->same([286, 301], $plan286301()['stat4Next286301PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next286301 prior range' => static fn (TestRunner $t) => $t->same([270, 285], $plan286301()['stat4Next286301PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next286301 prepared slices' => static fn (TestRunner $t) => $t->same(range(286, 301), $plan286301()['selectedPlan']['next286301PreparedSlices']),
    'planner stat4 expression partial current source next286301 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan286301()['selectedPlan']['next286301BlockedSlices']),
    'planner stat4 expression partial current source next286301 first continues' => static fn (TestRunner $t) => $t->same(270, $plan286301()['stat4Next286301PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next286301 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan286301()['stat4Next286301PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next286301 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan286301()['stat4Next286301PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next286301 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan286301()['stat4Next286301PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next286301 selected signature' => static fn (TestRunner $t) => $t->same($plan286301()['stat4Next286301PreparationFence']['handoffSignature'], $plan286301()['selectedPlan']['next286301HandoffSignature']),
    'planner stat4 expression partial current source next286301 stat4 signature' => static fn (TestRunner $t) => $t->same($plan286301()['stat4Next286301PreparationFence']['handoffSignature'], $plan286301()['stat4Fence']['next286301HandoffSignature']),
    'planner stat4 expression partial current source next286301 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan286301()['stat4Next270285PreparationFence']['handoffSignature'], $plan286301()['selectedPlan']['next286301PriorHandoffSignature']),
    'planner stat4 expression partial current source next286301 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext286301Handoff', $plan286301()['cursorProgram'][array_key_last($plan286301()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next286301 cursor mode' => static fn (TestRunner $t) => $t->same('next286-301-current-source-stat4-expression-partial-prep', $plan286301()['cursorProgram'][array_key_last($plan286301()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next286301 detail' => static fn (TestRunner $t) => $t->contains('NEXT286-301 PREPARED HANDOFF', $plan286301()['detail']),
    'planner stat4 expression partial current source next286301 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next286-301-prep', $plan286301()['dependencies'], true)),
    'planner stat4 expression partial current source next286301 dependency closure' => static fn (TestRunner $t) => $t->contains('next286-301 preparation extends', $plan286301()['dependency_closure']),
    'planner stat4 expression partial current source next286301 non overlap' => static fn (TestRunner $t) => $t->contains('next270-285 handoff windows', $plan286301()['non_overlap']),
    'planner stat4 expression partial current source next286301 malformed needed column' => static function (TestRunner $t) use ($prepared286301, $current286301, $terms286301): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materializeNext286301($prepared286301(), $current286301(), $terms286301(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next286301 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan286301): void {
        $plan = $plan286301();
        $t->same($plan['stat4Next286301PreparationFence']['handoffSignature'], $plan['selectedPlan']['next286301HandoffSignature']);
    };
}

return $tests;
