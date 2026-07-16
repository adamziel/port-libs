<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eqpreparedHandoffResumeWindow = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$likepreparedHandoffResumeWindow = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNullpreparedHandoffResumeWindow = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$betweenpreparedHandoffResumeWindow = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rowspreparedHandoffResumeWindow = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samplespreparedHandoffResumeWindow = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payloadpreparedHandoffResumeWindow = static fn (array $row): array => [
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

$preparedpreparedHandoffResumeWindow = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-prepared-handoff-resume-window',
    'schemaCookie' => 3868,
    'stat4Generation' => 382,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_prepared-handoff-resume-window',
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

$currentpreparedHandoffResumeWindow = static function (?array $rows = null, ?array $samples = null) use ($preparedpreparedHandoffResumeWindow, $rowspreparedHandoffResumeWindow, $samplespreparedHandoffResumeWindow, $payloadpreparedHandoffResumeWindow): array {
    $source = $preparedpreparedHandoffResumeWindow();
    $source['name'] = 'current-wp-options-stat4-handoff-prepared-handoff-resume-window';
    $source['schemaCookie'] = 4018;
    $source['stat4Generation'] = 950;
    $source['rows'] = $rows ?? $rowspreparedHandoffResumeWindow();
    $source['indexes'][0]['rootPage'] = 40188;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samplespreparedHandoffResumeWindow();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payloadpreparedHandoffResumeWindow, $source['rows']);

    return $source;
};

