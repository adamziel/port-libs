<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq235 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like235 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull235 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between235 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$payload235 = static fn (array $row): array => [
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

$prepared235 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-vector-next235',
    'schemaCookie' => 2350,
    'stat4Generation' => 235,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_stat4_vector_partial_next235',
        'rootPage' => 23501,
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
        'partialGroupedOrPredicateArms' => [[
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ]],
        'partialGroupedLikePredicateArms' => [[
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'plugin_%'],
        ]],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 1, 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 1, 20]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 1, 30]],
        ],
        'stat4ExpressionPayloads' => [],
    ]],
];

$current235 = static function () use ($prepared235, $payload235): array {
    $source = $prepared235();
    $source['name'] = 'current-wp-options-stat4-vector-next235';
    $source['schemaCookie'] = 2359;
    $source['stat4Generation'] = 435;
    $source['indexes'][0]['rootPage'] = 23588;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 1, 10]],
        ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 1, 40]],
        ['neq' => '4 3', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 1, 20]],
        ['neq' => '4 1', 'nlt' => '2 5', 'ndlt' => '2 3', 'sample' => ['plugin_forms', 2, 80]],
        ['neq' => '1 1', 'nlt' => '6 6', 'ndlt' => '3 4', 'sample' => ['plugin_mail', 1, 50]],
        ['neq' => '1 1', 'nlt' => '7 7', 'ndlt' => '4 5', 'sample' => ['plugin_seo', 1, 30]],
        ['neq' => '1 1', 'nlt' => '8 8', 'ndlt' => '5 6', 'sample' => ['plugin_zulu', 1, 60]],
    ];
    $source['rows'] = [
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
        ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
        ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
        ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
        ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache', 'updated_at' => 40],
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 10],
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ];
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload235, array_slice($source['rows'], 0, 9));

    return $source;
};

