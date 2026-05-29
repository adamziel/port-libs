<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq814829 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like814829 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull814829 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between814829 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows814829 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples814829 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload814829 = static fn (array $row): array => [
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

$prepared814829 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next814829',
    'schemaCookie' => 3868,
    'stat4Generation' => 382,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next814829',
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

$current814829 = static function (?array $rows = null, ?array $samples = null) use ($prepared814829, $rows814829, $samples814829, $payload814829): array {
    $source = $prepared814829();
    $source['name'] = 'current-wp-options-stat4-handoff-next814829';
    $source['schemaCookie'] = 4018;
    $source['stat4Generation'] = 950;
    $source['rows'] = $rows ?? $rows814829();
    $source['indexes'][0]['rootPage'] = 40188;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples814829();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload814829, $source['rows']);

    return $source;
};

$terms814829 = static fn (): array => [
    $between814829('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq814829('autoload', 'yes'),
    $notNull814829('option_name'),
    $eq814829('blog_id', 1),
    $like814829('option_name', 'plugin_%'),
];

$plan814829 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext814829(
    $prepared814829(),
    $current814829($rows, $samples),
    $terms814829(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next814829 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next814-829-prepared', $plan814829()['status']),
    'planner stat4 expression partial current source next814829 inherits next798813' => static fn (TestRunner $t) => $t->same(true, $plan814829()['selectedPlan']['next798813Prepared']),
    'planner stat4 expression partial current source next814829 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan814829()['selectedPlan']['next814829Prepared']),
    'planner stat4 expression partial current source next814829 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan814829()['stat4Next814829PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next814829 slice range' => static fn (TestRunner $t) => $t->same([814, 829], $plan814829()['stat4Next814829PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next814829 prior range' => static fn (TestRunner $t) => $t->same([798, 813], $plan814829()['stat4Next814829PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next814829 prepared slices' => static fn (TestRunner $t) => $t->same(range(814, 829), $plan814829()['selectedPlan']['next814829PreparedSlices']),
    'planner stat4 expression partial current source next814829 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan814829()['selectedPlan']['next814829BlockedSlices']),
    'planner stat4 expression partial current source next814829 first continues' => static fn (TestRunner $t) => $t->same(798, $plan814829()['stat4Next814829PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next814829 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan814829()['stat4Next814829PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next814829 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan814829()['stat4Next814829PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next814829 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan814829()['stat4Next814829PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next814829 selected signature' => static fn (TestRunner $t) => $t->same($plan814829()['stat4Next814829PreparationFence']['handoffSignature'], $plan814829()['selectedPlan']['next814829HandoffSignature']),
    'planner stat4 expression partial current source next814829 stat4 signature' => static fn (TestRunner $t) => $t->same($plan814829()['stat4Next814829PreparationFence']['handoffSignature'], $plan814829()['stat4Fence']['next814829HandoffSignature']),
    'planner stat4 expression partial current source next814829 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan814829()['stat4Next798813PreparationFence']['handoffSignature'], $plan814829()['selectedPlan']['next814829PriorHandoffSignature']),
    'planner stat4 expression partial current source next814829 preserves next798813 fence' => static fn (TestRunner $t) => $t->same(range(798, 813), $plan814829()['stat4Next798813PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next814829 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext814829Handoff', $plan814829()['cursorProgram'][array_key_last($plan814829()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next814829 cursor mode' => static fn (TestRunner $t) => $t->same('next814-829-current-source-stat4-expression-partial-prep', $plan814829()['cursorProgram'][array_key_last($plan814829()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next814829 detail' => static fn (TestRunner $t) => $t->contains('NEXT814-829 PREPARED HANDOFF', $plan814829()['detail']),
    'planner stat4 expression partial current source next814829 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next814-829-prep', $plan814829()['dependencies'], true)),
    'planner stat4 expression partial current source next814829 dependency closure' => static fn (TestRunner $t) => $t->contains('next814-829 preparation extends', $plan814829()['dependency_closure']),
    'planner stat4 expression partial current source next814829 non overlap' => static fn (TestRunner $t) => $t->contains('next798-813 handoff windows', $plan814829()['non_overlap']),
    'planner stat4 expression partial current source next814829 malformed needed column' => static function (TestRunner $t) use ($prepared814829, $current814829, $terms814829): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext814829($prepared814829(), $current814829(), $terms814829(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next814829 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan814829): void {
        $plan = $plan814829();
        $t->same($plan['stat4Next814829PreparationFence']['handoffSignature'], $plan['selectedPlan']['next814829HandoffSignature']);
    };
}

return $tests;
