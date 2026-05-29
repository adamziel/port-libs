<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq221 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like221 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull221 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between221 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared221 = static fn (): array => [
    'name' => 'prepared-wp-options-sample-window-next221',
    'schemaCookie' => 2210,
    'stat4Generation' => 221,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_sample_window_stat4_next221',
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

$current221 = static function (array $overrides = []) use ($prepared221): array {
    $source = $prepared221();
    $source['name'] = 'current-wp-options-sample-window-next221';
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

$terms221 = static fn (): array => [
    $between221('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq221('autoload', 'yes'),
    $notNull221('option_name'),
    $eq221('blog_id', 1),
    $like221('option_name', 'plugin_%'),
];
$plan221 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext221(
    $prepared ?? $prepared221(),
    $current ?? $current221(),
    $terms ?? $terms221(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$uncovered221 = static function () use ($current221, $plan221): array {
    $current = $current221();
    $current['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20]],
        ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50]],
        ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
    ];

    return $plan221(5, 1, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next221 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next221-ready', $plan221()['status']),
    'planner stat4 expression partial current source next221 selected current' => static fn (TestRunner $t) => $t->same('current', $plan221()['selectedSource']),
    'planner stat4 expression partial current source next221 inherited next217' => static fn (TestRunner $t) => $t->same(true, $plan221()['selectedPlan']['next217Ready']),
    'planner stat4 expression partial current source next221 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan221()['selectedPlan']['next221Ready']),
    'planner stat4 expression partial current source next221 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_sample_window_stat4_next221', $plan221()['selectedPlan']['name']),
    'planner stat4 expression partial current source next221 root page' => static fn (TestRunner $t) => $t->same(22188, $plan221()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next221 page rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan221()['stat4YieldFence']['pageRowids']),
    'planner stat4 expression partial current source next221 lookahead rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22, 40], $plan221()['stat4YieldFence']['lookaheadRowids']),
    'planner stat4 expression partial current source next221 sample keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo', 'plugin_zulu'], $plan221()['stat4SampleWindowFence']['currentStat4SampleKeys']),
    'planner stat4 expression partial current source next221 sample positions' => static fn (TestRunner $t) => $t->same([4, 3, 2, 2, 2, 1], $plan221()['stat4SampleWindowFence']['samplePositions']),
    'planner stat4 expression partial current source next221 selected sample positions' => static fn (TestRunner $t) => $t->same([4, 3, 2, 2, 2, 1], $plan221()['selectedPlan']['next221SamplePositions']),
    'planner stat4 expression partial current source next221 all covered' => static fn (TestRunner $t) => $t->same(true, $plan221()['stat4SampleWindowFence']['allRowsCoveredByCurrentStat4Samples']),
    'planner stat4 expression partial current source next221 descending positions' => static fn (TestRunner $t) => $t->same(true, $plan221()['stat4SampleWindowFence']['samplePositionsPreserveDescendingScan']),
    'planner stat4 expression partial current source next221 no rejected samples' => static fn (TestRunner $t) => $t->same([], $plan221()['stat4SampleWindowFence']['rowidsRejectedByCurrentStat4Samples']),
    'planner stat4 expression partial current source next221 no rejected order' => static fn (TestRunner $t) => $t->same([], $plan221()['stat4SampleWindowFence']['rowidsRejectedByDescendingSampleOrder']),
    'planner stat4 expression partial current source next221 proof count' => static fn (TestRunner $t) => $t->same(6, count($plan221()['stat4SampleWindowFence']['rowProofs'])),
    'planner stat4 expression partial current source next221 first lower sample' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan221()['stat4SampleWindowFence']['rowProofs'][0]['lowerSampleKey']),
    'planner stat4 expression partial current source next221 first upper sample' => static fn (TestRunner $t) => $t->same('plugin_zulu', $plan221()['stat4SampleWindowFence']['rowProofs'][0]['upperSampleKey']),
    'planner stat4 expression partial current source next221 lookahead lower sample' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan221()['stat4SampleWindowFence']['rowProofs'][5]['lowerSampleKey']),
    'planner stat4 expression partial current source next221 lookahead upper sample' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan221()['stat4SampleWindowFence']['rowProofs'][5]['upperSampleKey']),
    'planner stat4 expression partial current source next221 row proof keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_forms', 'plugin_forms', 'plugin_cache'], array_column($plan221()['stat4SampleWindowFence']['rowProofs'], 'expressionKey')),
    'planner stat4 expression partial current source next221 row proof rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22, 40], array_column($plan221()['stat4SampleWindowFence']['rowProofs'], 'rowid')),
    'planner stat4 expression partial current source next221 covered flags' => static fn (TestRunner $t) => $t->same([true, true, true, true, true, true], array_column($plan221()['stat4SampleWindowFence']['rowProofs'], 'coveredByCurrentStat4Samples')),
    'planner stat4 expression partial current source next221 descending flags' => static fn (TestRunner $t) => $t->same([true, true, true, true, true, true], array_column($plan221()['stat4SampleWindowFence']['rowProofs'], 'descendingScanPositionOk')),
    'planner stat4 expression partial current source next221 signatures length' => static fn (TestRunner $t) => $t->same([64, 64], [strlen($plan221()['stat4SampleWindowFence']['currentStat4SampleSignature']), strlen($plan221()['stat4SampleWindowFence']['proofSignature'])]),
    'planner stat4 expression partial current source next221 selected sample signature' => static fn (TestRunner $t) => $t->same($plan221()['stat4SampleWindowFence']['currentStat4SampleSignature'], $plan221()['selectedPlan']['next221CurrentStat4SampleSignature']),
    'planner stat4 expression partial current source next221 selected proof signature' => static fn (TestRunner $t) => $t->same($plan221()['stat4SampleWindowFence']['proofSignature'], $plan221()['selectedPlan']['next221ProofSignature']),
    'planner stat4 expression partial current source next221 stat4 sample signature' => static fn (TestRunner $t) => $t->same($plan221()['stat4SampleWindowFence']['currentStat4SampleSignature'], $plan221()['stat4Fence']['next221CurrentStat4SampleSignature']),
    'planner stat4 expression partial current source next221 stat4 proof signature' => static fn (TestRunner $t) => $t->same($plan221()['stat4SampleWindowFence']['proofSignature'], $plan221()['stat4Fence']['next221SampleWindowProofSignature']),
    'planner stat4 expression partial current source next221 cursor appended' => static fn (TestRunner $t) => $t->same('RecheckStat4SampleWindowYield', $plan221()['cursorProgram'][array_key_last($plan221()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next221 cursor mode' => static fn (TestRunner $t) => $t->same('next221-current-source-stat4-expression-partial-sample-window', $plan221()['cursorProgram'][array_key_last($plan221()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next221 cursor positions' => static fn (TestRunner $t) => $t->same([4, 3, 2, 2, 2, 1], $plan221()['cursorProgram'][array_key_last($plan221()['cursorProgram'])]['samplePositions']),
    'planner stat4 expression partial current source next221 cursor signature' => static fn (TestRunner $t) => $t->same($plan221()['stat4SampleWindowFence']['proofSignature'], $plan221()['cursorProgram'][array_key_last($plan221()['cursorProgram'])]['signature']),
    'planner stat4 expression partial current source next221 current yield preserved' => static fn (TestRunner $t) => $t->same(true, $plan221()['stat4YieldFence']['currentPageMatchesLookaheadPrefix']),
    'planner stat4 expression partial current source next221 next lookahead preserved' => static fn (TestRunner $t) => $t->same(40, $plan221()['stat4YieldFence']['nextRowid']),
    'planner stat4 expression partial current source next221 grouped like preserved' => static fn (TestRunner $t) => $t->same(true, $plan221()['groupedLikePredicateFence']['currentGroupedLikePredicateImplied']),
    'planner stat4 expression partial current source next221 grouped or preserved' => static fn (TestRunner $t) => $t->same(true, $plan221()['groupedPartialOrPredicateFence']['currentGroupedPartialOrPredicateImplied']),
    'planner stat4 expression partial current source next221 partial fence preserved' => static fn (TestRunner $t) => $t->same(true, $plan221()['partialPredicateFence']['currentPartialPredicateImplied']),
    'planner stat4 expression partial current source next221 payload fence preserved' => static fn (TestRunner $t) => $t->same(true, $plan221()['payloadExpressionFence']['allPayloadExpressionKeysMatch']),
    'planner stat4 expression partial current source next221 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next221', $plan221()['dependencies'], true)),
    'planner stat4 expression partial current source next221 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan221()['dependency_closure']),
    'planner stat4 expression partial current source next221 non overlap' => static fn (TestRunner $t) => $t->contains('current-source STAT4 samples', $plan221()['non_overlap']),
    'planner stat4 expression partial current source next221 detail' => static fn (TestRunner $t) => $t->contains('NEXT221 SAMPLE WINDOW FENCE', $plan221()['detail']),
    'planner stat4 expression partial current source next221 uncovered blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-sample-window-reprepare', $uncovered221()['status']),
    'planner stat4 expression partial current source next221 uncovered ready false' => static fn (TestRunner $t) => $t->same(false, $uncovered221()['selectedPlan']['next221Ready']),
    'planner stat4 expression partial current source next221 uncovered rejected rowid' => static fn (TestRunner $t) => $t->same([40], $uncovered221()['stat4SampleWindowFence']['rowidsRejectedByCurrentStat4Samples']),
    'planner stat4 expression partial current source next221 uncovered no cursor append' => static fn (TestRunner $t) => $t->same(false, in_array('RecheckStat4SampleWindowYield', array_column($uncovered221()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next221 invalid negative limit' => static function (TestRunner $t) use ($plan221): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan221(-1, 0));
    },
    'planner stat4 expression partial current source next221 invalid negative offset' => static function (TestRunner $t) use ($plan221): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan221(1, -1));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next221 repeated sample window ' . $case] = static function (TestRunner $t) use ($plan221, $case): void {
        $plan = $plan221(1 + ($case % 5), $case % 4);
        $t->same($plan['stat4SampleWindowFence']['proofSignature'], $plan['selectedPlan']['next221ProofSignature']);
    };
}

return $tests;
