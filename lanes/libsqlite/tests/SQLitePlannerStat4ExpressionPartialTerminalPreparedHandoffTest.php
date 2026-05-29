<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eqTerminalPreparedHandoff = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$likeTerminalPreparedHandoff = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNullTerminalPreparedHandoff = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$betweenTerminalPreparedHandoff = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rowsTerminalPreparedHandoff = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samplesTerminalPreparedHandoff = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payloadTerminalPreparedHandoff = static fn (array $row): array => [
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

$preparedTerminalPreparedHandoff = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-terminalPreparedHandoff',
    'schemaCookie' => 3920,
    'stat4Generation' => 398,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_terminalPreparedHandoff',
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

$currentTerminalPreparedHandoff = static function (?array $rows = null, ?array $samples = null) use ($preparedTerminalPreparedHandoff, $rowsTerminalPreparedHandoff, $samplesTerminalPreparedHandoff, $payloadTerminalPreparedHandoff): array {
    $source = $preparedTerminalPreparedHandoff();
    $source['name'] = 'current-wp-options-stat4-handoff-terminalPreparedHandoff';
    $source['schemaCookie'] = 4070;
    $source['stat4Generation'] = 966;
    $source['rows'] = $rows ?? $rowsTerminalPreparedHandoff();
    $source['indexes'][0]['rootPage'] = 40708;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samplesTerminalPreparedHandoff();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payloadTerminalPreparedHandoff, $source['rows']);

    return $source;
};

