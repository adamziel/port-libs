<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eqAdvancedPreparedHandoffContinuation = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$likeAdvancedPreparedHandoffContinuation = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNullAdvancedPreparedHandoffContinuation = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$betweenAdvancedPreparedHandoffContinuation = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rowsAdvancedPreparedHandoffContinuation = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samplesAdvancedPreparedHandoffContinuation = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payloadAdvancedPreparedHandoffContinuation = static fn (array $row): array => [
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

$preparedAdvancedPreparedHandoffContinuation = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-advanced prepared handoff continuation',
    'schemaCookie' => 3920,
    'stat4Generation' => 398,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_advanced prepared handoff continuation',
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

$currentAdvancedPreparedHandoffContinuation = static function (?array $rows = null, ?array $samples = null) use ($preparedAdvancedPreparedHandoffContinuation, $rowsAdvancedPreparedHandoffContinuation, $samplesAdvancedPreparedHandoffContinuation, $payloadAdvancedPreparedHandoffContinuation): array {
    $source = $preparedAdvancedPreparedHandoffContinuation();
    $source['name'] = 'current-wp-options-stat4-handoff-advanced prepared handoff continuation';
    $source['schemaCookie'] = 4070;
    $source['stat4Generation'] = 966;
    $source['rows'] = $rows ?? $rowsAdvancedPreparedHandoffContinuation();
    $source['indexes'][0]['rootPage'] = 40708;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samplesAdvancedPreparedHandoffContinuation();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payloadAdvancedPreparedHandoffContinuation, $source['rows']);

    return $source;
};