$terms235 = static fn (): array => [
    $between235('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq235('autoload', 'yes'),
    $notNull235('option_name'),
    $eq235('blog_id', 1),
    $like235('option_name', 'plugin_%'),
];
$plan235 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext235(
    $prepared ?? $prepared235(),
    $current ?? $current235(),
    $terms ?? $terms235(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$staleSecondNeq235 = static function () use ($current235, $plan235): array {
    $current = $current235();
    $current['indexes'][0]['stat4Samples'][2]['neq'] = '4 2';

    return $plan235(5, 1, null, $current);
};
$staleSecondNlt235 = static function () use ($current235, $plan235): array {
    $current = $current235();
    $current['indexes'][0]['stat4Samples'][3]['nlt'] = '2 4';

    return $plan235(5, 1, null, $current);
};
$staleSecondNdlt235 = static function () use ($current235, $plan235): array {
    $current = $current235();
    $current['indexes'][0]['stat4Samples'][4]['ndlt'] = '3 3';

    return $plan235(5, 1, null, $current);
};
$crossBlogChurn235 = static function () use ($current235, $payload235, $plan235): array {
    $current = $current235();
    $current['rows'][] = ['rowid' => 90, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog-copy', 'updated_at' => 90];
    $current['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload235, array_slice($current['rows'], 0, 10));

    return $plan235(5, 1, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next235 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next235-ready', $plan235()['status']),
    'planner stat4 expression partial current source next235 inherits next232' => static fn (TestRunner $t) => $t->same(true, $plan235()['selectedPlan']['next232Ready']),
    'planner stat4 expression partial current source next235 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan235()['selectedPlan']['next235Ready']),
    'planner stat4 expression partial current source next235 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_stat4_vector_partial_next235', $plan235()['selectedPlan']['name']),
    'planner stat4 expression partial current source next235 partial count' => static fn (TestRunner $t) => $t->same(9, $plan235()['stat4VectorCounterFence']['partialRowCount']),
    'planner stat4 expression partial current source next235 expression count' => static fn (TestRunner $t) => $t->same(6, $plan235()['stat4VectorCounterFence']['expressionKeyCount']),
    'planner stat4 expression partial current source next235 vector count' => static fn (TestRunner $t) => $t->same(7, $plan235()['stat4VectorCounterFence']['distinctVectorKeyCount']),
    'planner stat4 expression partial current source next235 partial vectors' => static fn (TestRunner $t) => $t->same([['plugin_alpha', 1], ['plugin_cache', 1], ['plugin_forms', 1], ['plugin_forms', 1], ['plugin_forms', 1], ['plugin_forms', 2], ['plugin_mail', 1], ['plugin_seo', 1], ['plugin_zulu', 1]], $plan235()['stat4VectorCounterFence']['partialVectorKeys']),
    'planner stat4 expression partial current source next235 distinct vectors' => static fn (TestRunner $t) => $t->same([['plugin_alpha', 1], ['plugin_cache', 1], ['plugin_forms', 1], ['plugin_forms', 2], ['plugin_mail', 1], ['plugin_seo', 1], ['plugin_zulu', 1]], $plan235()['stat4VectorCounterFence']['distinctVectorKeys']),
    'planner stat4 expression partial current source next235 proof rowids' => static fn (TestRunner $t) => $t->same([10, 40, 20, 80, 50, 30, 60], array_column($plan235()['stat4VectorCounterFence']['sampleVectorProofs'], 'sampleRowid')),
    'planner stat4 expression partial current source next235 proof vectors' => static fn (TestRunner $t) => $t->same([['plugin_alpha', 1], ['plugin_cache', 1], ['plugin_forms', 1], ['plugin_forms', 2], ['plugin_mail', 1], ['plugin_seo', 1], ['plugin_zulu', 1]], array_column($plan235()['stat4VectorCounterFence']['sampleVectorProofs'], 'sampleVector')),
    'planner stat4 expression partial current source next235 stat4 neq vectors' => static fn (TestRunner $t) => $t->same([[1, 1], [1, 1], [4, 3], [4, 1], [1, 1], [1, 1], [1, 1]], array_column($plan235()['stat4VectorCounterFence']['sampleVectorProofs'], 'stat4NeqVector')),
    'planner stat4 expression partial current source next235 current neq vectors' => static fn (TestRunner $t) => $t->same([[1, 1], [1, 1], [4, 3], [4, 1], [1, 1], [1, 1], [1, 1]], array_column($plan235()['stat4VectorCounterFence']['sampleVectorProofs'], 'currentNeqVector')),
    'planner stat4 expression partial current source next235 stat4 nlt vectors' => static fn (TestRunner $t) => $t->same([[0, 0], [1, 1], [2, 2], [2, 5], [6, 6], [7, 7], [8, 8]], array_column($plan235()['stat4VectorCounterFence']['sampleVectorProofs'], 'stat4NltVector')),
    'planner stat4 expression partial current source next235 current nlt vectors' => static fn (TestRunner $t) => $t->same([[0, 0], [1, 1], [2, 2], [2, 5], [6, 6], [7, 7], [8, 8]], array_column($plan235()['stat4VectorCounterFence']['sampleVectorProofs'], 'currentNltVector')),
    'planner stat4 expression partial current source next235 stat4 ndlt vectors' => static fn (TestRunner $t) => $t->same([[0, 0], [1, 1], [2, 2], [2, 3], [3, 4], [4, 5], [5, 6]], array_column($plan235()['stat4VectorCounterFence']['sampleVectorProofs'], 'stat4NdltVector')),
    'planner stat4 expression partial current source next235 current ndlt vectors' => static fn (TestRunner $t) => $t->same([[0, 0], [1, 1], [2, 2], [2, 3], [3, 4], [4, 5], [5, 6]], array_column($plan235()['stat4VectorCounterFence']['sampleVectorProofs'], 'currentNdltVector')),
    'planner stat4 expression partial current source next235 proof flags' => static fn (TestRunner $t) => $t->same([true, true, true, true, true, true, true], array_column($plan235()['stat4VectorCounterFence']['sampleVectorProofs'], 'vectorCountersMatchCurrentPartialRows')),
    'planner stat4 expression partial current source next235 all vector counters match' => static fn (TestRunner $t) => $t->same(true, $plan235()['stat4VectorCounterFence']['allVectorCountersMatchCurrentPartialRows']),
    'planner stat4 expression partial current source next235 no mismatches' => static fn (TestRunner $t) => $t->same([], $plan235()['stat4VectorCounterFence']['vectorCounterMismatchRowids']),
    'planner stat4 expression partial current source next235 selected mismatch none' => static fn (TestRunner $t) => $t->same([], $plan235()['selectedPlan']['next235VectorMismatchRowids']),
    'planner stat4 expression partial current source next235 selected vector count' => static fn (TestRunner $t) => $t->same(7, $plan235()['selectedPlan']['next235VectorKeyCount']),
    'planner stat4 expression partial current source next235 signature lengths' => static fn (TestRunner $t) => $t->same([64, 64], [strlen($plan235()['stat4VectorCounterFence']['vectorSignature']), strlen($plan235()['stat4VectorCounterFence']['proofSignature'])]),
    'planner stat4 expression partial current source next235 selected vector signature' => static fn (TestRunner $t) => $t->same($plan235()['stat4VectorCounterFence']['vectorSignature'], $plan235()['selectedPlan']['next235VectorSignature']),
    'planner stat4 expression partial current source next235 selected proof signature' => static fn (TestRunner $t) => $t->same($plan235()['stat4VectorCounterFence']['proofSignature'], $plan235()['selectedPlan']['next235ProofSignature']),
    'planner stat4 expression partial current source next235 stat4 vector signature' => static fn (TestRunner $t) => $t->same($plan235()['stat4VectorCounterFence']['vectorSignature'], $plan235()['stat4Fence']['next235VectorSignature']),
    'planner stat4 expression partial current source next235 stat4 proof signature' => static fn (TestRunner $t) => $t->same($plan235()['stat4VectorCounterFence']['proofSignature'], $plan235()['stat4Fence']['next235ProofSignature']),
    'planner stat4 expression partial current source next235 cursor appended' => static fn (TestRunner $t) => $t->same('VerifyCurrentStat4VectorCounters', $plan235()['cursorProgram'][array_key_last($plan235()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next235 cursor mode' => static fn (TestRunner $t) => $t->same('next235-current-source-stat4-expression-partial-vector-counters', $plan235()['cursorProgram'][array_key_last($plan235()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next235 cursor counts' => static fn (TestRunner $t) => $t->same([9, 7], [$plan235()['cursorProgram'][array_key_last($plan235()['cursorProgram'])]['partialRowCount'], $plan235()['cursorProgram'][array_key_last($plan235()['cursorProgram'])]['distinctVectorKeyCount']]),
    'planner stat4 expression partial current source next235 cursor signature' => static fn (TestRunner $t) => $t->same($plan235()['stat4VectorCounterFence']['proofSignature'], $plan235()['cursorProgram'][array_key_last($plan235()['cursorProgram'])]['signature']),
    'planner stat4 expression partial current source next235 matched rowids preserved' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan235()['matchedRowids']),
    'planner stat4 expression partial current source next235 projected payload retained' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan235()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next235 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next235', $plan235()['dependencies'], true)),
    'planner stat4 expression partial current source next235 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan235()['dependency_closure']),
    'planner stat4 expression partial current source next235 non overlap' => static fn (TestRunner $t) => $t->contains('multi-prefix STAT4 vector counters', $plan235()['non_overlap']),
    'planner stat4 expression partial current source next235 detail' => static fn (TestRunner $t) => $t->contains('NEXT235 VECTOR COUNTER FENCE', $plan235()['detail']),
    'planner stat4 expression partial current source next235 stale second neq blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-vector-counter-reprepare', $staleSecondNeq235()['status']),
    'planner stat4 expression partial current source next235 stale second neq rowid' => static fn (TestRunner $t) => $t->same([20], $staleSecondNeq235()['stat4VectorCounterFence']['vectorCounterMismatchRowids']),
    'planner stat4 expression partial current source next235 stale second neq proof' => static fn (TestRunner $t) => $t->same([[4, 2], [4, 3], false], [$staleSecondNeq235()['stat4VectorCounterFence']['sampleVectorProofs'][2]['stat4NeqVector'], $staleSecondNeq235()['stat4VectorCounterFence']['sampleVectorProofs'][2]['currentNeqVector'], $staleSecondNeq235()['stat4VectorCounterFence']['sampleVectorProofs'][2]['vectorCountersMatchCurrentPartialRows']]),
    'planner stat4 expression partial current source next235 stale second neq no cursor' => static fn (TestRunner $t) => $t->same(false, in_array('VerifyCurrentStat4VectorCounters', array_column($staleSecondNeq235()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next235 stale second nlt blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-vector-counter-reprepare', $staleSecondNlt235()['status']),
    'planner stat4 expression partial current source next235 stale second nlt rowid' => static fn (TestRunner $t) => $t->same([80], $staleSecondNlt235()['stat4VectorCounterFence']['vectorCounterMismatchRowids']),
    'planner stat4 expression partial current source next235 stale second nlt proof' => static fn (TestRunner $t) => $t->same([[2, 4], [2, 5], false], [$staleSecondNlt235()['stat4VectorCounterFence']['sampleVectorProofs'][3]['stat4NltVector'], $staleSecondNlt235()['stat4VectorCounterFence']['sampleVectorProofs'][3]['currentNltVector'], $staleSecondNlt235()['stat4VectorCounterFence']['sampleVectorProofs'][3]['vectorCountersMatchCurrentPartialRows']]),
    'planner stat4 expression partial current source next235 stale second ndlt blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-vector-counter-reprepare', $staleSecondNdlt235()['status']),
    'planner stat4 expression partial current source next235 stale second ndlt rowid' => static fn (TestRunner $t) => $t->same([50], $staleSecondNdlt235()['stat4VectorCounterFence']['vectorCounterMismatchRowids']),
    'planner stat4 expression partial current source next235 stale second ndlt proof' => static fn (TestRunner $t) => $t->same([[3, 3], [3, 4], false], [$staleSecondNdlt235()['stat4VectorCounterFence']['sampleVectorProofs'][4]['stat4NdltVector'], $staleSecondNdlt235()['stat4VectorCounterFence']['sampleVectorProofs'][4]['currentNdltVector'], $staleSecondNdlt235()['stat4VectorCounterFence']['sampleVectorProofs'][4]['vectorCountersMatchCurrentPartialRows']]),
    'planner stat4 expression partial current source next235 cross blog churn blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-vector-counter-reprepare', $crossBlogChurn235()['status']),
    'planner stat4 expression partial current source next235 cross blog vector count unchanged' => static fn (TestRunner $t) => $t->same(7, $crossBlogChurn235()['stat4VectorCounterFence']['distinctVectorKeyCount']),
    'planner stat4 expression partial current source next235 cross blog row count' => static fn (TestRunner $t) => $t->same(10, $crossBlogChurn235()['stat4VectorCounterFence']['partialRowCount']),
    'planner stat4 expression partial current source next235 cross blog mismatch rowids' => static fn (TestRunner $t) => $t->same([20, 80, 50, 30, 60], $crossBlogChurn235()['stat4VectorCounterFence']['vectorCounterMismatchRowids']),
    'planner stat4 expression partial current source next235 invalid current rows' => static function (TestRunner $t) use ($current235, $plan235): void {
        $bad = $current235();
        $bad['rows'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan235(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next235 malformed vector' => static function (TestRunner $t) use ($current235, $plan235): void {
        $bad = $current235();
        $bad['indexes'][0]['stat4Samples'][0]['neq'] = '1';
        $t->throws(InvalidArgumentException::class, static fn () => $plan235(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next235 malformed sample' => static function (TestRunner $t) use ($current235, $plan235): void {
        $bad = $current235();
        $bad['indexes'][0]['stat4Samples'][0]['sample'] = ['plugin_alpha', 1];
        $t->throws(InvalidArgumentException::class, static fn () => $plan235(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next235 unsupported partial op' => static function (TestRunner $t) use ($current235, $plan235): void {
        $bad = $current235();
        $bad['indexes'][0]['partialPredicateTerms'][0]['operator'] = 'GLOB';
        $t->throws(InvalidArgumentException::class, static fn () => $plan235(5, 1, null, $bad));
    },
];

foreach (range(1, 18) as $case) {
    $tests['planner stat4 expression partial current source next235 repeated vector proof ' . $case] = static function (TestRunner $t) use ($plan235, $case): void {
        $plan = $plan235(1 + ($case % 5), $case % 4);
        $t->same($plan['stat4VectorCounterFence']['proofSignature'], $plan['selectedPlan']['next235ProofSignature']);
    };
}

return $tests;
