<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan;

$eq846861 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like846861 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull846861 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between846861 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows846861 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples846861 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload846861 = static fn (array $row): array => [
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

$prepared846861 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next846861',
    'schemaCookie' => 3868,
    'stat4Generation' => 382,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next846861',
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

$current846861 = static function (?array $rows = null, ?array $samples = null) use ($prepared846861, $rows846861, $samples846861, $payload846861): array {
    $source = $prepared846861();
    $source['name'] = 'current-wp-options-stat4-handoff-next846861';
    $source['schemaCookie'] = 4018;
    $source['stat4Generation'] = 950;
    $source['rows'] = $rows ?? $rows846861();
    $source['indexes'][0]['rootPage'] = 40188;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples846861();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload846861, $source['rows']);

    return $source;
};

$terms846861 = static fn (): array => [
    $between846861('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq846861('autoload', 'yes'),
    $notNull846861('option_name'),
    $eq846861('blog_id', 1),
    $like846861('option_name', 'plugin_%'),
];

$plan846861 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materializeNext846861(
    $prepared846861(),
    $current846861($rows, $samples),
    $terms846861(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next846861 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next846-861-prepared', $plan846861()['status']),
    'planner stat4 expression partial current source next846861 inherits next830845' => static fn (TestRunner $t) => $t->same(true, $plan846861()['selectedPlan']['next830845Prepared']),
    'planner stat4 expression partial current source next846861 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan846861()['selectedPlan']['next846861Prepared']),
    'planner stat4 expression partial current source next846861 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan846861()['stat4Next846861PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next846861 slice range' => static fn (TestRunner $t) => $t->same([846, 861], $plan846861()['stat4Next846861PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next846861 prior range' => static fn (TestRunner $t) => $t->same([830, 845], $plan846861()['stat4Next846861PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next846861 prepared slices' => static fn (TestRunner $t) => $t->same(range(846, 861), $plan846861()['selectedPlan']['next846861PreparedSlices']),
    'planner stat4 expression partial current source next846861 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan846861()['selectedPlan']['next846861BlockedSlices']),
    'planner stat4 expression partial current source next846861 first continues' => static fn (TestRunner $t) => $t->same(830, $plan846861()['stat4Next846861PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next846861 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan846861()['stat4Next846861PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next846861 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan846861()['stat4Next846861PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next846861 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan846861()['stat4Next846861PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next846861 selected signature' => static fn (TestRunner $t) => $t->same($plan846861()['stat4Next846861PreparationFence']['handoffSignature'], $plan846861()['selectedPlan']['next846861HandoffSignature']),
    'planner stat4 expression partial current source next846861 stat4 signature' => static fn (TestRunner $t) => $t->same($plan846861()['stat4Next846861PreparationFence']['handoffSignature'], $plan846861()['stat4Fence']['next846861HandoffSignature']),
    'planner stat4 expression partial current source next846861 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan846861()['stat4Next830845PreparationFence']['handoffSignature'], $plan846861()['selectedPlan']['next846861PriorHandoffSignature']),
    'planner stat4 expression partial current source next846861 preserves next830845 fence' => static fn (TestRunner $t) => $t->same(range(830, 845), $plan846861()['stat4Next830845PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next846861 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext846861Handoff', $plan846861()['cursorProgram'][array_key_last($plan846861()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next846861 cursor mode' => static fn (TestRunner $t) => $t->same('next846-861-current-source-stat4-expression-partial-prep', $plan846861()['cursorProgram'][array_key_last($plan846861()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next846861 detail' => static fn (TestRunner $t) => $t->contains('NEXT846-861 PREPARED HANDOFF', $plan846861()['detail']),
    'planner stat4 expression partial current source next846861 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next846-861-prep', $plan846861()['dependencies'], true)),
    'planner stat4 expression partial current source next846861 dependency closure' => static fn (TestRunner $t) => $t->contains('next846-861 preparation extends', $plan846861()['dependency_closure']),
    'planner stat4 expression partial current source next846861 non overlap' => static fn (TestRunner $t) => $t->contains('next830-845 handoff windows', $plan846861()['non_overlap']),
    'planner stat4 expression partial current source next846861 malformed needed column' => static function (TestRunner $t) use ($prepared846861, $current846861, $terms846861): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materializeNext846861($prepared846861(), $current846861(), $terms846861(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next846861 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan846861): void {
        $plan = $plan846861();
        $t->same($plan['stat4Next846861PreparationFence']['handoffSignature'], $plan['selectedPlan']['next846861HandoffSignature']);
    };
}

return $tests;
