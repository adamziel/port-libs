<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eqPreparedHandoffValidationContinuation = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$likePreparedHandoffValidationContinuation = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNullPreparedHandoffValidationContinuation = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$betweenPreparedHandoffValidationContinuation = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rowsPreparedHandoffValidationContinuation = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samplesPreparedHandoffValidationContinuation = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payloadPreparedHandoffValidationContinuation = static fn (array $row): array => [
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

$preparedPreparedHandoffValidationContinuation = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-prepared handoff validation continuation',
    'schemaCookie' => 3868,
    'stat4Generation' => 382,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_prepared handoff validation continuation',
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

$currentPreparedHandoffValidationContinuation = static function (?array $rows = null, ?array $samples = null) use ($preparedPreparedHandoffValidationContinuation, $rowsPreparedHandoffValidationContinuation, $samplesPreparedHandoffValidationContinuation, $payloadPreparedHandoffValidationContinuation): array {
    $source = $preparedPreparedHandoffValidationContinuation();
    $source['name'] = 'current-wp-options-stat4-handoff-prepared handoff validation continuation';
    $source['schemaCookie'] = 4018;
    $source['stat4Generation'] = 950;
    $source['rows'] = $rows ?? $rowsPreparedHandoffValidationContinuation();
    $source['indexes'][0]['rootPage'] = 40188;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samplesPreparedHandoffValidationContinuation();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payloadPreparedHandoffValidationContinuation, $source['rows']);

    return $source;
};

