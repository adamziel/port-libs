<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq574589 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like574589 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull574589 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between574589 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows574589 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples574589 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload574589 = static fn (array $row): array => [
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

$prepared574589 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next574589',
    'schemaCookie' => 3820,
    'stat4Generation' => 334,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next574589',
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

$current574589 = static function (?array $rows = null, ?array $samples = null) use ($prepared574589, $rows574589, $samples574589, $payload574589): array {
    $source = $prepared574589();
    $source['name'] = 'current-wp-options-stat4-handoff-next574589';
    $source['schemaCookie'] = 3970;
    $source['stat4Generation'] = 902;
    $source['rows'] = $rows ?? $rows574589();
    $source['indexes'][0]['rootPage'] = 39708;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples574589();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload574589, $source['rows']);

    return $source;
};

$terms574589 = static fn (): array => [
    $between574589('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq574589('autoload', 'yes'),
    $notNull574589('option_name'),
    $eq574589('blog_id', 1),
    $like574589('option_name', 'plugin_%'),
];

$plan574589 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffPenultimateSeed(
    $prepared574589(),
    $current574589($rows, $samples),
    $terms574589(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next574589 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next574-589-prepared', $plan574589()['status']),
    'planner stat4 expression partial current source next574589 inherits prepared handoff' => static fn (TestRunner $t) => $t->same(true, $plan574589()['selectedPlan']['preparedHandoffReady']),
    'planner stat4 expression partial current source next574589 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan574589()['selectedPlan']['next574589Prepared']),
    'planner stat4 expression partial current source next574589 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan574589()['stat4Next574589PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next574589 slice range' => static fn (TestRunner $t) => $t->same([574, 589], $plan574589()['stat4Next574589PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next574589 prior range' => static fn (TestRunner $t) => $t->same([558, 573], $plan574589()['stat4Next574589PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next574589 prepared slices' => static fn (TestRunner $t) => $t->same(range(574, 589), $plan574589()['selectedPlan']['next574589PreparedSlices']),
    'planner stat4 expression partial current source next574589 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan574589()['selectedPlan']['next574589BlockedSlices']),
    'planner stat4 expression partial current source next574589 first continues' => static fn (TestRunner $t) => $t->same(558, $plan574589()['stat4Next574589PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next574589 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan574589()['stat4Next574589PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next574589 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan574589()['stat4Next574589PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next574589 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan574589()['stat4Next574589PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next574589 selected signature' => static fn (TestRunner $t) => $t->same($plan574589()['stat4Next574589PreparationFence']['handoffSignature'], $plan574589()['selectedPlan']['next574589HandoffSignature']),
    'planner stat4 expression partial current source next574589 stat4 signature' => static fn (TestRunner $t) => $t->same($plan574589()['stat4Next574589PreparationFence']['handoffSignature'], $plan574589()['stat4Fence']['next574589HandoffSignature']),
    'planner stat4 expression partial current source next574589 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan574589()['stat4PreparedHandoffFence']['handoffSignature'], $plan574589()['selectedPlan']['next574589PriorHandoffSignature']),
    'planner stat4 expression partial current source next574589 preserves prepared handoff fence' => static fn (TestRunner $t) => $t->same(range(558, 573), $plan574589()['stat4PreparedHandoffFence']['preparedSlices']),
    'planner stat4 expression partial current source next574589 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialPreparedHandoffRange', $plan574589()['cursorProgram'][array_key_last($plan574589()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next574589 cursor mode' => static fn (TestRunner $t) => $t->same('prepared-handoff-range-current-source-stat4-expression-partial-prep', $plan574589()['cursorProgram'][array_key_last($plan574589()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next574589 canonical cursor opcode' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialPreparedHandoffRange', $plan574589()['cursorProgram'][array_key_last($plan574589()['cursorProgram'])]['canonicalOpcode']),
    'planner stat4 expression partial current source next574589 canonical cursor mode' => static fn (TestRunner $t) => $t->same('prepared-handoff-range-current-source-stat4-expression-partial-prep', $plan574589()['cursorProgram'][array_key_last($plan574589()['cursorProgram'])]['canonicalMode']),
    'planner stat4 expression partial current source next574589 detail' => static fn (TestRunner $t) => $t->contains('NEXT574-589 PREPARED HANDOFF', $plan574589()['detail']),
    'planner stat4 expression partial current source next574589 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next574-589-prep', $plan574589()['dependencies'], true)),
    'planner stat4 expression partial current source next574589 dependency closure' => static fn (TestRunner $t) => $t->contains('next574-589 preparation extends', $plan574589()['dependency_closure']),
    'planner stat4 expression partial current source next574589 non overlap' => static fn (TestRunner $t) => $t->contains('prepared handoff windows', $plan574589()['non_overlap']),
    'planner stat4 expression partial current source next574589 malformed needed column' => static function (TestRunner $t) use ($prepared574589, $current574589, $terms574589): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffPenultimateSeed($prepared574589(), $current574589(), $terms574589(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next574589 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan574589): void {
        $plan = $plan574589();
        $t->same($plan['stat4Next574589PreparationFence']['handoffSignature'], $plan['selectedPlan']['next574589HandoffSignature']);
    };
}

return $tests;