$termsAdvancedPreparedHandoffContinuation = static fn (): array => [
    $betweenAdvancedPreparedHandoffContinuation('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eqAdvancedPreparedHandoffContinuation('autoload', 'yes'),
    $notNullAdvancedPreparedHandoffContinuation('option_name'),
    $eqAdvancedPreparedHandoffContinuation('blog_id', 1),
    $likeAdvancedPreparedHandoffContinuation('option_name', 'plugin_%'),
];

$planAdvancedPreparedHandoffContinuation = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeAdvancedPreparedHandoffContinuation(
    $preparedAdvancedPreparedHandoffContinuation(),
    $currentAdvancedPreparedHandoffContinuation($rows, $samples),
    $termsAdvancedPreparedHandoffContinuation(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source advanced prepared handoff continuation status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-advanced-prepared-handoff-continuation-prepared', $planAdvancedPreparedHandoffContinuation()['status']),
    'planner stat4 expression partial current source advanced prepared handoff continuation inherits final prepared handoff continuation' => static fn (TestRunner $t) => $t->same(true, $planAdvancedPreparedHandoffContinuation()['selectedPlan']['finalPreparedHandoffContinuationReady']),
    'planner stat4 expression partial current source advanced prepared handoff continuation ready flag' => static fn (TestRunner $t) => $t->same(true, $planAdvancedPreparedHandoffContinuation()['selectedPlan']['advancedPreparedHandoffContinuationReady']),
    'planner stat4 expression partial current source advanced prepared handoff continuation prior ready' => static fn (TestRunner $t) => $t->same(true, $planAdvancedPreparedHandoffContinuation()['stat4AdvancedPreparedHandoffContinuationFence']['previousFenceReady']),
    'planner stat4 expression partial current source advanced prepared handoff continuation slice range' => static fn (TestRunner $t) => $t->same([926, 941], $planAdvancedPreparedHandoffContinuation()['stat4AdvancedPreparedHandoffContinuationFence']['sliceRange']),
    'planner stat4 expression partial current source advanced prepared handoff continuation prior range' => static fn (TestRunner $t) => $t->same([910, 925], $planAdvancedPreparedHandoffContinuation()['stat4AdvancedPreparedHandoffContinuationFence']['priorSliceRange']),
    'planner stat4 expression partial current source advanced prepared handoff continuation prepared slices' => static fn (TestRunner $t) => $t->same(range(926, 941), $planAdvancedPreparedHandoffContinuation()['selectedPlan']['advancedPreparedHandoffContinuationPreparedSlices']),
    'planner stat4 expression partial current source advanced prepared handoff continuation blocked slices empty' => static fn (TestRunner $t) => $t->same([], $planAdvancedPreparedHandoffContinuation()['selectedPlan']['advancedPreparedHandoffContinuationBlockedSlices']),
    'planner stat4 expression partial current source advanced prepared handoff continuation first continues' => static fn (TestRunner $t) => $t->same(910, $planAdvancedPreparedHandoffContinuation()['stat4AdvancedPreparedHandoffContinuationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source advanced prepared handoff continuation first rowid' => static fn (TestRunner $t) => $t->same(60, $planAdvancedPreparedHandoffContinuation()['stat4AdvancedPreparedHandoffContinuationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source advanced prepared handoff continuation first projection matches' => static fn (TestRunner $t) => $t->same(true, $planAdvancedPreparedHandoffContinuation()['stat4AdvancedPreparedHandoffContinuationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source advanced prepared handoff continuation signature length' => static fn (TestRunner $t) => $t->same(64, strlen($planAdvancedPreparedHandoffContinuation()['stat4AdvancedPreparedHandoffContinuationFence']['handoffSignature'])),
    'planner stat4 expression partial current source advanced prepared handoff continuation selected signature' => static fn (TestRunner $t) => $t->same($planAdvancedPreparedHandoffContinuation()['stat4AdvancedPreparedHandoffContinuationFence']['handoffSignature'], $planAdvancedPreparedHandoffContinuation()['selectedPlan']['advancedPreparedHandoffContinuationHandoffSignature']),
    'planner stat4 expression partial current source advanced prepared handoff continuation stat4 signature' => static fn (TestRunner $t) => $t->same($planAdvancedPreparedHandoffContinuation()['stat4AdvancedPreparedHandoffContinuationFence']['handoffSignature'], $planAdvancedPreparedHandoffContinuation()['stat4Fence']['advancedPreparedHandoffContinuationHandoffSignature']),
    'planner stat4 expression partial current source advanced prepared handoff continuation prior signature threaded' => static fn (TestRunner $t) => $t->same($planAdvancedPreparedHandoffContinuation()['stat4FinalPreparedHandoffContinuationFence']['handoffSignature'], $planAdvancedPreparedHandoffContinuation()['selectedPlan']['advancedPreparedHandoffContinuationPriorHandoffSignature']),
    'planner stat4 expression partial current source advanced prepared handoff continuation preserves final prepared handoff continuation fence' => static fn (TestRunner $t) => $t->same(range(910, 925), $planAdvancedPreparedHandoffContinuation()['stat4FinalPreparedHandoffContinuationFence']['preparedSlices']),
    'planner stat4 expression partial current source advanced prepared handoff continuation cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialAdvancedPreparedHandoffContinuation', $planAdvancedPreparedHandoffContinuation()['cursorProgram'][array_key_last($planAdvancedPreparedHandoffContinuation()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source advanced prepared handoff continuation cursor mode' => static fn (TestRunner $t) => $t->same('advanced-prepared-handoff-continuation-current-source-stat4-expression-partial-prep', $planAdvancedPreparedHandoffContinuation()['cursorProgram'][array_key_last($planAdvancedPreparedHandoffContinuation()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source advanced prepared handoff continuation detail' => static fn (TestRunner $t) => $t->contains('ADVANCED PREPARED HANDOFF CONTINUATION', $planAdvancedPreparedHandoffContinuation()['detail']),
    'planner stat4 expression partial current source advanced prepared handoff continuation dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-advanced-prepared-handoff-continuation', $planAdvancedPreparedHandoffContinuation()['dependencies'], true)),
    'planner stat4 expression partial current source advanced prepared handoff continuation dependency closure' => static fn (TestRunner $t) => $t->contains('advanced prepared handoff continuation preparation extends', $planAdvancedPreparedHandoffContinuation()['dependency_closure']),
    'planner stat4 expression partial current source advanced prepared handoff continuation non overlap' => static fn (TestRunner $t) => $t->contains('final prepared handoff continuation handoff windows', $planAdvancedPreparedHandoffContinuation()['non_overlap']),
    'planner stat4 expression partial current source advanced prepared handoff continuation malformed needed column' => static function (TestRunner $t) use ($preparedAdvancedPreparedHandoffContinuation, $currentAdvancedPreparedHandoffContinuation, $termsAdvancedPreparedHandoffContinuation): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeAdvancedPreparedHandoffContinuation($preparedAdvancedPreparedHandoffContinuation(), $currentAdvancedPreparedHandoffContinuation(), $termsAdvancedPreparedHandoffContinuation(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source advanced prepared handoff continuation repeated handoff proof ' . $case] = static function (TestRunner $t) use ($planAdvancedPreparedHandoffContinuation): void {
        $plan = $planAdvancedPreparedHandoffContinuation();
        $t->same($plan['stat4AdvancedPreparedHandoffContinuationFence']['handoffSignature'], $plan['selectedPlan']['advancedPreparedHandoffContinuationHandoffSignature']);
    };
}

return $tests;
