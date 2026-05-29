<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eqfinalPreparedHandoff = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$likefinalPreparedHandoff = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNullfinalPreparedHandoff = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$betweenfinalPreparedHandoff = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rowsfinalPreparedHandoff = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samplesfinalPreparedHandoff = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payloadfinalPreparedHandoff = static fn (array $row): array => [
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

$preparedfinalPreparedHandoff = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-finalPreparedHandoff',
    'schemaCookie' => 3920,
    'stat4Generation' => 398,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_finalPreparedHandoff',
        'rootPage' => 39201,
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

$currentfinalPreparedHandoff = static function (?array $rows = null, ?array $samples = null) use ($preparedfinalPreparedHandoff, $rowsfinalPreparedHandoff, $samplesfinalPreparedHandoff, $payloadfinalPreparedHandoff): array {
    $source = $preparedfinalPreparedHandoff();
    $source['name'] = 'current-wp-options-stat4-handoff-finalPreparedHandoff';
    $source['schemaCookie'] = 4070;
    $source['stat4Generation'] = 966;
    $source['rows'] = $rows ?? $rowsfinalPreparedHandoff();
    $source['indexes'][0]['rootPage'] = 40708;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samplesfinalPreparedHandoff();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payloadfinalPreparedHandoff, $source['rows']);

    return $source;
};

$termsfinalPreparedHandoff = static fn (): array => [
    $betweenfinalPreparedHandoff('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eqfinalPreparedHandoff('autoload', 'yes'),
    $notNullfinalPreparedHandoff('option_name'),
    $eqfinalPreparedHandoff('blog_id', 1),
    $likefinalPreparedHandoff('option_name', 'plugin_%'),
];

$planfinalPreparedHandoff = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeFinalPreparedHandoff(
    $preparedfinalPreparedHandoff(),
    $currentfinalPreparedHandoff($rows, $samples),
    $termsfinalPreparedHandoff(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source finalPreparedHandoff status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-final-prepared-handoff-prepared', $planfinalPreparedHandoff()['status']),
    'planner stat4 expression partial current source finalPreparedHandoff inherits next958973' => static fn (TestRunner $t) => $t->same(true, $planfinalPreparedHandoff()['selectedPlan']['next958973Prepared']),
    'planner stat4 expression partial current source finalPreparedHandoff ready flag' => static fn (TestRunner $t) => $t->same(true, $planfinalPreparedHandoff()['selectedPlan']['finalPreparedHandoffPrepared']),
    'planner stat4 expression partial current source finalPreparedHandoff prior ready' => static fn (TestRunner $t) => $t->same(true, $planfinalPreparedHandoff()['stat4FinalPreparedHandoffPreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source finalPreparedHandoff slice range' => static fn (TestRunner $t) => $t->same([990, 1005], $planfinalPreparedHandoff()['stat4FinalPreparedHandoffPreparationFence']['sliceRange']),
    'planner stat4 expression partial current source finalPreparedHandoff prior range' => static fn (TestRunner $t) => $t->same([958, 973], $planfinalPreparedHandoff()['stat4FinalPreparedHandoffPreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source finalPreparedHandoff prepared slices' => static fn (TestRunner $t) => $t->same(range(990, 1005), $planfinalPreparedHandoff()['selectedPlan']['finalPreparedHandoffPreparedSlices']),
    'planner stat4 expression partial current source finalPreparedHandoff blocked slices empty' => static fn (TestRunner $t) => $t->same([], $planfinalPreparedHandoff()['selectedPlan']['finalPreparedHandoffBlockedSlices']),
    'planner stat4 expression partial current source finalPreparedHandoff first continues' => static fn (TestRunner $t) => $t->same(958, $planfinalPreparedHandoff()['stat4FinalPreparedHandoffPreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source finalPreparedHandoff first rowid' => static fn (TestRunner $t) => $t->same(60, $planfinalPreparedHandoff()['stat4FinalPreparedHandoffPreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source finalPreparedHandoff first projection matches' => static fn (TestRunner $t) => $t->same(true, $planfinalPreparedHandoff()['stat4FinalPreparedHandoffPreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source finalPreparedHandoff signature length' => static fn (TestRunner $t) => $t->same(64, strlen($planfinalPreparedHandoff()['stat4FinalPreparedHandoffPreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source finalPreparedHandoff selected signature' => static fn (TestRunner $t) => $t->same($planfinalPreparedHandoff()['stat4FinalPreparedHandoffPreparationFence']['handoffSignature'], $planfinalPreparedHandoff()['selectedPlan']['finalPreparedHandoffHandoffSignature']),
    'planner stat4 expression partial current source finalPreparedHandoff stat4 signature' => static fn (TestRunner $t) => $t->same($planfinalPreparedHandoff()['stat4FinalPreparedHandoffPreparationFence']['handoffSignature'], $planfinalPreparedHandoff()['stat4Fence']['finalPreparedHandoffHandoffSignature']),
    'planner stat4 expression partial current source finalPreparedHandoff prior signature threaded' => static fn (TestRunner $t) => $t->same($planfinalPreparedHandoff()['stat4Next958973PreparationFence']['handoffSignature'], $planfinalPreparedHandoff()['selectedPlan']['finalPreparedHandoffPriorHandoffSignature']),
    'planner stat4 expression partial current source finalPreparedHandoff preserves finalPreparedHandoff fence' => static fn (TestRunner $t) => $t->same(range(958, 973), $planfinalPreparedHandoff()['stat4Next958973PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source finalPreparedHandoff cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialFinalPreparedHandoffHandoff', $planfinalPreparedHandoff()['cursorProgram'][array_key_last($planfinalPreparedHandoff()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source finalPreparedHandoff cursor mode' => static fn (TestRunner $t) => $t->same('final prepared handoff-current-source-stat4-expression-partial-prep', $planfinalPreparedHandoff()['cursorProgram'][array_key_last($planfinalPreparedHandoff()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source finalPreparedHandoff detail' => static fn (TestRunner $t) => $t->contains('FINAL PREPARED HANDOFF', $planfinalPreparedHandoff()['detail']),
    'planner stat4 expression partial current source finalPreparedHandoff dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-final-prepared-handoff-prep', $planfinalPreparedHandoff()['dependencies'], true)),
    'planner stat4 expression partial current source finalPreparedHandoff dependency closure' => static fn (TestRunner $t) => $t->contains('final prepared handoff preparation extends', $planfinalPreparedHandoff()['dependency_closure']),
    'planner stat4 expression partial current source finalPreparedHandoff non overlap' => static fn (TestRunner $t) => $t->contains('next958-973 handoff windows', $planfinalPreparedHandoff()['non_overlap']),
    'planner stat4 expression partial current source finalPreparedHandoff malformed needed column' => static function (TestRunner $t) use ($preparedfinalPreparedHandoff, $currentfinalPreparedHandoff, $termsfinalPreparedHandoff): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeFinalPreparedHandoff($preparedfinalPreparedHandoff(), $currentfinalPreparedHandoff(), $termsfinalPreparedHandoff(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source finalPreparedHandoff repeated handoff proof ' . $case] = static function (TestRunner $t) use ($planfinalPreparedHandoff): void {
        $plan = $planfinalPreparedHandoff();
        $t->same($plan['stat4FinalPreparedHandoffPreparationFence']['handoffSignature'], $plan['selectedPlan']['finalPreparedHandoffHandoffSignature']);
    };
}

return $tests;
