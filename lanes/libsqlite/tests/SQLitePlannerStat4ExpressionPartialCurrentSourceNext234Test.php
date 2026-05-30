<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq234 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like234 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull234 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between234 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$payload234 = static fn (array $row): array => [
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

$prepared234 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-partial-histogram-next234',
    'schemaCookie' => 2340,
    'stat4Generation' => 234,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_stat4_partial_histogram_next234',
        'rootPage' => 23401,
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

$current234 = static function () use ($prepared234, $payload234): array {
    $source = $prepared234();
    $source['name'] = 'current-wp-options-stat4-partial-histogram-next234';
    $source['schemaCookie'] = 2349;
    $source['stat4Generation'] = 432;
    $source['indexes'][0]['rootPage'] = 23488;
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
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload234, array_slice($source['rows'], 0, 8));

    return $source;
};

$terms234 = static fn (): array => [
    $between234('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq234('autoload', 'yes'),
    $notNull234('option_name'),
    $eq234('blog_id', 1),
    $like234('option_name', 'plugin_%'),
];
$plan234 = static fn (?array $prepared = null, ?array $current = null, ?array $terms = null, int $limit = 5, int $offset = 1): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceStat4HistogramFence(
    $prepared ?? $prepared234(),
    $current ?? $current234(),
    $terms ?? $terms234(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$staleNeq234 = static function () use ($current234, $plan234): array {
    $current = $current234();
    $current['indexes'][0]['stat4Samples'][2]['neq'] = '2 1';

    return $plan234(null, $current);
};
$staleNlt234 = static function () use ($current234, $plan234): array {
    $current = $current234();
    $current['indexes'][0]['stat4Samples'][4]['nlt'] = '5 4';

    return $plan234(null, $current);
};
$staleNdlt234 = static function () use ($current234, $plan234): array {
    $current = $current234();
    $current['indexes'][0]['stat4Samples'][5]['ndlt'] = '4 5';

    return $plan234(null, $current);
};
$insertedStale234 = static function () use ($current234, $payload234, $plan234): array {
    $current = $current234();
    array_unshift($current['rows'], ['rowid' => 55, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_updates', 'option_value' => 'updates', 'updated_at' => 55]);
    $current['indexes'][0]['stat4Samples'][] = ['neq' => '1 1', 'nlt' => '7 5', 'ndlt' => '5 5', 'sample' => ['plugin_updates', 55]];
    $current['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload234, array_slice($current['rows'], 0, 9));

    return $plan234(null, $current);
};

$tests = [
    'planner stat4 expression partial current source next234 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next234-ready', $plan234()['status']),
    'planner stat4 expression partial current source next234 inherits next231' => static fn (TestRunner $t) => $t->same(true, $plan234()['selectedPlan']['next231Ready']),
    'planner stat4 expression partial current source next234 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan234()['selectedPlan']['next234Ready']),
    'planner stat4 expression partial current source next234 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_stat4_partial_histogram_next234', $plan234()['selectedPlan']['name']),
    'planner stat4 expression partial current source next234 root page' => static fn (TestRunner $t) => $t->same(23488, $plan234()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next234 qualified count' => static fn (TestRunner $t) => $t->same(8, $plan234()['stat4HistogramFence']['qualifiedRowCount']),
    'planner stat4 expression partial current source next234 distinct count' => static fn (TestRunner $t) => $t->same(6, $plan234()['stat4HistogramFence']['distinctExpressionKeyCount']),
    'planner stat4 expression partial current source next234 qualified keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_forms', 'plugin_forms', 'plugin_mail', 'plugin_seo', 'plugin_zulu'], $plan234()['stat4HistogramFence']['qualifiedExpressionKeys']),
    'planner stat4 expression partial current source next234 distinct keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo', 'plugin_zulu'], $plan234()['stat4HistogramFence']['distinctExpressionKeys']),
    'planner stat4 expression partial current source next234 sample proof count' => static fn (TestRunner $t) => $t->same(6, count($plan234()['stat4HistogramFence']['sampleHistogramProofs'])),
    'planner stat4 expression partial current source next234 all match' => static fn (TestRunner $t) => $t->same(true, $plan234()['stat4HistogramFence']['allHistogramRowsMatchCurrentSource']),
    'planner stat4 expression partial current source next234 no mismatches' => static fn (TestRunner $t) => $t->same([], $plan234()['stat4HistogramFence']['mismatchedSampleRowids']),
    'planner stat4 expression partial current source next234 forms expected' => static fn (TestRunner $t) => $t->same(['neq' => 3, 'nlt' => 2, 'ndlt' => 2], $plan234()['stat4HistogramFence']['sampleHistogramProofs'][2]['expected']),
    'planner stat4 expression partial current source next234 forms actual' => static fn (TestRunner $t) => $t->same(['neq' => 3, 'nlt' => 2, 'ndlt' => 2], $plan234()['stat4HistogramFence']['sampleHistogramProofs'][2]['actual']),
    'planner stat4 expression partial current source next234 zulu expected' => static fn (TestRunner $t) => $t->same(['neq' => 1, 'nlt' => 7, 'ndlt' => 5], $plan234()['stat4HistogramFence']['sampleHistogramProofs'][5]['expected']),
    'planner stat4 expression partial current source next234 selected signature' => static fn (TestRunner $t) => $t->same($plan234()['stat4HistogramFence']['histogramSignature'], $plan234()['selectedPlan']['next234HistogramSignature']),
    'planner stat4 expression partial current source next234 proof signature' => static fn (TestRunner $t) => $t->same($plan234()['stat4HistogramFence']['proofSignature'], $plan234()['selectedPlan']['next234ProofSignature']),
    'planner stat4 expression partial current source next234 stat4 signature' => static fn (TestRunner $t) => $t->same($plan234()['stat4HistogramFence']['proofSignature'], $plan234()['stat4Fence']['next234ProofSignature']),
    'planner stat4 expression partial current source next234 signature lengths' => static fn (TestRunner $t) => $t->same([64, 64], [strlen($plan234()['stat4HistogramFence']['histogramSignature']), strlen($plan234()['stat4HistogramFence']['proofSignature'])]),
    'planner stat4 expression partial current source next234 cursor appended' => static fn (TestRunner $t) => $t->same('RecheckCurrentStat4ExpressionPartialHistogram', $plan234()['cursorProgram'][array_key_last($plan234()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next234 cursor mode' => static fn (TestRunner $t) => $t->same('next234-current-source-stat4-expression-partial-histogram', $plan234()['cursorProgram'][array_key_last($plan234()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next234 cursor count' => static fn (TestRunner $t) => $t->same(8, $plan234()['cursorProgram'][array_key_last($plan234()['cursorProgram'])]['qualifiedRowCount']),
    'planner stat4 expression partial current source next234 cursor distinct' => static fn (TestRunner $t) => $t->same(6, $plan234()['cursorProgram'][array_key_last($plan234()['cursorProgram'])]['distinctExpressionKeyCount']),
    'planner stat4 expression partial current source next234 cursor signature' => static fn (TestRunner $t) => $t->same($plan234()['stat4HistogramFence']['proofSignature'], $plan234()['cursorProgram'][array_key_last($plan234()['cursorProgram'])]['signature']),
    'planner stat4 expression partial current source next234 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next234', $plan234()['dependencies'], true)),
    'planner stat4 expression partial current source next234 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan234()['dependency_closure']),
    'planner stat4 expression partial current source next234 non overlap' => static fn (TestRunner $t) => $t->contains('histogram cardinality validation', $plan234()['non_overlap']),
    'planner stat4 expression partial current source next234 detail' => static fn (TestRunner $t) => $t->contains('NEXT234 HISTOGRAM FENCE', $plan234()['detail']),
    'planner stat4 expression partial current source next234 stale neq blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-histogram-reprepare', $staleNeq234()['status']),
    'planner stat4 expression partial current source next234 stale neq rowid' => static fn (TestRunner $t) => $t->same([20], $staleNeq234()['stat4HistogramFence']['mismatchedSampleRowids']),
    'planner stat4 expression partial current source next234 stale neq expected' => static fn (TestRunner $t) => $t->same(['neq' => 3, 'nlt' => 2, 'ndlt' => 2], $staleNeq234()['stat4HistogramFence']['sampleHistogramProofs'][2]['expected']),
    'planner stat4 expression partial current source next234 stale neq actual' => static fn (TestRunner $t) => $t->same(['neq' => 2, 'nlt' => 2, 'ndlt' => 2], $staleNeq234()['stat4HistogramFence']['sampleHistogramProofs'][2]['actual']),
    'planner stat4 expression partial current source next234 stale nlt blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-histogram-reprepare', $staleNlt234()['status']),
    'planner stat4 expression partial current source next234 stale nlt rowid' => static fn (TestRunner $t) => $t->same([30], $staleNlt234()['stat4HistogramFence']['mismatchedSampleRowids']),
    'planner stat4 expression partial current source next234 stale ndlt blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-histogram-reprepare', $staleNdlt234()['status']),
    'planner stat4 expression partial current source next234 stale ndlt rowid' => static fn (TestRunner $t) => $t->same([60], $staleNdlt234()['stat4HistogramFence']['mismatchedSampleRowids']),
    'planner stat4 expression partial current source next234 inserted stale blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-histogram-reprepare', $insertedStale234()['status']),
    'planner stat4 expression partial current source next234 inserted mismatches zulu' => static fn (TestRunner $t) => $t->true(in_array(60, $insertedStale234()['stat4HistogramFence']['mismatchedSampleRowids'], true)),
    'planner stat4 expression partial current source next234 inserted count changes' => static fn (TestRunner $t) => $t->same(9, $insertedStale234()['stat4HistogramFence']['qualifiedRowCount']),
    'planner stat4 expression partial current source next234 inserted distinct changes' => static fn (TestRunner $t) => $t->same(7, $insertedStale234()['stat4HistogramFence']['distinctExpressionKeyCount']),
    'planner stat4 expression partial current source next234 inserted no histogram cursor' => static fn (TestRunner $t) => $t->same(false, in_array('RecheckCurrentStat4ExpressionPartialHistogram', array_column($insertedStale234()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next234 invalid stat value' => static function (TestRunner $t) use ($current234, $plan234): void {
        $bad = $current234();
        $bad['indexes'][0]['stat4Samples'][0]['neq'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan234(null, $bad));
    },
    'planner stat4 expression partial current source next234 invalid operator' => static function (TestRunner $t) use ($terms234, $plan234): void {
        $terms = $terms234();
        $terms[0]['operator'] = 'GLOB';
        $t->throws(InvalidArgumentException::class, static fn () => $plan234(null, null, $terms));
    },
];

foreach (range(1, 24) as $case) {
    $tests['planner stat4 expression partial current source next234 repeated histogram proof ' . $case] = static function (TestRunner $t) use ($plan234, $case): void {
        $plan = $plan234(null, null, null, 1 + ($case % 5), $case % 4);
        $t->same($plan['stat4HistogramFence']['proofSignature'], $plan['selectedPlan']['next234ProofSignature']);
    };
}

return $tests;
