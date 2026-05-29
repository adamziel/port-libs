<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan;

$eq894909 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like894909 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull894909 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between894909 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows894909 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples894909 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload894909 = static fn (array $row): array => [
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

$prepared894909 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next894909',
    'schemaCookie' => 3884,
    'stat4Generation' => 382,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next894909',
        'rootPage' => 38841,
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

$current894909 = static function (?array $rows = null, ?array $samples = null) use ($prepared894909, $rows894909, $samples894909, $payload894909): array {
    $source = $prepared894909();
    $source['name'] = 'current-wp-options-stat4-handoff-next894909';
    $source['schemaCookie'] = 4034;
    $source['stat4Generation'] = 950;
    $source['rows'] = $rows ?? $rows894909();
    $source['indexes'][0]['rootPage'] = 40348;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples894909();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload894909, $source['rows']);

    return $source;
};

$terms894909 = static fn (): array => [
    $between894909('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq894909('autoload', 'yes'),
    $notNull894909('option_name'),
    $eq894909('blog_id', 1),
    $like894909('option_name', 'plugin_%'),
];

$plan894909 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materializeNext894909(
    $prepared894909(),
    $current894909($rows, $samples),
    $terms894909(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next894909 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next894-909-prepared', $plan894909()['status']),
    'planner stat4 expression partial current source next894909 inherits next878893' => static fn (TestRunner $t) => $t->same(true, $plan894909()['selectedPlan']['next878893Prepared']),
    'planner stat4 expression partial current source next894909 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan894909()['selectedPlan']['next894909Prepared']),
    'planner stat4 expression partial current source next894909 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan894909()['stat4Next894909PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next894909 slice range' => static fn (TestRunner $t) => $t->same([894, 909], $plan894909()['stat4Next894909PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next894909 prior range' => static fn (TestRunner $t) => $t->same([878, 893], $plan894909()['stat4Next894909PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next894909 prepared slices' => static fn (TestRunner $t) => $t->same(range(894, 909), $plan894909()['selectedPlan']['next894909PreparedSlices']),
    'planner stat4 expression partial current source next894909 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan894909()['selectedPlan']['next894909BlockedSlices']),
    'planner stat4 expression partial current source next894909 first continues' => static fn (TestRunner $t) => $t->same(878, $plan894909()['stat4Next894909PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next894909 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan894909()['stat4Next894909PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next894909 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan894909()['stat4Next894909PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next894909 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan894909()['stat4Next894909PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next894909 selected signature' => static fn (TestRunner $t) => $t->same($plan894909()['stat4Next894909PreparationFence']['handoffSignature'], $plan894909()['selectedPlan']['next894909HandoffSignature']),
    'planner stat4 expression partial current source next894909 stat4 signature' => static fn (TestRunner $t) => $t->same($plan894909()['stat4Next894909PreparationFence']['handoffSignature'], $plan894909()['stat4Fence']['next894909HandoffSignature']),
    'planner stat4 expression partial current source next894909 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan894909()['stat4Next878893PreparationFence']['handoffSignature'], $plan894909()['selectedPlan']['next894909PriorHandoffSignature']),
    'planner stat4 expression partial current source next894909 preserves next878893 fence' => static fn (TestRunner $t) => $t->same(range(878, 893), $plan894909()['stat4Next878893PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next894909 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext894909Handoff', $plan894909()['cursorProgram'][array_key_last($plan894909()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next894909 cursor mode' => static fn (TestRunner $t) => $t->same('next894-909-current-source-stat4-expression-partial-prep', $plan894909()['cursorProgram'][array_key_last($plan894909()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next894909 detail' => static fn (TestRunner $t) => $t->contains('NEXT894-909 PREPARED HANDOFF', $plan894909()['detail']),
    'planner stat4 expression partial current source next894909 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next894-909-prep', $plan894909()['dependencies'], true)),
    'planner stat4 expression partial current source next894909 dependency closure' => static fn (TestRunner $t) => $t->contains('next894-909 preparation extends', $plan894909()['dependency_closure']),
    'planner stat4 expression partial current source next894909 non overlap' => static fn (TestRunner $t) => $t->contains('next878-893 handoff windows', $plan894909()['non_overlap']),
    'planner stat4 expression partial current source next894909 malformed needed column' => static function (TestRunner $t) use ($prepared894909, $current894909, $terms894909): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materializeNext894909($prepared894909(), $current894909(), $terms894909(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next894909 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan894909): void {
        $plan = $plan894909();
        $t->same($plan['stat4Next894909PreparationFence']['handoffSignature'], $plan['selectedPlan']['next894909HandoffSignature']);
    };
}

return $tests;
