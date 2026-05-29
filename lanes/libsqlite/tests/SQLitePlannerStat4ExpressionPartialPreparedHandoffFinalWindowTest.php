<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eqpreparedHandoff = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$likepreparedHandoff = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNullpreparedHandoff = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$betweenpreparedHandoff = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rowspreparedHandoff = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samplespreparedHandoff = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payloadpreparedHandoff = static fn (array $row): array => [
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

$preparedpreparedHandoff = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-preparedHandoff',
    'schemaCookie' => 3852,
    'stat4Generation' => 366,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_preparedHandoff',
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

$currentpreparedHandoff = static function (?array $rows = null, ?array $samples = null) use ($preparedpreparedHandoff, $rowspreparedHandoff, $samplespreparedHandoff, $payloadpreparedHandoff): array {
    $source = $preparedpreparedHandoff();
    $source['name'] = 'current-wp-options-stat4-handoff-preparedHandoff';
    $source['schemaCookie'] = 4002;
    $source['stat4Generation'] = 934;
    $source['rows'] = $rows ?? $rowspreparedHandoff();
    $source['indexes'][0]['rootPage'] = 40028;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samplespreparedHandoff();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payloadpreparedHandoff, $source['rows']);

    return $source;
};

$termspreparedHandoff = static fn (): array => [
    $betweenpreparedHandoff('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eqpreparedHandoff('autoload', 'yes'),
    $notNullpreparedHandoff('option_name'),
    $eqpreparedHandoff('blog_id', 1),
    $likepreparedHandoff('option_name', 'plugin_%'),
];

$planpreparedHandoff = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoff(
    $preparedpreparedHandoff(),
    $currentpreparedHandoff($rows, $samples),
    $termspreparedHandoff(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source preparedHandoff status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-prepared-handoff-ready', $planpreparedHandoff()['status']),
    'planner stat4 expression partial current source preparedHandoff inherits next718733' => static fn (TestRunner $t) => $t->same(true, $planpreparedHandoff()['selectedPlan']['next718733Prepared']),
    'planner stat4 expression partial current source preparedHandoff ready flag' => static fn (TestRunner $t) => $t->same(true, $planpreparedHandoff()['selectedPlan']['preparedHandoffReady']),
    'planner stat4 expression partial current source preparedHandoff prior ready' => static fn (TestRunner $t) => $t->same(true, $planpreparedHandoff()['stat4PreparedHandoffPreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source preparedHandoff slice range' => static fn (TestRunner $t) => $t->same([734, 749], $planpreparedHandoff()['stat4PreparedHandoffPreparationFence']['sliceRange']),
    'planner stat4 expression partial current source preparedHandoff prior range' => static fn (TestRunner $t) => $t->same([718, 733], $planpreparedHandoff()['stat4PreparedHandoffPreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source preparedHandoff prepared slices' => static fn (TestRunner $t) => $t->same(range(734, 749), $planpreparedHandoff()['selectedPlan']['preparedHandoffPreparedSlices']),
    'planner stat4 expression partial current source preparedHandoff blocked slices empty' => static fn (TestRunner $t) => $t->same([], $planpreparedHandoff()['selectedPlan']['preparedHandoffBlockedSlices']),
    'planner stat4 expression partial current source preparedHandoff first continues' => static fn (TestRunner $t) => $t->same(718, $planpreparedHandoff()['stat4PreparedHandoffPreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source preparedHandoff first rowid' => static fn (TestRunner $t) => $t->same(60, $planpreparedHandoff()['stat4PreparedHandoffPreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source preparedHandoff first projection matches' => static fn (TestRunner $t) => $t->same(true, $planpreparedHandoff()['stat4PreparedHandoffPreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source preparedHandoff signature length' => static fn (TestRunner $t) => $t->same(64, strlen($planpreparedHandoff()['stat4PreparedHandoffPreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source preparedHandoff selected signature' => static fn (TestRunner $t) => $t->same($planpreparedHandoff()['stat4PreparedHandoffPreparationFence']['handoffSignature'], $planpreparedHandoff()['selectedPlan']['preparedHandoffSignature']),
    'planner stat4 expression partial current source preparedHandoff stat4 signature' => static fn (TestRunner $t) => $t->same($planpreparedHandoff()['stat4PreparedHandoffPreparationFence']['handoffSignature'], $planpreparedHandoff()['stat4Fence']['preparedHandoffSignature']),
    'planner stat4 expression partial current source preparedHandoff prior signature threaded' => static fn (TestRunner $t) => $t->same($planpreparedHandoff()['stat4Next718733PreparationFence']['handoffSignature'], $planpreparedHandoff()['selectedPlan']['preparedHandoffPriorSignature']),
    'planner stat4 expression partial current source preparedHandoff preserves next718733 fence' => static fn (TestRunner $t) => $t->same(range(718, 733), $planpreparedHandoff()['stat4Next718733PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source preparedHandoff cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialPreparedHandoff', $planpreparedHandoff()['cursorProgram'][array_key_last($planpreparedHandoff()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source preparedHandoff cursor mode' => static fn (TestRunner $t) => $t->same('prepared-handoff-current-source-stat4-expression-partial-prep', $planpreparedHandoff()['cursorProgram'][array_key_last($planpreparedHandoff()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source preparedHandoff detail' => static fn (TestRunner $t) => $t->contains('PREPARED HANDOFF FINAL WINDOW', $planpreparedHandoff()['detail']),
    'planner stat4 expression partial current source preparedHandoff dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-prepared-handoff', $planpreparedHandoff()['dependencies'], true)),
    'planner stat4 expression partial current source preparedHandoff dependency closure' => static fn (TestRunner $t) => $t->contains('prepared handoff extends', $planpreparedHandoff()['dependency_closure']),
    'planner stat4 expression partial current source preparedHandoff non overlap' => static fn (TestRunner $t) => $t->contains('next718-733 handoff windows', $planpreparedHandoff()['non_overlap']),
    'planner stat4 expression partial current source preparedHandoff malformed needed column' => static function (TestRunner $t) use ($preparedpreparedHandoff, $currentpreparedHandoff, $termspreparedHandoff): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoff($preparedpreparedHandoff(), $currentpreparedHandoff(), $termspreparedHandoff(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source preparedHandoff repeated handoff proof ' . $case] = static function (TestRunner $t) use ($planpreparedHandoff): void {
        $plan = $planpreparedHandoff();
        $t->same($plan['stat4PreparedHandoffPreparationFence']['handoffSignature'], $plan['selectedPlan']['preparedHandoffSignature']);
    };
}

return $tests;
