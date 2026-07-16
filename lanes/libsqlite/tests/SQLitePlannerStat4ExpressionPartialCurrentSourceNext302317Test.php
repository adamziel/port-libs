<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq302317 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like302317 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull302317 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between302317 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows302317 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples302317 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload302317 = static fn (array $row): array => [
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

$prepared302317 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next302317',
    'schemaCookie' => 3020,
    'stat4Generation' => 302,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next302317',
        'rootPage' => 30201,
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

$current302317 = static function (?array $rows = null, ?array $samples = null) use ($prepared302317, $rows302317, $samples302317, $payload302317): array {
    $source = $prepared302317();
    $source['name'] = 'current-wp-options-stat4-handoff-next302317';
    $source['schemaCookie'] = 3028;
    $source['stat4Generation'] = 902;
    $source['rows'] = $rows ?? $rows302317();
    $source['indexes'][0]['rootPage'] = 30288;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples302317();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload302317, $source['rows']);

    return $source;
};

$terms302317 = static fn (): array => [
    $between302317('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq302317('autoload', 'yes'),
    $notNull302317('option_name'),
    $eq302317('blog_id', 1),
    $like302317('option_name', 'plugin_%'),
];

$plan302317 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4PayloadHandoffValidation(
    $prepared302317(),
    $current302317($rows, $samples),
    $terms302317(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next302317 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next302-317-prepared', $plan302317()['status']),
    'planner stat4 expression partial current source next302317 inherits next286301' => static fn (TestRunner $t) => $t->same(true, $plan302317()['selectedPlan']['next286301Prepared']),
    'planner stat4 expression partial current source next302317 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan302317()['selectedPlan']['next302317Prepared']),
    'planner stat4 expression partial current source next302317 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan302317()['stat4Next302317PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next302317 slice range' => static fn (TestRunner $t) => $t->same([302, 317], $plan302317()['stat4Next302317PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next302317 prior range' => static fn (TestRunner $t) => $t->same([286, 301], $plan302317()['stat4Next302317PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next302317 prepared slices' => static fn (TestRunner $t) => $t->same(range(302, 317), $plan302317()['selectedPlan']['next302317PreparedSlices']),
    'planner stat4 expression partial current source next302317 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan302317()['selectedPlan']['next302317BlockedSlices']),
    'planner stat4 expression partial current source next302317 first continues' => static fn (TestRunner $t) => $t->same(286, $plan302317()['stat4Next302317PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next302317 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan302317()['stat4Next302317PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next302317 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan302317()['stat4Next302317PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next302317 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan302317()['stat4Next302317PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next302317 selected signature' => static fn (TestRunner $t) => $t->same($plan302317()['stat4Next302317PreparationFence']['handoffSignature'], $plan302317()['selectedPlan']['next302317HandoffSignature']),
    'planner stat4 expression partial current source next302317 stat4 signature' => static fn (TestRunner $t) => $t->same($plan302317()['stat4Next302317PreparationFence']['handoffSignature'], $plan302317()['stat4Fence']['next302317HandoffSignature']),
    'planner stat4 expression partial current source next302317 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan302317()['stat4Next286301PreparationFence']['handoffSignature'], $plan302317()['selectedPlan']['next302317PriorHandoffSignature']),
    'planner stat4 expression partial current source next302317 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialPreparedHandoffRange', $plan302317()['cursorProgram'][array_key_last($plan302317()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next302317 cursor mode' => static fn (TestRunner $t) => $t->same('prepared-handoff-range-current-source-stat4-expression-partial-prep', $plan302317()['cursorProgram'][array_key_last($plan302317()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next302317 detail' => static fn (TestRunner $t) => $t->contains('NEXT302-317 PREPARED HANDOFF', $plan302317()['detail']),
    'planner stat4 expression partial current source next302317 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next302-317-prep', $plan302317()['dependencies'], true)),
    'planner stat4 expression partial current source next302317 dependency closure' => static fn (TestRunner $t) => $t->contains('next302-317 preparation extends', $plan302317()['dependency_closure']),
    'planner stat4 expression partial current source next302317 non overlap' => static fn (TestRunner $t) => $t->contains('next286-301 handoff windows', $plan302317()['non_overlap']),
    'planner stat4 expression partial current source next302317 malformed needed column' => static function (TestRunner $t) use ($prepared302317, $current302317, $terms302317): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4PayloadHandoffValidation($prepared302317(), $current302317(), $terms302317(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next302317 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan302317): void {
        $plan = $plan302317();
        $t->same($plan['stat4Next302317PreparationFence']['handoffSignature'], $plan['selectedPlan']['next302317HandoffSignature']);
    };
}

return $tests;
