<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq830845 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like830845 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull830845 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between830845 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows830845 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples830845 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload830845 = static fn (array $row): array => [
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

$prepared830845 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next830845',
    'schemaCookie' => 3868,
    'stat4Generation' => 382,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next830845',
        'rootPage' => 38681,
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

$current830845 = static function (?array $rows = null, ?array $samples = null) use ($prepared830845, $rows830845, $samples830845, $payload830845): array {
    $source = $prepared830845();
    $source['name'] = 'current-wp-options-stat4-handoff-next830845';
    $source['schemaCookie'] = 4018;
    $source['stat4Generation'] = 950;
    $source['rows'] = $rows ?? $rows830845();
    $source['indexes'][0]['rootPage'] = 40188;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples830845();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload830845, $source['rows']);

    return $source;
};

$terms830845 = static fn (): array => [
    $between830845('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq830845('autoload', 'yes'),
    $notNull830845('option_name'),
    $eq830845('blog_id', 1),
    $like830845('option_name', 'plugin_%'),
];

$plan830845 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffWindow(
    $prepared830845(),
    $current830845($rows, $samples),
    $terms830845(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next830845 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next830-845-prepared', $plan830845()['status']),
    'planner stat4 expression partial current source next830845 inherits next814829' => static fn (TestRunner $t) => $t->same(true, $plan830845()['selectedPlan']['next814829Prepared']),
    'planner stat4 expression partial current source next830845 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan830845()['selectedPlan']['next830845Prepared']),
    'planner stat4 expression partial current source next830845 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan830845()['stat4Next830845PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next830845 slice range' => static fn (TestRunner $t) => $t->same([830, 845], $plan830845()['stat4Next830845PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next830845 prior range' => static fn (TestRunner $t) => $t->same([814, 829], $plan830845()['stat4Next830845PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next830845 prepared slices' => static fn (TestRunner $t) => $t->same(range(830, 845), $plan830845()['selectedPlan']['next830845PreparedSlices']),
    'planner stat4 expression partial current source next830845 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan830845()['selectedPlan']['next830845BlockedSlices']),
    'planner stat4 expression partial current source next830845 first continues' => static fn (TestRunner $t) => $t->same(814, $plan830845()['stat4Next830845PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next830845 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan830845()['stat4Next830845PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next830845 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan830845()['stat4Next830845PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next830845 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan830845()['stat4Next830845PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next830845 selected signature' => static fn (TestRunner $t) => $t->same($plan830845()['stat4Next830845PreparationFence']['handoffSignature'], $plan830845()['selectedPlan']['next830845HandoffSignature']),
    'planner stat4 expression partial current source next830845 stat4 signature' => static fn (TestRunner $t) => $t->same($plan830845()['stat4Next830845PreparationFence']['handoffSignature'], $plan830845()['stat4Fence']['next830845HandoffSignature']),
    'planner stat4 expression partial current source next830845 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan830845()['stat4Next814829PreparationFence']['handoffSignature'], $plan830845()['selectedPlan']['next830845PriorHandoffSignature']),
    'planner stat4 expression partial current source next830845 preserves next814829 fence' => static fn (TestRunner $t) => $t->same(range(814, 829), $plan830845()['stat4Next814829PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next830845 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext830845Handoff', $plan830845()['cursorProgram'][array_key_last($plan830845()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next830845 cursor mode' => static fn (TestRunner $t) => $t->same('next830-845-current-source-stat4-expression-partial-prep', $plan830845()['cursorProgram'][array_key_last($plan830845()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next830845 detail' => static fn (TestRunner $t) => $t->contains('NEXT830-845 PREPARED HANDOFF', $plan830845()['detail']),
    'planner stat4 expression partial current source next830845 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next830-845-prep', $plan830845()['dependencies'], true)),
    'planner stat4 expression partial current source next830845 dependency closure' => static fn (TestRunner $t) => $t->contains('next830-845 preparation extends', $plan830845()['dependency_closure']),
    'planner stat4 expression partial current source next830845 non overlap' => static fn (TestRunner $t) => $t->contains('next814-829 handoff windows', $plan830845()['non_overlap']),
    'planner stat4 expression partial current source next830845 malformed needed column' => static function (TestRunner $t) use ($prepared830845, $current830845, $terms830845): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffWindow($prepared830845(), $current830845(), $terms830845(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next830845 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan830845): void {
        $plan = $plan830845();
        $t->same($plan['stat4Next830845PreparationFence']['handoffSignature'], $plan['selectedPlan']['next830845HandoffSignature']);
    };
}

return $tests;
