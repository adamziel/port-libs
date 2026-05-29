<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan;

$eq9901005 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like9901005 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull9901005 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between9901005 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows9901005 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples9901005 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload9901005 = static fn (array $row): array => [
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

$prepared9901005 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next9901005',
    'schemaCookie' => 3920,
    'stat4Generation' => 398,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next9901005',
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

$current9901005 = static function (?array $rows = null, ?array $samples = null) use ($prepared9901005, $rows9901005, $samples9901005, $payload9901005): array {
    $source = $prepared9901005();
    $source['name'] = 'current-wp-options-stat4-handoff-next9901005';
    $source['schemaCookie'] = 4070;
    $source['stat4Generation'] = 966;
    $source['rows'] = $rows ?? $rows9901005();
    $source['indexes'][0]['rootPage'] = 40708;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples9901005();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload9901005, $source['rows']);

    return $source;
};

$terms9901005 = static fn (): array => [
    $between9901005('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq9901005('autoload', 'yes'),
    $notNull9901005('option_name'),
    $eq9901005('blog_id', 1),
    $like9901005('option_name', 'plugin_%'),
];

$plan9901005 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materializeNext9901005(
    $prepared9901005(),
    $current9901005($rows, $samples),
    $terms9901005(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next9901005 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next990-1005-prepared', $plan9901005()['status']),
    'planner stat4 expression partial current source next9901005 inherits next974989' => static fn (TestRunner $t) => $t->same(true, $plan9901005()['selectedPlan']['next974989Prepared']),
    'planner stat4 expression partial current source next9901005 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan9901005()['selectedPlan']['next9901005Prepared']),
    'planner stat4 expression partial current source next9901005 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan9901005()['stat4Next9901005PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next9901005 slice range' => static fn (TestRunner $t) => $t->same([990, 1005], $plan9901005()['stat4Next9901005PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next9901005 prior range' => static fn (TestRunner $t) => $t->same([974, 989], $plan9901005()['stat4Next9901005PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next9901005 prepared slices' => static fn (TestRunner $t) => $t->same(range(990, 1005), $plan9901005()['selectedPlan']['next9901005PreparedSlices']),
    'planner stat4 expression partial current source next9901005 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan9901005()['selectedPlan']['next9901005BlockedSlices']),
    'planner stat4 expression partial current source next9901005 first continues' => static fn (TestRunner $t) => $t->same(974, $plan9901005()['stat4Next9901005PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next9901005 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan9901005()['stat4Next9901005PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next9901005 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan9901005()['stat4Next9901005PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next9901005 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan9901005()['stat4Next9901005PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next9901005 selected signature' => static fn (TestRunner $t) => $t->same($plan9901005()['stat4Next9901005PreparationFence']['handoffSignature'], $plan9901005()['selectedPlan']['next9901005HandoffSignature']),
    'planner stat4 expression partial current source next9901005 stat4 signature' => static fn (TestRunner $t) => $t->same($plan9901005()['stat4Next9901005PreparationFence']['handoffSignature'], $plan9901005()['stat4Fence']['next9901005HandoffSignature']),
    'planner stat4 expression partial current source next9901005 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan9901005()['stat4Next974989PreparationFence']['handoffSignature'], $plan9901005()['selectedPlan']['next9901005PriorHandoffSignature']),
    'planner stat4 expression partial current source next9901005 preserves next974989 fence' => static fn (TestRunner $t) => $t->same(range(974, 989), $plan9901005()['stat4Next974989PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next9901005 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext9901005Handoff', $plan9901005()['cursorProgram'][array_key_last($plan9901005()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next9901005 cursor mode' => static fn (TestRunner $t) => $t->same('next990-1005-current-source-stat4-expression-partial-prep', $plan9901005()['cursorProgram'][array_key_last($plan9901005()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next9901005 detail' => static fn (TestRunner $t) => $t->contains('NEXT990-1005 PREPARED HANDOFF', $plan9901005()['detail']),
    'planner stat4 expression partial current source next9901005 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next990-1005-prep', $plan9901005()['dependencies'], true)),
    'planner stat4 expression partial current source next9901005 dependency closure' => static fn (TestRunner $t) => $t->contains('next990-1005 preparation extends', $plan9901005()['dependency_closure']),
    'planner stat4 expression partial current source next9901005 non overlap' => static fn (TestRunner $t) => $t->contains('next974-989 handoff windows', $plan9901005()['non_overlap']),
    'planner stat4 expression partial current source next9901005 malformed needed column' => static function (TestRunner $t) use ($prepared9901005, $current9901005, $terms9901005): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materializeNext9901005($prepared9901005(), $current9901005(), $terms9901005(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next9901005 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan9901005): void {
        $plan = $plan9901005();
        $t->same($plan['stat4Next9901005PreparationFence']['handoffSignature'], $plan['selectedPlan']['next9901005HandoffSignature']);
    };
}

return $tests;
