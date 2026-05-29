<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eqHandoffContinuationWindow = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$likeHandoffContinuationWindow = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNullHandoffContinuationWindow = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$betweenHandoffContinuationWindow = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rowsHandoffContinuationWindow = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samplesHandoffContinuationWindow = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payloadHandoffContinuationWindow = static fn (array $row): array => [
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

$preparedHandoffContinuationWindow = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-prepared handoff continuation window',
    'schemaCookie' => 3868,
    'stat4Generation' => 382,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_prepared handoff continuation window',
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

$currentHandoffContinuationWindow = static function (?array $rows = null, ?array $samples = null) use ($preparedHandoffContinuationWindow, $rowsHandoffContinuationWindow, $samplesHandoffContinuationWindow, $payloadHandoffContinuationWindow): array {
    $source = $preparedHandoffContinuationWindow();
    $source['name'] = 'current-wp-options-stat4-handoff-prepared handoff continuation window';
    $source['schemaCookie'] = 4018;
    $source['stat4Generation'] = 950;
    $source['rows'] = $rows ?? $rowsHandoffContinuationWindow();
    $source['indexes'][0]['rootPage'] = 40188;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samplesHandoffContinuationWindow();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payloadHandoffContinuationWindow, $source['rows']);

    return $source;
};

$termsHandoffContinuationWindow = static fn (): array => [
    $betweenHandoffContinuationWindow('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eqHandoffContinuationWindow('autoload', 'yes'),
    $notNullHandoffContinuationWindow('option_name'),
    $eqHandoffContinuationWindow('blog_id', 1),
    $likeHandoffContinuationWindow('option_name', 'plugin_%'),
];

$planHandoffContinuationWindow = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffContinuationWindow(
    $preparedHandoffContinuationWindow(),
    $currentHandoffContinuationWindow($rows, $samples),
    $termsHandoffContinuationWindow(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source prepared handoff continuation window status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-prepared-handoff-continuation-window-prepared', $planHandoffContinuationWindow()['status']),
    'planner stat4 expression partial current source prepared handoff continuation window inherits prior handoff' => static fn (TestRunner $t) => $t->same(true, $planHandoffContinuationWindow()['stat4PreparedHandoffContinuationWindowFence']['previousFenceReady']),
    'planner stat4 expression partial current source prepared handoff continuation window ready flag' => static fn (TestRunner $t) => $t->same(true, $planHandoffContinuationWindow()['selectedPlan']['preparedHandoffContinuationWindowReady']),
    'planner stat4 expression partial current source prepared handoff continuation window prior ready' => static fn (TestRunner $t) => $t->same(true, $planHandoffContinuationWindow()['stat4PreparedHandoffContinuationWindowFence']['previousFenceReady']),
    'planner stat4 expression partial current source prepared handoff continuation window slice range' => static fn (TestRunner $t) => $t->same([846, 861], $planHandoffContinuationWindow()['stat4PreparedHandoffContinuationWindowFence']['sliceRange']),
    'planner stat4 expression partial current source prepared handoff continuation window prior range' => static fn (TestRunner $t) => $t->same([830, 845], $planHandoffContinuationWindow()['stat4PreparedHandoffContinuationWindowFence']['priorSliceRange']),
    'planner stat4 expression partial current source prepared handoff continuation window prepared slices' => static fn (TestRunner $t) => $t->same(range(846, 861), $planHandoffContinuationWindow()['selectedPlan']['preparedHandoffContinuationWindowPreparedSlices']),
    'planner stat4 expression partial current source prepared handoff continuation window blocked slices empty' => static fn (TestRunner $t) => $t->same([], $planHandoffContinuationWindow()['selectedPlan']['preparedHandoffContinuationWindowBlockedSlices']),
    'planner stat4 expression partial current source prepared handoff continuation window first continues' => static fn (TestRunner $t) => $t->same(830, $planHandoffContinuationWindow()['stat4PreparedHandoffContinuationWindowFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source prepared handoff continuation window first rowid' => static fn (TestRunner $t) => $t->same(60, $planHandoffContinuationWindow()['stat4PreparedHandoffContinuationWindowFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source prepared handoff continuation window first projection matches' => static fn (TestRunner $t) => $t->same(true, $planHandoffContinuationWindow()['stat4PreparedHandoffContinuationWindowFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source prepared handoff continuation window signature length' => static fn (TestRunner $t) => $t->same(64, strlen($planHandoffContinuationWindow()['stat4PreparedHandoffContinuationWindowFence']['handoffSignature'])),
    'planner stat4 expression partial current source prepared handoff continuation window selected signature' => static fn (TestRunner $t) => $t->same($planHandoffContinuationWindow()['stat4PreparedHandoffContinuationWindowFence']['handoffSignature'], $planHandoffContinuationWindow()['selectedPlan']['preparedHandoffContinuationWindowHandoffSignature']),
    'planner stat4 expression partial current source prepared handoff continuation window stat4 signature' => static fn (TestRunner $t) => $t->same($planHandoffContinuationWindow()['stat4PreparedHandoffContinuationWindowFence']['handoffSignature'], $planHandoffContinuationWindow()['stat4Fence']['preparedHandoffContinuationWindowHandoffSignature']),
    'planner stat4 expression partial current source prepared handoff continuation window prior signature threaded' => static fn (TestRunner $t) => $t->same($planHandoffContinuationWindow()['stat4PreparedHandoffContinuationWindowFence']['priorHandoffSignature'], $planHandoffContinuationWindow()['selectedPlan']['preparedHandoffContinuationWindowPriorHandoffSignature']),
    'planner stat4 expression partial current source prepared handoff continuation window preserves prior fence' => static fn (TestRunner $t) => $t->same([830, 845], $planHandoffContinuationWindow()['stat4PreparedHandoffContinuationWindowFence']['priorSliceRange']),
    'planner stat4 expression partial current source prepared handoff continuation window cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialPreparedHandoffContinuationWindow', $planHandoffContinuationWindow()['cursorProgram'][array_key_last($planHandoffContinuationWindow()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source prepared handoff continuation window cursor mode' => static fn (TestRunner $t) => $t->same('prepared-handoff-continuation-window-current-source-stat4-expression-partial-prep', $planHandoffContinuationWindow()['cursorProgram'][array_key_last($planHandoffContinuationWindow()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source prepared handoff continuation window detail' => static fn (TestRunner $t) => $t->contains('PREPARED HANDOFF CONTINUATION WINDOW', $planHandoffContinuationWindow()['detail']),
    'planner stat4 expression partial current source prepared handoff continuation window dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-prepared-handoff-continuation-window', $planHandoffContinuationWindow()['dependencies'], true)),
    'planner stat4 expression partial current source prepared handoff continuation window dependency closure' => static fn (TestRunner $t) => $t->contains('prepared handoff continuation-window preparation extends', $planHandoffContinuationWindow()['dependency_closure']),
    'planner stat4 expression partial current source prepared handoff continuation window non overlap' => static fn (TestRunner $t) => $t->contains('prepared handoff windows', $planHandoffContinuationWindow()['non_overlap']),
    'planner stat4 expression partial current source prepared handoff continuation window malformed needed column' => static function (TestRunner $t) use ($preparedHandoffContinuationWindow, $currentHandoffContinuationWindow, $termsHandoffContinuationWindow): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffContinuationWindow($preparedHandoffContinuationWindow(), $currentHandoffContinuationWindow(), $termsHandoffContinuationWindow(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source prepared handoff continuation window repeated handoff proof ' . $case] = static function (TestRunner $t) use ($planHandoffContinuationWindow): void {
        $plan = $planHandoffContinuationWindow();
        $t->same($plan['stat4PreparedHandoffContinuationWindowFence']['handoffSignature'], $plan['selectedPlan']['preparedHandoffContinuationWindowHandoffSignature']);
    };
}

return $tests;
