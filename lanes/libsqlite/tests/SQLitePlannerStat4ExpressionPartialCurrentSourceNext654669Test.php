<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan;

$eq654669 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like654669 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull654669 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between654669 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows654669 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples654669 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload654669 = static fn (array $row): array => [
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

$prepared654669 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next654669',
    'schemaCookie' => 3852,
    'stat4Generation' => 366,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next654669',
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

$current654669 = static function (?array $rows = null, ?array $samples = null) use ($prepared654669, $rows654669, $samples654669, $payload654669): array {
    $source = $prepared654669();
    $source['name'] = 'current-wp-options-stat4-handoff-next654669';
    $source['schemaCookie'] = 4002;
    $source['stat4Generation'] = 934;
    $source['rows'] = $rows ?? $rows654669();
    $source['indexes'][0]['rootPage'] = 40028;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples654669();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload654669, $source['rows']);

    return $source;
};

$terms654669 = static fn (): array => [
    $between654669('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq654669('autoload', 'yes'),
    $notNull654669('option_name'),
    $eq654669('blog_id', 1),
    $like654669('option_name', 'plugin_%'),
];

$plan654669 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materializeNext654669(
    $prepared654669(),
    $current654669($rows, $samples),
    $terms654669(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next654669 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next654-669-prepared', $plan654669()['status']),
    'planner stat4 expression partial current source next654669 inherits next638653' => static fn (TestRunner $t) => $t->same(true, $plan654669()['selectedPlan']['next638653Prepared']),
    'planner stat4 expression partial current source next654669 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan654669()['selectedPlan']['next654669Prepared']),
    'planner stat4 expression partial current source next654669 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan654669()['stat4Next654669PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next654669 slice range' => static fn (TestRunner $t) => $t->same([654, 669], $plan654669()['stat4Next654669PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next654669 prior range' => static fn (TestRunner $t) => $t->same([638, 653], $plan654669()['stat4Next654669PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next654669 prepared slices' => static fn (TestRunner $t) => $t->same(range(654, 669), $plan654669()['selectedPlan']['next654669PreparedSlices']),
    'planner stat4 expression partial current source next654669 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan654669()['selectedPlan']['next654669BlockedSlices']),
    'planner stat4 expression partial current source next654669 first continues' => static fn (TestRunner $t) => $t->same(638, $plan654669()['stat4Next654669PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next654669 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan654669()['stat4Next654669PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next654669 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan654669()['stat4Next654669PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next654669 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan654669()['stat4Next654669PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next654669 selected signature' => static fn (TestRunner $t) => $t->same($plan654669()['stat4Next654669PreparationFence']['handoffSignature'], $plan654669()['selectedPlan']['next654669HandoffSignature']),
    'planner stat4 expression partial current source next654669 stat4 signature' => static fn (TestRunner $t) => $t->same($plan654669()['stat4Next654669PreparationFence']['handoffSignature'], $plan654669()['stat4Fence']['next654669HandoffSignature']),
    'planner stat4 expression partial current source next654669 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan654669()['stat4Next638653PreparationFence']['handoffSignature'], $plan654669()['selectedPlan']['next654669PriorHandoffSignature']),
    'planner stat4 expression partial current source next654669 preserves next638653 fence' => static fn (TestRunner $t) => $t->same(range(638, 653), $plan654669()['stat4Next638653PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next654669 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext654669Handoff', $plan654669()['cursorProgram'][array_key_last($plan654669()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next654669 cursor mode' => static fn (TestRunner $t) => $t->same('next654-669-current-source-stat4-expression-partial-prep', $plan654669()['cursorProgram'][array_key_last($plan654669()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next654669 detail' => static fn (TestRunner $t) => $t->contains('NEXT654-669 PREPARED HANDOFF', $plan654669()['detail']),
    'planner stat4 expression partial current source next654669 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next654-669-prep', $plan654669()['dependencies'], true)),
    'planner stat4 expression partial current source next654669 dependency closure' => static fn (TestRunner $t) => $t->contains('next654-669 preparation extends', $plan654669()['dependency_closure']),
    'planner stat4 expression partial current source next654669 non overlap' => static fn (TestRunner $t) => $t->contains('next638-653 handoff windows', $plan654669()['non_overlap']),
    'planner stat4 expression partial current source next654669 malformed needed column' => static function (TestRunner $t) use ($prepared654669, $current654669, $terms654669): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materializeNext654669($prepared654669(), $current654669(), $terms654669(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next654669 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan654669): void {
        $plan = $plan654669();
        $t->same($plan['stat4Next654669PreparationFence']['handoffSignature'], $plan['selectedPlan']['next654669HandoffSignature']);
    };
}

return $tests;
