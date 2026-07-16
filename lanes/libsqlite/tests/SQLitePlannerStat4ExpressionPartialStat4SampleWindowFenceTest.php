<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eqStat4SampleWindow = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$likeStat4SampleWindow = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNullStat4SampleWindow = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$betweenStat4SampleWindow = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$preparedStat4SampleWindow = static fn (): array => [
    'name' => 'prepared-wp-options-sample-window-stat4-sample-window',
    'schemaCookie' => 2210,
    'stat4Generation' => 221,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_sample_window_stat4_stat4-sample-window',
        'rootPage' => 22101,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'descending' => true,
        'partialPredicateTerms' => [
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_alpha'],
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<=', 'right' => 'plugin_zulu'],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ],
        'partialGroupedOrPredicateArms' => [
            [
                ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ],
            [
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'critical'],
            ],
        ],
        'partialGroupedLikePredicateArms' => [
            [
                ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'plugin_%'],
            ],
            [
                ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'network_%'],
            ],
        ],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
        ],
    ]],
];

$currentStat4SampleWindow = static function (array $overrides = []) use ($preparedStat4SampleWindow): array {
    $source = $preparedStat4SampleWindow();
    $source['name'] = 'current-wp-options-sample-window-stat4-sample-window';
    $source['schemaCookie'] = 2218;
    $source['stat4Generation'] = 281;
    $source['indexes'][0]['rootPage'] = 22188;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
        ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 40]],
        ['neq' => '3 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 20]],
        ['neq' => '1 1', 'nlt' => '5 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 50]],
        ['neq' => '1 1', 'nlt' => '6 4', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 30]],
        ['neq' => '1 1', 'nlt' => '7 5', 'ndlt' => '5 5', 'sample' => ['plugin_zulu', 60]],
    ];
    $source['rows'] = [
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
        ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
        ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
        ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache', 'updated_at' => 40],
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 10],
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
        ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
    ];
    foreach ($overrides as $key => $value) {
        $source[$key] = $value;
    }

    return $source;
};