$termspreparedHandoffResumeWindow = static fn (): array => [
    $betweenpreparedHandoffResumeWindow('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eqpreparedHandoffResumeWindow('autoload', 'yes'),
    $notNullpreparedHandoffResumeWindow('option_name'),
    $eqpreparedHandoffResumeWindow('blog_id', 1),
    $likepreparedHandoffResumeWindow('option_name', 'plugin_%'),
];

$planpreparedHandoffResumeWindow = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffResumeWindow(
    $preparedpreparedHandoffResumeWindow(),
    $currentpreparedHandoffResumeWindow($rows, $samples),
    $termspreparedHandoffResumeWindow(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source prepared handoff resume window status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-prepared-handoff-resume-window-prepared', $planpreparedHandoffResumeWindow()['status']),
    'planner stat4 expression partial current source prepared handoff resume window inherits prepared handoff continuation window' => static fn (TestRunner $t) => $t->same(true, $planpreparedHandoffResumeWindow()['selectedPlan']['preparedHandoffContinuationWindowReady']),
    'planner stat4 expression partial current source prepared handoff resume window ready flag' => static fn (TestRunner $t) => $t->same(true, $planpreparedHandoffResumeWindow()['selectedPlan']['preparedHandoffResumeWindowReady']),
    'planner stat4 expression partial current source prepared handoff resume window prior ready' => static fn (TestRunner $t) => $t->same(true, $planpreparedHandoffResumeWindow()['stat4PreparedHandoffResumeWindowFence']['previousFenceReady']),
    'planner stat4 expression partial current source prepared handoff resume window slice range' => static fn (TestRunner $t) => $t->same([862, 877], $planpreparedHandoffResumeWindow()['stat4PreparedHandoffResumeWindowFence']['sliceRange']),
    'planner stat4 expression partial current source prepared handoff resume window prior range' => static fn (TestRunner $t) => $t->same([846, 861], $planpreparedHandoffResumeWindow()['stat4PreparedHandoffResumeWindowFence']['priorSliceRange']),
    'planner stat4 expression partial current source prepared handoff resume window prepared slices' => static fn (TestRunner $t) => $t->same(range(862, 877), $planpreparedHandoffResumeWindow()['selectedPlan']['preparedHandoffResumeWindowPreparedSlices']),
    'planner stat4 expression partial current source prepared handoff resume window blocked slices empty' => static fn (TestRunner $t) => $t->same([], $planpreparedHandoffResumeWindow()['selectedPlan']['preparedHandoffResumeWindowBlockedSlices']),
    'planner stat4 expression partial current source prepared handoff resume window first continues' => static fn (TestRunner $t) => $t->same(846, $planpreparedHandoffResumeWindow()['stat4PreparedHandoffResumeWindowFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source prepared handoff resume window first rowid' => static fn (TestRunner $t) => $t->same(60, $planpreparedHandoffResumeWindow()['stat4PreparedHandoffResumeWindowFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source prepared handoff resume window first projection matches' => static fn (TestRunner $t) => $t->same(true, $planpreparedHandoffResumeWindow()['stat4PreparedHandoffResumeWindowFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source prepared handoff resume window signature length' => static fn (TestRunner $t) => $t->same(64, strlen($planpreparedHandoffResumeWindow()['stat4PreparedHandoffResumeWindowFence']['handoffSignature'])),
    'planner stat4 expression partial current source prepared handoff resume window selected signature' => static fn (TestRunner $t) => $t->same($planpreparedHandoffResumeWindow()['stat4PreparedHandoffResumeWindowFence']['handoffSignature'], $planpreparedHandoffResumeWindow()['selectedPlan']['preparedHandoffResumeWindowHandoffSignature']),
    'planner stat4 expression partial current source prepared handoff resume window stat4 signature' => static fn (TestRunner $t) => $t->same($planpreparedHandoffResumeWindow()['stat4PreparedHandoffResumeWindowFence']['handoffSignature'], $planpreparedHandoffResumeWindow()['stat4Fence']['preparedHandoffResumeWindowHandoffSignature']),
    'planner stat4 expression partial current source prepared handoff resume window prior signature threaded' => static fn (TestRunner $t) => $t->same($planpreparedHandoffResumeWindow()['stat4PreparedHandoffContinuationWindowFence']['handoffSignature'], $planpreparedHandoffResumeWindow()['selectedPlan']['preparedHandoffResumeWindowPriorHandoffSignature']),
    'planner stat4 expression partial current source prepared handoff resume window preserves prepared handoff continuation window fence' => static fn (TestRunner $t) => $t->same(range(846, 861), $planpreparedHandoffResumeWindow()['stat4PreparedHandoffContinuationWindowFence']['preparedSlices']),
    'planner stat4 expression partial current source prepared handoff resume window cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialPreparedHandoffResumeWindow', $planpreparedHandoffResumeWindow()['cursorProgram'][array_key_last($planpreparedHandoffResumeWindow()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source prepared handoff resume window cursor mode' => static fn (TestRunner $t) => $t->same('prepared-handoff-resume-window-current-source-stat4-expression-partial-prep', $planpreparedHandoffResumeWindow()['cursorProgram'][array_key_last($planpreparedHandoffResumeWindow()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source prepared handoff resume window detail' => static fn (TestRunner $t) => $t->contains('PREPARED HANDOFF RESUME WINDOW', $planpreparedHandoffResumeWindow()['detail']),
    'planner stat4 expression partial current source prepared handoff resume window dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-prepared-handoff-resume-window', $planpreparedHandoffResumeWindow()['dependencies'], true)),
    'planner stat4 expression partial current source prepared handoff resume window dependency closure' => static fn (TestRunner $t) => $t->contains('prepared handoff resume-window preparation extends', $planpreparedHandoffResumeWindow()['dependency_closure']),
    'planner stat4 expression partial current source prepared handoff resume window non overlap' => static fn (TestRunner $t) => $t->contains('prepared handoff continuation-window handoff windows', $planpreparedHandoffResumeWindow()['non_overlap']),
    'planner stat4 expression partial current source prepared handoff resume window malformed needed column' => static function (TestRunner $t) use ($preparedpreparedHandoffResumeWindow, $currentpreparedHandoffResumeWindow, $termspreparedHandoffResumeWindow): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffResumeWindow($preparedpreparedHandoffResumeWindow(), $currentpreparedHandoffResumeWindow(), $termspreparedHandoffResumeWindow(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source prepared handoff resume window repeated handoff proof ' . $case] = static function (TestRunner $t) use ($planpreparedHandoffResumeWindow): void {
        $plan = $planpreparedHandoffResumeWindow();
        $t->same($plan['stat4PreparedHandoffResumeWindowFence']['handoffSignature'], $plan['selectedPlan']['preparedHandoffResumeWindowHandoffSignature']);
    };
}

return $tests;
