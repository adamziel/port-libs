<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNext227Plan;

$eq227 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like227 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull227 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between227 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$payload227 = static fn (array $row): array => [
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

$prepared227 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-peer-cardinality-next227',
    'schemaCookie' => 2270,
    'stat4Generation' => 227,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_stat4_peer_cardinality_next227',
        'rootPage' => 22701,
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

$current227 = static function (array $overrides = []) use ($prepared227, $payload227): array {
    $source = $prepared227();
    $source['name'] = 'current-wp-options-stat4-peer-cardinality-next227';
    $source['schemaCookie'] = 2279;
    $source['stat4Generation'] = 297;
    $source['indexes'][0]['rootPage'] = 22788;
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
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload227, array_slice($source['rows'], 0, 8));
    foreach ($overrides as $key => $value) {
        $source[$key] = $value;
    }

    return $source;
};

$terms227 = static fn (): array => [
    $between227('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq227('autoload', 'yes'),
    $notNull227('option_name'),
    $eq227('blog_id', 1),
    $like227('option_name', 'plugin_%'),
];
$plan227 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNext227Plan::materialize(
    $prepared ?? $prepared227(),
    $current ?? $current227(),
    $terms ?? $terms227(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$staleNeq227 = static function () use ($current227, $plan227): array {
    $current = $current227();
    $current['indexes'][0]['stat4Samples'][2]['neq'] = '2 1';

    return $plan227(5, 1, null, $current);
};
$stalePayload227 = static function () use ($current227, $plan227): array {
    $current = $current227();
    $removedOneFormsPeer = false;
    $current['indexes'][0]['stat4ExpressionPayloads'] = array_values(array_filter(
        $current['indexes'][0]['stat4ExpressionPayloads'],
        static function (array $payload) use (&$removedOneFormsPeer): bool {
            if (!$removedOneFormsPeer && ($payload['expressionKey'] ?? null) === 'plugin_forms') {
                $removedOneFormsPeer = true;

                return false;
            }

            return true;
        },
    ));

    return $plan227(5, 1, null, $current);
};
$unproved227 = static function () use ($terms227, $plan227): array {
    $terms = array_values(array_filter($terms227(), static fn (array $term): bool => ($term['operator'] ?? null) !== 'LIKE'));

    return $plan227(5, 1, null, null, $terms);
};

$tests = [
    'planner stat4 expression partial current source next227 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next227-ready', $plan227()['status']),
    'planner stat4 expression partial current source next227 selected current' => static fn (TestRunner $t) => $t->same('current', $plan227()['selectedSource']),
    'planner stat4 expression partial current source next227 inherited next224' => static fn (TestRunner $t) => $t->same(true, $plan227()['selectedPlan']['next224Ready']),
    'planner stat4 expression partial current source next227 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan227()['selectedPlan']['next227Ready']),
    'planner stat4 expression partial current source next227 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_stat4_peer_cardinality_next227', $plan227()['selectedPlan']['name']),
    'planner stat4 expression partial current source next227 root page' => static fn (TestRunner $t) => $t->same(22788, $plan227()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next227 matched rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan227()['matchedRowids']),
    'planner stat4 expression partial current source next227 payload peer counts' => static fn (TestRunner $t) => $t->same(['plugin_alpha' => 1, 'plugin_cache' => 1, 'plugin_forms' => 3, 'plugin_mail' => 1, 'plugin_seo' => 1, 'plugin_zulu' => 1], $plan227()['stat4PeerCardinalityFence']['payloadPeerCounts']),
    'planner stat4 expression partial current source next227 selected peer keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms'], array_column($plan227()['stat4PeerCardinalityFence']['selectedPeerCounts'], 'expressionKey')),
    'planner stat4 expression partial current source next227 stat4 neq' => static fn (TestRunner $t) => $t->same([1, 1, 3], array_column($plan227()['stat4PeerCardinalityFence']['selectedPeerCounts'], 'stat4Neq')),
    'planner stat4 expression partial current source next227 payload counts' => static fn (TestRunner $t) => $t->same([1, 1, 3], array_column($plan227()['stat4PeerCardinalityFence']['selectedPeerCounts'], 'currentPayloadPeerCount')),
    'planner stat4 expression partial current source next227 selected ordinals' => static fn (TestRunner $t) => $t->same([4, 3, 2], array_column($plan227()['stat4PeerCardinalityFence']['selectedPeerCounts'], 'sampleOrdinal')),
    'planner stat4 expression partial current source next227 selected nlt' => static fn (TestRunner $t) => $t->same([6, 5, 2], array_column($plan227()['stat4PeerCardinalityFence']['selectedPeerCounts'], 'sampleNlt')),
    'planner stat4 expression partial current source next227 selected ndlt' => static fn (TestRunner $t) => $t->same([4, 3, 2], array_column($plan227()['stat4PeerCardinalityFence']['selectedPeerCounts'], 'sampleNdlt')),
    'planner stat4 expression partial current source next227 selected match flags' => static fn (TestRunner $t) => $t->same([true, true, true], array_column($plan227()['stat4PeerCardinalityFence']['selectedPeerCounts'], 'matchesCurrentPayloadPeers')),
    'planner stat4 expression partial current source next227 all counts match' => static fn (TestRunner $t) => $t->same(true, $plan227()['stat4PeerCardinalityFence']['allSelectedSamplePeerCountsMatchCurrentPayloads']),
    'planner stat4 expression partial current source next227 stale keys none' => static fn (TestRunner $t) => $t->same([], $plan227()['stat4PeerCardinalityFence']['expressionKeysWithStalePeerCounts']),
    'planner stat4 expression partial current source next227 selected stale keys none' => static fn (TestRunner $t) => $t->same([], $plan227()['selectedPlan']['next227StalePeerCountKeys']),
    'planner stat4 expression partial current source next227 selected count rows' => static fn (TestRunner $t) => $t->same($plan227()['stat4PeerCardinalityFence']['selectedPeerCounts'], $plan227()['selectedPlan']['next227SelectedPeerCounts']),
    'planner stat4 expression partial current source next227 signature lengths' => static fn (TestRunner $t) => $t->same([64, 64], [strlen($plan227()['stat4PeerCardinalityFence']['peerCardinalitySignature']), strlen($plan227()['stat4PeerCardinalityFence']['proofSignature'])]),
    'planner stat4 expression partial current source next227 selected signature' => static fn (TestRunner $t) => $t->same($plan227()['stat4PeerCardinalityFence']['peerCardinalitySignature'], $plan227()['selectedPlan']['next227PeerCardinalitySignature']),
    'planner stat4 expression partial current source next227 selected proof signature' => static fn (TestRunner $t) => $t->same($plan227()['stat4PeerCardinalityFence']['proofSignature'], $plan227()['selectedPlan']['next227PeerCardinalityProofSignature']),
    'planner stat4 expression partial current source next227 stat4 signature' => static fn (TestRunner $t) => $t->same($plan227()['stat4PeerCardinalityFence']['peerCardinalitySignature'], $plan227()['stat4Fence']['next227PeerCardinalitySignature']),
    'planner stat4 expression partial current source next227 stat4 proof signature' => static fn (TestRunner $t) => $t->same($plan227()['stat4PeerCardinalityFence']['proofSignature'], $plan227()['stat4Fence']['next227PeerCardinalityProofSignature']),
    'planner stat4 expression partial current source next227 cursor appended' => static fn (TestRunner $t) => $t->same('RecheckCurrentStat4PeerCardinality', $plan227()['cursorProgram'][array_key_last($plan227()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next227 cursor mode' => static fn (TestRunner $t) => $t->same('next227-current-source-stat4-expression-partial-peer-cardinality', $plan227()['cursorProgram'][array_key_last($plan227()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next227 cursor counts' => static fn (TestRunner $t) => $t->same($plan227()['stat4PeerCardinalityFence']['selectedPeerCounts'], $plan227()['cursorProgram'][array_key_last($plan227()['cursorProgram'])]['selectedPeerCounts']),
    'planner stat4 expression partial current source next227 cursor signature' => static fn (TestRunner $t) => $t->same($plan227()['stat4PeerCardinalityFence']['proofSignature'], $plan227()['cursorProgram'][array_key_last($plan227()['cursorProgram'])]['signature']),
    'planner stat4 expression partial current source next227 sample order preserved' => static fn (TestRunner $t) => $t->same(true, $plan227()['stat4SampleOrderFence']['currentSamplesPreserveSelectedScanOrder']),
    'planner stat4 expression partial current source next227 payload fence preserved' => static fn (TestRunner $t) => $t->same(true, $plan227()['expressionPayloadFence']['allMatchedRowsHaveCurrentExpressionPayload']),
    'planner stat4 expression partial current source next227 covering preserved' => static fn (TestRunner $t) => $t->same(true, $plan227()['expressionPayloadFence']['allNeededColumnsCoveredByCurrentIndex']),
    'planner stat4 expression partial current source next227 grouped like preserved' => static fn (TestRunner $t) => $t->same(true, $plan227()['groupedLikePredicateFence']['currentGroupedLikePredicateImplied']),
    'planner stat4 expression partial current source next227 grouped or preserved' => static fn (TestRunner $t) => $t->same(true, $plan227()['groupedPartialOrPredicateFence']['currentGroupedPartialOrPredicateImplied']),
    'planner stat4 expression partial current source next227 projected payload retained' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan227()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next227 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next227', $plan227()['dependencies'], true)),
    'planner stat4 expression partial current source next227 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan227()['dependency_closure']),
    'planner stat4 expression partial current source next227 non overlap' => static fn (TestRunner $t) => $t->contains('peer-cardinality validation', $plan227()['non_overlap']),
    'planner stat4 expression partial current source next227 detail' => static fn (TestRunner $t) => $t->contains('NEXT227 PEER CARDINALITY FENCE', $plan227()['detail']),
    'planner stat4 expression partial current source next227 stale neq blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-peer-cardinality-reprepare', $staleNeq227()['status']),
    'planner stat4 expression partial current source next227 stale neq key' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $staleNeq227()['stat4PeerCardinalityFence']['expressionKeysWithStalePeerCounts']),
    'planner stat4 expression partial current source next227 stale neq selected key' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $staleNeq227()['selectedPlan']['next227StalePeerCountKeys']),
    'planner stat4 expression partial current source next227 stale neq mismatch row' => static fn (TestRunner $t) => $t->same(['expressionKey' => 'plugin_forms', 'stat4Neq' => 2, 'currentPayloadPeerCount' => 3, 'matchesCurrentPayloadPeers' => false, 'sampleOrdinal' => 2, 'sampleNlt' => 2, 'sampleNdlt' => 2], $staleNeq227()['stat4PeerCardinalityFence']['selectedPeerCounts'][2]),
    'planner stat4 expression partial current source next227 stale payload blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-peer-cardinality-reprepare', $stalePayload227()['status']),
    'planner stat4 expression partial current source next227 stale payload key' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $stalePayload227()['stat4PeerCardinalityFence']['expressionKeysWithStalePeerCounts']),
    'planner stat4 expression partial current source next227 stale payload count' => static fn (TestRunner $t) => $t->same(2, $stalePayload227()['stat4PeerCardinalityFence']['payloadPeerCounts']['plugin_forms'] ?? 0),
    'planner stat4 expression partial current source next227 no cursor when blocked' => static fn (TestRunner $t) => $t->same(false, in_array('RecheckCurrentStat4PeerCardinality', array_column($staleNeq227()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next227 unproved blocked before peer cardinality' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-peer-cardinality-reprepare', $unproved227()['status']),
    'planner stat4 expression partial current source next227 unproved ready false' => static fn (TestRunner $t) => $t->same(false, $unproved227()['selectedPlan']['next227Ready']),
    'planner stat4 expression partial current source next227 first page ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next227-ready', $plan227(3, 0)['status']),
    'planner stat4 expression partial current source next227 first page keys' => static fn (TestRunner $t) => $t->same(['plugin_zulu', 'plugin_seo', 'plugin_mail'], array_column($plan227(3, 0)['stat4PeerCardinalityFence']['selectedPeerCounts'], 'expressionKey')),
    'planner stat4 expression partial current source next227 peer-only page ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next227-ready', $plan227(3, 3)['status']),
    'planner stat4 expression partial current source next227 peer-only page key' => static fn (TestRunner $t) => $t->same(['plugin_forms'], array_column($plan227(3, 3)['stat4PeerCardinalityFence']['selectedPeerCounts'], 'expressionKey')),
    'planner stat4 expression partial current source next227 invalid payload list' => static function (TestRunner $t) use ($current227, $plan227): void {
        $bad = $current227();
        $bad['indexes'][0]['stat4ExpressionPayloads'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan227(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next227 invalid payload entry' => static function (TestRunner $t) use ($current227, $plan227): void {
        $bad = $current227();
        $bad['indexes'][0]['stat4ExpressionPayloads'][0] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan227(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next227 invalid payload key' => static function (TestRunner $t) use ($current227, $plan227): void {
        $bad = $current227();
        unset($bad['indexes'][0]['stat4ExpressionPayloads'][0]['expressionKey'], $bad['indexes'][0]['stat4ExpressionPayloads'][0]['coveredValues']);
        $t->throws(InvalidArgumentException::class, static fn () => $plan227(5, 1, null, $bad));
    },
];

foreach (range(1, 18) as $case) {
    $tests['planner stat4 expression partial current source next227 repeated peer cardinality fence ' . $case] = static function (TestRunner $t) use ($plan227, $case): void {
        $plan = $plan227(1 + ($case % 5), $case % 4);
        $t->same($plan['stat4PeerCardinalityFence']['peerCardinalitySignature'], $plan['selectedPlan']['next227PeerCardinalitySignature']);
    };
}

return $tests;
