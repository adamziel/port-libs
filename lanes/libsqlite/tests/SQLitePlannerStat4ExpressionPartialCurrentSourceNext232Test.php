<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq232 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like232 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull232 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between232 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$payload232 = static fn (array $row): array => [
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

$prepared232 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-partial-counters-next232',
    'schemaCookie' => 2320,
    'stat4Generation' => 232,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_stat4_partial_counters_next232',
        'rootPage' => 23201,
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
        'stat4ExpressionPayloads' => [],
    ]],
];

$current232 = static function () use ($prepared232, $payload232): array {
    $source = $prepared232();
    $source['name'] = 'current-wp-options-stat4-partial-counters-next232';
    $source['schemaCookie'] = 2329;
    $source['stat4Generation'] = 332;
    $source['indexes'][0]['rootPage'] = 23288;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
        ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 40]],
        ['neq' => '4 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 20]],
        ['neq' => '1 1', 'nlt' => '6 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 50]],
        ['neq' => '1 1', 'nlt' => '7 4', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 30]],
        ['neq' => '1 1', 'nlt' => '8 5', 'ndlt' => '5 5', 'sample' => ['plugin_zulu', 60]],
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
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload232, array_slice($source['rows'], 0, 8));

    return $source;
};

$terms232 = static fn (): array => [
    $between232('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq232('autoload', 'yes'),
    $notNull232('option_name'),
    $eq232('blog_id', 1),
    $like232('option_name', 'plugin_%'),
];
$plan232 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceStat4CounterFence(
    $prepared ?? $prepared232(),
    $current ?? $current232(),
    $terms ?? $terms232(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$staleNeq232 = static function () use ($current232, $plan232): array {
    $current = $current232();
    $current['indexes'][0]['stat4Samples'][2]['neq'] = '2 1';

    return $plan232(5, 1, null, $current);
};
$staleNlt232 = static function () use ($current232, $plan232): array {
    $current = $current232();
    $current['indexes'][0]['stat4Samples'][3]['nlt'] = '4 3';

    return $plan232(5, 1, null, $current);
};
$staleNdlt232 = static function () use ($current232, $plan232): array {
    $current = $current232();
    $current['indexes'][0]['stat4Samples'][4]['ndlt'] = '3 4';

    return $plan232(5, 1, null, $current);
};
$partialRowChurn232 = static function () use ($current232, $payload232, $plan232): array {
    $current = $current232();
    $current['rows'][] = ['rowid' => 90, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_extra', 'option_value' => 'extra', 'updated_at' => 90];
    $current['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload232, array_slice($current['rows'], 0, 9));

    return $plan232(5, 1, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next232 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next232-ready', $plan232()['status']),
    'planner stat4 expression partial current source next232 inherited next228' => static fn (TestRunner $t) => $t->same(true, $plan232()['selectedPlan']['next228Ready']),
    'planner stat4 expression partial current source next232 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan232()['selectedPlan']['next232Ready']),
    'planner stat4 expression partial current source next232 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_stat4_partial_counters_next232', $plan232()['selectedPlan']['name']),
    'planner stat4 expression partial current source next232 partial count' => static fn (TestRunner $t) => $t->same(9, $plan232()['stat4CounterFence']['partialRowCount']),
    'planner stat4 expression partial current source next232 distinct count' => static fn (TestRunner $t) => $t->same(6, $plan232()['stat4CounterFence']['distinctExpressionKeyCount']),
    'planner stat4 expression partial current source next232 partial keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_forms', 'plugin_forms', 'plugin_forms', 'plugin_mail', 'plugin_seo', 'plugin_zulu'], $plan232()['stat4CounterFence']['partialExpressionKeys']),
    'planner stat4 expression partial current source next232 distinct keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo', 'plugin_zulu'], $plan232()['stat4CounterFence']['distinctExpressionKeys']),
    'planner stat4 expression partial current source next232 proof rowids' => static fn (TestRunner $t) => $t->same([10, 40, 20, 50, 30, 60], array_column($plan232()['stat4CounterFence']['sampleCounterProofs'], 'sampleRowid')),
    'planner stat4 expression partial current source next232 proof keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo', 'plugin_zulu'], array_column($plan232()['stat4CounterFence']['sampleCounterProofs'], 'expressionKey')),
    'planner stat4 expression partial current source next232 stat4 neq' => static fn (TestRunner $t) => $t->same([1, 1, 4, 1, 1, 1], array_column($plan232()['stat4CounterFence']['sampleCounterProofs'], 'stat4Neq')),
    'planner stat4 expression partial current source next232 stat4 nlt' => static fn (TestRunner $t) => $t->same([0, 1, 2, 6, 7, 8], array_column($plan232()['stat4CounterFence']['sampleCounterProofs'], 'stat4Nlt')),
    'planner stat4 expression partial current source next232 stat4 ndlt' => static fn (TestRunner $t) => $t->same([0, 1, 2, 3, 4, 5], array_column($plan232()['stat4CounterFence']['sampleCounterProofs'], 'stat4Ndlt')),
    'planner stat4 expression partial current source next232 current neq' => static fn (TestRunner $t) => $t->same([1, 1, 4, 1, 1, 1], array_column($plan232()['stat4CounterFence']['sampleCounterProofs'], 'currentNeq')),
    'planner stat4 expression partial current source next232 current nlt' => static fn (TestRunner $t) => $t->same([0, 1, 2, 6, 7, 8], array_column($plan232()['stat4CounterFence']['sampleCounterProofs'], 'currentNlt')),
    'planner stat4 expression partial current source next232 current ndlt' => static fn (TestRunner $t) => $t->same([0, 1, 2, 3, 4, 5], array_column($plan232()['stat4CounterFence']['sampleCounterProofs'], 'currentNdlt')),
    'planner stat4 expression partial current source next232 match flags' => static fn (TestRunner $t) => $t->same([true, true, true, true, true, true], array_column($plan232()['stat4CounterFence']['sampleCounterProofs'], 'counterMatchesCurrentPartialRows')),
    'planner stat4 expression partial current source next232 all counters match' => static fn (TestRunner $t) => $t->same(true, $plan232()['stat4CounterFence']['allCurrentStat4CountersMatchPartialRows']),
    'planner stat4 expression partial current source next232 no mismatch rowids' => static fn (TestRunner $t) => $t->same([], $plan232()['stat4CounterFence']['counterMismatchRowids']),
    'planner stat4 expression partial current source next232 selected mismatch none' => static fn (TestRunner $t) => $t->same([], $plan232()['selectedPlan']['next232CounterMismatchRowids']),
    'planner stat4 expression partial current source next232 selected partial count' => static fn (TestRunner $t) => $t->same(9, $plan232()['selectedPlan']['next232PartialRowCount']),
    'planner stat4 expression partial current source next232 selected distinct count' => static fn (TestRunner $t) => $t->same(6, $plan232()['selectedPlan']['next232DistinctExpressionKeyCount']),
    'planner stat4 expression partial current source next232 signature lengths' => static fn (TestRunner $t) => $t->same([64, 64], [strlen($plan232()['stat4CounterFence']['counterSignature']), strlen($plan232()['stat4CounterFence']['proofSignature'])]),
    'planner stat4 expression partial current source next232 selected counter signature' => static fn (TestRunner $t) => $t->same($plan232()['stat4CounterFence']['counterSignature'], $plan232()['selectedPlan']['next232CounterSignature']),
    'planner stat4 expression partial current source next232 selected proof signature' => static fn (TestRunner $t) => $t->same($plan232()['stat4CounterFence']['proofSignature'], $plan232()['selectedPlan']['next232ProofSignature']),
    'planner stat4 expression partial current source next232 stat4 counter signature' => static fn (TestRunner $t) => $t->same($plan232()['stat4CounterFence']['counterSignature'], $plan232()['stat4Fence']['next232CounterSignature']),
    'planner stat4 expression partial current source next232 stat4 proof signature' => static fn (TestRunner $t) => $t->same($plan232()['stat4CounterFence']['proofSignature'], $plan232()['stat4Fence']['next232ProofSignature']),
    'planner stat4 expression partial current source next232 cursor appended' => static fn (TestRunner $t) => $t->same('VerifyCurrentStat4PartialCounters', $plan232()['cursorProgram'][array_key_last($plan232()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next232 cursor mode' => static fn (TestRunner $t) => $t->same('next232-current-source-stat4-expression-partial-counters', $plan232()['cursorProgram'][array_key_last($plan232()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next232 cursor counts' => static fn (TestRunner $t) => $t->same([9, 6], [$plan232()['cursorProgram'][array_key_last($plan232()['cursorProgram'])]['partialRowCount'], $plan232()['cursorProgram'][array_key_last($plan232()['cursorProgram'])]['distinctExpressionKeyCount']]),
    'planner stat4 expression partial current source next232 cursor signature' => static fn (TestRunner $t) => $t->same($plan232()['stat4CounterFence']['proofSignature'], $plan232()['cursorProgram'][array_key_last($plan232()['cursorProgram'])]['signature']),
    'planner stat4 expression partial current source next232 matched rowids preserved' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan232()['matchedRowids']),
    'planner stat4 expression partial current source next232 projected payload retained' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan232()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next232 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next232', $plan232()['dependencies'], true)),
    'planner stat4 expression partial current source next232 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan232()['dependency_closure']),
    'planner stat4 expression partial current source next232 non overlap' => static fn (TestRunner $t) => $t->contains('counter cardinalities', $plan232()['non_overlap']),
    'planner stat4 expression partial current source next232 detail' => static fn (TestRunner $t) => $t->contains('NEXT232 COUNTER FENCE', $plan232()['detail']),
    'planner stat4 expression partial current source next232 stale neq blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-counter-reprepare', $staleNeq232()['status']),
    'planner stat4 expression partial current source next232 stale neq rowid' => static fn (TestRunner $t) => $t->same([20], $staleNeq232()['stat4CounterFence']['counterMismatchRowids']),
    'planner stat4 expression partial current source next232 stale neq proof' => static fn (TestRunner $t) => $t->same([2, 4, false], [$staleNeq232()['stat4CounterFence']['sampleCounterProofs'][2]['stat4Neq'], $staleNeq232()['stat4CounterFence']['sampleCounterProofs'][2]['currentNeq'], $staleNeq232()['stat4CounterFence']['sampleCounterProofs'][2]['counterMatchesCurrentPartialRows']]),
    'planner stat4 expression partial current source next232 stale neq selected' => static fn (TestRunner $t) => $t->same([20], $staleNeq232()['selectedPlan']['next232CounterMismatchRowids']),
    'planner stat4 expression partial current source next232 stale neq no cursor' => static fn (TestRunner $t) => $t->same(false, in_array('VerifyCurrentStat4PartialCounters', array_column($staleNeq232()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next232 stale nlt blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-counter-reprepare', $staleNlt232()['status']),
    'planner stat4 expression partial current source next232 stale nlt rowid' => static fn (TestRunner $t) => $t->same([50], $staleNlt232()['stat4CounterFence']['counterMismatchRowids']),
    'planner stat4 expression partial current source next232 stale nlt proof' => static fn (TestRunner $t) => $t->same([4, 6, false], [$staleNlt232()['stat4CounterFence']['sampleCounterProofs'][3]['stat4Nlt'], $staleNlt232()['stat4CounterFence']['sampleCounterProofs'][3]['currentNlt'], $staleNlt232()['stat4CounterFence']['sampleCounterProofs'][3]['counterMatchesCurrentPartialRows']]),
    'planner stat4 expression partial current source next232 stale ndlt blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-counter-reprepare', $staleNdlt232()['status']),
    'planner stat4 expression partial current source next232 stale ndlt rowid' => static fn (TestRunner $t) => $t->same([30], $staleNdlt232()['stat4CounterFence']['counterMismatchRowids']),
    'planner stat4 expression partial current source next232 stale ndlt proof' => static fn (TestRunner $t) => $t->same([3, 4, false], [$staleNdlt232()['stat4CounterFence']['sampleCounterProofs'][4]['stat4Ndlt'], $staleNdlt232()['stat4CounterFence']['sampleCounterProofs'][4]['currentNdlt'], $staleNdlt232()['stat4CounterFence']['sampleCounterProofs'][4]['counterMatchesCurrentPartialRows']]),
    'planner stat4 expression partial current source next232 partial churn blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-counter-reprepare', $partialRowChurn232()['status']),
    'planner stat4 expression partial current source next232 partial churn count' => static fn (TestRunner $t) => $t->same(10, $partialRowChurn232()['stat4CounterFence']['partialRowCount']),
    'planner stat4 expression partial current source next232 partial churn rowids' => static fn (TestRunner $t) => $t->same([20, 50, 30, 60], $partialRowChurn232()['stat4CounterFence']['counterMismatchRowids']),
    'planner stat4 expression partial current source next232 invalid current rows' => static function (TestRunner $t) use ($current232, $plan232): void {
        $bad = $current232();
        $bad['rows'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan232(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next232 malformed neq' => static function (TestRunner $t) use ($current232, $plan232): void {
        $bad = $current232();
        $bad['indexes'][0]['stat4Samples'][0]['neq'] = 'x';
        $t->throws(InvalidArgumentException::class, static fn () => $plan232(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next232 unsupported partial op' => static function (TestRunner $t) use ($current232, $plan232): void {
        $bad = $current232();
        $bad['indexes'][0]['partialPredicateTerms'][0]['operator'] = 'GLOB';
        $t->throws(InvalidArgumentException::class, static fn () => $plan232(5, 1, null, $bad));
    },
];

foreach (range(1, 18) as $case) {
    $tests['planner stat4 expression partial current source next232 repeated counter fence ' . $case] = static function (TestRunner $t) use ($plan232, $case): void {
        $plan = $plan232(1 + ($case % 5), $case % 4);
        $t->same($plan['stat4CounterFence']['proofSignature'], $plan['selectedPlan']['next232ProofSignature']);
    };
}

return $tests;
