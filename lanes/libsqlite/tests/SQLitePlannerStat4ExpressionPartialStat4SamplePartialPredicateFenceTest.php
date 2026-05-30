<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq228 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like228 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull228 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between228 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$payload228 = static fn (array $row): array => [
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

$prepared228 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-partial-sample-next228',
    'schemaCookie' => 2280,
    'stat4Generation' => 228,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_stat4_partial_sample_next228',
        'rootPage' => 22801,
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

$current228 = static function () use ($prepared228, $payload228): array {
    $source = $prepared228();
    $source['name'] = 'current-wp-options-stat4-partial-sample-next228';
    $source['schemaCookie'] = 2289;
    $source['stat4Generation'] = 308;
    $source['indexes'][0]['rootPage'] = 22888;
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
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload228, array_slice($source['rows'], 0, 8));

    return $source;
};

$terms228 = static fn (): array => [
    $between228('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq228('autoload', 'yes'),
    $notNull228('option_name'),
    $eq228('blog_id', 1),
    $like228('option_name', 'plugin_%'),
];
$plan228 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4SamplePartialPredicateFence(
    $prepared ?? $prepared228(),
    $current ?? $current228(),
    $terms ?? $terms228(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$missingSampleRow228 = static function () use ($current228, $plan228): array {
    $current = $current228();
    $current['rows'] = array_values(array_filter($current['rows'], static fn (array $row): bool => $row['rowid'] !== 50));
    $current['indexes'][0]['stat4ExpressionPayloads'] = array_values(array_filter($current['indexes'][0]['stat4ExpressionPayloads'], static fn (array $row): bool => $row['rowid'] !== 50));

    return $plan228(5, 1, null, $current);
};
$staleKey228 = static function () use ($current228, $payload228, $plan228): array {
    $current = $current228();
    foreach ($current['rows'] as &$row) {
        if ($row['rowid'] === 50) {
            $row['option_name'] = 'plugin_mail_renamed';
        }
    }
    unset($row);
    $current['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload228, array_slice($current['rows'], 0, 8));

    return $plan228(5, 1, null, $current);
};
$partialReject228 = static function () use ($current228, $payload228, $plan228): array {
    $current = $current228();
    foreach ($current['rows'] as &$row) {
        if ($row['rowid'] === 50) {
            $row['autoload'] = 'no';
        }
    }
    unset($row);
    $current['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload228, array_slice($current['rows'], 0, 8));

    return $plan228(5, 1, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next228 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next228-ready', $plan228()['status']),
    'planner stat4 expression partial current source next228 selected current' => static fn (TestRunner $t) => $t->same('current', $plan228()['selectedSource']),
    'planner stat4 expression partial current source next228 inherited next224' => static fn (TestRunner $t) => $t->same(true, $plan228()['selectedPlan']['next224Ready']),
    'planner stat4 expression partial current source next228 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan228()['selectedPlan']['next228Ready']),
    'planner stat4 expression partial current source next228 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_stat4_partial_sample_next228', $plan228()['selectedPlan']['name']),
    'planner stat4 expression partial current source next228 root page' => static fn (TestRunner $t) => $t->same(22888, $plan228()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next228 sample row count' => static fn (TestRunner $t) => $t->same(6, $plan228()['stat4SamplePartialPredicateFence']['sampleRowCount']),
    'planner stat4 expression partial current source next228 term count' => static fn (TestRunner $t) => $t->same(4, $plan228()['stat4SamplePartialPredicateFence']['partialPredicateTermCount']),
    'planner stat4 expression partial current source next228 sample rowids' => static fn (TestRunner $t) => $t->same([10, 40, 20, 50, 30, 60], array_column($plan228()['stat4SamplePartialPredicateFence']['sampleRowProofs'], 'sampleRowid')),
    'planner stat4 expression partial current source next228 sample keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo', 'plugin_zulu'], array_column($plan228()['stat4SamplePartialPredicateFence']['sampleRowProofs'], 'sampleExpressionKey')),
    'planner stat4 expression partial current source next228 current keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo', 'plugin_zulu'], array_column($plan228()['stat4SamplePartialPredicateFence']['sampleRowProofs'], 'currentExpressionKey')),
    'planner stat4 expression partial current source next228 matched sample keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms'], $plan228()['stat4SamplePartialPredicateFence']['matchedSampleKeys']),
    'planner stat4 expression partial current source next228 matched sample rowids' => static fn (TestRunner $t) => $t->same([20, 50, 30], $plan228()['stat4SamplePartialPredicateFence']['matchedSampleRowids']),
    'planner stat4 expression partial current source next228 all rows resolve' => static fn (TestRunner $t) => $t->same(true, $plan228()['stat4SamplePartialPredicateFence']['allSampleRowsResolveToCurrentSource']),
    'planner stat4 expression partial current source next228 all keys match' => static fn (TestRunner $t) => $t->same(true, $plan228()['stat4SamplePartialPredicateFence']['allSampleExpressionKeysMatchCurrentRows']),
    'planner stat4 expression partial current source next228 all partial satisfied' => static fn (TestRunner $t) => $t->same(true, $plan228()['stat4SamplePartialPredicateFence']['allSampleRowsSatisfyCurrentPartialPredicate']),
    'planner stat4 expression partial current source next228 no missing' => static fn (TestRunner $t) => $t->same([], $plan228()['stat4SamplePartialPredicateFence']['missingCurrentSampleRowids']),
    'planner stat4 expression partial current source next228 no key rejects' => static fn (TestRunner $t) => $t->same([], $plan228()['stat4SamplePartialPredicateFence']['sampleRowidsRejectedByExpressionKey']),
    'planner stat4 expression partial current source next228 no partial rejects' => static fn (TestRunner $t) => $t->same([], $plan228()['stat4SamplePartialPredicateFence']['sampleRowidsRejectedByPartialPredicate']),
    'planner stat4 expression partial current source next228 term proof left keys' => static fn (TestRunner $t) => $t->same(['expression:lower(option_name)', 'expression:lower(option_name)', 'column:autoload', 'column:option_name'], array_column($plan228()['stat4SamplePartialPredicateFence']['sampleRowProofs'][0]['partialPredicateTermProofs'], 'leftKey')),
    'planner stat4 expression partial current source next228 term proof operators' => static fn (TestRunner $t) => $t->same(['>=', '<=', '=', 'IS NOT NULL'], array_column($plan228()['stat4SamplePartialPredicateFence']['sampleRowProofs'][0]['partialPredicateTermProofs'], 'operator')),
    'planner stat4 expression partial current source next228 term proof values' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_alpha', 'yes', 'plugin_alpha'], array_column($plan228()['stat4SamplePartialPredicateFence']['sampleRowProofs'][0]['partialPredicateTermProofs'], 'value')),
    'planner stat4 expression partial current source next228 term proof flags' => static fn (TestRunner $t) => $t->same([true, true, true, true], array_column($plan228()['stat4SamplePartialPredicateFence']['sampleRowProofs'][0]['partialPredicateTermProofs'], 'satisfied')),
    'planner stat4 expression partial current source next228 all proof flags' => static fn (TestRunner $t) => $t->same([true, true, true, true, true, true], array_column($plan228()['stat4SamplePartialPredicateFence']['sampleRowProofs'], 'satisfiesCurrentPartialPredicate')),
    'planner stat4 expression partial current source next228 expression key flags' => static fn (TestRunner $t) => $t->same([true, true, true, true, true, true], array_column($plan228()['stat4SamplePartialPredicateFence']['sampleRowProofs'], 'expressionKeyMatchesCurrentRow')),
    'planner stat4 expression partial current source next228 current present flags' => static fn (TestRunner $t) => $t->same([true, true, true, true, true, true], array_column($plan228()['stat4SamplePartialPredicateFence']['sampleRowProofs'], 'currentRowPresent')),
    'planner stat4 expression partial current source next228 selected missing none' => static fn (TestRunner $t) => $t->same([], $plan228()['selectedPlan']['next228MissingCurrentSampleRowids']),
    'planner stat4 expression partial current source next228 selected partial rejects none' => static fn (TestRunner $t) => $t->same([], $plan228()['selectedPlan']['next228RowsRejectedByPartialPredicate']),
    'planner stat4 expression partial current source next228 selected key rejects none' => static fn (TestRunner $t) => $t->same([], $plan228()['selectedPlan']['next228RowsRejectedByExpressionKey']),
    'planner stat4 expression partial current source next228 signature lengths' => static fn (TestRunner $t) => $t->same([64, 64], [strlen($plan228()['stat4SamplePartialPredicateFence']['samplePartialPredicateSignature']), strlen($plan228()['stat4SamplePartialPredicateFence']['proofSignature'])]),
    'planner stat4 expression partial current source next228 selected predicate signature' => static fn (TestRunner $t) => $t->same($plan228()['stat4SamplePartialPredicateFence']['samplePartialPredicateSignature'], $plan228()['selectedPlan']['next228SamplePartialPredicateSignature']),
    'planner stat4 expression partial current source next228 selected proof signature' => static fn (TestRunner $t) => $t->same($plan228()['stat4SamplePartialPredicateFence']['proofSignature'], $plan228()['selectedPlan']['next228ProofSignature']),
    'planner stat4 expression partial current source next228 stat4 predicate signature' => static fn (TestRunner $t) => $t->same($plan228()['stat4SamplePartialPredicateFence']['samplePartialPredicateSignature'], $plan228()['stat4Fence']['next228SamplePartialPredicateSignature']),
    'planner stat4 expression partial current source next228 stat4 proof signature' => static fn (TestRunner $t) => $t->same($plan228()['stat4SamplePartialPredicateFence']['proofSignature'], $plan228()['stat4Fence']['next228ProofSignature']),
    'planner stat4 expression partial current source next228 cursor appended' => static fn (TestRunner $t) => $t->same('RecheckCurrentStat4PartialSamples', $plan228()['cursorProgram'][array_key_last($plan228()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next228 cursor mode' => static fn (TestRunner $t) => $t->same('next228-current-source-stat4-expression-partial-sample-predicate', $plan228()['cursorProgram'][array_key_last($plan228()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next228 cursor rowids' => static fn (TestRunner $t) => $t->same([10, 40, 20, 50, 30, 60], $plan228()['cursorProgram'][array_key_last($plan228()['cursorProgram'])]['sampleRowids']),
    'planner stat4 expression partial current source next228 cursor matched rowids' => static fn (TestRunner $t) => $t->same([20, 50, 30], $plan228()['cursorProgram'][array_key_last($plan228()['cursorProgram'])]['matchedSampleRowids']),
    'planner stat4 expression partial current source next228 cursor signature' => static fn (TestRunner $t) => $t->same($plan228()['stat4SamplePartialPredicateFence']['proofSignature'], $plan228()['cursorProgram'][array_key_last($plan228()['cursorProgram'])]['signature']),
    'planner stat4 expression partial current source next228 sample order preserved' => static fn (TestRunner $t) => $t->same(true, $plan228()['stat4SampleOrderFence']['currentSamplesPreserveSelectedScanOrder']),
    'planner stat4 expression partial current source next228 matched rowids preserved' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan228()['matchedRowids']),
    'planner stat4 expression partial current source next228 projected payload retained' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan228()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next228 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next228', $plan228()['dependencies'], true)),
    'planner stat4 expression partial current source next228 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan228()['dependency_closure']),
    'planner stat4 expression partial current source next228 non overlap' => static fn (TestRunner $t) => $t->contains('sample-row partial-predicate validation', $plan228()['non_overlap']),
    'planner stat4 expression partial current source next228 detail' => static fn (TestRunner $t) => $t->contains('NEXT228 SAMPLE PARTIAL-PREDICATE FENCE', $plan228()['detail']),
    'planner stat4 expression partial current source next228 missing sample blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-partial-sample-reprepare', $missingSampleRow228()['status']),
    'planner stat4 expression partial current source next228 missing sample rowid' => static fn (TestRunner $t) => $t->same([50], $missingSampleRow228()['stat4SamplePartialPredicateFence']['missingCurrentSampleRowids']),
    'planner stat4 expression partial current source next228 missing sample selected' => static fn (TestRunner $t) => $t->same([50], $missingSampleRow228()['selectedPlan']['next228MissingCurrentSampleRowids']),
    'planner stat4 expression partial current source next228 missing no cursor' => static fn (TestRunner $t) => $t->same(false, in_array('RecheckCurrentStat4PartialSamples', array_column($missingSampleRow228()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next228 stale key blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-partial-sample-reprepare', $staleKey228()['status']),
    'planner stat4 expression partial current source next228 stale key rowid' => static fn (TestRunner $t) => $t->same([50], $staleKey228()['stat4SamplePartialPredicateFence']['sampleRowidsRejectedByExpressionKey']),
    'planner stat4 expression partial current source next228 stale key current key' => static fn (TestRunner $t) => $t->same('plugin_mail_renamed', $staleKey228()['stat4SamplePartialPredicateFence']['sampleRowProofs'][3]['currentExpressionKey']),
    'planner stat4 expression partial current source next228 stale key selected' => static fn (TestRunner $t) => $t->same([50], $staleKey228()['selectedPlan']['next228RowsRejectedByExpressionKey']),
    'planner stat4 expression partial current source next228 partial reject blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-partial-sample-reprepare', $partialReject228()['status']),
    'planner stat4 expression partial current source next228 partial reject rowid' => static fn (TestRunner $t) => $t->same([50], $partialReject228()['stat4SamplePartialPredicateFence']['sampleRowidsRejectedByPartialPredicate']),
    'planner stat4 expression partial current source next228 partial reject autoload flag' => static fn (TestRunner $t) => $t->same(false, $partialReject228()['stat4SamplePartialPredicateFence']['sampleRowProofs'][3]['partialPredicateTermProofs'][2]['satisfied']),
    'planner stat4 expression partial current source next228 partial reject selected' => static fn (TestRunner $t) => $t->same([50], $partialReject228()['selectedPlan']['next228RowsRejectedByPartialPredicate']),
    'planner stat4 expression partial current source next228 invalid current rows' => static function (TestRunner $t) use ($current228, $plan228): void {
        $bad = $current228();
        $bad['rows'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan228(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next228 invalid partial terms' => static function (TestRunner $t) use ($current228, $plan228): void {
        $bad = $current228();
        $bad['indexes'][0]['partialPredicateTerms'] = [];
        $t->throws(InvalidArgumentException::class, static fn () => $plan228(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next228 unsupported partial op' => static function (TestRunner $t) use ($current228, $plan228): void {
        $bad = $current228();
        $bad['indexes'][0]['partialPredicateTerms'][0]['operator'] = 'GLOB';
        $t->throws(InvalidArgumentException::class, static fn () => $plan228(5, 1, null, $bad));
    },
];

foreach (range(1, 18) as $case) {
    $tests['planner stat4 expression partial current source next228 repeated sample partial fence ' . $case] = static function (TestRunner $t) use ($plan228, $case): void {
        $plan = $plan228(1 + ($case % 5), $case % 4);
        $t->same($plan['stat4SamplePartialPredicateFence']['proofSignature'], $plan['selectedPlan']['next228ProofSignature']);
    };
}

return $tests;
