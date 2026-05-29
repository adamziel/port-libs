<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq718733 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like718733 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull718733 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between718733 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows718733 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples718733 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload718733 = static fn (array $row): array => [
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

$prepared718733 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next718733',
    'schemaCookie' => 3852,
    'stat4Generation' => 366,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next718733',
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

$current718733 = static function (?array $rows = null, ?array $samples = null) use ($prepared718733, $rows718733, $samples718733, $payload718733): array {
    $source = $prepared718733();
    $source['name'] = 'current-wp-options-stat4-handoff-next718733';
    $source['schemaCookie'] = 4002;
    $source['stat4Generation'] = 934;
    $source['rows'] = $rows ?? $rows718733();
    $source['indexes'][0]['rootPage'] = 40028;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples718733();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload718733, $source['rows']);

    return $source;
};

$terms718733 = static fn (): array => [
    $between718733('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq718733('autoload', 'yes'),
    $notNull718733('option_name'),
    $eq718733('blog_id', 1),
    $like718733('option_name', 'plugin_%'),
];

$plan718733 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4ExpressionPartialLateHandoffStageFour(
    $prepared718733(),
    $current718733($rows, $samples),
    $terms718733(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next718733 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next718-733-prepared', $plan718733()['status']),
    'planner stat4 expression partial current source next718733 inherits next702717' => static fn (TestRunner $t) => $t->same(true, $plan718733()['selectedPlan']['next702717Prepared']),
    'planner stat4 expression partial current source next718733 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan718733()['selectedPlan']['next718733Prepared']),
    'planner stat4 expression partial current source next718733 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan718733()['stat4Next718733PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next718733 slice range' => static fn (TestRunner $t) => $t->same([718, 733], $plan718733()['stat4Next718733PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next718733 prior range' => static fn (TestRunner $t) => $t->same([702, 717], $plan718733()['stat4Next718733PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next718733 prepared slices' => static fn (TestRunner $t) => $t->same(range(718, 733), $plan718733()['selectedPlan']['next718733PreparedSlices']),
    'planner stat4 expression partial current source next718733 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan718733()['selectedPlan']['next718733BlockedSlices']),
    'planner stat4 expression partial current source next718733 first continues' => static fn (TestRunner $t) => $t->same(702, $plan718733()['stat4Next718733PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next718733 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan718733()['stat4Next718733PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next718733 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan718733()['stat4Next718733PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next718733 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan718733()['stat4Next718733PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next718733 selected signature' => static fn (TestRunner $t) => $t->same($plan718733()['stat4Next718733PreparationFence']['handoffSignature'], $plan718733()['selectedPlan']['next718733HandoffSignature']),
    'planner stat4 expression partial current source next718733 stat4 signature' => static fn (TestRunner $t) => $t->same($plan718733()['stat4Next718733PreparationFence']['handoffSignature'], $plan718733()['stat4Fence']['next718733HandoffSignature']),
    'planner stat4 expression partial current source next718733 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan718733()['stat4Next702717PreparationFence']['handoffSignature'], $plan718733()['selectedPlan']['next718733PriorHandoffSignature']),
    'planner stat4 expression partial current source next718733 preserves next702717 fence' => static fn (TestRunner $t) => $t->same(range(702, 717), $plan718733()['stat4Next702717PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next718733 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext718733Handoff', $plan718733()['cursorProgram'][array_key_last($plan718733()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next718733 cursor mode' => static fn (TestRunner $t) => $t->same('next718-733-current-source-stat4-expression-partial-prep', $plan718733()['cursorProgram'][array_key_last($plan718733()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next718733 detail' => static fn (TestRunner $t) => $t->contains('NEXT718-733 PREPARED HANDOFF', $plan718733()['detail']),
    'planner stat4 expression partial current source next718733 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next718-733-prep', $plan718733()['dependencies'], true)),
    'planner stat4 expression partial current source next718733 dependency closure' => static fn (TestRunner $t) => $t->contains('next718-733 preparation extends', $plan718733()['dependency_closure']),
    'planner stat4 expression partial current source next718733 non overlap' => static fn (TestRunner $t) => $t->contains('next702-717 handoff windows', $plan718733()['non_overlap']),
    'planner stat4 expression partial current source next718733 malformed needed column' => static function (TestRunner $t) use ($prepared718733, $current718733, $terms718733): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4ExpressionPartialLateHandoffStageFour($prepared718733(), $current718733(), $terms718733(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next718733 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan718733): void {
        $plan = $plan718733();
        $t->same($plan['stat4Next718733PreparationFence']['handoffSignature'], $plan['selectedPlan']['next718733HandoffSignature']);
    };
}

return $tests;