$termsTerminalPreparedHandoff = static fn (): array => [
    $betweenTerminalPreparedHandoff('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eqTerminalPreparedHandoff('autoload', 'yes'),
    $notNullTerminalPreparedHandoff('option_name'),
    $eqTerminalPreparedHandoff('blog_id', 1),
    $likeTerminalPreparedHandoff('option_name', 'plugin_%'),
];

$planTerminalPreparedHandoff = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeTerminalPreparedHandoff(
    $preparedTerminalPreparedHandoff(),
    $currentTerminalPreparedHandoff($rows, $samples),
    $termsTerminalPreparedHandoff(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source terminalPreparedHandoff status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-terminal-prepared-handoff-prepared', $planTerminalPreparedHandoff()['status']),
    'planner stat4 expression partial current source terminalPreparedHandoff inherits next942957' => static fn (TestRunner $t) => $t->same(true, $planTerminalPreparedHandoff()['selectedPlan']['next942957Prepared']),
    'planner stat4 expression partial current source terminalPreparedHandoff ready flag' => static fn (TestRunner $t) => $t->same(true, $planTerminalPreparedHandoff()['selectedPlan']['terminalPreparedHandoffPrepared']),
    'planner stat4 expression partial current source terminalPreparedHandoff prior ready' => static fn (TestRunner $t) => $t->same(true, $planTerminalPreparedHandoff()['stat4TerminalPreparedHandoffPreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source terminalPreparedHandoff slice range' => static fn (TestRunner $t) => $t->same([958, 973], $planTerminalPreparedHandoff()['stat4TerminalPreparedHandoffPreparationFence']['sliceRange']),
    'planner stat4 expression partial current source terminalPreparedHandoff prior range' => static fn (TestRunner $t) => $t->same([942, 957], $planTerminalPreparedHandoff()['stat4TerminalPreparedHandoffPreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source terminalPreparedHandoff prepared slices' => static fn (TestRunner $t) => $t->same(range(958, 973), $planTerminalPreparedHandoff()['selectedPlan']['terminalPreparedHandoffPreparedSlices']),
    'planner stat4 expression partial current source terminalPreparedHandoff blocked slices empty' => static fn (TestRunner $t) => $t->same([], $planTerminalPreparedHandoff()['selectedPlan']['terminalPreparedHandoffBlockedSlices']),
    'planner stat4 expression partial current source terminalPreparedHandoff first continues' => static fn (TestRunner $t) => $t->same(942, $planTerminalPreparedHandoff()['stat4TerminalPreparedHandoffPreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source terminalPreparedHandoff first rowid' => static fn (TestRunner $t) => $t->same(60, $planTerminalPreparedHandoff()['stat4TerminalPreparedHandoffPreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source terminalPreparedHandoff first projection matches' => static fn (TestRunner $t) => $t->same(true, $planTerminalPreparedHandoff()['stat4TerminalPreparedHandoffPreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source terminalPreparedHandoff signature length' => static fn (TestRunner $t) => $t->same(64, strlen($planTerminalPreparedHandoff()['stat4TerminalPreparedHandoffPreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source terminalPreparedHandoff selected signature' => static fn (TestRunner $t) => $t->same($planTerminalPreparedHandoff()['stat4TerminalPreparedHandoffPreparationFence']['handoffSignature'], $planTerminalPreparedHandoff()['selectedPlan']['terminalPreparedHandoffHandoffSignature']),
    'planner stat4 expression partial current source terminalPreparedHandoff stat4 signature' => static fn (TestRunner $t) => $t->same($planTerminalPreparedHandoff()['stat4TerminalPreparedHandoffPreparationFence']['handoffSignature'], $planTerminalPreparedHandoff()['stat4Fence']['terminalPreparedHandoffHandoffSignature']),
    'planner stat4 expression partial current source terminalPreparedHandoff prior signature threaded' => static fn (TestRunner $t) => $t->same($planTerminalPreparedHandoff()['stat4Next942957PreparationFence']['handoffSignature'], $planTerminalPreparedHandoff()['selectedPlan']['terminalPreparedHandoffPriorHandoffSignature']),
    'planner stat4 expression partial current source terminalPreparedHandoff preserves next942957 fence' => static fn (TestRunner $t) => $t->same(range(942, 957), $planTerminalPreparedHandoff()['stat4Next942957PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source terminalPreparedHandoff cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialTerminalPreparedHandoff', $planTerminalPreparedHandoff()['cursorProgram'][array_key_last($planTerminalPreparedHandoff()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source terminalPreparedHandoff cursor mode' => static fn (TestRunner $t) => $t->same('terminal-prepared-handoff-current-source-stat4-expression-partial-prep', $planTerminalPreparedHandoff()['cursorProgram'][array_key_last($planTerminalPreparedHandoff()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source terminalPreparedHandoff detail' => static fn (TestRunner $t) => $t->contains('TERMINAL PREPARED HANDOFF', $planTerminalPreparedHandoff()['detail']),
    'planner stat4 expression partial current source terminalPreparedHandoff dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-terminal-prepared-handoff-prep', $planTerminalPreparedHandoff()['dependencies'], true)),
    'planner stat4 expression partial current source terminalPreparedHandoff dependency closure' => static fn (TestRunner $t) => $t->contains('terminal prepared handoff preparation extends', $planTerminalPreparedHandoff()['dependency_closure']),
    'planner stat4 expression partial current source terminalPreparedHandoff non overlap' => static fn (TestRunner $t) => $t->contains('next942-957 handoff windows', $planTerminalPreparedHandoff()['non_overlap']),
    'planner stat4 expression partial current source terminalPreparedHandoff malformed needed column' => static function (TestRunner $t) use ($preparedTerminalPreparedHandoff, $currentTerminalPreparedHandoff, $termsTerminalPreparedHandoff): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeTerminalPreparedHandoff($preparedTerminalPreparedHandoff(), $currentTerminalPreparedHandoff(), $termsTerminalPreparedHandoff(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source terminalPreparedHandoff repeated handoff proof ' . $case] = static function (TestRunner $t) use ($planTerminalPreparedHandoff): void {
        $plan = $planTerminalPreparedHandoff();
        $t->same($plan['stat4TerminalPreparedHandoffPreparationFence']['handoffSignature'], $plan['selectedPlan']['terminalPreparedHandoffHandoffSignature']);
    };
}

return $tests;