$termsPreparedHandoffValidationContinuation = static fn (): array => [
    $betweenPreparedHandoffValidationContinuation('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eqPreparedHandoffValidationContinuation('autoload', 'yes'),
    $notNullPreparedHandoffValidationContinuation('option_name'),
    $eqPreparedHandoffValidationContinuation('blog_id', 1),
    $likePreparedHandoffValidationContinuation('option_name', 'plugin_%'),
];

$planPreparedHandoffValidationContinuation = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffValidationContinuation(
    $preparedPreparedHandoffValidationContinuation(),
    $currentPreparedHandoffValidationContinuation($rows, $samples),
    $termsPreparedHandoffValidationContinuation(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source prepared handoff validation continuation status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-prepared-handoff-validation-continuation-prepared', $planPreparedHandoffValidationContinuation()['status']),
    'planner stat4 expression partial current source prepared handoff validation continuation inherits prepared handoff resume window' => static fn (TestRunner $t) => $t->same(true, $planPreparedHandoffValidationContinuation()['selectedPlan']['preparedHandoffResumeWindowReady']),
    'planner stat4 expression partial current source prepared handoff validation continuation ready flag' => static fn (TestRunner $t) => $t->same(true, $planPreparedHandoffValidationContinuation()['selectedPlan']['preparedHandoffValidationContinuationReady']),
    'planner stat4 expression partial current source prepared handoff validation continuation prior ready' => static fn (TestRunner $t) => $t->same(true, $planPreparedHandoffValidationContinuation()['stat4PreparedHandoffValidationContinuationFence']['previousFenceReady']),
    'planner stat4 expression partial current source prepared handoff validation continuation slice range' => static fn (TestRunner $t) => $t->same([878, 893], $planPreparedHandoffValidationContinuation()['stat4PreparedHandoffValidationContinuationFence']['sliceRange']),
    'planner stat4 expression partial current source prepared handoff validation continuation prior range' => static fn (TestRunner $t) => $t->same([862, 877], $planPreparedHandoffValidationContinuation()['stat4PreparedHandoffValidationContinuationFence']['priorSliceRange']),
    'planner stat4 expression partial current source prepared handoff validation continuation prepared slices' => static fn (TestRunner $t) => $t->same(range(878, 893), $planPreparedHandoffValidationContinuation()['selectedPlan']['preparedHandoffValidationContinuationPreparedSlices']),
    'planner stat4 expression partial current source prepared handoff validation continuation blocked slices empty' => static fn (TestRunner $t) => $t->same([], $planPreparedHandoffValidationContinuation()['selectedPlan']['preparedHandoffValidationContinuationBlockedSlices']),
    'planner stat4 expression partial current source prepared handoff validation continuation first continues' => static fn (TestRunner $t) => $t->same(862, $planPreparedHandoffValidationContinuation()['stat4PreparedHandoffValidationContinuationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source prepared handoff validation continuation first rowid' => static fn (TestRunner $t) => $t->same(60, $planPreparedHandoffValidationContinuation()['stat4PreparedHandoffValidationContinuationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source prepared handoff validation continuation first projection matches' => static fn (TestRunner $t) => $t->same(true, $planPreparedHandoffValidationContinuation()['stat4PreparedHandoffValidationContinuationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source prepared handoff validation continuation signature length' => static fn (TestRunner $t) => $t->same(64, strlen($planPreparedHandoffValidationContinuation()['stat4PreparedHandoffValidationContinuationFence']['handoffSignature'])),
    'planner stat4 expression partial current source prepared handoff validation continuation selected signature' => static fn (TestRunner $t) => $t->same($planPreparedHandoffValidationContinuation()['stat4PreparedHandoffValidationContinuationFence']['handoffSignature'], $planPreparedHandoffValidationContinuation()['selectedPlan']['preparedHandoffValidationContinuationHandoffSignature']),
    'planner stat4 expression partial current source prepared handoff validation continuation stat4 signature' => static fn (TestRunner $t) => $t->same($planPreparedHandoffValidationContinuation()['stat4PreparedHandoffValidationContinuationFence']['handoffSignature'], $planPreparedHandoffValidationContinuation()['stat4Fence']['preparedHandoffValidationContinuationHandoffSignature']),
    'planner stat4 expression partial current source prepared handoff validation continuation prior signature threaded' => static fn (TestRunner $t) => $t->same($planPreparedHandoffValidationContinuation()['stat4PreparedHandoffResumeWindowFence']['handoffSignature'], $planPreparedHandoffValidationContinuation()['selectedPlan']['preparedHandoffValidationContinuationPriorHandoffSignature']),
    'planner stat4 expression partial current source prepared handoff validation continuation preserves prepared handoff resume window fence' => static fn (TestRunner $t) => $t->same(range(862, 877), $planPreparedHandoffValidationContinuation()['stat4PreparedHandoffResumeWindowFence']['preparedSlices']),
    'planner stat4 expression partial current source prepared handoff validation continuation cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialPreparedHandoffValidationContinuation', $planPreparedHandoffValidationContinuation()['cursorProgram'][array_key_last($planPreparedHandoffValidationContinuation()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source prepared handoff validation continuation cursor mode' => static fn (TestRunner $t) => $t->same('prepared-handoff-validation-continuation-current-source-stat4-expression-partial-prep', $planPreparedHandoffValidationContinuation()['cursorProgram'][array_key_last($planPreparedHandoffValidationContinuation()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source prepared handoff validation continuation detail' => static fn (TestRunner $t) => $t->contains('PREPARED HANDOFF VALIDATION CONTINUATION', $planPreparedHandoffValidationContinuation()['detail']),
    'planner stat4 expression partial current source prepared handoff validation continuation dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-prepared-handoff-validation-continuation', $planPreparedHandoffValidationContinuation()['dependencies'], true)),
    'planner stat4 expression partial current source prepared handoff validation continuation dependency closure' => static fn (TestRunner $t) => $t->contains('prepared handoff validation-continuation preparation extends', $planPreparedHandoffValidationContinuation()['dependency_closure']),
    'planner stat4 expression partial current source prepared handoff validation continuation non overlap' => static fn (TestRunner $t) => $t->contains('prepared handoff resume-window handoff windows', $planPreparedHandoffValidationContinuation()['non_overlap']),
    'planner stat4 expression partial current source prepared handoff validation continuation malformed needed column' => static function (TestRunner $t) use ($preparedPreparedHandoffValidationContinuation, $currentPreparedHandoffValidationContinuation, $termsPreparedHandoffValidationContinuation): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffValidationContinuation($preparedPreparedHandoffValidationContinuation(), $currentPreparedHandoffValidationContinuation(), $termsPreparedHandoffValidationContinuation(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source prepared handoff validation continuation repeated handoff proof ' . $case] = static function (TestRunner $t) use ($planPreparedHandoffValidationContinuation): void {
        $plan = $planPreparedHandoffValidationContinuation();
        $t->same($plan['stat4PreparedHandoffValidationContinuationFence']['handoffSignature'], $plan['selectedPlan']['preparedHandoffValidationContinuationHandoffSignature']);
    };
}

return $tests;
