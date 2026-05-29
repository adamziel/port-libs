<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq382397 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like382397 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull382397 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between382397 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows382397 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples382397 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload382397 = static fn (array $row): array => [
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

$prepared382397 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next382397',
    'schemaCookie' => 3820,
    'stat4Generation' => 334,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next382397',
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

$current382397 = static function (?array $rows = null, ?array $samples = null) use ($prepared382397, $rows382397, $samples382397, $payload382397): array {
    $source = $prepared382397();
    $source['name'] = 'current-wp-options-stat4-handoff-next382397';
    $source['schemaCookie'] = 3970;
    $source['stat4Generation'] = 902;
    $source['rows'] = $rows ?? $rows382397();
    $source['indexes'][0]['rootPage'] = 39708;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples382397();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload382397, $source['rows']);

    return $source;
};

$terms382397 = static fn (): array => [
    $between382397('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq382397('autoload', 'yes'),
    $notNull382397('option_name'),
    $eq382397('blog_id', 1),
    $like382397('option_name', 'plugin_%'),
];

$plan382397 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffBridgeValidation(
    $prepared382397(),
    $current382397($rows, $samples),
    $terms382397(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next382397 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next382-397-prepared', $plan382397()['status']),
    'planner stat4 expression partial current source next382397 inherits next366381' => static fn (TestRunner $t) => $t->same(true, $plan382397()['selectedPlan']['next366381Prepared']),
    'planner stat4 expression partial current source next382397 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan382397()['selectedPlan']['next382397Prepared']),
    'planner stat4 expression partial current source next382397 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan382397()['stat4Next382397PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next382397 slice range' => static fn (TestRunner $t) => $t->same([382, 397], $plan382397()['stat4Next382397PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next382397 prior range' => static fn (TestRunner $t) => $t->same([366, 381], $plan382397()['stat4Next382397PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next382397 prepared slices' => static fn (TestRunner $t) => $t->same(range(382, 397), $plan382397()['selectedPlan']['next382397PreparedSlices']),
    'planner stat4 expression partial current source next382397 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan382397()['selectedPlan']['next382397BlockedSlices']),
    'planner stat4 expression partial current source next382397 first continues' => static fn (TestRunner $t) => $t->same(366, $plan382397()['stat4Next382397PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next382397 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan382397()['stat4Next382397PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next382397 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan382397()['stat4Next382397PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next382397 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan382397()['stat4Next382397PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next382397 selected signature' => static fn (TestRunner $t) => $t->same($plan382397()['stat4Next382397PreparationFence']['handoffSignature'], $plan382397()['selectedPlan']['next382397HandoffSignature']),
    'planner stat4 expression partial current source next382397 stat4 signature' => static fn (TestRunner $t) => $t->same($plan382397()['stat4Next382397PreparationFence']['handoffSignature'], $plan382397()['stat4Fence']['next382397HandoffSignature']),
    'planner stat4 expression partial current source next382397 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan382397()['stat4Next366381PreparationFence']['handoffSignature'], $plan382397()['selectedPlan']['next382397PriorHandoffSignature']),
    'planner stat4 expression partial current source next382397 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext382397Handoff', $plan382397()['cursorProgram'][array_key_last($plan382397()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next382397 cursor mode' => static fn (TestRunner $t) => $t->same('next382-397-current-source-stat4-expression-partial-prep', $plan382397()['cursorProgram'][array_key_last($plan382397()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next382397 detail' => static fn (TestRunner $t) => $t->contains('NEXT382-397 PREPARED HANDOFF', $plan382397()['detail']),
    'planner stat4 expression partial current source next382397 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next382-397-prep', $plan382397()['dependencies'], true)),
    'planner stat4 expression partial current source next382397 dependency closure' => static fn (TestRunner $t) => $t->contains('next382-397 preparation extends', $plan382397()['dependency_closure']),
    'planner stat4 expression partial current source next382397 non overlap' => static fn (TestRunner $t) => $t->contains('next366-381 handoff windows', $plan382397()['non_overlap']),
    'planner stat4 expression partial current source next382397 malformed needed column' => static function (TestRunner $t) use ($prepared382397, $current382397, $terms382397): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffBridgeValidation($prepared382397(), $current382397(), $terms382397(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next382397 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan382397): void {
        $plan = $plan382397();
        $t->same($plan['stat4Next382397PreparationFence']['handoffSignature'], $plan['selectedPlan']['next382397HandoffSignature']);
    };
}

return $tests;
