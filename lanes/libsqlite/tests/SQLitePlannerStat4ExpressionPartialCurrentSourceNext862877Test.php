<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq862877 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like862877 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull862877 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between862877 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows862877 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples862877 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload862877 = static fn (array $row): array => [
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

$prepared862877 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next862877',
    'schemaCookie' => 3868,
    'stat4Generation' => 382,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next862877',
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

$current862877 = static function (?array $rows = null, ?array $samples = null) use ($prepared862877, $rows862877, $samples862877, $payload862877): array {
    $source = $prepared862877();
    $source['name'] = 'current-wp-options-stat4-handoff-next862877';
    $source['schemaCookie'] = 4018;
    $source['stat4Generation'] = 950;
    $source['rows'] = $rows ?? $rows862877();
    $source['indexes'][0]['rootPage'] = 40188;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples862877();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload862877, $source['rows']);

    return $source;
};

$terms862877 = static fn (): array => [
    $between862877('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq862877('autoload', 'yes'),
    $notNull862877('option_name'),
    $eq862877('blog_id', 1),
    $like862877('option_name', 'plugin_%'),
];

$plan862877 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffResumeWindow(
    $prepared862877(),
    $current862877($rows, $samples),
    $terms862877(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next862877 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next862-877-prepared', $plan862877()['status']),
    'planner stat4 expression partial current source next862877 inherits next846861' => static fn (TestRunner $t) => $t->same(true, $plan862877()['selectedPlan']['next846861Prepared']),
    'planner stat4 expression partial current source next862877 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan862877()['selectedPlan']['next862877Prepared']),
    'planner stat4 expression partial current source next862877 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan862877()['stat4Next862877PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next862877 slice range' => static fn (TestRunner $t) => $t->same([862, 877], $plan862877()['stat4Next862877PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next862877 prior range' => static fn (TestRunner $t) => $t->same([846, 861], $plan862877()['stat4Next862877PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next862877 prepared slices' => static fn (TestRunner $t) => $t->same(range(862, 877), $plan862877()['selectedPlan']['next862877PreparedSlices']),
    'planner stat4 expression partial current source next862877 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan862877()['selectedPlan']['next862877BlockedSlices']),
    'planner stat4 expression partial current source next862877 first continues' => static fn (TestRunner $t) => $t->same(846, $plan862877()['stat4Next862877PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next862877 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan862877()['stat4Next862877PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next862877 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan862877()['stat4Next862877PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next862877 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan862877()['stat4Next862877PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next862877 selected signature' => static fn (TestRunner $t) => $t->same($plan862877()['stat4Next862877PreparationFence']['handoffSignature'], $plan862877()['selectedPlan']['next862877HandoffSignature']),
    'planner stat4 expression partial current source next862877 stat4 signature' => static fn (TestRunner $t) => $t->same($plan862877()['stat4Next862877PreparationFence']['handoffSignature'], $plan862877()['stat4Fence']['next862877HandoffSignature']),
    'planner stat4 expression partial current source next862877 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan862877()['stat4Next846861PreparationFence']['handoffSignature'], $plan862877()['selectedPlan']['next862877PriorHandoffSignature']),
    'planner stat4 expression partial current source next862877 preserves next846861 fence' => static fn (TestRunner $t) => $t->same(range(846, 861), $plan862877()['stat4Next846861PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next862877 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext862877Handoff', $plan862877()['cursorProgram'][array_key_last($plan862877()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next862877 cursor mode' => static fn (TestRunner $t) => $t->same('next862-877-current-source-stat4-expression-partial-prep', $plan862877()['cursorProgram'][array_key_last($plan862877()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next862877 detail' => static fn (TestRunner $t) => $t->contains('NEXT862-877 PREPARED HANDOFF', $plan862877()['detail']),
    'planner stat4 expression partial current source next862877 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next862-877-prep', $plan862877()['dependencies'], true)),
    'planner stat4 expression partial current source next862877 dependency closure' => static fn (TestRunner $t) => $t->contains('next862-877 preparation extends', $plan862877()['dependency_closure']),
    'planner stat4 expression partial current source next862877 non overlap' => static fn (TestRunner $t) => $t->contains('next846-861 handoff windows', $plan862877()['non_overlap']),
    'planner stat4 expression partial current source next862877 malformed needed column' => static function (TestRunner $t) use ($prepared862877, $current862877, $terms862877): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffResumeWindow($prepared862877(), $current862877(), $terms862877(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next862877 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan862877): void {
        $plan = $plan862877();
        $t->same($plan['stat4Next862877PreparationFence']['handoffSignature'], $plan['selectedPlan']['next862877HandoffSignature']);
    };
}

return $tests;
