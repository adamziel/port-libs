<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq926941 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like926941 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull926941 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between926941 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows926941 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples926941 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload926941 = static fn (array $row): array => [
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

$prepared926941 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next926941',
    'schemaCookie' => 3920,
    'stat4Generation' => 398,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next926941',
        'rootPage' => 39201,
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

$current926941 = static function (?array $rows = null, ?array $samples = null) use ($prepared926941, $rows926941, $samples926941, $payload926941): array {
    $source = $prepared926941();
    $source['name'] = 'current-wp-options-stat4-handoff-next926941';
    $source['schemaCookie'] = 4070;
    $source['stat4Generation'] = 966;
    $source['rows'] = $rows ?? $rows926941();
    $source['indexes'][0]['rootPage'] = 40708;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples926941();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload926941, $source['rows']);

    return $source;
};

$terms926941 = static fn (): array => [
    $between926941('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq926941('autoload', 'yes'),
    $notNull926941('option_name'),
    $eq926941('blog_id', 1),
    $like926941('option_name', 'plugin_%'),
];

$plan926941 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeAdvancedPreparedHandoffContinuation(
    $prepared926941(),
    $current926941($rows, $samples),
    $terms926941(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next926941 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next926-941-prepared', $plan926941()['status']),
    'planner stat4 expression partial current source next926941 inherits next910925' => static fn (TestRunner $t) => $t->same(true, $plan926941()['selectedPlan']['next910925Prepared']),
    'planner stat4 expression partial current source next926941 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan926941()['selectedPlan']['next926941Prepared']),
    'planner stat4 expression partial current source next926941 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan926941()['stat4Next926941PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next926941 slice range' => static fn (TestRunner $t) => $t->same([926, 941], $plan926941()['stat4Next926941PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next926941 prior range' => static fn (TestRunner $t) => $t->same([910, 925], $plan926941()['stat4Next926941PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next926941 prepared slices' => static fn (TestRunner $t) => $t->same(range(926, 941), $plan926941()['selectedPlan']['next926941PreparedSlices']),
    'planner stat4 expression partial current source next926941 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan926941()['selectedPlan']['next926941BlockedSlices']),
    'planner stat4 expression partial current source next926941 first continues' => static fn (TestRunner $t) => $t->same(910, $plan926941()['stat4Next926941PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next926941 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan926941()['stat4Next926941PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next926941 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan926941()['stat4Next926941PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next926941 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan926941()['stat4Next926941PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next926941 selected signature' => static fn (TestRunner $t) => $t->same($plan926941()['stat4Next926941PreparationFence']['handoffSignature'], $plan926941()['selectedPlan']['next926941HandoffSignature']),
    'planner stat4 expression partial current source next926941 stat4 signature' => static fn (TestRunner $t) => $t->same($plan926941()['stat4Next926941PreparationFence']['handoffSignature'], $plan926941()['stat4Fence']['next926941HandoffSignature']),
    'planner stat4 expression partial current source next926941 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan926941()['stat4Next910925PreparationFence']['handoffSignature'], $plan926941()['selectedPlan']['next926941PriorHandoffSignature']),
    'planner stat4 expression partial current source next926941 preserves next910925 fence' => static fn (TestRunner $t) => $t->same(range(910, 925), $plan926941()['stat4Next910925PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next926941 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext926941Handoff', $plan926941()['cursorProgram'][array_key_last($plan926941()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next926941 cursor mode' => static fn (TestRunner $t) => $t->same('next926-941-current-source-stat4-expression-partial-prep', $plan926941()['cursorProgram'][array_key_last($plan926941()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next926941 detail' => static fn (TestRunner $t) => $t->contains('NEXT926-941 PREPARED HANDOFF', $plan926941()['detail']),
    'planner stat4 expression partial current source next926941 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next926-941-prep', $plan926941()['dependencies'], true)),
    'planner stat4 expression partial current source next926941 dependency closure' => static fn (TestRunner $t) => $t->contains('next926-941 preparation extends', $plan926941()['dependency_closure']),
    'planner stat4 expression partial current source next926941 non overlap' => static fn (TestRunner $t) => $t->contains('next910-925 handoff windows', $plan926941()['non_overlap']),
    'planner stat4 expression partial current source next926941 malformed needed column' => static function (TestRunner $t) use ($prepared926941, $current926941, $terms926941): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeAdvancedPreparedHandoffContinuation($prepared926941(), $current926941(), $terms926941(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next926941 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan926941): void {
        $plan = $plan926941();
        $t->same($plan['stat4Next926941PreparationFence']['handoffSignature'], $plan['selectedPlan']['next926941HandoffSignature']);
    };
}

return $tests;
