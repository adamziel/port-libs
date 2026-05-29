<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq350365 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like350365 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull350365 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between350365 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows350365 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples350365 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload350365 = static fn (array $row): array => [
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

$prepared350365 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next350365',
    'schemaCookie' => 3500,
    'stat4Generation' => 334,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next350365',
        'rootPage' => 35001,
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

$current350365 = static function (?array $rows = null, ?array $samples = null) use ($prepared350365, $rows350365, $samples350365, $payload350365): array {
    $source = $prepared350365();
    $source['name'] = 'current-wp-options-stat4-handoff-next350365';
    $source['schemaCookie'] = 3650;
    $source['stat4Generation'] = 902;
    $source['rows'] = $rows ?? $rows350365();
    $source['indexes'][0]['rootPage'] = 36508;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples350365();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload350365, $source['rows']);

    return $source;
};

$terms350365 = static fn (): array => [
    $between350365('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq350365('autoload', 'yes'),
    $notNull350365('option_name'),
    $eq350365('blog_id', 1),
    $like350365('option_name', 'plugin_%'),
];

$plan350365 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffBridgeMiddle(
    $prepared350365(),
    $current350365($rows, $samples),
    $terms350365(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next350365 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next350-365-prepared', $plan350365()['status']),
    'planner stat4 expression partial current source next350365 inherits next334349' => static fn (TestRunner $t) => $t->same(true, $plan350365()['selectedPlan']['next334349Prepared']),
    'planner stat4 expression partial current source next350365 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan350365()['selectedPlan']['next350365Prepared']),
    'planner stat4 expression partial current source next350365 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan350365()['stat4Next350365PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next350365 slice range' => static fn (TestRunner $t) => $t->same([350, 365], $plan350365()['stat4Next350365PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next350365 prior range' => static fn (TestRunner $t) => $t->same([334, 349], $plan350365()['stat4Next350365PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next350365 prepared slices' => static fn (TestRunner $t) => $t->same(range(350, 365), $plan350365()['selectedPlan']['next350365PreparedSlices']),
    'planner stat4 expression partial current source next350365 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan350365()['selectedPlan']['next350365BlockedSlices']),
    'planner stat4 expression partial current source next350365 first continues' => static fn (TestRunner $t) => $t->same(334, $plan350365()['stat4Next350365PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next350365 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan350365()['stat4Next350365PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next350365 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan350365()['stat4Next350365PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next350365 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan350365()['stat4Next350365PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next350365 selected signature' => static fn (TestRunner $t) => $t->same($plan350365()['stat4Next350365PreparationFence']['handoffSignature'], $plan350365()['selectedPlan']['next350365HandoffSignature']),
    'planner stat4 expression partial current source next350365 stat4 signature' => static fn (TestRunner $t) => $t->same($plan350365()['stat4Next350365PreparationFence']['handoffSignature'], $plan350365()['stat4Fence']['next350365HandoffSignature']),
    'planner stat4 expression partial current source next350365 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan350365()['stat4Next334349PreparationFence']['handoffSignature'], $plan350365()['selectedPlan']['next350365PriorHandoffSignature']),
    'planner stat4 expression partial current source next350365 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext350365Handoff', $plan350365()['cursorProgram'][array_key_last($plan350365()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next350365 cursor mode' => static fn (TestRunner $t) => $t->same('next350-365-current-source-stat4-expression-partial-prep', $plan350365()['cursorProgram'][array_key_last($plan350365()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next350365 detail' => static fn (TestRunner $t) => $t->contains('NEXT350-365 PREPARED HANDOFF', $plan350365()['detail']),
    'planner stat4 expression partial current source next350365 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next350-365-prep', $plan350365()['dependencies'], true)),
    'planner stat4 expression partial current source next350365 dependency closure' => static fn (TestRunner $t) => $t->contains('next350-365 preparation extends', $plan350365()['dependency_closure']),
    'planner stat4 expression partial current source next350365 non overlap' => static fn (TestRunner $t) => $t->contains('next334-349 handoff windows', $plan350365()['non_overlap']),
    'planner stat4 expression partial current source next350365 malformed needed column' => static function (TestRunner $t) use ($prepared350365, $current350365, $terms350365): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffBridgeMiddle($prepared350365(), $current350365(), $terms350365(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next350365 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan350365): void {
        $plan = $plan350365();
        $t->same($plan['stat4Next350365PreparationFence']['handoffSignature'], $plan['selectedPlan']['next350365HandoffSignature']);
    };
}

return $tests;
