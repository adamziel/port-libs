<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq750765 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like750765 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull750765 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between750765 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows750765 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples750765 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload750765 = static fn (array $row): array => [
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

$prepared750765 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next750765',
    'schemaCookie' => 3852,
    'stat4Generation' => 366,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next750765',
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

$current750765 = static function (?array $rows = null, ?array $samples = null) use ($prepared750765, $rows750765, $samples750765, $payload750765): array {
    $source = $prepared750765();
    $source['name'] = 'current-wp-options-stat4-handoff-next750765';
    $source['schemaCookie'] = 4002;
    $source['stat4Generation'] = 934;
    $source['rows'] = $rows ?? $rows750765();
    $source['indexes'][0]['rootPage'] = 40028;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples750765();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload750765, $source['rows']);

    return $source;
};

$terms750765 = static fn (): array => [
    $between750765('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq750765('autoload', 'yes'),
    $notNull750765('option_name'),
    $eq750765('blog_id', 1),
    $like750765('option_name', 'plugin_%'),
];

$plan750765 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffFirstContinuation(
    $prepared750765(),
    $current750765($rows, $samples),
    $terms750765(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next750765 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next750-765-prepared', $plan750765()['status']),
    'planner stat4 expression partial current source next750765 inherits prepared handoff' => static fn (TestRunner $t) => $t->same(true, $plan750765()['selectedPlan']['preparedHandoffReady']),
    'planner stat4 expression partial current source next750765 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan750765()['selectedPlan']['next750765Prepared']),
    'planner stat4 expression partial current source next750765 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan750765()['stat4Next750765PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next750765 slice range' => static fn (TestRunner $t) => $t->same([750, 765], $plan750765()['stat4Next750765PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next750765 prior range' => static fn (TestRunner $t) => $t->same([734, 749], $plan750765()['stat4Next750765PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next750765 prepared slices' => static fn (TestRunner $t) => $t->same(range(750, 765), $plan750765()['selectedPlan']['next750765PreparedSlices']),
    'planner stat4 expression partial current source next750765 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan750765()['selectedPlan']['next750765BlockedSlices']),
    'planner stat4 expression partial current source next750765 first continues' => static fn (TestRunner $t) => $t->same(734, $plan750765()['stat4Next750765PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next750765 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan750765()['stat4Next750765PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next750765 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan750765()['stat4Next750765PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next750765 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan750765()['stat4Next750765PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next750765 selected signature' => static fn (TestRunner $t) => $t->same($plan750765()['stat4Next750765PreparationFence']['handoffSignature'], $plan750765()['selectedPlan']['next750765HandoffSignature']),
    'planner stat4 expression partial current source next750765 stat4 signature' => static fn (TestRunner $t) => $t->same($plan750765()['stat4Next750765PreparationFence']['handoffSignature'], $plan750765()['stat4Fence']['next750765HandoffSignature']),
    'planner stat4 expression partial current source next750765 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan750765()['stat4PreparedHandoffPreparationFence']['handoffSignature'], $plan750765()['selectedPlan']['next750765PriorHandoffSignature']),
    'planner stat4 expression partial current source next750765 preserves prepared handoff fence' => static fn (TestRunner $t) => $t->same(range(734, 749), $plan750765()['stat4PreparedHandoffPreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next750765 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext750765Handoff', $plan750765()['cursorProgram'][array_key_last($plan750765()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next750765 cursor mode' => static fn (TestRunner $t) => $t->same('next750-765-current-source-stat4-expression-partial-prep', $plan750765()['cursorProgram'][array_key_last($plan750765()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next750765 detail' => static fn (TestRunner $t) => $t->contains('NEXT750-765 PREPARED HANDOFF', $plan750765()['detail']),
    'planner stat4 expression partial current source next750765 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next750-765-prep', $plan750765()['dependencies'], true)),
    'planner stat4 expression partial current source next750765 dependency closure' => static fn (TestRunner $t) => $t->contains('next750-765 preparation extends', $plan750765()['dependency_closure']),
    'planner stat4 expression partial current source next750765 non overlap' => static fn (TestRunner $t) => $t->contains('prepared handoff windows', $plan750765()['non_overlap']),
    'planner stat4 expression partial current source next750765 malformed needed column' => static function (TestRunner $t) use ($prepared750765, $current750765, $terms750765): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffFirstContinuation($prepared750765(), $current750765(), $terms750765(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next750765 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan750765): void {
        $plan = $plan750765();
        $t->same($plan['stat4Next750765PreparationFence']['handoffSignature'], $plan['selectedPlan']['next750765HandoffSignature']);
    };
}

return $tests;
