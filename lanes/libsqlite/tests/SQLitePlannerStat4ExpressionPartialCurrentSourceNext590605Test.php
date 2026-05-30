<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq590605 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like590605 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull590605 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between590605 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows590605 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples590605 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload590605 = static fn (array $row): array => [
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

$prepared590605 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next590605',
    'schemaCookie' => 3836,
    'stat4Generation' => 350,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next590605',
        'rootPage' => 38361,
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

$current590605 = static function (?array $rows = null, ?array $samples = null) use ($prepared590605, $rows590605, $samples590605, $payload590605): array {
    $source = $prepared590605();
    $source['name'] = 'current-wp-options-stat4-handoff-next590605';
    $source['schemaCookie'] = 3986;
    $source['stat4Generation'] = 918;
    $source['rows'] = $rows ?? $rows590605();
    $source['indexes'][0]['rootPage'] = 39868;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples590605();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload590605, $source['rows']);

    return $source;
};

$terms590605 = static fn (): array => [
    $between590605('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq590605('autoload', 'yes'),
    $notNull590605('option_name'),
    $eq590605('blog_id', 1),
    $like590605('option_name', 'plugin_%'),
];

$plan590605 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffFinalSeed(
    $prepared590605(),
    $current590605($rows, $samples),
    $terms590605(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next590605 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next590-605-prepared', $plan590605()['status']),
    'planner stat4 expression partial current source next590605 inherits next574589' => static fn (TestRunner $t) => $t->same(true, $plan590605()['selectedPlan']['next574589Prepared']),
    'planner stat4 expression partial current source next590605 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan590605()['selectedPlan']['next590605Prepared']),
    'planner stat4 expression partial current source next590605 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan590605()['stat4Next590605PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next590605 slice range' => static fn (TestRunner $t) => $t->same([590, 605], $plan590605()['stat4Next590605PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next590605 prior range' => static fn (TestRunner $t) => $t->same([574, 589], $plan590605()['stat4Next590605PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next590605 prepared slices' => static fn (TestRunner $t) => $t->same(range(590, 605), $plan590605()['selectedPlan']['next590605PreparedSlices']),
    'planner stat4 expression partial current source next590605 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan590605()['selectedPlan']['next590605BlockedSlices']),
    'planner stat4 expression partial current source next590605 first continues' => static fn (TestRunner $t) => $t->same(574, $plan590605()['stat4Next590605PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next590605 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan590605()['stat4Next590605PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next590605 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan590605()['stat4Next590605PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next590605 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan590605()['stat4Next590605PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next590605 selected signature' => static fn (TestRunner $t) => $t->same($plan590605()['stat4Next590605PreparationFence']['handoffSignature'], $plan590605()['selectedPlan']['next590605HandoffSignature']),
    'planner stat4 expression partial current source next590605 stat4 signature' => static fn (TestRunner $t) => $t->same($plan590605()['stat4Next590605PreparationFence']['handoffSignature'], $plan590605()['stat4Fence']['next590605HandoffSignature']),
    'planner stat4 expression partial current source next590605 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan590605()['stat4Next574589PreparationFence']['handoffSignature'], $plan590605()['selectedPlan']['next590605PriorHandoffSignature']),
    'planner stat4 expression partial current source next590605 preserves next574589 fence' => static fn (TestRunner $t) => $t->same(range(574, 589), $plan590605()['stat4Next574589PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next590605 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialPreparedHandoffRange', $plan590605()['cursorProgram'][array_key_last($plan590605()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next590605 cursor mode' => static fn (TestRunner $t) => $t->same('prepared-handoff-range-current-source-stat4-expression-partial-prep', $plan590605()['cursorProgram'][array_key_last($plan590605()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next590605 detail' => static fn (TestRunner $t) => $t->contains('NEXT590-605 PREPARED HANDOFF', $plan590605()['detail']),
    'planner stat4 expression partial current source next590605 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next590-605-prep', $plan590605()['dependencies'], true)),
    'planner stat4 expression partial current source next590605 dependency closure' => static fn (TestRunner $t) => $t->contains('next590-605 preparation extends', $plan590605()['dependency_closure']),
    'planner stat4 expression partial current source next590605 non overlap' => static fn (TestRunner $t) => $t->contains('next574-589 handoff windows', $plan590605()['non_overlap']),
    'planner stat4 expression partial current source next590605 malformed needed column' => static function (TestRunner $t) use ($prepared590605, $current590605, $terms590605): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffFinalSeed($prepared590605(), $current590605(), $terms590605(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next590605 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan590605): void {
        $plan = $plan590605();
        $t->same($plan['stat4Next590605PreparationFence']['handoffSignature'], $plan['selectedPlan']['next590605HandoffSignature']);
    };
}

return $tests;
