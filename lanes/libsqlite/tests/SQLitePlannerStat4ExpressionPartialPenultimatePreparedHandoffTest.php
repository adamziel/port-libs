<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eqPenultimatePreparedHandoff = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$likePenultimatePreparedHandoff = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNullPenultimatePreparedHandoff = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$betweenPenultimatePreparedHandoff = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rowsPenultimatePreparedHandoff = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samplesPenultimatePreparedHandoff = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payloadPenultimatePreparedHandoff = static fn (array $row): array => [
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

$preparedPenultimatePreparedHandoff = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-penultimate-handoff',
    'schemaCookie' => 3920,
    'stat4Generation' => 398,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_penultimate_handoff',
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

$currentPenultimatePreparedHandoff = static function (?array $rows = null, ?array $samples = null) use ($preparedPenultimatePreparedHandoff, $rowsPenultimatePreparedHandoff, $samplesPenultimatePreparedHandoff, $payloadPenultimatePreparedHandoff): array {
    $source = $preparedPenultimatePreparedHandoff();
    $source['name'] = 'current-wp-options-stat4-penultimate-handoff';
    $source['schemaCookie'] = 4070;
    $source['stat4Generation'] = 966;
    $source['rows'] = $rows ?? $rowsPenultimatePreparedHandoff();
    $source['indexes'][0]['rootPage'] = 40708;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samplesPenultimatePreparedHandoff();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payloadPenultimatePreparedHandoff, $source['rows']);

    return $source;
};

