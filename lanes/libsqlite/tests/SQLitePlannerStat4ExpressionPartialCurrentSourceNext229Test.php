<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq229 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like229 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull229 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between229 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$payload229 = static fn (array $row): array => [
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

$prepared229 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-selectivity-next229',
    'schemaCookie' => 2290,
    'stat4Generation' => 229,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_stat4_selectivity_next229',
        'rootPage' => 22901,
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

$current229 = static function () use ($prepared229, $payload229): array {
    $source = $prepared229();
    $source['name'] = 'current-wp-options-stat4-selectivity-next229';
    $source['schemaCookie'] = 2299;
    $source['stat4Generation'] = 309;
    $source['indexes'][0]['rootPage'] = 22988;
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
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload229, array_slice($source['rows'], 0, 8));

    return $source;
};

$terms229 = static fn (): array => [
    $between229('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq229('autoload', 'yes'),
    $notNull229('option_name'),
    $eq229('blog_id', 1),
    $like229('option_name', 'plugin_%'),
];
$plan229 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext229(
    $prepared ?? $prepared229(),
    $current ?? $current229(),
    $terms ?? $terms229(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$underestimated229 = static function () use ($current229, $plan229): array {
    $current = $current229();
    foreach ($current['indexes'][0]['stat4Samples'] as &$sample) {
        $sample['nlt'] = '1 1';
        $sample['neq'] = '1 1';
    }
    unset($sample);

    return $plan229(5, 1, null, $current);
};
$peerUnderestimated229 = static function () use ($current229, $plan229): array {
    $current = $current229();
    $current['indexes'][0]['stat4Samples'][2]['neq'] = '2 1';

    return $plan229(5, 1, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next229 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next229-ready', $plan229()['status']),
    'planner stat4 expression partial current source next229 selected current' => static fn (TestRunner $t) => $t->same('current', $plan229()['selectedSource']),
    'planner stat4 expression partial current source next229 inherited next224' => static fn (TestRunner $t) => $t->same(true, $plan229()['selectedPlan']['next224Ready']),
    'planner stat4 expression partial current source next229 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan229()['selectedPlan']['next229Ready']),
    'planner stat4 expression partial current source next229 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_stat4_selectivity_next229', $plan229()['selectedPlan']['name']),
    'planner stat4 expression partial current source next229 root page' => static fn (TestRunner $t) => $t->same(22988, $plan229()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next229 matched rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan229()['matchedRowids']),
    'planner stat4 expression partial current source next229 estimated rows' => static fn (TestRunner $t) => $t->same(9, $plan229()['stat4SelectivityFence']['estimatedRows']),
    'planner stat4 expression partial current source next229 max nlt' => static fn (TestRunner $t) => $t->same(6, $plan229()['stat4SelectivityFence']['maxSampleNlt']),
    'planner stat4 expression partial current source next229 max neq' => static fn (TestRunner $t) => $t->same(3, $plan229()['stat4SelectivityFence']['maxSampleNeq']),
    'planner stat4 expression partial current source next229 matched count' => static fn (TestRunner $t) => $t->same(5, $plan229()['stat4SelectivityFence']['matchedRowCount']),
    'planner stat4 expression partial current source next229 limit' => static fn (TestRunner $t) => $t->same(5, $plan229()['stat4SelectivityFence']['limit']),
    'planner stat4 expression partial current source next229 offset' => static fn (TestRunner $t) => $t->same(1, $plan229()['stat4SelectivityFence']['offset']),
    'planner stat4 expression partial current source next229 page window' => static fn (TestRunner $t) => $t->same(['start' => 1, 'end' => 6], $plan229()['stat4SelectivityFence']['pageWindow']),
    'planner stat4 expression partial current source next229 selected estimated rows' => static fn (TestRunner $t) => $t->same(9, $plan229()['selectedPlan']['next229EstimatedRows']),
    'planner stat4 expression partial current source next229 selected matched rows' => static fn (TestRunner $t) => $t->same(5, $plan229()['selectedPlan']['next229MatchedRows']),
    'planner stat4 expression partial current source next229 selected page window' => static fn (TestRunner $t) => $t->same(['start' => 1, 'end' => 6], $plan229()['selectedPlan']['next229PageWindow']),
    'planner stat4 expression partial current source next229 brackets matched' => static fn (TestRunner $t) => $t->same(true, $plan229()['stat4SelectivityFence']['currentStat4CardinalityBracketsMatchedRows']),
    'planner stat4 expression partial current source next229 window in estimate' => static fn (TestRunner $t) => $t->same(true, $plan229()['stat4SelectivityFence']['pageWindowWithinCurrentStat4Estimate']),
    'planner stat4 expression partial current source next229 peer counts cover' => static fn (TestRunner $t) => $t->same(true, $plan229()['stat4SelectivityFence']['samplePeerCountsCoverMatchedPeers']),
    'planner stat4 expression partial current source next229 rejected none' => static fn (TestRunner $t) => $t->same([], $plan229()['stat4SelectivityFence']['rowidsRejectedBySelectivityFence']),
    'planner stat4 expression partial current source next229 selected rejected none' => static fn (TestRunner $t) => $t->same([], $plan229()['selectedPlan']['next229RowsRejectedBySelectivityFence']),
    'planner stat4 expression partial current source next229 peer keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms'], array_column($plan229()['stat4SelectivityFence']['peerSelectivityProofs'], 'expressionKey')),
    'planner stat4 expression partial current source next229 peer counts' => static fn (TestRunner $t) => $t->same([1, 1, 3], array_column($plan229()['stat4SelectivityFence']['peerSelectivityProofs'], 'matchedPeerCount')),
    'planner stat4 expression partial current source next229 peer neq' => static fn (TestRunner $t) => $t->same([1, 1, 3], array_column($plan229()['stat4SelectivityFence']['peerSelectivityProofs'], 'sampleNeq')),
    'planner stat4 expression partial current source next229 peer flags' => static fn (TestRunner $t) => $t->same([true, true, true], array_column($plan229()['stat4SelectivityFence']['peerSelectivityProofs'], 'sampleCoversPeers')),
    'planner stat4 expression partial current source next229 forms peer rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22], $plan229()['stat4SelectivityFence']['peerSelectivityProofs'][2]['matchedPeerRowids']),
    'planner stat4 expression partial current source next229 signatures length' => static fn (TestRunner $t) => $t->same([64, 64], [strlen($plan229()['stat4SelectivityFence']['selectivitySignature']), strlen($plan229()['stat4SelectivityFence']['proofSignature'])]),
    'planner stat4 expression partial current source next229 selected signature' => static fn (TestRunner $t) => $t->same($plan229()['stat4SelectivityFence']['selectivitySignature'], $plan229()['selectedPlan']['next229SelectivitySignature']),
    'planner stat4 expression partial current source next229 stat4 signature' => static fn (TestRunner $t) => $t->same($plan229()['stat4SelectivityFence']['selectivitySignature'], $plan229()['stat4Fence']['next229SelectivitySignature']),
    'planner stat4 expression partial current source next229 stat4 proof signature' => static fn (TestRunner $t) => $t->same($plan229()['stat4SelectivityFence']['proofSignature'], $plan229()['stat4Fence']['next229ProofSignature']),
    'planner stat4 expression partial current source next229 cursor appended' => static fn (TestRunner $t) => $t->same('RecheckCurrentStat4Selectivity', $plan229()['cursorProgram'][array_key_last($plan229()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next229 cursor mode' => static fn (TestRunner $t) => $t->same('next229-current-source-stat4-expression-partial-selectivity', $plan229()['cursorProgram'][array_key_last($plan229()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next229 cursor estimated rows' => static fn (TestRunner $t) => $t->same(9, $plan229()['cursorProgram'][array_key_last($plan229()['cursorProgram'])]['estimatedRows']),
    'planner stat4 expression partial current source next229 cursor matched rows' => static fn (TestRunner $t) => $t->same(5, $plan229()['cursorProgram'][array_key_last($plan229()['cursorProgram'])]['matchedRows']),
    'planner stat4 expression partial current source next229 cursor page window' => static fn (TestRunner $t) => $t->same(['start' => 1, 'end' => 6], $plan229()['cursorProgram'][array_key_last($plan229()['cursorProgram'])]['pageWindow']),
    'planner stat4 expression partial current source next229 sample order preserved' => static fn (TestRunner $t) => $t->same(true, $plan229()['stat4SampleOrderFence']['currentSamplesPreserveSelectedScanOrder']),
    'planner stat4 expression partial current source next229 payload fence preserved' => static fn (TestRunner $t) => $t->same(true, $plan229()['expressionPayloadFence']['allMatchedRowsHaveCurrentExpressionPayload']),
    'planner stat4 expression partial current source next229 covering preserved' => static fn (TestRunner $t) => $t->same(true, $plan229()['expressionPayloadFence']['allNeededColumnsCoveredByCurrentIndex']),
    'planner stat4 expression partial current source next229 grouped like preserved' => static fn (TestRunner $t) => $t->same(true, $plan229()['groupedLikePredicateFence']['currentGroupedLikePredicateImplied']),
    'planner stat4 expression partial current source next229 projected payload retained' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan229()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next229 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next229', $plan229()['dependencies'], true)),
    'planner stat4 expression partial current source next229 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan229()['dependency_closure']),
    'planner stat4 expression partial current source next229 non overlap' => static fn (TestRunner $t) => $t->contains('selectivity and peer-count cardinality', $plan229()['non_overlap']),
    'planner stat4 expression partial current source next229 detail' => static fn (TestRunner $t) => $t->contains('NEXT229 SELECTIVITY FENCE', $plan229()['detail']),
    'planner stat4 expression partial current source next229 underestimated blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-selectivity-reprepare', $underestimated229()['status']),
    'planner stat4 expression partial current source next229 underestimated estimate' => static fn (TestRunner $t) => $t->same(2, $underestimated229()['stat4SelectivityFence']['estimatedRows']),
    'planner stat4 expression partial current source next229 underestimated rejects rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $underestimated229()['stat4SelectivityFence']['rowidsRejectedBySelectivityFence']),
    'planner stat4 expression partial current source next229 underestimated no cursor append' => static fn (TestRunner $t) => $t->same(false, in_array('RecheckCurrentStat4Selectivity', array_column($underestimated229()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next229 peer underestimate blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-selectivity-reprepare', $peerUnderestimated229()['status']),
    'planner stat4 expression partial current source next229 peer underestimate flag' => static fn (TestRunner $t) => $t->same(false, $peerUnderestimated229()['stat4SelectivityFence']['samplePeerCountsCoverMatchedPeers']),
    'planner stat4 expression partial current source next229 peer underestimate rejected rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22], $peerUnderestimated229()['stat4SelectivityFence']['rowidsRejectedBySelectivityFence']),
    'planner stat4 expression partial current source next229 peer underestimate proof' => static fn (TestRunner $t) => $t->same(false, $peerUnderestimated229()['stat4SelectivityFence']['peerSelectivityProofs'][2]['sampleCoversPeers']),
    'planner stat4 expression partial current source next229 zero limit rejects empty proof' => static function (TestRunner $t) use ($plan229): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan229(0, 0));
    },
    'planner stat4 expression partial current source next229 tail window blocked by order fence' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-selectivity-reprepare', $plan229(2, 5)['status']),
    'planner stat4 expression partial current source next229 tail window' => static fn (TestRunner $t) => $t->same(['start' => 5, 'end' => 7], $plan229(2, 5)['stat4SelectivityFence']['pageWindow']),
    'planner stat4 expression partial current source next229 invalid negative limit' => static function (TestRunner $t) use ($plan229): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan229(-1, 0));
    },
    'planner stat4 expression partial current source next229 invalid negative offset' => static function (TestRunner $t) use ($plan229): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan229(1, -1));
    },
];

foreach (range(1, 20) as $case) {
    $tests['planner stat4 expression partial current source next229 repeated selectivity fence ' . $case] = static function (TestRunner $t) use ($plan229, $case): void {
        $plan = $plan229(1 + ($case % 5), $case % 4);
        $t->same($plan['stat4SelectivityFence']['selectivitySignature'], $plan['selectedPlan']['next229SelectivitySignature']);
    };
}

return $tests;
