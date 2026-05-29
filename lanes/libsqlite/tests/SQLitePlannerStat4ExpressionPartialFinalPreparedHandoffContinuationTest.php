<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq910925 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like910925 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull910925 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between910925 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows910925 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples910925 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload910925 = static fn (array $row): array => [
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

$prepared910925 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next910925',
    'schemaCookie' => 3900,
    'stat4Generation' => 382,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next910925',
        'rootPage' => 39001,
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

$current910925 = static function (?array $rows = null, ?array $samples = null) use ($prepared910925, $rows910925, $samples910925, $payload910925): array {
    $source = $prepared910925();
    $source['name'] = 'current-wp-options-stat4-handoff-next910925';
    $source['schemaCookie'] = 4050;
    $source['stat4Generation'] = 950;
    $source['rows'] = $rows ?? $rows910925();
    $source['indexes'][0]['rootPage'] = 40508;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples910925();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload910925, $source['rows']);

    return $source;
};

$terms910925 = static fn (): array => [
    $between910925('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq910925('autoload', 'yes'),
    $notNull910925('option_name'),
    $eq910925('blog_id', 1),
    $like910925('option_name', 'plugin_%'),
];

$plan910925 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeFinalPreparedHandoffContinuation(
    $prepared910925(),
    $current910925($rows, $samples),
    $terms910925(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next910925 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next910-925-prepared', $plan910925()['status']),
    'planner stat4 expression partial current source next910925 inherits next894909' => static fn (TestRunner $t) => $t->same(true, $plan910925()['selectedPlan']['next894909Prepared']),
    'planner stat4 expression partial current source next910925 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan910925()['selectedPlan']['next910925Prepared']),
    'planner stat4 expression partial current source next910925 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan910925()['stat4Next910925PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next910925 slice range' => static fn (TestRunner $t) => $t->same([910, 925], $plan910925()['stat4Next910925PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next910925 prior range' => static fn (TestRunner $t) => $t->same([894, 909], $plan910925()['stat4Next910925PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next910925 prepared slices' => static fn (TestRunner $t) => $t->same(range(910, 925), $plan910925()['selectedPlan']['next910925PreparedSlices']),
    'planner stat4 expression partial current source next910925 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan910925()['selectedPlan']['next910925BlockedSlices']),
    'planner stat4 expression partial current source next910925 first continues' => static fn (TestRunner $t) => $t->same(894, $plan910925()['stat4Next910925PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next910925 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan910925()['stat4Next910925PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next910925 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan910925()['stat4Next910925PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next910925 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan910925()['stat4Next910925PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next910925 selected signature' => static fn (TestRunner $t) => $t->same($plan910925()['stat4Next910925PreparationFence']['handoffSignature'], $plan910925()['selectedPlan']['next910925HandoffSignature']),
    'planner stat4 expression partial current source next910925 stat4 signature' => static fn (TestRunner $t) => $t->same($plan910925()['stat4Next910925PreparationFence']['handoffSignature'], $plan910925()['stat4Fence']['next910925HandoffSignature']),
    'planner stat4 expression partial current source next910925 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan910925()['stat4Next894909PreparationFence']['handoffSignature'], $plan910925()['selectedPlan']['next910925PriorHandoffSignature']),
    'planner stat4 expression partial current source next910925 preserves next894909 fence' => static fn (TestRunner $t) => $t->same(range(894, 909), $plan910925()['stat4Next894909PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next910925 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext910925Handoff', $plan910925()['cursorProgram'][array_key_last($plan910925()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next910925 cursor mode' => static fn (TestRunner $t) => $t->same('next910-925-current-source-stat4-expression-partial-prep', $plan910925()['cursorProgram'][array_key_last($plan910925()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next910925 detail' => static fn (TestRunner $t) => $t->contains('NEXT910-925 PREPARED HANDOFF', $plan910925()['detail']),
    'planner stat4 expression partial current source next910925 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next910-925-prep', $plan910925()['dependencies'], true)),
    'planner stat4 expression partial current source next910925 dependency closure' => static fn (TestRunner $t) => $t->contains('next910-925 preparation extends', $plan910925()['dependency_closure']),
    'planner stat4 expression partial current source next910925 non overlap' => static fn (TestRunner $t) => $t->contains('next894-909 handoff windows', $plan910925()['non_overlap']),
    'planner stat4 expression partial current source next910925 malformed needed column' => static function (TestRunner $t) use ($prepared910925, $current910925, $terms910925): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeFinalPreparedHandoffContinuation($prepared910925(), $current910925(), $terms910925(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next910925 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan910925): void {
        $plan = $plan910925();
        $t->same($plan['stat4Next910925PreparationFence']['handoffSignature'], $plan['selectedPlan']['next910925HandoffSignature']);
    };
}

return $tests;
