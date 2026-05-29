<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq224 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like224 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull224 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between224 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$payload224 = static fn (array $row): array => [
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

$prepared224 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-sample-order-next224',
    'schemaCookie' => 2240,
    'stat4Generation' => 224,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_stat4_sample_order_next224',
        'rootPage' => 22401,
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

$current224 = static function () use ($prepared224, $payload224): array {
    $source = $prepared224();
    $source['name'] = 'current-wp-options-stat4-sample-order-next224';
    $source['schemaCookie'] = 2249;
    $source['stat4Generation'] = 294;
    $source['indexes'][0]['rootPage'] = 22488;
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
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload224, array_slice($source['rows'], 0, 8));

    return $source;
};

$terms224 = static fn (): array => [
    $between224('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq224('autoload', 'yes'),
    $notNull224('option_name'),
    $eq224('blog_id', 1),
    $like224('option_name', 'plugin_%'),
];
$plan224 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext224(
    $prepared ?? $prepared224(),
    $current ?? $current224(),
    $terms ?? $terms224(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$missingSample224 = static function () use ($current224, $plan224): array {
    $current = $current224();
    $current['indexes'][0]['stat4Samples'] = array_values(array_filter(
        $current['indexes'][0]['stat4Samples'],
        static fn (array $sample): bool => ($sample['sample'][0] ?? null) !== 'plugin_mail',
    ));

    return $plan224(5, 1, null, $current);
};
$sampleOrder224 = static function () use ($current224, $plan224): array {
    $current = $current224();
    $current['indexes'][0]['stat4Samples'][3]['sample'] = ['plugin_seo', 30];
    $current['indexes'][0]['stat4Samples'][4]['sample'] = ['plugin_mail', 50];

    return $plan224(5, 1, null, $current);
};
$tests = [
    'planner stat4 expression partial current source next224 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next224-ready', $plan224()['status']),
    'planner stat4 expression partial current source next224 selected current' => static fn (TestRunner $t) => $t->same('current', $plan224()['selectedSource']),
    'planner stat4 expression partial current source next224 inherited next218' => static fn (TestRunner $t) => $t->same(true, $plan224()['selectedPlan']['next218Ready']),
    'planner stat4 expression partial current source next224 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan224()['selectedPlan']['next224Ready']),
    'planner stat4 expression partial current source next224 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_stat4_sample_order_next224', $plan224()['selectedPlan']['name']),
    'planner stat4 expression partial current source next224 root page' => static fn (TestRunner $t) => $t->same(22488, $plan224()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next224 sample count' => static fn (TestRunner $t) => $t->same(6, $plan224()['stat4SampleOrderFence']['sampleCount']),
    'planner stat4 expression partial current source next224 matched rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan224()['matchedRowids']),
    'planner stat4 expression partial current source next224 proof rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], array_column($plan224()['stat4SampleOrderFence']['matchedSampleProofs'], 'rowid')),
    'planner stat4 expression partial current source next224 proof keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_forms', 'plugin_forms'], array_column($plan224()['stat4SampleOrderFence']['matchedSampleProofs'], 'expressionKey')),
    'planner stat4 expression partial current source next224 proof ordinals' => static fn (TestRunner $t) => $t->same([4, 3, 2, 2, 2], array_column($plan224()['stat4SampleOrderFence']['matchedSampleProofs'], 'sampleOrdinal')),
    'planner stat4 expression partial current source next224 proof nlt' => static fn (TestRunner $t) => $t->same([6, 5, 2, 2, 2], array_column($plan224()['stat4SampleOrderFence']['matchedSampleProofs'], 'sampleNlt')),
    'planner stat4 expression partial current source next224 proof ndlt' => static fn (TestRunner $t) => $t->same([4, 3, 2, 2, 2], array_column($plan224()['stat4SampleOrderFence']['matchedSampleProofs'], 'sampleNdlt')),
    'planner stat4 expression partial current source next224 proof neq' => static fn (TestRunner $t) => $t->same([1, 1, 3, 3, 3], array_column($plan224()['stat4SampleOrderFence']['matchedSampleProofs'], 'sampleNeq')),
    'planner stat4 expression partial current source next224 proof relations' => static fn (TestRunner $t) => $t->same(['first', 'descending-stat4-sample', 'descending-stat4-sample', 'peer-rowid', 'peer-rowid'], array_column($plan224()['stat4SampleOrderFence']['matchedSampleProofs'], 'relationToPrevious')),
    'planner stat4 expression partial current source next224 proof ordered flags' => static fn (TestRunner $t) => $t->same([true, true, true, true, true], array_column($plan224()['stat4SampleOrderFence']['matchedSampleProofs'], 'preservesSelectedScanOrder')),
    'planner stat4 expression partial current source next224 all samples found' => static fn (TestRunner $t) => $t->same(true, $plan224()['stat4SampleOrderFence']['allMatchedExpressionKeysHaveCurrentSamples']),
    'planner stat4 expression partial current source next224 missing sample keys none' => static fn (TestRunner $t) => $t->same([], $plan224()['stat4SampleOrderFence']['expressionKeysMissingCurrentSamples']),
    'planner stat4 expression partial current source next224 scan order preserved' => static fn (TestRunner $t) => $t->same(true, $plan224()['stat4SampleOrderFence']['currentSamplesPreserveSelectedScanOrder']),
    'planner stat4 expression partial current source next224 peer order preserved' => static fn (TestRunner $t) => $t->same(true, $plan224()['stat4SampleOrderFence']['duplicateSamplePeersRemainInRowidOrder']),
    'planner stat4 expression partial current source next224 rejected none' => static fn (TestRunner $t) => $t->same([], $plan224()['stat4SampleOrderFence']['rowidsRejectedBySampleOrderFence']),
    'planner stat4 expression partial current source next224 selected missing none' => static fn (TestRunner $t) => $t->same([], $plan224()['selectedPlan']['next224MissingCurrentSampleKeys']),
    'planner stat4 expression partial current source next224 selected rejected none' => static fn (TestRunner $t) => $t->same([], $plan224()['selectedPlan']['next224RowsRejectedBySampleOrderFence']),
    'planner stat4 expression partial current source next224 sample signature selected' => static fn (TestRunner $t) => $t->same($plan224()['stat4SampleOrderFence']['sampleOrderSignature'], $plan224()['selectedPlan']['next224SampleOrderSignature']),
    'planner stat4 expression partial current source next224 proof signature selected' => static fn (TestRunner $t) => $t->same($plan224()['stat4SampleOrderFence']['proofSignature'], $plan224()['selectedPlan']['next224SampleProofSignature']),
    'planner stat4 expression partial current source next224 sample signature stat4' => static fn (TestRunner $t) => $t->same($plan224()['stat4SampleOrderFence']['sampleOrderSignature'], $plan224()['stat4Fence']['next224SampleOrderSignature']),
    'planner stat4 expression partial current source next224 proof signature stat4' => static fn (TestRunner $t) => $t->same($plan224()['stat4SampleOrderFence']['proofSignature'], $plan224()['stat4Fence']['next224SampleProofSignature']),
    'planner stat4 expression partial current source next224 signature lengths' => static fn (TestRunner $t) => $t->same([64, 64], [strlen($plan224()['stat4SampleOrderFence']['sampleOrderSignature']), strlen($plan224()['stat4SampleOrderFence']['proofSignature'])]),
    'planner stat4 expression partial current source next224 cursor appended' => static fn (TestRunner $t) => $t->same('RecheckCurrentStat4SampleOrder', $plan224()['cursorProgram'][array_key_last($plan224()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next224 cursor mode' => static fn (TestRunner $t) => $t->same('next224-current-source-stat4-expression-partial-sample-order', $plan224()['cursorProgram'][array_key_last($plan224()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next224 cursor rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan224()['cursorProgram'][array_key_last($plan224()['cursorProgram'])]['rowids']),
    'planner stat4 expression partial current source next224 cursor ordinals' => static fn (TestRunner $t) => $t->same([4, 3, 2, 2, 2], $plan224()['cursorProgram'][array_key_last($plan224()['cursorProgram'])]['sampleOrdinals']),
    'planner stat4 expression partial current source next224 payload fence preserved' => static fn (TestRunner $t) => $t->same(true, $plan224()['expressionPayloadFence']['allMatchedRowsHaveCurrentExpressionPayload']),
    'planner stat4 expression partial current source next224 covering preserved' => static fn (TestRunner $t) => $t->same(true, $plan224()['expressionPayloadFence']['allNeededColumnsCoveredByCurrentIndex']),
    'planner stat4 expression partial current source next224 grouped like preserved' => static fn (TestRunner $t) => $t->same(true, $plan224()['groupedLikePredicateFence']['currentGroupedLikePredicateImplied']),
    'planner stat4 expression partial current source next224 grouped or preserved' => static fn (TestRunner $t) => $t->same(true, $plan224()['groupedPartialOrPredicateFence']['currentGroupedPartialOrPredicateImplied']),
    'planner stat4 expression partial current source next224 projected payload retained' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan224()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next224 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next224', $plan224()['dependencies'], true)),
    'planner stat4 expression partial current source next224 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan224()['dependency_closure']),
    'planner stat4 expression partial current source next224 non overlap' => static fn (TestRunner $t) => $t->contains('sample-order validation', $plan224()['non_overlap']),
    'planner stat4 expression partial current source next224 detail' => static fn (TestRunner $t) => $t->contains('NEXT224 SAMPLE ORDER FENCE', $plan224()['detail']),
    'planner stat4 expression partial current source next224 missing sample blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-sample-order-reprepare', $missingSample224()['status']),
    'planner stat4 expression partial current source next224 missing sample key' => static fn (TestRunner $t) => $t->same(['plugin_mail'], $missingSample224()['stat4SampleOrderFence']['expressionKeysMissingCurrentSamples']),
    'planner stat4 expression partial current source next224 missing sample selected' => static fn (TestRunner $t) => $t->same(['plugin_mail'], $missingSample224()['selectedPlan']['next224MissingCurrentSampleKeys']),
    'planner stat4 expression partial current source next224 swapped sample blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-sample-order-reprepare', $sampleOrder224()['status']),
    'planner stat4 expression partial current source next224 swapped sample rejected rowid' => static fn (TestRunner $t) => $t->same([50], $sampleOrder224()['stat4SampleOrderFence']['rowidsRejectedBySampleOrderFence']),
    'planner stat4 expression partial current source next224 swapped sample relation' => static fn (TestRunner $t) => $t->same('out-of-order-current-stat4-sample', $sampleOrder224()['stat4SampleOrderFence']['matchedSampleProofs'][1]['relationToPrevious']),
    'planner stat4 expression partial current source next224 no cursor when blocked' => static fn (TestRunner $t) => $t->same(false, in_array('RecheckCurrentStat4SampleOrder', array_column($sampleOrder224()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next224 invalid samples' => static function (TestRunner $t) use ($current224, $plan224): void {
        $bad = $current224();
        $bad['indexes'][0]['stat4Samples'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan224(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next224 invalid sample entry' => static function (TestRunner $t) use ($current224, $plan224): void {
        $bad = $current224();
        $bad['indexes'][0]['stat4Samples'][0]['sample'] = ['plugin_alpha'];
        $t->throws(InvalidArgumentException::class, static fn () => $plan224(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next224 invalid stat int' => static function (TestRunner $t) use ($current224, $plan224): void {
        $bad = $current224();
        $bad['indexes'][0]['stat4Samples'][0]['nlt'] = 'x';
        $t->throws(InvalidArgumentException::class, static fn () => $plan224(5, 1, null, $bad));
    },
];

foreach (range(1, 18) as $case) {
    $tests['planner stat4 expression partial current source next224 repeated sample fence ' . $case] = static function (TestRunner $t) use ($plan224, $case): void {
        $plan = $plan224(1 + ($case % 5), $case % 4);
        $t->same(count($plan['matchedRows']), count($plan['stat4SampleOrderFence']['matchedSampleProofs']));
    };
}

return $tests;
