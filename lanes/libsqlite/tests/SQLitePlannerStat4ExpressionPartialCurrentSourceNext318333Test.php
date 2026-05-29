<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq318333 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like318333 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull318333 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between318333 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows318333 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples318333 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload318333 = static fn (array $row): array => [
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

$prepared318333 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next318333',
    'schemaCookie' => 3180,
    'stat4Generation' => 318,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next318333',
        'rootPage' => 31801,
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

$current318333 = static function (?array $rows = null, ?array $samples = null) use ($prepared318333, $rows318333, $samples318333, $payload318333): array {
    $source = $prepared318333();
    $source['name'] = 'current-wp-options-stat4-handoff-next318333';
    $source['schemaCookie'] = 3188;
    $source['stat4Generation'] = 902;
    $source['rows'] = $rows ?? $rows318333();
    $source['indexes'][0]['rootPage'] = 31888;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples318333();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload318333, $source['rows']);

    return $source;
};

$terms318333 = static fn (): array => [
    $between318333('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq318333('autoload', 'yes'),
    $notNull318333('option_name'),
    $eq318333('blog_id', 1),
    $like318333('option_name', 'plugin_%'),
];

$plan318333 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext318333(
    $prepared318333(),
    $current318333($rows, $samples),
    $terms318333(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next318333 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next318-333-prepared', $plan318333()['status']),
    'planner stat4 expression partial current source next318333 inherits next302317' => static fn (TestRunner $t) => $t->same(true, $plan318333()['selectedPlan']['next302317Prepared']),
    'planner stat4 expression partial current source next318333 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan318333()['selectedPlan']['next318333Prepared']),
    'planner stat4 expression partial current source next318333 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan318333()['stat4Next318333PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next318333 slice range' => static fn (TestRunner $t) => $t->same([318, 333], $plan318333()['stat4Next318333PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next318333 prior range' => static fn (TestRunner $t) => $t->same([302, 317], $plan318333()['stat4Next318333PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next318333 prepared slices' => static fn (TestRunner $t) => $t->same(range(318, 333), $plan318333()['selectedPlan']['next318333PreparedSlices']),
    'planner stat4 expression partial current source next318333 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan318333()['selectedPlan']['next318333BlockedSlices']),
    'planner stat4 expression partial current source next318333 first continues' => static fn (TestRunner $t) => $t->same(302, $plan318333()['stat4Next318333PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next318333 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan318333()['stat4Next318333PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next318333 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan318333()['stat4Next318333PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next318333 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan318333()['stat4Next318333PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next318333 selected signature' => static fn (TestRunner $t) => $t->same($plan318333()['stat4Next318333PreparationFence']['handoffSignature'], $plan318333()['selectedPlan']['next318333HandoffSignature']),
    'planner stat4 expression partial current source next318333 stat4 signature' => static fn (TestRunner $t) => $t->same($plan318333()['stat4Next318333PreparationFence']['handoffSignature'], $plan318333()['stat4Fence']['next318333HandoffSignature']),
    'planner stat4 expression partial current source next318333 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan318333()['stat4Next302317PreparationFence']['handoffSignature'], $plan318333()['selectedPlan']['next318333PriorHandoffSignature']),
    'planner stat4 expression partial current source next318333 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext318333Handoff', $plan318333()['cursorProgram'][array_key_last($plan318333()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next318333 cursor mode' => static fn (TestRunner $t) => $t->same('next318-333-current-source-stat4-expression-partial-prep', $plan318333()['cursorProgram'][array_key_last($plan318333()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next318333 detail' => static fn (TestRunner $t) => $t->contains('NEXT318-333 PREPARED HANDOFF', $plan318333()['detail']),
    'planner stat4 expression partial current source next318333 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next318-333-prep', $plan318333()['dependencies'], true)),
    'planner stat4 expression partial current source next318333 dependency closure' => static fn (TestRunner $t) => $t->contains('next318-333 preparation extends', $plan318333()['dependency_closure']),
    'planner stat4 expression partial current source next318333 non overlap' => static fn (TestRunner $t) => $t->contains('next302-317 handoff windows', $plan318333()['non_overlap']),
    'planner stat4 expression partial current source next318333 malformed needed column' => static function (TestRunner $t) use ($prepared318333, $current318333, $terms318333): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext318333($prepared318333(), $current318333(), $terms318333(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next318333 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan318333): void {
        $plan = $plan318333();
        $t->same($plan['stat4Next318333PreparationFence']['handoffSignature'], $plan['selectedPlan']['next318333HandoffSignature']);
    };
}

return $tests;
