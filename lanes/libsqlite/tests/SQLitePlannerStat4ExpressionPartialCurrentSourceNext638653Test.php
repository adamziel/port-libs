<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq638653 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like638653 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull638653 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between638653 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows638653 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples638653 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload638653 = static fn (array $row): array => [
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

$prepared638653 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next638653',
    'schemaCookie' => 3852,
    'stat4Generation' => 366,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next638653',
        'rootPage' => 38521,
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

$current638653 = static function (?array $rows = null, ?array $samples = null) use ($prepared638653, $rows638653, $samples638653, $payload638653): array {
    $source = $prepared638653();
    $source['name'] = 'current-wp-options-stat4-handoff-next638653';
    $source['schemaCookie'] = 4002;
    $source['stat4Generation'] = 934;
    $source['rows'] = $rows ?? $rows638653();
    $source['indexes'][0]['rootPage'] = 40028;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples638653();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload638653, $source['rows']);

    return $source;
};

$terms638653 = static fn (): array => [
    $between638653('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq638653('autoload', 'yes'),
    $notNull638653('option_name'),
    $eq638653('blog_id', 1),
    $like638653('option_name', 'plugin_%'),
];

$plan638653 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4ExpressionPartialPreparedFollowup(
    $prepared638653(),
    $current638653($rows, $samples),
    $terms638653(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next638653 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next638-653-prepared', $plan638653()['status']),
    'planner stat4 expression partial current source next638653 inherits next622637' => static fn (TestRunner $t) => $t->same(true, $plan638653()['selectedPlan']['next622637Prepared']),
    'planner stat4 expression partial current source next638653 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan638653()['selectedPlan']['next638653Prepared']),
    'planner stat4 expression partial current source next638653 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan638653()['stat4Next638653PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next638653 slice range' => static fn (TestRunner $t) => $t->same([638, 653], $plan638653()['stat4Next638653PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next638653 prior range' => static fn (TestRunner $t) => $t->same([622, 637], $plan638653()['stat4Next638653PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next638653 prepared slices' => static fn (TestRunner $t) => $t->same(range(638, 653), $plan638653()['selectedPlan']['next638653PreparedSlices']),
    'planner stat4 expression partial current source next638653 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan638653()['selectedPlan']['next638653BlockedSlices']),
    'planner stat4 expression partial current source next638653 first continues' => static fn (TestRunner $t) => $t->same(622, $plan638653()['stat4Next638653PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next638653 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan638653()['stat4Next638653PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next638653 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan638653()['stat4Next638653PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next638653 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan638653()['stat4Next638653PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next638653 selected signature' => static fn (TestRunner $t) => $t->same($plan638653()['stat4Next638653PreparationFence']['handoffSignature'], $plan638653()['selectedPlan']['next638653HandoffSignature']),
    'planner stat4 expression partial current source next638653 stat4 signature' => static fn (TestRunner $t) => $t->same($plan638653()['stat4Next638653PreparationFence']['handoffSignature'], $plan638653()['stat4Fence']['next638653HandoffSignature']),
    'planner stat4 expression partial current source next638653 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan638653()['stat4Next622637PreparationFence']['handoffSignature'], $plan638653()['selectedPlan']['next638653PriorHandoffSignature']),
    'planner stat4 expression partial current source next638653 preserves next622637 fence' => static fn (TestRunner $t) => $t->same(range(622, 637), $plan638653()['stat4Next622637PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next638653 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialPreparedHandoffRange', $plan638653()['cursorProgram'][array_key_last($plan638653()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next638653 cursor mode' => static fn (TestRunner $t) => $t->same('prepared-handoff-range-current-source-stat4-expression-partial-prep', $plan638653()['cursorProgram'][array_key_last($plan638653()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next638653 detail' => static fn (TestRunner $t) => $t->contains('NEXT638-653 PREPARED HANDOFF', $plan638653()['detail']),
    'planner stat4 expression partial current source next638653 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next638-653-prep', $plan638653()['dependencies'], true)),
    'planner stat4 expression partial current source next638653 dependency closure' => static fn (TestRunner $t) => $t->contains('next638-653 preparation extends', $plan638653()['dependency_closure']),
    'planner stat4 expression partial current source next638653 non overlap' => static fn (TestRunner $t) => $t->contains('next622-637 handoff windows', $plan638653()['non_overlap']),
    'planner stat4 expression partial current source next638653 malformed needed column' => static function (TestRunner $t) use ($prepared638653, $current638653, $terms638653): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4ExpressionPartialPreparedFollowup($prepared638653(), $current638653(), $terms638653(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next638653 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan638653): void {
        $plan = $plan638653();
        $t->same($plan['stat4Next638653PreparationFence']['handoffSignature'], $plan['selectedPlan']['next638653HandoffSignature']);
    };
}

return $tests;
