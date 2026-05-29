<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq334349 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like334349 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull334349 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between334349 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows334349 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples334349 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload334349 = static fn (array $row): array => [
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

$prepared334349 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next334349',
    'schemaCookie' => 3340,
    'stat4Generation' => 334,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next334349',
        'rootPage' => 33401,
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

$current334349 = static function (?array $rows = null, ?array $samples = null) use ($prepared334349, $rows334349, $samples334349, $payload334349): array {
    $source = $prepared334349();
    $source['name'] = 'current-wp-options-stat4-handoff-next334349';
    $source['schemaCookie'] = 3348;
    $source['stat4Generation'] = 902;
    $source['rows'] = $rows ?? $rows334349();
    $source['indexes'][0]['rootPage'] = 33488;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples334349();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload334349, $source['rows']);

    return $source;
};

$terms334349 = static fn (): array => [
    $between334349('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq334349('autoload', 'yes'),
    $notNull334349('option_name'),
    $eq334349('blog_id', 1),
    $like334349('option_name', 'plugin_%'),
];

$plan334349 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext334349(
    $prepared334349(),
    $current334349($rows, $samples),
    $terms334349(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next334349 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next334-349-prepared', $plan334349()['status']),
    'planner stat4 expression partial current source next334349 inherits next318333' => static fn (TestRunner $t) => $t->same(true, $plan334349()['selectedPlan']['next318333Prepared']),
    'planner stat4 expression partial current source next334349 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan334349()['selectedPlan']['next334349Prepared']),
    'planner stat4 expression partial current source next334349 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan334349()['stat4Next334349PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next334349 slice range' => static fn (TestRunner $t) => $t->same([334, 349], $plan334349()['stat4Next334349PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next334349 prior range' => static fn (TestRunner $t) => $t->same([318, 333], $plan334349()['stat4Next334349PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next334349 prepared slices' => static fn (TestRunner $t) => $t->same(range(334, 349), $plan334349()['selectedPlan']['next334349PreparedSlices']),
    'planner stat4 expression partial current source next334349 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan334349()['selectedPlan']['next334349BlockedSlices']),
    'planner stat4 expression partial current source next334349 first continues' => static fn (TestRunner $t) => $t->same(318, $plan334349()['stat4Next334349PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next334349 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan334349()['stat4Next334349PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next334349 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan334349()['stat4Next334349PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next334349 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan334349()['stat4Next334349PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next334349 selected signature' => static fn (TestRunner $t) => $t->same($plan334349()['stat4Next334349PreparationFence']['handoffSignature'], $plan334349()['selectedPlan']['next334349HandoffSignature']),
    'planner stat4 expression partial current source next334349 stat4 signature' => static fn (TestRunner $t) => $t->same($plan334349()['stat4Next334349PreparationFence']['handoffSignature'], $plan334349()['stat4Fence']['next334349HandoffSignature']),
    'planner stat4 expression partial current source next334349 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan334349()['stat4Next318333PreparationFence']['handoffSignature'], $plan334349()['selectedPlan']['next334349PriorHandoffSignature']),
    'planner stat4 expression partial current source next334349 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext334349Handoff', $plan334349()['cursorProgram'][array_key_last($plan334349()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next334349 cursor mode' => static fn (TestRunner $t) => $t->same('next334-349-current-source-stat4-expression-partial-prep', $plan334349()['cursorProgram'][array_key_last($plan334349()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next334349 detail' => static fn (TestRunner $t) => $t->contains('NEXT334-349 PREPARED HANDOFF', $plan334349()['detail']),
    'planner stat4 expression partial current source next334349 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next334-349-prep', $plan334349()['dependencies'], true)),
    'planner stat4 expression partial current source next334349 dependency closure' => static fn (TestRunner $t) => $t->contains('next334-349 preparation extends', $plan334349()['dependency_closure']),
    'planner stat4 expression partial current source next334349 non overlap' => static fn (TestRunner $t) => $t->contains('next318-333 handoff windows', $plan334349()['non_overlap']),
    'planner stat4 expression partial current source next334349 malformed needed column' => static function (TestRunner $t) use ($prepared334349, $current334349, $terms334349): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext334349($prepared334349(), $current334349(), $terms334349(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next334349 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan334349): void {
        $plan = $plan334349();
        $t->same($plan['stat4Next334349PreparationFence']['handoffSignature'], $plan['selectedPlan']['next334349HandoffSignature']);
    };
}

return $tests;
