<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq686701 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like686701 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull686701 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between686701 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows686701 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples686701 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload686701 = static fn (array $row): array => [
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

$prepared686701 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next686701',
    'schemaCookie' => 3852,
    'stat4Generation' => 366,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next686701',
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

$current686701 = static function (?array $rows = null, ?array $samples = null) use ($prepared686701, $rows686701, $samples686701, $payload686701): array {
    $source = $prepared686701();
    $source['name'] = 'current-wp-options-stat4-handoff-next686701';
    $source['schemaCookie'] = 4002;
    $source['stat4Generation'] = 934;
    $source['rows'] = $rows ?? $rows686701();
    $source['indexes'][0]['rootPage'] = 40028;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples686701();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload686701, $source['rows']);

    return $source;
};

$terms686701 = static fn (): array => [
    $between686701('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq686701('autoload', 'yes'),
    $notNull686701('option_name'),
    $eq686701('blog_id', 1),
    $like686701('option_name', 'plugin_%'),
];

$plan686701 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4ExpressionPartialLateHandoffStageTwo(
    $prepared686701(),
    $current686701($rows, $samples),
    $terms686701(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next686701 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next686-701-prepared', $plan686701()['status']),
    'planner stat4 expression partial current source next686701 inherits next670685' => static fn (TestRunner $t) => $t->same(true, $plan686701()['selectedPlan']['next670685Prepared']),
    'planner stat4 expression partial current source next686701 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan686701()['selectedPlan']['next686701Prepared']),
    'planner stat4 expression partial current source next686701 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan686701()['stat4Next686701PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next686701 slice range' => static fn (TestRunner $t) => $t->same([686, 701], $plan686701()['stat4Next686701PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next686701 prior range' => static fn (TestRunner $t) => $t->same([670, 685], $plan686701()['stat4Next686701PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next686701 prepared slices' => static fn (TestRunner $t) => $t->same(range(686, 701), $plan686701()['selectedPlan']['next686701PreparedSlices']),
    'planner stat4 expression partial current source next686701 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan686701()['selectedPlan']['next686701BlockedSlices']),
    'planner stat4 expression partial current source next686701 first continues' => static fn (TestRunner $t) => $t->same(670, $plan686701()['stat4Next686701PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next686701 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan686701()['stat4Next686701PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next686701 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan686701()['stat4Next686701PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next686701 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan686701()['stat4Next686701PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next686701 selected signature' => static fn (TestRunner $t) => $t->same($plan686701()['stat4Next686701PreparationFence']['handoffSignature'], $plan686701()['selectedPlan']['next686701HandoffSignature']),
    'planner stat4 expression partial current source next686701 stat4 signature' => static fn (TestRunner $t) => $t->same($plan686701()['stat4Next686701PreparationFence']['handoffSignature'], $plan686701()['stat4Fence']['next686701HandoffSignature']),
    'planner stat4 expression partial current source next686701 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan686701()['stat4Next670685PreparationFence']['handoffSignature'], $plan686701()['selectedPlan']['next686701PriorHandoffSignature']),
    'planner stat4 expression partial current source next686701 preserves next670685 fence' => static fn (TestRunner $t) => $t->same(range(670, 685), $plan686701()['stat4Next670685PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next686701 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialPreparedHandoffRange', $plan686701()['cursorProgram'][array_key_last($plan686701()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next686701 cursor mode' => static fn (TestRunner $t) => $t->same('prepared-handoff-range-current-source-stat4-expression-partial-prep', $plan686701()['cursorProgram'][array_key_last($plan686701()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next686701 detail' => static fn (TestRunner $t) => $t->contains('NEXT686-701 PREPARED HANDOFF', $plan686701()['detail']),
    'planner stat4 expression partial current source next686701 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next686-701-prep', $plan686701()['dependencies'], true)),
    'planner stat4 expression partial current source next686701 dependency closure' => static fn (TestRunner $t) => $t->contains('next686-701 preparation extends', $plan686701()['dependency_closure']),
    'planner stat4 expression partial current source next686701 non overlap' => static fn (TestRunner $t) => $t->contains('next670-685 handoff windows', $plan686701()['non_overlap']),
    'planner stat4 expression partial current source next686701 malformed needed column' => static function (TestRunner $t) use ($prepared686701, $current686701, $terms686701): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4ExpressionPartialLateHandoffStageTwo($prepared686701(), $current686701(), $terms686701(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next686701 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan686701): void {
        $plan = $plan686701();
        $t->same($plan['stat4Next686701PreparationFence']['handoffSignature'], $plan['selectedPlan']['next686701HandoffSignature']);
    };
}

return $tests;
