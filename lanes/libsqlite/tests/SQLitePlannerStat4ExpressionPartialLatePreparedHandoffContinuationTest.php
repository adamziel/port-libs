<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eqLatePreparedHandoffContinuation = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$likeLatePreparedHandoffContinuation = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNullLatePreparedHandoffContinuation = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$betweenLatePreparedHandoffContinuation = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rowsLatePreparedHandoffContinuation = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samplesLatePreparedHandoffContinuation = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payloadLatePreparedHandoffContinuation = static fn (array $row): array => [
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

$preparedLatePreparedHandoffContinuation = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-late prepared handoff continuation',
    'schemaCookie' => 3884,
    'stat4Generation' => 382,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_late prepared handoff continuation',
        'rootPage' => 38841,
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

$currentLatePreparedHandoffContinuation = static function (?array $rows = null, ?array $samples = null) use ($preparedLatePreparedHandoffContinuation, $rowsLatePreparedHandoffContinuation, $samplesLatePreparedHandoffContinuation, $payloadLatePreparedHandoffContinuation): array {
    $source = $preparedLatePreparedHandoffContinuation();
    $source['name'] = 'current-wp-options-stat4-handoff-late prepared handoff continuation';
    $source['schemaCookie'] = 4034;
    $source['stat4Generation'] = 950;
    $source['rows'] = $rows ?? $rowsLatePreparedHandoffContinuation();
    $source['indexes'][0]['rootPage'] = 40348;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samplesLatePreparedHandoffContinuation();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payloadLatePreparedHandoffContinuation, $source['rows']);

    return $source;
};