$termsPenultimatePreparedHandoff = static fn (): array => [
    $betweenPenultimatePreparedHandoff('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eqPenultimatePreparedHandoff('autoload', 'yes'),
    $notNullPenultimatePreparedHandoff('option_name'),
    $eqPenultimatePreparedHandoff('blog_id', 1),
    $likePenultimatePreparedHandoff('option_name', 'plugin_%'),
];

$planPenultimatePreparedHandoff = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePenultimatePreparedHandoff(
    $preparedPenultimatePreparedHandoff(),
    $currentPenultimatePreparedHandoff($rows, $samples),
    $termsPenultimatePreparedHandoff(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source penultimate prepared handoff status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-penultimate-prepared-handoff-prepared', $planPenultimatePreparedHandoff()['status']),
    'planner stat4 expression partial current source penultimate prepared handoff inherits advanced handoff' => static fn (TestRunner $t) => $t->same(true, $planPenultimatePreparedHandoff()['selectedPlan']['next926941Prepared']),
    'planner stat4 expression partial current source penultimate prepared handoff ready flag' => static fn (TestRunner $t) => $t->same(true, $planPenultimatePreparedHandoff()['selectedPlan']['penultimatePreparedHandoffPrepared']),
    'planner stat4 expression partial current source penultimate prepared handoff prior ready' => static fn (TestRunner $t) => $t->same(true, $planPenultimatePreparedHandoff()['stat4PenultimatePreparedHandoffPreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source penultimate prepared handoff slice range' => static fn (TestRunner $t) => $t->same([942, 957], $planPenultimatePreparedHandoff()['stat4PenultimatePreparedHandoffPreparationFence']['sliceRange']),
    'planner stat4 expression partial current source penultimate prepared handoff prior range' => static fn (TestRunner $t) => $t->same([926, 941], $planPenultimatePreparedHandoff()['stat4PenultimatePreparedHandoffPreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source penultimate prepared handoff prepared slices' => static fn (TestRunner $t) => $t->same(range(942, 957), $planPenultimatePreparedHandoff()['selectedPlan']['penultimatePreparedHandoffPreparedSlices']),
    'planner stat4 expression partial current source penultimate prepared handoff blocked slices empty' => static fn (TestRunner $t) => $t->same([], $planPenultimatePreparedHandoff()['selectedPlan']['penultimatePreparedHandoffBlockedSlices']),
    'planner stat4 expression partial current source penultimate prepared handoff first continues' => static fn (TestRunner $t) => $t->same(926, $planPenultimatePreparedHandoff()['stat4PenultimatePreparedHandoffPreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source penultimate prepared handoff first rowid' => static fn (TestRunner $t) => $t->same(60, $planPenultimatePreparedHandoff()['stat4PenultimatePreparedHandoffPreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source penultimate prepared handoff first projection matches' => static fn (TestRunner $t) => $t->same(true, $planPenultimatePreparedHandoff()['stat4PenultimatePreparedHandoffPreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source penultimate prepared handoff signature length' => static fn (TestRunner $t) => $t->same(64, strlen($planPenultimatePreparedHandoff()['stat4PenultimatePreparedHandoffPreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source penultimate prepared handoff selected signature' => static fn (TestRunner $t) => $t->same($planPenultimatePreparedHandoff()['stat4PenultimatePreparedHandoffPreparationFence']['handoffSignature'], $planPenultimatePreparedHandoff()['selectedPlan']['penultimatePreparedHandoffHandoffSignature']),
    'planner stat4 expression partial current source penultimate prepared handoff stat4 signature' => static fn (TestRunner $t) => $t->same($planPenultimatePreparedHandoff()['stat4PenultimatePreparedHandoffPreparationFence']['handoffSignature'], $planPenultimatePreparedHandoff()['stat4Fence']['penultimatePreparedHandoffHandoffSignature']),
    'planner stat4 expression partial current source penultimate prepared handoff prior signature threaded' => static fn (TestRunner $t) => $t->same($planPenultimatePreparedHandoff()['stat4Next926941PreparationFence']['handoffSignature'], $planPenultimatePreparedHandoff()['selectedPlan']['penultimatePreparedHandoffPriorHandoffSignature']),
    'planner stat4 expression partial current source penultimate prepared handoff preserves advanced fence' => static fn (TestRunner $t) => $t->same(range(926, 941), $planPenultimatePreparedHandoff()['stat4Next926941PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source penultimate prepared handoff cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialPenultimatePreparedHandoff', $planPenultimatePreparedHandoff()['cursorProgram'][array_key_last($planPenultimatePreparedHandoff()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source penultimate prepared handoff cursor mode' => static fn (TestRunner $t) => $t->same('penultimate-prepared-handoff-current-source-stat4-expression-partial-prep', $planPenultimatePreparedHandoff()['cursorProgram'][array_key_last($planPenultimatePreparedHandoff()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source penultimate prepared handoff detail' => static fn (TestRunner $t) => $t->contains('PENULTIMATE PREPARED HANDOFF', $planPenultimatePreparedHandoff()['detail']),
    'planner stat4 expression partial current source penultimate prepared handoff dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-penultimate-prepared-handoff-prep', $planPenultimatePreparedHandoff()['dependencies'], true)),
    'planner stat4 expression partial current source penultimate prepared handoff dependency closure' => static fn (TestRunner $t) => $t->contains('penultimate prepared handoff preparation extends', $planPenultimatePreparedHandoff()['dependency_closure']),
    'planner stat4 expression partial current source penultimate prepared handoff non overlap' => static fn (TestRunner $t) => $t->contains('advanced prepared', $planPenultimatePreparedHandoff()['non_overlap']),
    'planner stat4 expression partial current source penultimate prepared handoff malformed needed column' => static function (TestRunner $t) use ($preparedPenultimatePreparedHandoff, $currentPenultimatePreparedHandoff, $termsPenultimatePreparedHandoff): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePenultimatePreparedHandoff($preparedPenultimatePreparedHandoff(), $currentPenultimatePreparedHandoff(), $termsPenultimatePreparedHandoff(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source penultimate prepared handoff repeated proof ' . $case] = static function (TestRunner $t) use ($planPenultimatePreparedHandoff): void {
        $plan = $planPenultimatePreparedHandoff();
        $t->same($plan['stat4PenultimatePreparedHandoffPreparationFence']['handoffSignature'], $plan['selectedPlan']['penultimatePreparedHandoffHandoffSignature']);
    };
}

return $tests;
