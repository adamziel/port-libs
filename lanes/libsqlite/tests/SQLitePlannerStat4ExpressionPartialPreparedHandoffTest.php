<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eqPreparedHandoff = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$likePreparedHandoff = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNullPreparedHandoff = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$betweenPreparedHandoff = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rowsPreparedHandoff = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samplesPreparedHandoff = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payloadPreparedHandoff = static fn (array $row): array => [
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

$preparedHandoffSource = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff',
    'schemaCookie' => 3820,
    'stat4Generation' => 334,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff',
        'rootPage' => 38201,
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

$currentPreparedHandoffSource = static function (?array $rows = null, ?array $samples = null) use ($preparedHandoffSource, $rowsPreparedHandoff, $samplesPreparedHandoff, $payloadPreparedHandoff): array {
    $source = $preparedHandoffSource();
    $source['name'] = 'current-wp-options-stat4-handoff';
    $source['schemaCookie'] = 3970;
    $source['stat4Generation'] = 902;
    $source['rows'] = $rows ?? $rowsPreparedHandoff();
    $source['indexes'][0]['rootPage'] = 39708;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samplesPreparedHandoff();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payloadPreparedHandoff, $source['rows']);

    return $source;
};

$termsPreparedHandoff = static fn (): array => [
    $betweenPreparedHandoff('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eqPreparedHandoff('autoload', 'yes'),
    $notNullPreparedHandoff('option_name'),
    $eqPreparedHandoff('blog_id', 1),
    $likePreparedHandoff('option_name', 'plugin_%'),
];

$planPreparedHandoff = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4ExpressionPartialPreparedHandoff(
    $preparedHandoffSource(),
    $currentPreparedHandoffSource($rows, $samples),
    $termsPreparedHandoff(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial prepared handoff status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-prepared-handoff-ready', $planPreparedHandoff()['status']),
    'planner stat4 expression partial prepared handoff inherits prior bridge' => static fn (TestRunner $t) => $t->same(true, $planPreparedHandoff()['selectedPlan']['next542557Prepared']),
    'planner stat4 expression partial prepared handoff ready flag' => static fn (TestRunner $t) => $t->same(true, $planPreparedHandoff()['selectedPlan']['preparedHandoffReady']),
    'planner stat4 expression partial prepared handoff prior ready' => static fn (TestRunner $t) => $t->same(true, $planPreparedHandoff()['stat4PreparedHandoffFence']['previousFenceReady']),
    'planner stat4 expression partial prepared handoff slice range' => static fn (TestRunner $t) => $t->same([558, 573], $planPreparedHandoff()['stat4PreparedHandoffFence']['sliceRange']),
    'planner stat4 expression partial prepared handoff prior range' => static fn (TestRunner $t) => $t->same([542, 557], $planPreparedHandoff()['stat4PreparedHandoffFence']['priorSliceRange']),
    'planner stat4 expression partial prepared handoff prepared slices' => static fn (TestRunner $t) => $t->same(range(558, 573), $planPreparedHandoff()['selectedPlan']['preparedHandoffSlices']),
    'planner stat4 expression partial prepared handoff blocked slices empty' => static fn (TestRunner $t) => $t->same([], $planPreparedHandoff()['selectedPlan']['preparedHandoffBlockedSlices']),
    'planner stat4 expression partial prepared handoff first continues' => static fn (TestRunner $t) => $t->same(542, $planPreparedHandoff()['stat4PreparedHandoffFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial prepared handoff first rowid' => static fn (TestRunner $t) => $t->same(60, $planPreparedHandoff()['stat4PreparedHandoffFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial prepared handoff first projection matches' => static fn (TestRunner $t) => $t->same(true, $planPreparedHandoff()['stat4PreparedHandoffFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial prepared handoff signature length' => static fn (TestRunner $t) => $t->same(64, strlen($planPreparedHandoff()['stat4PreparedHandoffFence']['handoffSignature'])),
    'planner stat4 expression partial prepared handoff selected signature' => static fn (TestRunner $t) => $t->same($planPreparedHandoff()['stat4PreparedHandoffFence']['handoffSignature'], $planPreparedHandoff()['selectedPlan']['preparedHandoffSignature']),
    'planner stat4 expression partial prepared handoff stat4 signature' => static fn (TestRunner $t) => $t->same($planPreparedHandoff()['stat4PreparedHandoffFence']['handoffSignature'], $planPreparedHandoff()['stat4Fence']['preparedHandoffSignature']),
    'planner stat4 expression partial prepared handoff prior signature threaded' => static fn (TestRunner $t) => $t->same($planPreparedHandoff()['stat4Next542557PreparationFence']['handoffSignature'], $planPreparedHandoff()['selectedPlan']['preparedHandoffPriorSignature']),
    'planner stat4 expression partial prepared handoff preserves prior fence' => static fn (TestRunner $t) => $t->same(range(542, 557), $planPreparedHandoff()['stat4Next542557PreparationFence']['preparedSlices']),
    'planner stat4 expression partial prepared handoff cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialPreparedHandoff', $planPreparedHandoff()['cursorProgram'][array_key_last($planPreparedHandoff()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial prepared handoff cursor mode' => static fn (TestRunner $t) => $t->same('current-source-stat4-expression-partial-prepared-handoff', $planPreparedHandoff()['cursorProgram'][array_key_last($planPreparedHandoff()['cursorProgram'])]['mode']),
    'planner stat4 expression partial prepared handoff detail' => static fn (TestRunner $t) => $t->contains('PREPARED HANDOFF', $planPreparedHandoff()['detail']),
    'planner stat4 expression partial prepared handoff dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-prepared-handoff', $planPreparedHandoff()['dependencies'], true)),
    'planner stat4 expression partial prepared handoff dependency closure' => static fn (TestRunner $t) => $t->contains('prepared handoff extends', $planPreparedHandoff()['dependency_closure']),
    'planner stat4 expression partial prepared handoff non overlap' => static fn (TestRunner $t) => $t->contains('descriptive current-source handoff', $planPreparedHandoff()['non_overlap']),
    'planner stat4 expression partial prepared handoff malformed needed column' => static function (TestRunner $t) use ($preparedHandoffSource, $currentPreparedHandoffSource, $termsPreparedHandoff): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4ExpressionPartialPreparedHandoff($preparedHandoffSource(), $currentPreparedHandoffSource(), $termsPreparedHandoff(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial prepared handoff repeated proof ' . $case] = static function (TestRunner $t) use ($planPreparedHandoff): void {
        $plan = $planPreparedHandoff();
        $t->same($plan['stat4PreparedHandoffFence']['handoffSignature'], $plan['selectedPlan']['preparedHandoffSignature']);
    };
}

return $tests;
