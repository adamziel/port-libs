<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq798813 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like798813 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull798813 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between798813 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows798813 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples798813 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload798813 = static fn (array $row): array => [
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

$prepared798813 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next798813',
    'schemaCookie' => 3868,
    'stat4Generation' => 382,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next798813',
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

$current798813 = static function (?array $rows = null, ?array $samples = null) use ($prepared798813, $rows798813, $samples798813, $payload798813): array {
    $source = $prepared798813();
    $source['name'] = 'current-wp-options-stat4-handoff-next798813';
    $source['schemaCookie'] = 4018;
    $source['stat4Generation'] = 950;
    $source['rows'] = $rows ?? $rows798813();
    $source['indexes'][0]['rootPage'] = 40188;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples798813();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload798813, $source['rows']);

    return $source;
};

$terms798813 = static fn (): array => [
    $between798813('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq798813('autoload', 'yes'),
    $notNull798813('option_name'),
    $eq798813('blog_id', 1),
    $like798813('option_name', 'plugin_%'),
];

$plan798813 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffValidationRange(
    $prepared798813(),
    $current798813($rows, $samples),
    $terms798813(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next798813 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next798-813-prepared', $plan798813()['status']),
    'planner stat4 expression partial current source next798813 inherits next782797' => static fn (TestRunner $t) => $t->same(true, $plan798813()['selectedPlan']['next782797Prepared']),
    'planner stat4 expression partial current source next798813 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan798813()['selectedPlan']['next798813Prepared']),
    'planner stat4 expression partial current source next798813 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan798813()['stat4Next798813PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next798813 slice range' => static fn (TestRunner $t) => $t->same([798, 813], $plan798813()['stat4Next798813PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next798813 prior range' => static fn (TestRunner $t) => $t->same([782, 797], $plan798813()['stat4Next798813PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next798813 prepared slices' => static fn (TestRunner $t) => $t->same(range(798, 813), $plan798813()['selectedPlan']['next798813PreparedSlices']),
    'planner stat4 expression partial current source next798813 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan798813()['selectedPlan']['next798813BlockedSlices']),
    'planner stat4 expression partial current source next798813 first continues' => static fn (TestRunner $t) => $t->same(782, $plan798813()['stat4Next798813PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next798813 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan798813()['stat4Next798813PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next798813 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan798813()['stat4Next798813PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next798813 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan798813()['stat4Next798813PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next798813 selected signature' => static fn (TestRunner $t) => $t->same($plan798813()['stat4Next798813PreparationFence']['handoffSignature'], $plan798813()['selectedPlan']['next798813HandoffSignature']),
    'planner stat4 expression partial current source next798813 stat4 signature' => static fn (TestRunner $t) => $t->same($plan798813()['stat4Next798813PreparationFence']['handoffSignature'], $plan798813()['stat4Fence']['next798813HandoffSignature']),
    'planner stat4 expression partial current source next798813 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan798813()['stat4Next782797PreparationFence']['handoffSignature'], $plan798813()['selectedPlan']['next798813PriorHandoffSignature']),
    'planner stat4 expression partial current source next798813 preserves next782797 fence' => static fn (TestRunner $t) => $t->same(range(782, 797), $plan798813()['stat4Next782797PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next798813 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialPreparedHandoffRange', $plan798813()['cursorProgram'][array_key_last($plan798813()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next798813 cursor mode' => static fn (TestRunner $t) => $t->same('prepared-handoff-range-current-source-stat4-expression-partial-prep', $plan798813()['cursorProgram'][array_key_last($plan798813()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next798813 detail' => static fn (TestRunner $t) => $t->contains('NEXT798-813 PREPARED HANDOFF', $plan798813()['detail']),
    'planner stat4 expression partial current source next798813 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next798-813-prep', $plan798813()['dependencies'], true)),
    'planner stat4 expression partial current source next798813 dependency closure' => static fn (TestRunner $t) => $t->contains('next798-813 preparation extends', $plan798813()['dependency_closure']),
    'planner stat4 expression partial current source next798813 non overlap' => static fn (TestRunner $t) => $t->contains('next782-797 handoff windows', $plan798813()['non_overlap']),
    'planner stat4 expression partial current source next798813 malformed needed column' => static function (TestRunner $t) use ($prepared798813, $current798813, $terms798813): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffValidationRange($prepared798813(), $current798813(), $terms798813(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next798813 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan798813): void {
        $plan = $plan798813();
        $t->same($plan['stat4Next798813PreparationFence']['handoffSignature'], $plan['selectedPlan']['next798813HandoffSignature']);
    };
}

return $tests;
