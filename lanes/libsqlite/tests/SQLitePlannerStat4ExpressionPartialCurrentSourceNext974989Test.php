<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq974989 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like974989 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull974989 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between974989 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows974989 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples974989 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload974989 = static fn (array $row): array => [
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

$prepared974989 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next974989',
    'schemaCookie' => 3920,
    'stat4Generation' => 398,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next974989',
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

$current974989 = static function (?array $rows = null, ?array $samples = null) use ($prepared974989, $rows974989, $samples974989, $payload974989): array {
    $source = $prepared974989();
    $source['name'] = 'current-wp-options-stat4-handoff-next974989';
    $source['schemaCookie'] = 4070;
    $source['stat4Generation'] = 966;
    $source['rows'] = $rows ?? $rows974989();
    $source['indexes'][0]['rootPage'] = 40708;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples974989();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload974989, $source['rows']);

    return $source;
};

$terms974989 = static fn (): array => [
    $between974989('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq974989('autoload', 'yes'),
    $notNull974989('option_name'),
    $eq974989('blog_id', 1),
    $like974989('option_name', 'plugin_%'),
];

$plan974989 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext974989(
    $prepared974989(),
    $current974989($rows, $samples),
    $terms974989(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next974989 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next974-989-prepared', $plan974989()['status']),
    'planner stat4 expression partial current source next974989 inherits next958973' => static fn (TestRunner $t) => $t->same(true, $plan974989()['selectedPlan']['next958973Prepared']),
    'planner stat4 expression partial current source next974989 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan974989()['selectedPlan']['next974989Prepared']),
    'planner stat4 expression partial current source next974989 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan974989()['stat4Next974989PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next974989 slice range' => static fn (TestRunner $t) => $t->same([974, 989], $plan974989()['stat4Next974989PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next974989 prior range' => static fn (TestRunner $t) => $t->same([958, 973], $plan974989()['stat4Next974989PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next974989 prepared slices' => static fn (TestRunner $t) => $t->same(range(974, 989), $plan974989()['selectedPlan']['next974989PreparedSlices']),
    'planner stat4 expression partial current source next974989 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan974989()['selectedPlan']['next974989BlockedSlices']),
    'planner stat4 expression partial current source next974989 first continues' => static fn (TestRunner $t) => $t->same(958, $plan974989()['stat4Next974989PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next974989 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan974989()['stat4Next974989PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next974989 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan974989()['stat4Next974989PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next974989 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan974989()['stat4Next974989PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next974989 selected signature' => static fn (TestRunner $t) => $t->same($plan974989()['stat4Next974989PreparationFence']['handoffSignature'], $plan974989()['selectedPlan']['next974989HandoffSignature']),
    'planner stat4 expression partial current source next974989 stat4 signature' => static fn (TestRunner $t) => $t->same($plan974989()['stat4Next974989PreparationFence']['handoffSignature'], $plan974989()['stat4Fence']['next974989HandoffSignature']),
    'planner stat4 expression partial current source next974989 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan974989()['stat4Next958973PreparationFence']['handoffSignature'], $plan974989()['selectedPlan']['next974989PriorHandoffSignature']),
    'planner stat4 expression partial current source next974989 preserves next958973 fence' => static fn (TestRunner $t) => $t->same(range(958, 973), $plan974989()['stat4Next958973PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next974989 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext974989Handoff', $plan974989()['cursorProgram'][array_key_last($plan974989()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next974989 cursor mode' => static fn (TestRunner $t) => $t->same('next974-989-current-source-stat4-expression-partial-prep', $plan974989()['cursorProgram'][array_key_last($plan974989()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next974989 detail' => static fn (TestRunner $t) => $t->contains('NEXT974-989 PREPARED HANDOFF', $plan974989()['detail']),
    'planner stat4 expression partial current source next974989 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next974-989-prep', $plan974989()['dependencies'], true)),
    'planner stat4 expression partial current source next974989 dependency closure' => static fn (TestRunner $t) => $t->contains('next974-989 preparation extends', $plan974989()['dependency_closure']),
    'planner stat4 expression partial current source next974989 non overlap' => static fn (TestRunner $t) => $t->contains('next958-973 handoff windows', $plan974989()['non_overlap']),
    'planner stat4 expression partial current source next974989 malformed needed column' => static function (TestRunner $t) use ($prepared974989, $current974989, $terms974989): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext974989($prepared974989(), $current974989(), $terms974989(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next974989 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan974989): void {
        $plan = $plan974989();
        $t->same($plan['stat4Next974989PreparationFence']['handoffSignature'], $plan['selectedPlan']['next974989HandoffSignature']);
    };
}

return $tests;
