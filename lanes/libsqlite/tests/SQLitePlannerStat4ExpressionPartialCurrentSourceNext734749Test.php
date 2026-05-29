<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan;

$eq734749 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like734749 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull734749 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between734749 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows734749 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples734749 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload734749 = static fn (array $row): array => [
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

$prepared734749 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next734749',
    'schemaCookie' => 3852,
    'stat4Generation' => 366,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next734749',
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

$current734749 = static function (?array $rows = null, ?array $samples = null) use ($prepared734749, $rows734749, $samples734749, $payload734749): array {
    $source = $prepared734749();
    $source['name'] = 'current-wp-options-stat4-handoff-next734749';
    $source['schemaCookie'] = 4002;
    $source['stat4Generation'] = 934;
    $source['rows'] = $rows ?? $rows734749();
    $source['indexes'][0]['rootPage'] = 40028;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples734749();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload734749, $source['rows']);

    return $source;
};

$terms734749 = static fn (): array => [
    $between734749('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq734749('autoload', 'yes'),
    $notNull734749('option_name'),
    $eq734749('blog_id', 1),
    $like734749('option_name', 'plugin_%'),
];

$plan734749 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materializeNext734749(
    $prepared734749(),
    $current734749($rows, $samples),
    $terms734749(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next734749 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next734-749-prepared', $plan734749()['status']),
    'planner stat4 expression partial current source next734749 inherits next718733' => static fn (TestRunner $t) => $t->same(true, $plan734749()['selectedPlan']['next718733Prepared']),
    'planner stat4 expression partial current source next734749 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan734749()['selectedPlan']['next734749Prepared']),
    'planner stat4 expression partial current source next734749 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan734749()['stat4Next734749PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next734749 slice range' => static fn (TestRunner $t) => $t->same([734, 749], $plan734749()['stat4Next734749PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next734749 prior range' => static fn (TestRunner $t) => $t->same([718, 733], $plan734749()['stat4Next734749PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next734749 prepared slices' => static fn (TestRunner $t) => $t->same(range(734, 749), $plan734749()['selectedPlan']['next734749PreparedSlices']),
    'planner stat4 expression partial current source next734749 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan734749()['selectedPlan']['next734749BlockedSlices']),
    'planner stat4 expression partial current source next734749 first continues' => static fn (TestRunner $t) => $t->same(718, $plan734749()['stat4Next734749PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next734749 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan734749()['stat4Next734749PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next734749 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan734749()['stat4Next734749PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next734749 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan734749()['stat4Next734749PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next734749 selected signature' => static fn (TestRunner $t) => $t->same($plan734749()['stat4Next734749PreparationFence']['handoffSignature'], $plan734749()['selectedPlan']['next734749HandoffSignature']),
    'planner stat4 expression partial current source next734749 stat4 signature' => static fn (TestRunner $t) => $t->same($plan734749()['stat4Next734749PreparationFence']['handoffSignature'], $plan734749()['stat4Fence']['next734749HandoffSignature']),
    'planner stat4 expression partial current source next734749 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan734749()['stat4Next718733PreparationFence']['handoffSignature'], $plan734749()['selectedPlan']['next734749PriorHandoffSignature']),
    'planner stat4 expression partial current source next734749 preserves next718733 fence' => static fn (TestRunner $t) => $t->same(range(718, 733), $plan734749()['stat4Next718733PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next734749 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext734749Handoff', $plan734749()['cursorProgram'][array_key_last($plan734749()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next734749 cursor mode' => static fn (TestRunner $t) => $t->same('next734-749-current-source-stat4-expression-partial-prep', $plan734749()['cursorProgram'][array_key_last($plan734749()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next734749 detail' => static fn (TestRunner $t) => $t->contains('NEXT734-749 PREPARED HANDOFF', $plan734749()['detail']),
    'planner stat4 expression partial current source next734749 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next734-749-prep', $plan734749()['dependencies'], true)),
    'planner stat4 expression partial current source next734749 dependency closure' => static fn (TestRunner $t) => $t->contains('next734-749 preparation extends', $plan734749()['dependency_closure']),
    'planner stat4 expression partial current source next734749 non overlap' => static fn (TestRunner $t) => $t->contains('next718-733 handoff windows', $plan734749()['non_overlap']),
    'planner stat4 expression partial current source next734749 malformed needed column' => static function (TestRunner $t) use ($prepared734749, $current734749, $terms734749): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materializeNext734749($prepared734749(), $current734749(), $terms734749(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next734749 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan734749): void {
        $plan = $plan734749();
        $t->same($plan['stat4Next734749PreparationFence']['handoffSignature'], $plan['selectedPlan']['next734749HandoffSignature']);
    };
}

return $tests;
