<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq670685 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like670685 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull670685 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between670685 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows670685 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples670685 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload670685 = static fn (array $row): array => [
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

$prepared670685 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next670685',
    'schemaCookie' => 3852,
    'stat4Generation' => 366,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next670685',
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

$current670685 = static function (?array $rows = null, ?array $samples = null) use ($prepared670685, $rows670685, $samples670685, $payload670685): array {
    $source = $prepared670685();
    $source['name'] = 'current-wp-options-stat4-handoff-next670685';
    $source['schemaCookie'] = 4002;
    $source['stat4Generation'] = 934;
    $source['rows'] = $rows ?? $rows670685();
    $source['indexes'][0]['rootPage'] = 40028;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples670685();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload670685, $source['rows']);

    return $source;
};

$terms670685 = static fn (): array => [
    $between670685('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq670685('autoload', 'yes'),
    $notNull670685('option_name'),
    $eq670685('blog_id', 1),
    $like670685('option_name', 'plugin_%'),
];

$plan670685 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4ExpressionPartialLateHandoffStageOne(
    $prepared670685(),
    $current670685($rows, $samples),
    $terms670685(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next670685 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next670-685-prepared', $plan670685()['status']),
    'planner stat4 expression partial current source next670685 inherits next654669' => static fn (TestRunner $t) => $t->same(true, $plan670685()['selectedPlan']['next654669Prepared']),
    'planner stat4 expression partial current source next670685 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan670685()['selectedPlan']['next670685Prepared']),
    'planner stat4 expression partial current source next670685 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan670685()['stat4Next670685PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next670685 slice range' => static fn (TestRunner $t) => $t->same([670, 685], $plan670685()['stat4Next670685PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next670685 prior range' => static fn (TestRunner $t) => $t->same([654, 669], $plan670685()['stat4Next670685PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next670685 prepared slices' => static fn (TestRunner $t) => $t->same(range(670, 685), $plan670685()['selectedPlan']['next670685PreparedSlices']),
    'planner stat4 expression partial current source next670685 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan670685()['selectedPlan']['next670685BlockedSlices']),
    'planner stat4 expression partial current source next670685 first continues' => static fn (TestRunner $t) => $t->same(654, $plan670685()['stat4Next670685PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next670685 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan670685()['stat4Next670685PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next670685 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan670685()['stat4Next670685PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next670685 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan670685()['stat4Next670685PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next670685 selected signature' => static fn (TestRunner $t) => $t->same($plan670685()['stat4Next670685PreparationFence']['handoffSignature'], $plan670685()['selectedPlan']['next670685HandoffSignature']),
    'planner stat4 expression partial current source next670685 stat4 signature' => static fn (TestRunner $t) => $t->same($plan670685()['stat4Next670685PreparationFence']['handoffSignature'], $plan670685()['stat4Fence']['next670685HandoffSignature']),
    'planner stat4 expression partial current source next670685 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan670685()['stat4Next654669PreparationFence']['handoffSignature'], $plan670685()['selectedPlan']['next670685PriorHandoffSignature']),
    'planner stat4 expression partial current source next670685 preserves next654669 fence' => static fn (TestRunner $t) => $t->same(range(654, 669), $plan670685()['stat4Next654669PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next670685 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialPreparedHandoffRange', $plan670685()['cursorProgram'][array_key_last($plan670685()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next670685 cursor mode' => static fn (TestRunner $t) => $t->same('prepared-handoff-range-current-source-stat4-expression-partial-prep', $plan670685()['cursorProgram'][array_key_last($plan670685()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next670685 detail' => static fn (TestRunner $t) => $t->contains('NEXT670-685 PREPARED HANDOFF', $plan670685()['detail']),
    'planner stat4 expression partial current source next670685 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next670-685-prep', $plan670685()['dependencies'], true)),
    'planner stat4 expression partial current source next670685 dependency closure' => static fn (TestRunner $t) => $t->contains('next670-685 preparation extends', $plan670685()['dependency_closure']),
    'planner stat4 expression partial current source next670685 non overlap' => static fn (TestRunner $t) => $t->contains('next654-669 handoff windows', $plan670685()['non_overlap']),
    'planner stat4 expression partial current source next670685 malformed needed column' => static function (TestRunner $t) use ($prepared670685, $current670685, $terms670685): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4ExpressionPartialLateHandoffStageOne($prepared670685(), $current670685(), $terms670685(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next670685 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan670685): void {
        $plan = $plan670685();
        $t->same($plan['stat4Next670685PreparationFence']['handoffSignature'], $plan['selectedPlan']['next670685HandoffSignature']);
    };
}

return $tests;
