<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eqPreparedContinuationBase = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$likePreparedContinuationBase = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNullPreparedContinuationBase = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$betweenPreparedContinuationBase = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rowsPreparedContinuationBase = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samplesPreparedContinuationBase = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payloadPreparedContinuationBase = static fn (array $row): array => [
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

$preparedPreparedContinuationBase = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-continuation-base',
    'schemaCookie' => 3852,
    'stat4Generation' => 366,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_continuation_base',
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

$currentPreparedContinuationBase = static function (?array $rows = null, ?array $samples = null) use ($preparedPreparedContinuationBase, $rowsPreparedContinuationBase, $samplesPreparedContinuationBase, $payloadPreparedContinuationBase): array {
    $source = $preparedPreparedContinuationBase();
    $source['name'] = 'current-wp-options-stat4-handoff-continuation-base';
    $source['schemaCookie'] = 4002;
    $source['stat4Generation'] = 934;
    $source['rows'] = $rows ?? $rowsPreparedContinuationBase();
    $source['indexes'][0]['rootPage'] = 40028;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samplesPreparedContinuationBase();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payloadPreparedContinuationBase, $source['rows']);

    return $source;
};

$termsPreparedContinuationBase = static fn (): array => [
    $betweenPreparedContinuationBase('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eqPreparedContinuationBase('autoload', 'yes'),
    $notNullPreparedContinuationBase('option_name'),
    $eqPreparedContinuationBase('blog_id', 1),
    $likePreparedContinuationBase('option_name', 'plugin_%'),
];

$planPreparedContinuationBase = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedContinuationBase(
    $preparedPreparedContinuationBase(),
    $currentPreparedContinuationBase($rows, $samples),
    $termsPreparedContinuationBase(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial prepared continuation base status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-prepared-continuation-base-prepared', $planPreparedContinuationBase()['status']),
    'planner stat4 expression partial prepared continuation base inherits next590605' => static fn (TestRunner $t) => $t->same(true, $planPreparedContinuationBase()['selectedPlan']['next590605Prepared']),
    'planner stat4 expression partial prepared continuation base ready flag' => static fn (TestRunner $t) => $t->same(true, $planPreparedContinuationBase()['selectedPlan']['preparedContinuationBasePrepared']),
    'planner stat4 expression partial prepared continuation base prior ready' => static fn (TestRunner $t) => $t->same(true, $planPreparedContinuationBase()['stat4PreparedContinuationBasePreparationFence']['previousFenceReady']),
    'planner stat4 expression partial prepared continuation base slice range' => static fn (TestRunner $t) => $t->same([606, 621], $planPreparedContinuationBase()['stat4PreparedContinuationBasePreparationFence']['sliceRange']),
    'planner stat4 expression partial prepared continuation base prior range' => static fn (TestRunner $t) => $t->same([590, 605], $planPreparedContinuationBase()['stat4PreparedContinuationBasePreparationFence']['priorSliceRange']),
    'planner stat4 expression partial prepared continuation base prepared slices' => static fn (TestRunner $t) => $t->same(range(606, 621), $planPreparedContinuationBase()['selectedPlan']['preparedContinuationBasePreparedSlices']),
    'planner stat4 expression partial prepared continuation base blocked slices empty' => static fn (TestRunner $t) => $t->same([], $planPreparedContinuationBase()['selectedPlan']['preparedContinuationBaseBlockedSlices']),
    'planner stat4 expression partial prepared continuation base first continues' => static fn (TestRunner $t) => $t->same(590, $planPreparedContinuationBase()['stat4PreparedContinuationBasePreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial prepared continuation base first rowid' => static fn (TestRunner $t) => $t->same(60, $planPreparedContinuationBase()['stat4PreparedContinuationBasePreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial prepared continuation base first projection matches' => static fn (TestRunner $t) => $t->same(true, $planPreparedContinuationBase()['stat4PreparedContinuationBasePreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial prepared continuation base signature length' => static fn (TestRunner $t) => $t->same(64, strlen($planPreparedContinuationBase()['stat4PreparedContinuationBasePreparationFence']['handoffSignature'])),
    'planner stat4 expression partial prepared continuation base selected signature' => static fn (TestRunner $t) => $t->same($planPreparedContinuationBase()['stat4PreparedContinuationBasePreparationFence']['handoffSignature'], $planPreparedContinuationBase()['selectedPlan']['preparedContinuationBaseHandoffSignature']),
    'planner stat4 expression partial prepared continuation base stat4 signature' => static fn (TestRunner $t) => $t->same($planPreparedContinuationBase()['stat4PreparedContinuationBasePreparationFence']['handoffSignature'], $planPreparedContinuationBase()['stat4Fence']['preparedContinuationBaseHandoffSignature']),
    'planner stat4 expression partial prepared continuation base prior signature threaded' => static fn (TestRunner $t) => $t->same($planPreparedContinuationBase()['stat4Next590605PreparationFence']['handoffSignature'], $planPreparedContinuationBase()['selectedPlan']['preparedContinuationBasePriorHandoffSignature']),
    'planner stat4 expression partial prepared continuation base preserves next590605 fence' => static fn (TestRunner $t) => $t->same(range(590, 605), $planPreparedContinuationBase()['stat4Next590605PreparationFence']['preparedSlices']),
    'planner stat4 expression partial prepared continuation base cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialPreparedContinuationBase', $planPreparedContinuationBase()['cursorProgram'][array_key_last($planPreparedContinuationBase()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial prepared continuation base cursor mode' => static fn (TestRunner $t) => $t->same('prepared-continuation-base-current-source-stat4-expression-partial-prep', $planPreparedContinuationBase()['cursorProgram'][array_key_last($planPreparedContinuationBase()['cursorProgram'])]['mode']),
    'planner stat4 expression partial prepared continuation base detail' => static fn (TestRunner $t) => $t->contains('PREPARED CONTINUATION BASE', $planPreparedContinuationBase()['detail']),
    'planner stat4 expression partial prepared continuation base dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-prepared-continuation-base-prep', $planPreparedContinuationBase()['dependencies'], true)),
    'planner stat4 expression partial prepared continuation base dependency closure' => static fn (TestRunner $t) => $t->contains('prepared continuation base extends', $planPreparedContinuationBase()['dependency_closure']),
    'planner stat4 expression partial prepared continuation base non overlap' => static fn (TestRunner $t) => $t->contains('prior handoff windows', $planPreparedContinuationBase()['non_overlap']),
    'planner stat4 expression partial prepared continuation base malformed needed column' => static function (TestRunner $t) use ($preparedPreparedContinuationBase, $currentPreparedContinuationBase, $termsPreparedContinuationBase): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedContinuationBase($preparedPreparedContinuationBase(), $currentPreparedContinuationBase(), $termsPreparedContinuationBase(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial prepared continuation base repeated handoff proof ' . $case] = static function (TestRunner $t) use ($planPreparedContinuationBase): void {
        $plan = $planPreparedContinuationBase();
        $t->same($plan['stat4PreparedContinuationBasePreparationFence']['handoffSignature'], $plan['selectedPlan']['preparedContinuationBaseHandoffSignature']);
    };
}

return $tests;
