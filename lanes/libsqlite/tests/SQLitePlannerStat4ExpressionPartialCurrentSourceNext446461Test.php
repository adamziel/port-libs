<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan;

$eq446461 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like446461 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull446461 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between446461 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows446461 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples446461 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload446461 = static fn (array $row): array => [
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

$prepared446461 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next446461',
    'schemaCookie' => 3820,
    'stat4Generation' => 334,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next446461',
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

$current446461 = static function (?array $rows = null, ?array $samples = null) use ($prepared446461, $rows446461, $samples446461, $payload446461): array {
    $source = $prepared446461();
    $source['name'] = 'current-wp-options-stat4-handoff-next446461';
    $source['schemaCookie'] = 3970;
    $source['stat4Generation'] = 902;
    $source['rows'] = $rows ?? $rows446461();
    $source['indexes'][0]['rootPage'] = 39708;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples446461();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload446461, $source['rows']);

    return $source;
};

$terms446461 = static fn (): array => [
    $between446461('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq446461('autoload', 'yes'),
    $notNull446461('option_name'),
    $eq446461('blog_id', 1),
    $like446461('option_name', 'plugin_%'),
];

$plan446461 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materializeNext446461(
    $prepared446461(),
    $current446461($rows, $samples),
    $terms446461(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next446461 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next446-461-prepared', $plan446461()['status']),
    'planner stat4 expression partial current source next446461 inherits next430445' => static fn (TestRunner $t) => $t->same(true, $plan446461()['selectedPlan']['next430445Prepared']),
    'planner stat4 expression partial current source next446461 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan446461()['selectedPlan']['next446461Prepared']),
    'planner stat4 expression partial current source next446461 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan446461()['stat4Next446461PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next446461 slice range' => static fn (TestRunner $t) => $t->same([446, 461], $plan446461()['stat4Next446461PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next446461 prior range' => static fn (TestRunner $t) => $t->same([430, 445], $plan446461()['stat4Next446461PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next446461 prepared slices' => static fn (TestRunner $t) => $t->same(range(446, 461), $plan446461()['selectedPlan']['next446461PreparedSlices']),
    'planner stat4 expression partial current source next446461 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan446461()['selectedPlan']['next446461BlockedSlices']),
    'planner stat4 expression partial current source next446461 first continues' => static fn (TestRunner $t) => $t->same(430, $plan446461()['stat4Next446461PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next446461 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan446461()['stat4Next446461PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next446461 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan446461()['stat4Next446461PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next446461 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan446461()['stat4Next446461PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next446461 selected signature' => static fn (TestRunner $t) => $t->same($plan446461()['stat4Next446461PreparationFence']['handoffSignature'], $plan446461()['selectedPlan']['next446461HandoffSignature']),
    'planner stat4 expression partial current source next446461 stat4 signature' => static fn (TestRunner $t) => $t->same($plan446461()['stat4Next446461PreparationFence']['handoffSignature'], $plan446461()['stat4Fence']['next446461HandoffSignature']),
    'planner stat4 expression partial current source next446461 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan446461()['stat4Next430445PreparationFence']['handoffSignature'], $plan446461()['selectedPlan']['next446461PriorHandoffSignature']),
    'planner stat4 expression partial current source next446461 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext446461Handoff', $plan446461()['cursorProgram'][array_key_last($plan446461()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next446461 cursor mode' => static fn (TestRunner $t) => $t->same('next446-461-current-source-stat4-expression-partial-prep', $plan446461()['cursorProgram'][array_key_last($plan446461()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next446461 detail' => static fn (TestRunner $t) => $t->contains('NEXT446-461 PREPARED HANDOFF', $plan446461()['detail']),
    'planner stat4 expression partial current source next446461 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next446-461-prep', $plan446461()['dependencies'], true)),
    'planner stat4 expression partial current source next446461 dependency closure' => static fn (TestRunner $t) => $t->contains('next446-461 preparation extends', $plan446461()['dependency_closure']),
    'planner stat4 expression partial current source next446461 non overlap' => static fn (TestRunner $t) => $t->contains('next430-445 handoff windows', $plan446461()['non_overlap']),
    'planner stat4 expression partial current source next446461 malformed needed column' => static function (TestRunner $t) use ($prepared446461, $current446461, $terms446461): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materializeNext446461($prepared446461(), $current446461(), $terms446461(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next446461 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan446461): void {
        $plan = $plan446461();
        $t->same($plan['stat4Next446461PreparationFence']['handoffSignature'], $plan['selectedPlan']['next446461HandoffSignature']);
    };
}

return $tests;
