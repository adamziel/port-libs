<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eqpreparedHandoffWindow = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$likepreparedHandoffWindow = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNullpreparedHandoffWindow = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$betweenpreparedHandoffWindow = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rowspreparedHandoffWindow = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samplespreparedHandoffWindow = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payloadpreparedHandoffWindow = static fn (array $row): array => [
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

$preparedpreparedHandoffWindow = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-window',
    'schemaCookie' => 3868,
    'stat4Generation' => 382,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_window',
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

$currentpreparedHandoffWindow = static function (?array $rows = null, ?array $samples = null) use ($preparedpreparedHandoffWindow, $rowspreparedHandoffWindow, $samplespreparedHandoffWindow, $payloadpreparedHandoffWindow): array {
    $source = $preparedpreparedHandoffWindow();
    $source['name'] = 'current-wp-options-stat4-handoff-window';
    $source['schemaCookie'] = 4018;
    $source['stat4Generation'] = 950;
    $source['rows'] = $rows ?? $rowspreparedHandoffWindow();
    $source['indexes'][0]['rootPage'] = 40188;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samplespreparedHandoffWindow();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payloadpreparedHandoffWindow, $source['rows']);

    return $source;
};

$termspreparedHandoffWindow = static fn (): array => [
    $betweenpreparedHandoffWindow('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eqpreparedHandoffWindow('autoload', 'yes'),
    $notNullpreparedHandoffWindow('option_name'),
    $eqpreparedHandoffWindow('blog_id', 1),
    $likepreparedHandoffWindow('option_name', 'plugin_%'),
];

$planpreparedHandoffWindow = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffWindow(
    $preparedpreparedHandoffWindow(),
    $currentpreparedHandoffWindow($rows, $samples),
    $termspreparedHandoffWindow(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial prepared handoff window status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-prepared-handoff-window-prepared', $planpreparedHandoffWindow()['status']),
    'planner stat4 expression partial prepared handoff window inherits next814829' => static fn (TestRunner $t) => $t->same(true, $planpreparedHandoffWindow()['selectedPlan']['next814829Prepared']),
    'planner stat4 expression partial prepared handoff window ready flag' => static fn (TestRunner $t) => $t->same(true, $planpreparedHandoffWindow()['selectedPlan']['preparedHandoffWindowReady']),
    'planner stat4 expression partial prepared handoff window prior ready' => static fn (TestRunner $t) => $t->same(true, $planpreparedHandoffWindow()['stat4PreparedHandoffWindowFence']['previousFenceReady']),
    'planner stat4 expression partial prepared handoff window slice range' => static fn (TestRunner $t) => $t->same([830, 845], $planpreparedHandoffWindow()['stat4PreparedHandoffWindowFence']['sliceRange']),
    'planner stat4 expression partial prepared handoff window prior range' => static fn (TestRunner $t) => $t->same([814, 829], $planpreparedHandoffWindow()['stat4PreparedHandoffWindowFence']['priorSliceRange']),
    'planner stat4 expression partial prepared handoff window prepared slices' => static fn (TestRunner $t) => $t->same(range(830, 845), $planpreparedHandoffWindow()['selectedPlan']['preparedHandoffWindowPreparedSlices']),
    'planner stat4 expression partial prepared handoff window blocked slices empty' => static fn (TestRunner $t) => $t->same([], $planpreparedHandoffWindow()['selectedPlan']['preparedHandoffWindowBlockedSlices']),
    'planner stat4 expression partial prepared handoff window first continues' => static fn (TestRunner $t) => $t->same(814, $planpreparedHandoffWindow()['stat4PreparedHandoffWindowFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial prepared handoff window first rowid' => static fn (TestRunner $t) => $t->same(60, $planpreparedHandoffWindow()['stat4PreparedHandoffWindowFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial prepared handoff window first projection matches' => static fn (TestRunner $t) => $t->same(true, $planpreparedHandoffWindow()['stat4PreparedHandoffWindowFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial prepared handoff window signature length' => static fn (TestRunner $t) => $t->same(64, strlen($planpreparedHandoffWindow()['stat4PreparedHandoffWindowFence']['handoffSignature'])),
    'planner stat4 expression partial prepared handoff window selected signature' => static fn (TestRunner $t) => $t->same($planpreparedHandoffWindow()['stat4PreparedHandoffWindowFence']['handoffSignature'], $planpreparedHandoffWindow()['selectedPlan']['preparedHandoffWindowHandoffSignature']),
    'planner stat4 expression partial prepared handoff window stat4 signature' => static fn (TestRunner $t) => $t->same($planpreparedHandoffWindow()['stat4PreparedHandoffWindowFence']['handoffSignature'], $planpreparedHandoffWindow()['stat4Fence']['preparedHandoffWindowHandoffSignature']),
    'planner stat4 expression partial prepared handoff window prior signature threaded' => static fn (TestRunner $t) => $t->same($planpreparedHandoffWindow()['stat4Next814829PreparationFence']['handoffSignature'], $planpreparedHandoffWindow()['selectedPlan']['preparedHandoffWindowPriorHandoffSignature']),
    'planner stat4 expression partial prepared handoff window preserves next814829 fence' => static fn (TestRunner $t) => $t->same(range(814, 829), $planpreparedHandoffWindow()['stat4Next814829PreparationFence']['preparedSlices']),
    'planner stat4 expression partial prepared handoff window cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialPreparedHandoffWindow', $planpreparedHandoffWindow()['cursorProgram'][array_key_last($planpreparedHandoffWindow()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial prepared handoff window cursor mode' => static fn (TestRunner $t) => $t->same('prepared-handoff-window-stat4-expression-partial-prep', $planpreparedHandoffWindow()['cursorProgram'][array_key_last($planpreparedHandoffWindow()['cursorProgram'])]['mode']),
    'planner stat4 expression partial prepared handoff window detail' => static fn (TestRunner $t) => $t->contains('PREPARED HANDOFF WINDOW', $planpreparedHandoffWindow()['detail']),
    'planner stat4 expression partial prepared handoff window dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-prepared-handoff-window', $planpreparedHandoffWindow()['dependencies'], true)),
    'planner stat4 expression partial prepared handoff window dependency closure' => static fn (TestRunner $t) => $t->contains('prepared handoff window extends', $planpreparedHandoffWindow()['dependency_closure']),
    'planner stat4 expression partial prepared handoff window non overlap' => static fn (TestRunner $t) => $t->contains('next814-829 handoff windows', $planpreparedHandoffWindow()['non_overlap']),
    'planner stat4 expression partial prepared handoff window malformed needed column' => static function (TestRunner $t) use ($preparedpreparedHandoffWindow, $currentpreparedHandoffWindow, $termspreparedHandoffWindow): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffWindow($preparedpreparedHandoffWindow(), $currentpreparedHandoffWindow(), $termspreparedHandoffWindow(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial prepared handoff window repeated handoff proof ' . $case] = static function (TestRunner $t) use ($planpreparedHandoffWindow): void {
        $plan = $planpreparedHandoffWindow();
        $t->same($plan['stat4PreparedHandoffWindowFence']['handoffSignature'], $plan['selectedPlan']['preparedHandoffWindowHandoffSignature']);
    };
}

return $tests;
