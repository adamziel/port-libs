<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq414429 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like414429 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull414429 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between414429 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows414429 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples414429 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload414429 = static fn (array $row): array => [
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

$prepared414429 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next414429',
    'schemaCookie' => 3820,
    'stat4Generation' => 334,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next414429',
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

$current414429 = static function (?array $rows = null, ?array $samples = null) use ($prepared414429, $rows414429, $samples414429, $payload414429): array {
    $source = $prepared414429();
    $source['name'] = 'current-wp-options-stat4-handoff-next414429';
    $source['schemaCookie'] = 3970;
    $source['stat4Generation'] = 902;
    $source['rows'] = $rows ?? $rows414429();
    $source['indexes'][0]['rootPage'] = 39708;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples414429();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload414429, $source['rows']);

    return $source;
};

$terms414429 = static fn (): array => [
    $between414429('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq414429('autoload', 'yes'),
    $notNull414429('option_name'),
    $eq414429('blog_id', 1),
    $like414429('option_name', 'plugin_%'),
];

$plan414429 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffBridgePenultimate(
    $prepared414429(),
    $current414429($rows, $samples),
    $terms414429(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next414429 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next414-429-prepared', $plan414429()['status']),
    'planner stat4 expression partial current source next414429 inherits next398413' => static fn (TestRunner $t) => $t->same(true, $plan414429()['selectedPlan']['next398413Prepared']),
    'planner stat4 expression partial current source next414429 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan414429()['selectedPlan']['next414429Prepared']),
    'planner stat4 expression partial current source next414429 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan414429()['stat4Next414429PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next414429 slice range' => static fn (TestRunner $t) => $t->same([414, 429], $plan414429()['stat4Next414429PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next414429 prior range' => static fn (TestRunner $t) => $t->same([398, 413], $plan414429()['stat4Next414429PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next414429 prepared slices' => static fn (TestRunner $t) => $t->same(range(414, 429), $plan414429()['selectedPlan']['next414429PreparedSlices']),
    'planner stat4 expression partial current source next414429 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan414429()['selectedPlan']['next414429BlockedSlices']),
    'planner stat4 expression partial current source next414429 first continues' => static fn (TestRunner $t) => $t->same(398, $plan414429()['stat4Next414429PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next414429 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan414429()['stat4Next414429PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next414429 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan414429()['stat4Next414429PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next414429 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan414429()['stat4Next414429PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next414429 selected signature' => static fn (TestRunner $t) => $t->same($plan414429()['stat4Next414429PreparationFence']['handoffSignature'], $plan414429()['selectedPlan']['next414429HandoffSignature']),
    'planner stat4 expression partial current source next414429 stat4 signature' => static fn (TestRunner $t) => $t->same($plan414429()['stat4Next414429PreparationFence']['handoffSignature'], $plan414429()['stat4Fence']['next414429HandoffSignature']),
    'planner stat4 expression partial current source next414429 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan414429()['stat4Next398413PreparationFence']['handoffSignature'], $plan414429()['selectedPlan']['next414429PriorHandoffSignature']),
    'planner stat4 expression partial current source next414429 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialPreparedHandoffRange', $plan414429()['cursorProgram'][array_key_last($plan414429()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next414429 cursor mode' => static fn (TestRunner $t) => $t->same('prepared-handoff-range-current-source-stat4-expression-partial-prep', $plan414429()['cursorProgram'][array_key_last($plan414429()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next414429 detail' => static fn (TestRunner $t) => $t->contains('NEXT414-429 PREPARED HANDOFF', $plan414429()['detail']),
    'planner stat4 expression partial current source next414429 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next414-429-prep', $plan414429()['dependencies'], true)),
    'planner stat4 expression partial current source next414429 dependency closure' => static fn (TestRunner $t) => $t->contains('next414-429 preparation extends', $plan414429()['dependency_closure']),
    'planner stat4 expression partial current source next414429 non overlap' => static fn (TestRunner $t) => $t->contains('next398-413 handoff windows', $plan414429()['non_overlap']),
    'planner stat4 expression partial current source next414429 malformed needed column' => static function (TestRunner $t) use ($prepared414429, $current414429, $terms414429): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffBridgePenultimate($prepared414429(), $current414429(), $terms414429(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next414429 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan414429): void {
        $plan = $plan414429();
        $t->same($plan['stat4Next414429PreparationFence']['handoffSignature'], $plan['selectedPlan']['next414429HandoffSignature']);
    };
}

return $tests;
