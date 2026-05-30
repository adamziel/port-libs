<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq478493 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like478493 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull478493 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between478493 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows478493 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples478493 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload478493 = static fn (array $row): array => [
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

$prepared478493 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next478493',
    'schemaCookie' => 3820,
    'stat4Generation' => 334,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next478493',
        'rootPage' => 38201,
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

$current478493 = static function (?array $rows = null, ?array $samples = null) use ($prepared478493, $rows478493, $samples478493, $payload478493): array {
    $source = $prepared478493();
    $source['name'] = 'current-wp-options-stat4-handoff-next478493';
    $source['schemaCookie'] = 3970;
    $source['stat4Generation'] = 902;
    $source['rows'] = $rows ?? $rows478493();
    $source['indexes'][0]['rootPage'] = 39708;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples478493();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload478493, $source['rows']);

    return $source;
};

$terms478493 = static fn (): array => [
    $between478493('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq478493('autoload', 'yes'),
    $notNull478493('option_name'),
    $eq478493('blog_id', 1),
    $like478493('option_name', 'plugin_%'),
];

$plan478493 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4ExpressionPartialPreparedBridgeSecondContinuation(
    $prepared478493(),
    $current478493($rows, $samples),
    $terms478493(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next478493 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next478-493-prepared', $plan478493()['status']),
    'planner stat4 expression partial current source next478493 inherits next462477' => static fn (TestRunner $t) => $t->same(true, $plan478493()['selectedPlan']['next462477Prepared']),
    'planner stat4 expression partial current source next478493 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan478493()['selectedPlan']['next478493Prepared']),
    'planner stat4 expression partial current source next478493 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan478493()['stat4Next478493PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next478493 slice range' => static fn (TestRunner $t) => $t->same([478, 493], $plan478493()['stat4Next478493PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next478493 prior range' => static fn (TestRunner $t) => $t->same([462, 477], $plan478493()['stat4Next478493PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next478493 prepared slices' => static fn (TestRunner $t) => $t->same(range(478, 493), $plan478493()['selectedPlan']['next478493PreparedSlices']),
    'planner stat4 expression partial current source next478493 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan478493()['selectedPlan']['next478493BlockedSlices']),
    'planner stat4 expression partial current source next478493 first continues' => static fn (TestRunner $t) => $t->same(462, $plan478493()['stat4Next478493PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next478493 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan478493()['stat4Next478493PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next478493 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan478493()['stat4Next478493PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next478493 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan478493()['stat4Next478493PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next478493 selected signature' => static fn (TestRunner $t) => $t->same($plan478493()['stat4Next478493PreparationFence']['handoffSignature'], $plan478493()['selectedPlan']['next478493HandoffSignature']),
    'planner stat4 expression partial current source next478493 stat4 signature' => static fn (TestRunner $t) => $t->same($plan478493()['stat4Next478493PreparationFence']['handoffSignature'], $plan478493()['stat4Fence']['next478493HandoffSignature']),
    'planner stat4 expression partial current source next478493 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan478493()['stat4Next462477PreparationFence']['handoffSignature'], $plan478493()['selectedPlan']['next478493PriorHandoffSignature']),
    'planner stat4 expression partial current source next478493 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialPreparedHandoffRange', $plan478493()['cursorProgram'][array_key_last($plan478493()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next478493 cursor mode' => static fn (TestRunner $t) => $t->same('prepared-handoff-range-current-source-stat4-expression-partial-prep', $plan478493()['cursorProgram'][array_key_last($plan478493()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next478493 detail' => static fn (TestRunner $t) => $t->contains('NEXT478-493 PREPARED HANDOFF', $plan478493()['detail']),
    'planner stat4 expression partial current source next478493 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next478-493-prep', $plan478493()['dependencies'], true)),
    'planner stat4 expression partial current source next478493 dependency closure' => static fn (TestRunner $t) => $t->contains('next478-493 preparation extends', $plan478493()['dependency_closure']),
    'planner stat4 expression partial current source next478493 non overlap' => static fn (TestRunner $t) => $t->contains('next462-477 handoff windows', $plan478493()['non_overlap']),
    'planner stat4 expression partial current source next478493 malformed needed column' => static function (TestRunner $t) use ($prepared478493, $current478493, $terms478493): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4ExpressionPartialPreparedBridgeSecondContinuation($prepared478493(), $current478493(), $terms478493(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next478493 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan478493): void {
        $plan = $plan478493();
        $t->same($plan['stat4Next478493PreparationFence']['handoffSignature'], $plan['selectedPlan']['next478493HandoffSignature']);
    };
}

return $tests;
