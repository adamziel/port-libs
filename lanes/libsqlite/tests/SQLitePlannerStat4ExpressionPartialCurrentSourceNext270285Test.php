<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq270285 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like270285 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull270285 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between270285 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows270285 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples270285 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload270285 = static fn (array $row): array => [
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

$prepared270285 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next270285',
    'schemaCookie' => 2700,
    'stat4Generation' => 270,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next270285',
        'rootPage' => 27001,
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

$current270285 = static function (?array $rows = null, ?array $samples = null) use ($prepared270285, $rows270285, $samples270285, $payload270285): array {
    $source = $prepared270285();
    $source['name'] = 'current-wp-options-stat4-handoff-next270285';
    $source['schemaCookie'] = 2708;
    $source['stat4Generation'] = 870;
    $source['rows'] = $rows ?? $rows270285();
    $source['indexes'][0]['rootPage'] = 27088;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples270285();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload270285, $source['rows']);

    return $source;
};

$terms270285 = static fn (): array => [
    $between270285('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq270285('autoload', 'yes'),
    $notNull270285('option_name'),
    $eq270285('blog_id', 1),
    $like270285('option_name', 'plugin_%'),
];

$plan270285 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext270285(
    $prepared270285(),
    $current270285($rows, $samples),
    $terms270285(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next270285 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next270-285-prepared', $plan270285()['status']),
    'planner stat4 expression partial current source next270285 inherits next254269' => static fn (TestRunner $t) => $t->same(true, $plan270285()['selectedPlan']['next254269Prepared']),
    'planner stat4 expression partial current source next270285 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan270285()['selectedPlan']['next270285Prepared']),
    'planner stat4 expression partial current source next270285 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan270285()['stat4Next270285PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next270285 slice range' => static fn (TestRunner $t) => $t->same([270, 285], $plan270285()['stat4Next270285PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next270285 prior range' => static fn (TestRunner $t) => $t->same([254, 269], $plan270285()['stat4Next270285PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next270285 prepared slices' => static fn (TestRunner $t) => $t->same(range(270, 285), $plan270285()['selectedPlan']['next270285PreparedSlices']),
    'planner stat4 expression partial current source next270285 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan270285()['selectedPlan']['next270285BlockedSlices']),
    'planner stat4 expression partial current source next270285 first continues' => static fn (TestRunner $t) => $t->same(254, $plan270285()['stat4Next270285PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next270285 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan270285()['stat4Next270285PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next270285 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan270285()['stat4Next270285PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next270285 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan270285()['stat4Next270285PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next270285 selected signature' => static fn (TestRunner $t) => $t->same($plan270285()['stat4Next270285PreparationFence']['handoffSignature'], $plan270285()['selectedPlan']['next270285HandoffSignature']),
    'planner stat4 expression partial current source next270285 stat4 signature' => static fn (TestRunner $t) => $t->same($plan270285()['stat4Next270285PreparationFence']['handoffSignature'], $plan270285()['stat4Fence']['next270285HandoffSignature']),
    'planner stat4 expression partial current source next270285 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan270285()['stat4Next254269PreparationFence']['handoffSignature'], $plan270285()['selectedPlan']['next270285PriorHandoffSignature']),
    'planner stat4 expression partial current source next270285 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext270285Handoff', $plan270285()['cursorProgram'][array_key_last($plan270285()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next270285 cursor mode' => static fn (TestRunner $t) => $t->same('next270-285-current-source-stat4-expression-partial-prep', $plan270285()['cursorProgram'][array_key_last($plan270285()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next270285 detail' => static fn (TestRunner $t) => $t->contains('NEXT270-285 PREPARED HANDOFF', $plan270285()['detail']),
    'planner stat4 expression partial current source next270285 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next270-285-prep', $plan270285()['dependencies'], true)),
    'planner stat4 expression partial current source next270285 dependency closure' => static fn (TestRunner $t) => $t->contains('next270-285 preparation extends', $plan270285()['dependency_closure']),
    'planner stat4 expression partial current source next270285 non overlap' => static fn (TestRunner $t) => $t->contains('next254-269 handoff windows', $plan270285()['non_overlap']),
    'planner stat4 expression partial current source next270285 malformed needed column' => static function (TestRunner $t) use ($prepared270285, $current270285, $terms270285): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext270285($prepared270285(), $current270285(), $terms270285(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next270285 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan270285): void {
        $plan = $plan270285();
        $t->same($plan['stat4Next270285PreparationFence']['handoffSignature'], $plan['selectedPlan']['next270285HandoffSignature']);
    };
}

return $tests;
