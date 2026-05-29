<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan;

$eq462477 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like462477 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull462477 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between462477 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows462477 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples462477 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload462477 = static fn (array $row): array => [
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

$prepared462477 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next462477',
    'schemaCookie' => 3820,
    'stat4Generation' => 334,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next462477',
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

$current462477 = static function (?array $rows = null, ?array $samples = null) use ($prepared462477, $rows462477, $samples462477, $payload462477): array {
    $source = $prepared462477();
    $source['name'] = 'current-wp-options-stat4-handoff-next462477';
    $source['schemaCookie'] = 3970;
    $source['stat4Generation'] = 902;
    $source['rows'] = $rows ?? $rows462477();
    $source['indexes'][0]['rootPage'] = 39708;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples462477();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload462477, $source['rows']);

    return $source;
};

$terms462477 = static fn (): array => [
    $between462477('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq462477('autoload', 'yes'),
    $notNull462477('option_name'),
    $eq462477('blog_id', 1),
    $like462477('option_name', 'plugin_%'),
];

$plan462477 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materializeNext462477(
    $prepared462477(),
    $current462477($rows, $samples),
    $terms462477(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next462477 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next462-477-prepared', $plan462477()['status']),
    'planner stat4 expression partial current source next462477 inherits next446461' => static fn (TestRunner $t) => $t->same(true, $plan462477()['selectedPlan']['next446461Prepared']),
    'planner stat4 expression partial current source next462477 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan462477()['selectedPlan']['next462477Prepared']),
    'planner stat4 expression partial current source next462477 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan462477()['stat4Next462477PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next462477 slice range' => static fn (TestRunner $t) => $t->same([462, 477], $plan462477()['stat4Next462477PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next462477 prior range' => static fn (TestRunner $t) => $t->same([446, 461], $plan462477()['stat4Next462477PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next462477 prepared slices' => static fn (TestRunner $t) => $t->same(range(462, 477), $plan462477()['selectedPlan']['next462477PreparedSlices']),
    'planner stat4 expression partial current source next462477 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan462477()['selectedPlan']['next462477BlockedSlices']),
    'planner stat4 expression partial current source next462477 first continues' => static fn (TestRunner $t) => $t->same(446, $plan462477()['stat4Next462477PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next462477 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan462477()['stat4Next462477PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next462477 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan462477()['stat4Next462477PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next462477 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan462477()['stat4Next462477PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next462477 selected signature' => static fn (TestRunner $t) => $t->same($plan462477()['stat4Next462477PreparationFence']['handoffSignature'], $plan462477()['selectedPlan']['next462477HandoffSignature']),
    'planner stat4 expression partial current source next462477 stat4 signature' => static fn (TestRunner $t) => $t->same($plan462477()['stat4Next462477PreparationFence']['handoffSignature'], $plan462477()['stat4Fence']['next462477HandoffSignature']),
    'planner stat4 expression partial current source next462477 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan462477()['stat4Next446461PreparationFence']['handoffSignature'], $plan462477()['selectedPlan']['next462477PriorHandoffSignature']),
    'planner stat4 expression partial current source next462477 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext462477Handoff', $plan462477()['cursorProgram'][array_key_last($plan462477()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next462477 cursor mode' => static fn (TestRunner $t) => $t->same('next462-477-current-source-stat4-expression-partial-prep', $plan462477()['cursorProgram'][array_key_last($plan462477()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next462477 detail' => static fn (TestRunner $t) => $t->contains('NEXT462-477 PREPARED HANDOFF', $plan462477()['detail']),
    'planner stat4 expression partial current source next462477 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next462-477-prep', $plan462477()['dependencies'], true)),
    'planner stat4 expression partial current source next462477 dependency closure' => static fn (TestRunner $t) => $t->contains('next462-477 preparation extends', $plan462477()['dependency_closure']),
    'planner stat4 expression partial current source next462477 non overlap' => static fn (TestRunner $t) => $t->contains('next446-461 handoff windows', $plan462477()['non_overlap']),
    'planner stat4 expression partial current source next462477 malformed needed column' => static function (TestRunner $t) use ($prepared462477, $current462477, $terms462477): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materializeNext462477($prepared462477(), $current462477(), $terms462477(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next462477 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan462477): void {
        $plan = $plan462477();
        $t->same($plan['stat4Next462477PreparationFence']['handoffSignature'], $plan['selectedPlan']['next462477HandoffSignature']);
    };
}

return $tests;
