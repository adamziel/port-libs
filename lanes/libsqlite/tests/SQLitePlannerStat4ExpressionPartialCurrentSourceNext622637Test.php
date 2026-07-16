<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq622637 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like622637 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull622637 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between622637 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows622637 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples622637 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload622637 = static fn (array $row): array => [
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

$prepared622637 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next622637',
    'schemaCookie' => 3852,
    'stat4Generation' => 366,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next622637',
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

$current622637 = static function (?array $rows = null, ?array $samples = null) use ($prepared622637, $rows622637, $samples622637, $payload622637): array {
    $source = $prepared622637();
    $source['name'] = 'current-wp-options-stat4-handoff-next622637';
    $source['schemaCookie'] = 4002;
    $source['stat4Generation'] = 934;
    $source['rows'] = $rows ?? $rows622637();
    $source['indexes'][0]['rootPage'] = 40028;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples622637();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload622637, $source['rows']);

    return $source;
};

$terms622637 = static fn (): array => [
    $between622637('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq622637('autoload', 'yes'),
    $notNull622637('option_name'),
    $eq622637('blog_id', 1),
    $like622637('option_name', 'plugin_%'),
];

$plan622637 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4ExpressionPartialPreparedContinuation(
    $prepared622637(),
    $current622637($rows, $samples),
    $terms622637(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next622637 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next622-637-prepared', $plan622637()['status']),
    'planner stat4 expression partial current source next622637 inherits preparedContinuationBase' => static fn (TestRunner $t) => $t->same(true, $plan622637()['selectedPlan']['preparedContinuationBasePrepared']),
    'planner stat4 expression partial current source next622637 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan622637()['selectedPlan']['next622637Prepared']),
    'planner stat4 expression partial current source next622637 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan622637()['stat4Next622637PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next622637 slice range' => static fn (TestRunner $t) => $t->same([622, 637], $plan622637()['stat4Next622637PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next622637 prior range' => static fn (TestRunner $t) => $t->same([606, 621], $plan622637()['stat4Next622637PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next622637 prepared slices' => static fn (TestRunner $t) => $t->same(range(622, 637), $plan622637()['selectedPlan']['next622637PreparedSlices']),
    'planner stat4 expression partial current source next622637 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan622637()['selectedPlan']['next622637BlockedSlices']),
    'planner stat4 expression partial current source next622637 first continues' => static fn (TestRunner $t) => $t->same(606, $plan622637()['stat4Next622637PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next622637 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan622637()['stat4Next622637PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next622637 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan622637()['stat4Next622637PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next622637 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan622637()['stat4Next622637PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next622637 selected signature' => static fn (TestRunner $t) => $t->same($plan622637()['stat4Next622637PreparationFence']['handoffSignature'], $plan622637()['selectedPlan']['next622637HandoffSignature']),
    'planner stat4 expression partial current source next622637 stat4 signature' => static fn (TestRunner $t) => $t->same($plan622637()['stat4Next622637PreparationFence']['handoffSignature'], $plan622637()['stat4Fence']['next622637HandoffSignature']),
    'planner stat4 expression partial current source next622637 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan622637()['stat4PreparedContinuationBasePreparationFence']['handoffSignature'], $plan622637()['selectedPlan']['next622637PriorHandoffSignature']),
    'planner stat4 expression partial current source next622637 preserves preparedContinuationBase fence' => static fn (TestRunner $t) => $t->same(range(606, 621), $plan622637()['stat4PreparedContinuationBasePreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next622637 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialPreparedHandoffRange', $plan622637()['cursorProgram'][array_key_last($plan622637()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next622637 cursor mode' => static fn (TestRunner $t) => $t->same('prepared-handoff-range-current-source-stat4-expression-partial-prep', $plan622637()['cursorProgram'][array_key_last($plan622637()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next622637 detail' => static fn (TestRunner $t) => $t->contains('NEXT622-637 PREPARED HANDOFF', $plan622637()['detail']),
    'planner stat4 expression partial current source next622637 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next622-637-prep', $plan622637()['dependencies'], true)),
    'planner stat4 expression partial current source next622637 dependency closure' => static fn (TestRunner $t) => $t->contains('next622-637 preparation extends', $plan622637()['dependency_closure']),
    'planner stat4 expression partial current source next622637 non overlap' => static fn (TestRunner $t) => $t->contains('prepared-continuation-base handoff windows', $plan622637()['non_overlap']),
    'planner stat4 expression partial current source next622637 malformed needed column' => static function (TestRunner $t) use ($prepared622637, $current622637, $terms622637): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4ExpressionPartialPreparedContinuation($prepared622637(), $current622637(), $terms622637(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next622637 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan622637): void {
        $plan = $plan622637();
        $t->same($plan['stat4Next622637PreparationFence']['handoffSignature'], $plan['selectedPlan']['next622637HandoffSignature']);
    };
}

return $tests;