$termsStat4SampleWindow = static fn (): array => [
    $betweenStat4SampleWindow('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eqStat4SampleWindow('autoload', 'yes'),
    $notNullStat4SampleWindow('option_name'),
    $eqStat4SampleWindow('blog_id', 1),
    $likeStat4SampleWindow('option_name', 'plugin_%'),
];
$planStat4SampleWindow = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4SampleWindowFence(
    $prepared ?? $preparedStat4SampleWindow(),
    $current ?? $currentStat4SampleWindow(),
    $terms ?? $termsStat4SampleWindow(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$uncoveredStat4SampleWindow = static function () use ($currentStat4SampleWindow, $planStat4SampleWindow): array {
    $current = $currentStat4SampleWindow();
    $current['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20]],
        ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50]],
        ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
    ];

    return $planStat4SampleWindow(5, 1, null, $current);
};

$tests = [
    'planner stat4 expression partial current source stat4-sample-window status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-sample-window-ready', $planStat4SampleWindow()['status']),
    'planner stat4 expression partial current source stat4-sample-window selected current' => static fn (TestRunner $t) => $t->same('current', $planStat4SampleWindow()['selectedSource']),
    'planner stat4 expression partial current source stat4-sample-window inherited next217' => static fn (TestRunner $t) => $t->same(true, $planStat4SampleWindow()['selectedPlan']['next217Ready']),
    'planner stat4 expression partial current source stat4-sample-window ready flag' => static fn (TestRunner $t) => $t->same(true, $planStat4SampleWindow()['selectedPlan']['stat4SampleWindowReady']),
    'planner stat4 expression partial current source stat4-sample-window selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_sample_window_stat4_stat4-sample-window', $planStat4SampleWindow()['selectedPlan']['name']),
    'planner stat4 expression partial current source stat4-sample-window root page' => static fn (TestRunner $t) => $t->same(22188, $planStat4SampleWindow()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source stat4-sample-window page rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $planStat4SampleWindow()['stat4YieldFence']['pageRowids']),
    'planner stat4 expression partial current source stat4-sample-window lookahead rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22, 40], $planStat4SampleWindow()['stat4YieldFence']['lookaheadRowids']),
    'planner stat4 expression partial current source stat4-sample-window sample keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo', 'plugin_zulu'], $planStat4SampleWindow()['stat4SampleWindowFence']['currentStat4SampleKeys']),
    'planner stat4 expression partial current source stat4-sample-window sample positions' => static fn (TestRunner $t) => $t->same([4, 3, 2, 2, 2, 1], $planStat4SampleWindow()['stat4SampleWindowFence']['samplePositions']),
    'planner stat4 expression partial current source stat4-sample-window selected sample positions' => static fn (TestRunner $t) => $t->same([4, 3, 2, 2, 2, 1], $planStat4SampleWindow()['selectedPlan']['stat4SampleWindowPositions']),
    'planner stat4 expression partial current source stat4-sample-window all covered' => static fn (TestRunner $t) => $t->same(true, $planStat4SampleWindow()['stat4SampleWindowFence']['allRowsCoveredByCurrentStat4Samples']),
    'planner stat4 expression partial current source stat4-sample-window descending positions' => static fn (TestRunner $t) => $t->same(true, $planStat4SampleWindow()['stat4SampleWindowFence']['samplePositionsPreserveDescendingScan']),
    'planner stat4 expression partial current source stat4-sample-window no rejected samples' => static fn (TestRunner $t) => $t->same([], $planStat4SampleWindow()['stat4SampleWindowFence']['rowidsRejectedByCurrentStat4Samples']),
    'planner stat4 expression partial current source stat4-sample-window no rejected order' => static fn (TestRunner $t) => $t->same([], $planStat4SampleWindow()['stat4SampleWindowFence']['rowidsRejectedByDescendingSampleOrder']),
    'planner stat4 expression partial current source stat4-sample-window proof count' => static fn (TestRunner $t) => $t->same(6, count($planStat4SampleWindow()['stat4SampleWindowFence']['rowProofs'])),
    'planner stat4 expression partial current source stat4-sample-window first lower sample' => static fn (TestRunner $t) => $t->same('plugin_seo', $planStat4SampleWindow()['stat4SampleWindowFence']['rowProofs'][0]['lowerSampleKey']),
    'planner stat4 expression partial current source stat4-sample-window first upper sample' => static fn (TestRunner $t) => $t->same('plugin_zulu', $planStat4SampleWindow()['stat4SampleWindowFence']['rowProofs'][0]['upperSampleKey']),
    'planner stat4 expression partial current source stat4-sample-window lookahead lower sample' => static fn (TestRunner $t) => $t->same('plugin_cache', $planStat4SampleWindow()['stat4SampleWindowFence']['rowProofs'][5]['lowerSampleKey']),
    'planner stat4 expression partial current source stat4-sample-window lookahead upper sample' => static fn (TestRunner $t) => $t->same('plugin_forms', $planStat4SampleWindow()['stat4SampleWindowFence']['rowProofs'][5]['upperSampleKey']),
    'planner stat4 expression partial current source stat4-sample-window row proof keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_forms', 'plugin_forms', 'plugin_cache'], array_column($planStat4SampleWindow()['stat4SampleWindowFence']['rowProofs'], 'expressionKey')),
    'planner stat4 expression partial current source stat4-sample-window row proof rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22, 40], array_column($planStat4SampleWindow()['stat4SampleWindowFence']['rowProofs'], 'rowid')),
    'planner stat4 expression partial current source stat4-sample-window covered flags' => static fn (TestRunner $t) => $t->same([true, true, true, true, true, true], array_column($planStat4SampleWindow()['stat4SampleWindowFence']['rowProofs'], 'coveredByCurrentStat4Samples')),
    'planner stat4 expression partial current source stat4-sample-window descending flags' => static fn (TestRunner $t) => $t->same([true, true, true, true, true, true], array_column($planStat4SampleWindow()['stat4SampleWindowFence']['rowProofs'], 'descendingScanPositionOk')),
    'planner stat4 expression partial current source stat4-sample-window signatures length' => static fn (TestRunner $t) => $t->same([64, 64], [strlen($planStat4SampleWindow()['stat4SampleWindowFence']['currentStat4SampleSignature']), strlen($planStat4SampleWindow()['stat4SampleWindowFence']['proofSignature'])]),
    'planner stat4 expression partial current source stat4-sample-window selected sample signature' => static fn (TestRunner $t) => $t->same($planStat4SampleWindow()['stat4SampleWindowFence']['currentStat4SampleSignature'], $planStat4SampleWindow()['selectedPlan']['stat4SampleWindowCurrentStat4SampleSignature']),
    'planner stat4 expression partial current source stat4-sample-window selected proof signature' => static fn (TestRunner $t) => $t->same($planStat4SampleWindow()['stat4SampleWindowFence']['proofSignature'], $planStat4SampleWindow()['selectedPlan']['stat4SampleWindowProofSignature']),
    'planner stat4 expression partial current source stat4-sample-window stat4 sample signature' => static fn (TestRunner $t) => $t->same($planStat4SampleWindow()['stat4SampleWindowFence']['currentStat4SampleSignature'], $planStat4SampleWindow()['stat4Fence']['stat4SampleWindowCurrentStat4SampleSignature']),
    'planner stat4 expression partial current source stat4-sample-window stat4 proof signature' => static fn (TestRunner $t) => $t->same($planStat4SampleWindow()['stat4SampleWindowFence']['proofSignature'], $planStat4SampleWindow()['stat4Fence']['stat4SampleWindowProofSignature']),
    'planner stat4 expression partial current source stat4-sample-window cursor appended' => static fn (TestRunner $t) => $t->same('RecheckStat4SampleWindowYield', $planStat4SampleWindow()['cursorProgram'][array_key_last($planStat4SampleWindow()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source stat4-sample-window cursor mode' => static fn (TestRunner $t) => $t->same('current-source-stat4-expression-partial-sample-window', $planStat4SampleWindow()['cursorProgram'][array_key_last($planStat4SampleWindow()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source stat4-sample-window cursor positions' => static fn (TestRunner $t) => $t->same([4, 3, 2, 2, 2, 1], $planStat4SampleWindow()['cursorProgram'][array_key_last($planStat4SampleWindow()['cursorProgram'])]['samplePositions']),
    'planner stat4 expression partial current source stat4-sample-window cursor signature' => static fn (TestRunner $t) => $t->same($planStat4SampleWindow()['stat4SampleWindowFence']['proofSignature'], $planStat4SampleWindow()['cursorProgram'][array_key_last($planStat4SampleWindow()['cursorProgram'])]['signature']),
    'planner stat4 expression partial current source stat4-sample-window current yield preserved' => static fn (TestRunner $t) => $t->same(true, $planStat4SampleWindow()['stat4YieldFence']['currentPageMatchesLookaheadPrefix']),
    'planner stat4 expression partial current source stat4-sample-window next lookahead preserved' => static fn (TestRunner $t) => $t->same(40, $planStat4SampleWindow()['stat4YieldFence']['nextRowid']),
    'planner stat4 expression partial current source stat4-sample-window grouped like preserved' => static fn (TestRunner $t) => $t->same(true, $planStat4SampleWindow()['groupedLikePredicateFence']['currentGroupedLikePredicateImplied']),
    'planner stat4 expression partial current source stat4-sample-window grouped or preserved' => static fn (TestRunner $t) => $t->same(true, $planStat4SampleWindow()['groupedPartialOrPredicateFence']['currentGroupedPartialOrPredicateImplied']),
    'planner stat4 expression partial current source stat4-sample-window partial fence preserved' => static fn (TestRunner $t) => $t->same(true, $planStat4SampleWindow()['partialPredicateFence']['currentPartialPredicateImplied']),
    'planner stat4 expression partial current source stat4-sample-window payload fence preserved' => static fn (TestRunner $t) => $t->same(true, $planStat4SampleWindow()['payloadExpressionFence']['allPayloadExpressionKeysMatch']),
    'planner stat4 expression partial current source stat4-sample-window dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-sample-window', $planStat4SampleWindow()['dependencies'], true)),
    'planner stat4 expression partial current source stat4-sample-window dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $planStat4SampleWindow()['dependency_closure']),
    'planner stat4 expression partial current source stat4-sample-window non overlap' => static fn (TestRunner $t) => $t->contains('current-source STAT4 samples', $planStat4SampleWindow()['non_overlap']),
    'planner stat4 expression partial current source stat4-sample-window detail' => static fn (TestRunner $t) => $t->contains('SAMPLE WINDOW FENCE', $planStat4SampleWindow()['detail']),
    'planner stat4 expression partial current source stat4-sample-window uncovered blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-sample-window-reprepare', $uncoveredStat4SampleWindow()['status']),
    'planner stat4 expression partial current source stat4-sample-window uncovered ready false' => static fn (TestRunner $t) => $t->same(false, $uncoveredStat4SampleWindow()['selectedPlan']['stat4SampleWindowReady']),
    'planner stat4 expression partial current source stat4-sample-window uncovered rejected rowid' => static fn (TestRunner $t) => $t->same([40], $uncoveredStat4SampleWindow()['stat4SampleWindowFence']['rowidsRejectedByCurrentStat4Samples']),
    'planner stat4 expression partial current source stat4-sample-window uncovered no cursor append' => static fn (TestRunner $t) => $t->same(false, in_array('RecheckStat4SampleWindowYield', array_column($uncoveredStat4SampleWindow()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source stat4-sample-window invalid negative limit' => static function (TestRunner $t) use ($planStat4SampleWindow): void {
        $t->throws(InvalidArgumentException::class, static fn () => $planStat4SampleWindow(-1, 0));
    },
    'planner stat4 expression partial current source stat4-sample-window invalid negative offset' => static function (TestRunner $t) use ($planStat4SampleWindow): void {
        $t->throws(InvalidArgumentException::class, static fn () => $planStat4SampleWindow(1, -1));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source stat4-sample-window repeated sample window ' . $case] = static function (TestRunner $t) use ($planStat4SampleWindow, $case): void {
        $plan = $planStat4SampleWindow(1 + ($case % 5), $case % 4);
        $t->same($plan['stat4SampleWindowFence']['proofSignature'], $plan['selectedPlan']['stat4SampleWindowProofSignature']);
    };
}

return $tests;
