<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq526541 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like526541 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull526541 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between526541 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows526541 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples526541 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload526541 = static fn (array $row): array => [
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

$prepared526541 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next526541',
    'schemaCookie' => 3820,
    'stat4Generation' => 334,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next526541',
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

$current526541 = static function (?array $rows = null, ?array $samples = null) use ($prepared526541, $rows526541, $samples526541, $payload526541): array {
    $source = $prepared526541();
    $source['name'] = 'current-wp-options-stat4-handoff-next526541';
    $source['schemaCookie'] = 3970;
    $source['stat4Generation'] = 902;
    $source['rows'] = $rows ?? $rows526541();
    $source['indexes'][0]['rootPage'] = 39708;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples526541();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload526541, $source['rows']);

    return $source;
};

$terms526541 = static fn (): array => [
    $between526541('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq526541('autoload', 'yes'),
    $notNull526541('option_name'),
    $eq526541('blog_id', 1),
    $like526541('option_name', 'plugin_%'),
];

$plan526541 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4ExpressionPartialPreparedBridgeFifthContinuation(
    $prepared526541(),
    $current526541($rows, $samples),
    $terms526541(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next526541 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next526-541-prepared', $plan526541()['status']),
    'planner stat4 expression partial current source next526541 inherits next510525' => static fn (TestRunner $t) => $t->same(true, $plan526541()['selectedPlan']['next510525Prepared']),
    'planner stat4 expression partial current source next526541 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan526541()['selectedPlan']['next526541Prepared']),
    'planner stat4 expression partial current source next526541 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan526541()['stat4Next526541PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next526541 slice range' => static fn (TestRunner $t) => $t->same([526, 541], $plan526541()['stat4Next526541PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next526541 prior range' => static fn (TestRunner $t) => $t->same([510, 525], $plan526541()['stat4Next526541PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next526541 prepared slices' => static fn (TestRunner $t) => $t->same(range(526, 541), $plan526541()['selectedPlan']['next526541PreparedSlices']),
    'planner stat4 expression partial current source next526541 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan526541()['selectedPlan']['next526541BlockedSlices']),
    'planner stat4 expression partial current source next526541 first continues' => static fn (TestRunner $t) => $t->same(510, $plan526541()['stat4Next526541PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next526541 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan526541()['stat4Next526541PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next526541 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan526541()['stat4Next526541PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next526541 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan526541()['stat4Next526541PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next526541 selected signature' => static fn (TestRunner $t) => $t->same($plan526541()['stat4Next526541PreparationFence']['handoffSignature'], $plan526541()['selectedPlan']['next526541HandoffSignature']),
    'planner stat4 expression partial current source next526541 stat4 signature' => static fn (TestRunner $t) => $t->same($plan526541()['stat4Next526541PreparationFence']['handoffSignature'], $plan526541()['stat4Fence']['next526541HandoffSignature']),
    'planner stat4 expression partial current source next526541 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan526541()['stat4Next510525PreparationFence']['handoffSignature'], $plan526541()['selectedPlan']['next526541PriorHandoffSignature']),
    'planner stat4 expression partial current source next526541 preserves next510525 fence' => static fn (TestRunner $t) => $t->same(range(510, 525), $plan526541()['stat4Next510525PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next526541 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialPreparedHandoffRange', $plan526541()['cursorProgram'][array_key_last($plan526541()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next526541 cursor mode' => static fn (TestRunner $t) => $t->same('prepared-handoff-range-current-source-stat4-expression-partial-prep', $plan526541()['cursorProgram'][array_key_last($plan526541()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next526541 detail' => static fn (TestRunner $t) => $t->contains('NEXT526-541 PREPARED HANDOFF', $plan526541()['detail']),
    'planner stat4 expression partial current source next526541 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next526-541-prep', $plan526541()['dependencies'], true)),
    'planner stat4 expression partial current source next526541 dependency closure' => static fn (TestRunner $t) => $t->contains('next526-541 preparation extends', $plan526541()['dependency_closure']),
    'planner stat4 expression partial current source next526541 non overlap' => static fn (TestRunner $t) => $t->contains('next510-525 handoff windows', $plan526541()['non_overlap']),
    'planner stat4 expression partial current source next526541 malformed needed column' => static function (TestRunner $t) use ($prepared526541, $current526541, $terms526541): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4ExpressionPartialPreparedBridgeFifthContinuation($prepared526541(), $current526541(), $terms526541(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next526541 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan526541): void {
        $plan = $plan526541();
        $t->same($plan['stat4Next526541PreparationFence']['handoffSignature'], $plan['selectedPlan']['next526541HandoffSignature']);
    };
}

return $tests;
