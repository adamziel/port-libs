<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq558573 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like558573 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull558573 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between558573 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows558573 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples558573 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload558573 = static fn (array $row): array => [
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

$prepared558573 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next558573',
    'schemaCookie' => 3820,
    'stat4Generation' => 334,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next558573',
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

$current558573 = static function (?array $rows = null, ?array $samples = null) use ($prepared558573, $rows558573, $samples558573, $payload558573): array {
    $source = $prepared558573();
    $source['name'] = 'current-wp-options-stat4-handoff-next558573';
    $source['schemaCookie'] = 3970;
    $source['stat4Generation'] = 902;
    $source['rows'] = $rows ?? $rows558573();
    $source['indexes'][0]['rootPage'] = 39708;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples558573();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload558573, $source['rows']);

    return $source;
};

$terms558573 = static fn (): array => [
    $between558573('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq558573('autoload', 'yes'),
    $notNull558573('option_name'),
    $eq558573('blog_id', 1),
    $like558573('option_name', 'plugin_%'),
];

$plan558573 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext558573(
    $prepared558573(),
    $current558573($rows, $samples),
    $terms558573(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next558573 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next558-573-prepared', $plan558573()['status']),
    'planner stat4 expression partial current source next558573 inherits next542557' => static fn (TestRunner $t) => $t->same(true, $plan558573()['selectedPlan']['next542557Prepared']),
    'planner stat4 expression partial current source next558573 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan558573()['selectedPlan']['next558573Prepared']),
    'planner stat4 expression partial current source next558573 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan558573()['stat4Next558573PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next558573 slice range' => static fn (TestRunner $t) => $t->same([558, 573], $plan558573()['stat4Next558573PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next558573 prior range' => static fn (TestRunner $t) => $t->same([542, 557], $plan558573()['stat4Next558573PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next558573 prepared slices' => static fn (TestRunner $t) => $t->same(range(558, 573), $plan558573()['selectedPlan']['next558573PreparedSlices']),
    'planner stat4 expression partial current source next558573 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan558573()['selectedPlan']['next558573BlockedSlices']),
    'planner stat4 expression partial current source next558573 first continues' => static fn (TestRunner $t) => $t->same(542, $plan558573()['stat4Next558573PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next558573 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan558573()['stat4Next558573PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next558573 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan558573()['stat4Next558573PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next558573 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan558573()['stat4Next558573PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next558573 selected signature' => static fn (TestRunner $t) => $t->same($plan558573()['stat4Next558573PreparationFence']['handoffSignature'], $plan558573()['selectedPlan']['next558573HandoffSignature']),
    'planner stat4 expression partial current source next558573 stat4 signature' => static fn (TestRunner $t) => $t->same($plan558573()['stat4Next558573PreparationFence']['handoffSignature'], $plan558573()['stat4Fence']['next558573HandoffSignature']),
    'planner stat4 expression partial current source next558573 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan558573()['stat4Next542557PreparationFence']['handoffSignature'], $plan558573()['selectedPlan']['next558573PriorHandoffSignature']),
    'planner stat4 expression partial current source next558573 preserves next542557 fence' => static fn (TestRunner $t) => $t->same(range(542, 557), $plan558573()['stat4Next542557PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next558573 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext558573Handoff', $plan558573()['cursorProgram'][array_key_last($plan558573()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next558573 cursor mode' => static fn (TestRunner $t) => $t->same('next558-573-current-source-stat4-expression-partial-prep', $plan558573()['cursorProgram'][array_key_last($plan558573()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next558573 detail' => static fn (TestRunner $t) => $t->contains('NEXT558-573 PREPARED HANDOFF', $plan558573()['detail']),
    'planner stat4 expression partial current source next558573 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next558-573-prep', $plan558573()['dependencies'], true)),
    'planner stat4 expression partial current source next558573 dependency closure' => static fn (TestRunner $t) => $t->contains('next558-573 preparation extends', $plan558573()['dependency_closure']),
    'planner stat4 expression partial current source next558573 non overlap' => static fn (TestRunner $t) => $t->contains('next542-557 handoff windows', $plan558573()['non_overlap']),
    'planner stat4 expression partial current source next558573 malformed needed column' => static function (TestRunner $t) use ($prepared558573, $current558573, $terms558573): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext558573($prepared558573(), $current558573(), $terms558573(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next558573 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan558573): void {
        $plan = $plan558573();
        $t->same($plan['stat4Next558573PreparationFence']['handoffSignature'], $plan['selectedPlan']['next558573HandoffSignature']);
    };
}

return $tests;
