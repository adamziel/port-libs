<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan;

$eq366381 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like366381 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull366381 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between366381 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows366381 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples366381 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload366381 = static fn (array $row): array => [
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

$prepared366381 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next366381',
    'schemaCookie' => 3660,
    'stat4Generation' => 334,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next366381',
        'rootPage' => 36601,
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

$current366381 = static function (?array $rows = null, ?array $samples = null) use ($prepared366381, $rows366381, $samples366381, $payload366381): array {
    $source = $prepared366381();
    $source['name'] = 'current-wp-options-stat4-handoff-next366381';
    $source['schemaCookie'] = 3810;
    $source['stat4Generation'] = 902;
    $source['rows'] = $rows ?? $rows366381();
    $source['indexes'][0]['rootPage'] = 38108;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples366381();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload366381, $source['rows']);

    return $source;
};

$terms366381 = static fn (): array => [
    $between366381('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq366381('autoload', 'yes'),
    $notNull366381('option_name'),
    $eq366381('blog_id', 1),
    $like366381('option_name', 'plugin_%'),
];

$plan366381 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materializeNext366381(
    $prepared366381(),
    $current366381($rows, $samples),
    $terms366381(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next366381 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next366-381-prepared', $plan366381()['status']),
    'planner stat4 expression partial current source next366381 inherits next350365' => static fn (TestRunner $t) => $t->same(true, $plan366381()['selectedPlan']['next350365Prepared']),
    'planner stat4 expression partial current source next366381 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan366381()['selectedPlan']['next366381Prepared']),
    'planner stat4 expression partial current source next366381 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan366381()['stat4Next366381PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next366381 slice range' => static fn (TestRunner $t) => $t->same([366, 381], $plan366381()['stat4Next366381PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next366381 prior range' => static fn (TestRunner $t) => $t->same([350, 365], $plan366381()['stat4Next366381PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next366381 prepared slices' => static fn (TestRunner $t) => $t->same(range(366, 381), $plan366381()['selectedPlan']['next366381PreparedSlices']),
    'planner stat4 expression partial current source next366381 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan366381()['selectedPlan']['next366381BlockedSlices']),
    'planner stat4 expression partial current source next366381 first continues' => static fn (TestRunner $t) => $t->same(350, $plan366381()['stat4Next366381PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next366381 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan366381()['stat4Next366381PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next366381 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan366381()['stat4Next366381PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next366381 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan366381()['stat4Next366381PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next366381 selected signature' => static fn (TestRunner $t) => $t->same($plan366381()['stat4Next366381PreparationFence']['handoffSignature'], $plan366381()['selectedPlan']['next366381HandoffSignature']),
    'planner stat4 expression partial current source next366381 stat4 signature' => static fn (TestRunner $t) => $t->same($plan366381()['stat4Next366381PreparationFence']['handoffSignature'], $plan366381()['stat4Fence']['next366381HandoffSignature']),
    'planner stat4 expression partial current source next366381 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan366381()['stat4Next350365PreparationFence']['handoffSignature'], $plan366381()['selectedPlan']['next366381PriorHandoffSignature']),
    'planner stat4 expression partial current source next366381 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext366381Handoff', $plan366381()['cursorProgram'][array_key_last($plan366381()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next366381 cursor mode' => static fn (TestRunner $t) => $t->same('next366-381-current-source-stat4-expression-partial-prep', $plan366381()['cursorProgram'][array_key_last($plan366381()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next366381 detail' => static fn (TestRunner $t) => $t->contains('NEXT366-381 PREPARED HANDOFF', $plan366381()['detail']),
    'planner stat4 expression partial current source next366381 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next366-381-prep', $plan366381()['dependencies'], true)),
    'planner stat4 expression partial current source next366381 dependency closure' => static fn (TestRunner $t) => $t->contains('next366-381 preparation extends', $plan366381()['dependency_closure']),
    'planner stat4 expression partial current source next366381 non overlap' => static fn (TestRunner $t) => $t->contains('next350-365 handoff windows', $plan366381()['non_overlap']),
    'planner stat4 expression partial current source next366381 malformed needed column' => static function (TestRunner $t) use ($prepared366381, $current366381, $terms366381): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materializeNext366381($prepared366381(), $current366381(), $terms366381(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next366381 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan366381): void {
        $plan = $plan366381();
        $t->same($plan['stat4Next366381PreparationFence']['handoffSignature'], $plan['selectedPlan']['next366381HandoffSignature']);
    };
}

return $tests;