$termsLatePreparedHandoffContinuation = static fn (): array => [
    $betweenLatePreparedHandoffContinuation('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eqLatePreparedHandoffContinuation('autoload', 'yes'),
    $notNullLatePreparedHandoffContinuation('option_name'),
    $eqLatePreparedHandoffContinuation('blog_id', 1),
    $likeLatePreparedHandoffContinuation('option_name', 'plugin_%'),
];

$planLatePreparedHandoffContinuation = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeLatePreparedHandoffContinuation(
    $preparedLatePreparedHandoffContinuation(),
    $currentLatePreparedHandoffContinuation($rows, $samples),
    $termsLatePreparedHandoffContinuation(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source late prepared handoff continuation status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-late-prepared-handoff-continuation-prepared', $planLatePreparedHandoffContinuation()['status']),
    'planner stat4 expression partial current source late prepared handoff continuation inherits prepared handoff validation continuation' => static fn (TestRunner $t) => $t->same(true, $planLatePreparedHandoffContinuation()['selectedPlan']['preparedHandoffValidationContinuationReady']),
    'planner stat4 expression partial current source late prepared handoff continuation ready flag' => static fn (TestRunner $t) => $t->same(true, $planLatePreparedHandoffContinuation()['selectedPlan']['latePreparedHandoffContinuationReady']),
    'planner stat4 expression partial current source late prepared handoff continuation prior ready' => static fn (TestRunner $t) => $t->same(true, $planLatePreparedHandoffContinuation()['stat4LatePreparedHandoffContinuationFence']['previousFenceReady']),
    'planner stat4 expression partial current source late prepared handoff continuation slice range' => static fn (TestRunner $t) => $t->same([894, 909], $planLatePreparedHandoffContinuation()['stat4LatePreparedHandoffContinuationFence']['sliceRange']),
    'planner stat4 expression partial current source late prepared handoff continuation prior range' => static fn (TestRunner $t) => $t->same([878, 893], $planLatePreparedHandoffContinuation()['stat4LatePreparedHandoffContinuationFence']['priorSliceRange']),
    'planner stat4 expression partial current source late prepared handoff continuation prepared slices' => static fn (TestRunner $t) => $t->same(range(894, 909), $planLatePreparedHandoffContinuation()['selectedPlan']['latePreparedHandoffContinuationPreparedSlices']),
    'planner stat4 expression partial current source late prepared handoff continuation blocked slices empty' => static fn (TestRunner $t) => $t->same([], $planLatePreparedHandoffContinuation()['selectedPlan']['latePreparedHandoffContinuationBlockedSlices']),
    'planner stat4 expression partial current source late prepared handoff continuation first continues' => static fn (TestRunner $t) => $t->same(878, $planLatePreparedHandoffContinuation()['stat4LatePreparedHandoffContinuationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source late prepared handoff continuation first rowid' => static fn (TestRunner $t) => $t->same(60, $planLatePreparedHandoffContinuation()['stat4LatePreparedHandoffContinuationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source late prepared handoff continuation first projection matches' => static fn (TestRunner $t) => $t->same(true, $planLatePreparedHandoffContinuation()['stat4LatePreparedHandoffContinuationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source late prepared handoff continuation signature length' => static fn (TestRunner $t) => $t->same(64, strlen($planLatePreparedHandoffContinuation()['stat4LatePreparedHandoffContinuationFence']['handoffSignature'])),
    'planner stat4 expression partial current source late prepared handoff continuation selected signature' => static fn (TestRunner $t) => $t->same($planLatePreparedHandoffContinuation()['stat4LatePreparedHandoffContinuationFence']['handoffSignature'], $planLatePreparedHandoffContinuation()['selectedPlan']['latePreparedHandoffContinuationHandoffSignature']),
    'planner stat4 expression partial current source late prepared handoff continuation stat4 signature' => static fn (TestRunner $t) => $t->same($planLatePreparedHandoffContinuation()['stat4LatePreparedHandoffContinuationFence']['handoffSignature'], $planLatePreparedHandoffContinuation()['stat4Fence']['latePreparedHandoffContinuationHandoffSignature']),
    'planner stat4 expression partial current source late prepared handoff continuation prior signature threaded' => static fn (TestRunner $t) => $t->same($planLatePreparedHandoffContinuation()['stat4PreparedHandoffValidationContinuationFence']['handoffSignature'], $planLatePreparedHandoffContinuation()['selectedPlan']['latePreparedHandoffContinuationPriorHandoffSignature']),
    'planner stat4 expression partial current source late prepared handoff continuation preserves prepared handoff validation continuation fence' => static fn (TestRunner $t) => $t->same(range(878, 893), $planLatePreparedHandoffContinuation()['stat4PreparedHandoffValidationContinuationFence']['preparedSlices']),
    'planner stat4 expression partial current source late prepared handoff continuation cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialLatePreparedHandoffContinuation', $planLatePreparedHandoffContinuation()['cursorProgram'][array_key_last($planLatePreparedHandoffContinuation()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source late prepared handoff continuation cursor mode' => static fn (TestRunner $t) => $t->same('late-prepared-handoff-continuation-current-source-stat4-expression-partial-prep', $planLatePreparedHandoffContinuation()['cursorProgram'][array_key_last($planLatePreparedHandoffContinuation()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source late prepared handoff continuation detail' => static fn (TestRunner $t) => $t->contains('LATE PREPARED HANDOFF CONTINUATION', $planLatePreparedHandoffContinuation()['detail']),
    'planner stat4 expression partial current source late prepared handoff continuation dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-late-prepared-handoff-continuation', $planLatePreparedHandoffContinuation()['dependencies'], true)),
    'planner stat4 expression partial current source late prepared handoff continuation dependency closure' => static fn (TestRunner $t) => $t->contains('late prepared handoff continuation preparation extends', $planLatePreparedHandoffContinuation()['dependency_closure']),
    'planner stat4 expression partial current source late prepared handoff continuation non overlap' => static fn (TestRunner $t) => $t->contains('prepared handoff validation-continuation handoff windows', $planLatePreparedHandoffContinuation()['non_overlap']),
    'planner stat4 expression partial current source late prepared handoff continuation malformed needed column' => static function (TestRunner $t) use ($preparedLatePreparedHandoffContinuation, $currentLatePreparedHandoffContinuation, $termsLatePreparedHandoffContinuation): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeLatePreparedHandoffContinuation($preparedLatePreparedHandoffContinuation(), $currentLatePreparedHandoffContinuation(), $termsLatePreparedHandoffContinuation(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source late prepared handoff continuation repeated handoff proof ' . $case] = static function (TestRunner $t) use ($planLatePreparedHandoffContinuation): void {
        $plan = $planLatePreparedHandoffContinuation();
        $t->same($plan['stat4LatePreparedHandoffContinuationFence']['handoffSignature'], $plan['selectedPlan']['latePreparedHandoffContinuationHandoffSignature']);
    };
}

return $tests;
