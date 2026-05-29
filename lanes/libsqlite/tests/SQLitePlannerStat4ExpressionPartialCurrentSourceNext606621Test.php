<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan;

$eq606621 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like606621 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull606621 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between606621 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows606621 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples606621 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload606621 = static fn (array $row): array => [
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

$prepared606621 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next606621',
    'schemaCookie' => 3852,
    'stat4Generation' => 366,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next606621',
        'rootPage' => 38521,
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

$current606621 = static function (?array $rows = null, ?array $samples = null) use ($prepared606621, $rows606621, $samples606621, $payload606621): array {
    $source = $prepared606621();
    $source['name'] = 'current-wp-options-stat4-handoff-next606621';
    $source['schemaCookie'] = 4002;
    $source['stat4Generation'] = 934;
    $source['rows'] = $rows ?? $rows606621();
    $source['indexes'][0]['rootPage'] = 40028;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples606621();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload606621, $source['rows']);

    return $source;
};

$terms606621 = static fn (): array => [
    $between606621('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq606621('autoload', 'yes'),
    $notNull606621('option_name'),
    $eq606621('blog_id', 1),
    $like606621('option_name', 'plugin_%'),
];

$plan606621 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materializeNext606621(
    $prepared606621(),
    $current606621($rows, $samples),
    $terms606621(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next606621 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next606-621-prepared', $plan606621()['status']),
    'planner stat4 expression partial current source next606621 inherits next590605' => static fn (TestRunner $t) => $t->same(true, $plan606621()['selectedPlan']['next590605Prepared']),
    'planner stat4 expression partial current source next606621 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan606621()['selectedPlan']['next606621Prepared']),
    'planner stat4 expression partial current source next606621 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan606621()['stat4Next606621PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next606621 slice range' => static fn (TestRunner $t) => $t->same([606, 621], $plan606621()['stat4Next606621PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next606621 prior range' => static fn (TestRunner $t) => $t->same([590, 605], $plan606621()['stat4Next606621PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next606621 prepared slices' => static fn (TestRunner $t) => $t->same(range(606, 621), $plan606621()['selectedPlan']['next606621PreparedSlices']),
    'planner stat4 expression partial current source next606621 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan606621()['selectedPlan']['next606621BlockedSlices']),
    'planner stat4 expression partial current source next606621 first continues' => static fn (TestRunner $t) => $t->same(590, $plan606621()['stat4Next606621PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next606621 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan606621()['stat4Next606621PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next606621 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan606621()['stat4Next606621PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next606621 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan606621()['stat4Next606621PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next606621 selected signature' => static fn (TestRunner $t) => $t->same($plan606621()['stat4Next606621PreparationFence']['handoffSignature'], $plan606621()['selectedPlan']['next606621HandoffSignature']),
    'planner stat4 expression partial current source next606621 stat4 signature' => static fn (TestRunner $t) => $t->same($plan606621()['stat4Next606621PreparationFence']['handoffSignature'], $plan606621()['stat4Fence']['next606621HandoffSignature']),
    'planner stat4 expression partial current source next606621 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan606621()['stat4Next590605PreparationFence']['handoffSignature'], $plan606621()['selectedPlan']['next606621PriorHandoffSignature']),
    'planner stat4 expression partial current source next606621 preserves next590605 fence' => static fn (TestRunner $t) => $t->same(range(590, 605), $plan606621()['stat4Next590605PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next606621 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext606621Handoff', $plan606621()['cursorProgram'][array_key_last($plan606621()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next606621 cursor mode' => static fn (TestRunner $t) => $t->same('next606-621-current-source-stat4-expression-partial-prep', $plan606621()['cursorProgram'][array_key_last($plan606621()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next606621 detail' => static fn (TestRunner $t) => $t->contains('NEXT606-621 PREPARED HANDOFF', $plan606621()['detail']),
    'planner stat4 expression partial current source next606621 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next606-621-prep', $plan606621()['dependencies'], true)),
    'planner stat4 expression partial current source next606621 dependency closure' => static fn (TestRunner $t) => $t->contains('next606-621 preparation extends', $plan606621()['dependency_closure']),
    'planner stat4 expression partial current source next606621 non overlap' => static fn (TestRunner $t) => $t->contains('next590-605 handoff windows', $plan606621()['non_overlap']),
    'planner stat4 expression partial current source next606621 malformed needed column' => static function (TestRunner $t) use ($prepared606621, $current606621, $terms606621): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materializeNext606621($prepared606621(), $current606621(), $terms606621(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next606621 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan606621): void {
        $plan = $plan606621();
        $t->same($plan['stat4Next606621PreparationFence']['handoffSignature'], $plan['selectedPlan']['next606621HandoffSignature']);
    };
}

return $tests;
