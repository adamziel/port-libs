<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq878893 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like878893 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull878893 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between878893 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows878893 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples878893 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload878893 = static fn (array $row): array => [
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

$prepared878893 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next878893',
    'schemaCookie' => 3868,
    'stat4Generation' => 382,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next878893',
        'rootPage' => 38681,
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

$current878893 = static function (?array $rows = null, ?array $samples = null) use ($prepared878893, $rows878893, $samples878893, $payload878893): array {
    $source = $prepared878893();
    $source['name'] = 'current-wp-options-stat4-handoff-next878893';
    $source['schemaCookie'] = 4018;
    $source['stat4Generation'] = 950;
    $source['rows'] = $rows ?? $rows878893();
    $source['indexes'][0]['rootPage'] = 40188;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples878893();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload878893, $source['rows']);

    return $source;
};

$terms878893 = static fn (): array => [
    $between878893('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq878893('autoload', 'yes'),
    $notNull878893('option_name'),
    $eq878893('blog_id', 1),
    $like878893('option_name', 'plugin_%'),
];

$plan878893 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext878893(
    $prepared878893(),
    $current878893($rows, $samples),
    $terms878893(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next878893 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next878-893-prepared', $plan878893()['status']),
    'planner stat4 expression partial current source next878893 inherits next862877' => static fn (TestRunner $t) => $t->same(true, $plan878893()['selectedPlan']['next862877Prepared']),
    'planner stat4 expression partial current source next878893 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan878893()['selectedPlan']['next878893Prepared']),
    'planner stat4 expression partial current source next878893 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan878893()['stat4Next878893PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next878893 slice range' => static fn (TestRunner $t) => $t->same([878, 893], $plan878893()['stat4Next878893PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next878893 prior range' => static fn (TestRunner $t) => $t->same([862, 877], $plan878893()['stat4Next878893PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next878893 prepared slices' => static fn (TestRunner $t) => $t->same(range(878, 893), $plan878893()['selectedPlan']['next878893PreparedSlices']),
    'planner stat4 expression partial current source next878893 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan878893()['selectedPlan']['next878893BlockedSlices']),
    'planner stat4 expression partial current source next878893 first continues' => static fn (TestRunner $t) => $t->same(862, $plan878893()['stat4Next878893PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next878893 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan878893()['stat4Next878893PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next878893 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan878893()['stat4Next878893PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next878893 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan878893()['stat4Next878893PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next878893 selected signature' => static fn (TestRunner $t) => $t->same($plan878893()['stat4Next878893PreparationFence']['handoffSignature'], $plan878893()['selectedPlan']['next878893HandoffSignature']),
    'planner stat4 expression partial current source next878893 stat4 signature' => static fn (TestRunner $t) => $t->same($plan878893()['stat4Next878893PreparationFence']['handoffSignature'], $plan878893()['stat4Fence']['next878893HandoffSignature']),
    'planner stat4 expression partial current source next878893 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan878893()['stat4Next862877PreparationFence']['handoffSignature'], $plan878893()['selectedPlan']['next878893PriorHandoffSignature']),
    'planner stat4 expression partial current source next878893 preserves next862877 fence' => static fn (TestRunner $t) => $t->same(range(862, 877), $plan878893()['stat4Next862877PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next878893 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext878893Handoff', $plan878893()['cursorProgram'][array_key_last($plan878893()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next878893 cursor mode' => static fn (TestRunner $t) => $t->same('next878-893-current-source-stat4-expression-partial-prep', $plan878893()['cursorProgram'][array_key_last($plan878893()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next878893 detail' => static fn (TestRunner $t) => $t->contains('NEXT878-893 PREPARED HANDOFF', $plan878893()['detail']),
    'planner stat4 expression partial current source next878893 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next878-893-prep', $plan878893()['dependencies'], true)),
    'planner stat4 expression partial current source next878893 dependency closure' => static fn (TestRunner $t) => $t->contains('next878-893 preparation extends', $plan878893()['dependency_closure']),
    'planner stat4 expression partial current source next878893 non overlap' => static fn (TestRunner $t) => $t->contains('next862-877 handoff windows', $plan878893()['non_overlap']),
    'planner stat4 expression partial current source next878893 malformed needed column' => static function (TestRunner $t) use ($prepared878893, $current878893, $terms878893): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext878893($prepared878893(), $current878893(), $terms878893(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next878893 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan878893): void {
        $plan = $plan878893();
        $t->same($plan['stat4Next878893PreparationFence']['handoffSignature'], $plan['selectedPlan']['next878893HandoffSignature']);
    };
}

return $tests;
