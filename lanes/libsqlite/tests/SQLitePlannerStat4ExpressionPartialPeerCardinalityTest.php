<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq227 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like227 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull227 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between227 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$payload227 = static fn (array $row): array => [
    'rowid' => $row['rowid'],
    'expressionKey' => strtolower((string) $row['key_name']),
    'coveredValues' => [
        'key_name' => $row['key_name'],
        'key_value' => $row['key_value'],
        'updated_at' => $row['updated_at'],
        'tenant_id' => $row['tenant_id'],
        'load_policy' => $row['load_policy'],
    ],
];

$prepared227 = static fn (): array => [
    'name' => 'prepared-app-settings-stat4-peer-cardinality-peerCardinality',
    'schemaCookie' => 2270,
    'stat4Generation' => 227,
    'rows' => [
        ['rowid' => 10, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'module_alpha', 'key_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'module_forms', 'key_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'module_seo', 'key_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_app_settings_stat4_peer_cardinality_peerCardinality',
        'rootPage' => 22701,
        'expression' => 'lower(key_name)',
        'expressionColumn' => '__expr_lower_key_name',
        'collation' => 'BINARY',
        'descending' => true,
        'partialPredicateTerms' => [
            ['left' => ['expression' => 'lower(key_name)'], 'operator' => '>=', 'right' => 'module_alpha'],
            ['left' => ['expression' => 'lower(key_name)'], 'operator' => '<=', 'right' => 'module_zulu'],
            ['left' => ['column' => 'load_policy'], 'operator' => '=', 'right' => 'eager'],
            ['left' => ['column' => 'key_name'], 'operator' => 'IS NOT NULL'],
        ],
        'partialGroupedOrPredicateArms' => [
            [
                ['left' => ['column' => 'tenant_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'load_policy'], 'operator' => '=', 'right' => 'eager'],
            ],
            [
                ['left' => ['column' => 'load_policy'], 'operator' => '=', 'right' => 'critical'],
            ],
        ],
        'partialGroupedLikePredicateArms' => [
            [
                ['left' => ['column' => 'tenant_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'key_name'], 'operator' => 'LIKE', 'right' => 'module_%'],
            ],
            [
                ['left' => ['column' => 'key_name'], 'operator' => 'LIKE', 'right' => 'tenant_%'],
            ],
        ],
        'coveringColumns' => ['key_name', 'key_value', 'updated_at', 'load_policy', 'tenant_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['module_alpha', 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['module_forms', 20]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['module_seo', 30]],
        ],
        'stat4ExpressionPayloads' => [],
    ]],
];

$current227 = static function (array $overrides = []) use ($prepared227, $payload227): array {
    $source = $prepared227();
    $source['name'] = 'current-app-settings-stat4-peer-cardinality-peerCardinality';
    $source['schemaCookie'] = 2279;
    $source['stat4Generation'] = 297;
    $source['indexes'][0]['rootPage'] = 22788;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['module_alpha', 10]],
        ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['module_cache', 40]],
        ['neq' => '3 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['module_forms', 20]],
        ['neq' => '1 1', 'nlt' => '5 3', 'ndlt' => '3 3', 'sample' => ['module_mail', 50]],
        ['neq' => '1 1', 'nlt' => '6 4', 'ndlt' => '4 4', 'sample' => ['module_seo', 30]],
        ['neq' => '1 1', 'nlt' => '7 5', 'ndlt' => '5 5', 'sample' => ['module_zulu', 60]],
    ];
    $source['rows'] = [
        ['rowid' => 60, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'module_zulu', 'key_value' => 'zulu', 'updated_at' => 60],
        ['rowid' => 30, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'module_seo', 'key_value' => 'seo', 'updated_at' => 30],
        ['rowid' => 50, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'Module_Mail', 'key_value' => 'mail', 'updated_at' => 50],
        ['rowid' => 20, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'module_forms', 'key_value' => 'forms-anchor', 'updated_at' => 20],
        ['rowid' => 21, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'Module_Forms', 'key_value' => 'forms-copy-a', 'updated_at' => 21],
        ['rowid' => 22, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'MODULE_FORMS', 'key_value' => 'forms-copy-b', 'updated_at' => 22],
        ['rowid' => 40, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'module_cache', 'key_value' => 'cache', 'updated_at' => 40],
        ['rowid' => 10, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'module_alpha', 'key_value' => 'alpha', 'updated_at' => 10],
        ['rowid' => 70, 'tenant_id' => 1, 'load_policy' => 'lazy', 'key_name' => 'module_forms', 'key_value' => 'lazy', 'updated_at' => 70],
        ['rowid' => 80, 'tenant_id' => 2, 'load_policy' => 'eager', 'key_name' => 'module_forms', 'key_value' => 'other-tenant', 'updated_at' => 80],
    ];
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload227, array_slice($source['rows'], 0, 8));
    foreach ($overrides as $key => $value) {
        $source[$key] = $value;
    }

    return $source;
};

$terms227 = static fn (): array => [
    $between227('LOWER(key_name)', 'module_alpha', 'module_zulu'),
    $eq227('load_policy', 'eager'),
    $notNull227('key_name'),
    $eq227('tenant_id', 1),
    $like227('key_name', 'module_%'),
];
$plan227 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4ExpressionPartialPeerCardinality(
    $prepared ?? $prepared227(),
    $current ?? $current227(),
    $terms ?? $terms227(),
    $needed ?? ['key_name', 'key_value', 'updated_at', 'tenant_id'],
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
            if (!$removedOneFormsPeer && ($payload['expressionKey'] ?? null) === 'module_forms') {
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
    'planner stat4 expression partial current source peerCardinality status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-peer-cardinality-ready', $plan227()['status']),
    'planner stat4 expression partial current source peerCardinality selected current' => static fn (TestRunner $t) => $t->same('current', $plan227()['selectedSource']),
    'planner stat4 expression partial current source peerCardinality inherited next224' => static fn (TestRunner $t) => $t->same(true, $plan227()['selectedPlan']['next224Ready']),
    'planner stat4 expression partial current source peerCardinality ready flag' => static fn (TestRunner $t) => $t->same(true, $plan227()['selectedPlan']['peerCardinalityReady']),
    'planner stat4 expression partial current source peerCardinality selected index' => static fn (TestRunner $t) => $t->same('idx_app_settings_stat4_peer_cardinality_peerCardinality', $plan227()['selectedPlan']['name']),
    'planner stat4 expression partial current source peerCardinality root page' => static fn (TestRunner $t) => $t->same(22788, $plan227()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source peerCardinality matched rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan227()['matchedRowids']),
    'planner stat4 expression partial current source peerCardinality payload peer counts' => static fn (TestRunner $t) => $t->same(['module_alpha' => 1, 'module_cache' => 1, 'module_forms' => 3, 'module_mail' => 1, 'module_seo' => 1, 'module_zulu' => 1], $plan227()['stat4PeerCardinalityFence']['payloadPeerCounts']),
    'planner stat4 expression partial current source peerCardinality selected peer keys' => static fn (TestRunner $t) => $t->same(['module_seo', 'module_mail', 'module_forms'], array_column($plan227()['stat4PeerCardinalityFence']['selectedPeerCounts'], 'expressionKey')),
    'planner stat4 expression partial current source peerCardinality stat4 neq' => static fn (TestRunner $t) => $t->same([1, 1, 3], array_column($plan227()['stat4PeerCardinalityFence']['selectedPeerCounts'], 'stat4Neq')),
    'planner stat4 expression partial current source peerCardinality payload counts' => static fn (TestRunner $t) => $t->same([1, 1, 3], array_column($plan227()['stat4PeerCardinalityFence']['selectedPeerCounts'], 'currentPayloadPeerCount')),
    'planner stat4 expression partial current source peerCardinality selected ordinals' => static fn (TestRunner $t) => $t->same([4, 3, 2], array_column($plan227()['stat4PeerCardinalityFence']['selectedPeerCounts'], 'sampleOrdinal')),
    'planner stat4 expression partial current source peerCardinality selected nlt' => static fn (TestRunner $t) => $t->same([6, 5, 2], array_column($plan227()['stat4PeerCardinalityFence']['selectedPeerCounts'], 'sampleNlt')),
    'planner stat4 expression partial current source peerCardinality selected ndlt' => static fn (TestRunner $t) => $t->same([4, 3, 2], array_column($plan227()['stat4PeerCardinalityFence']['selectedPeerCounts'], 'sampleNdlt')),
    'planner stat4 expression partial current source peerCardinality selected match flags' => static fn (TestRunner $t) => $t->same([true, true, true], array_column($plan227()['stat4PeerCardinalityFence']['selectedPeerCounts'], 'matchesCurrentPayloadPeers')),
    'planner stat4 expression partial current source peerCardinality all counts match' => static fn (TestRunner $t) => $t->same(true, $plan227()['stat4PeerCardinalityFence']['allSelectedSamplePeerCountsMatchCurrentPayloads']),
    'planner stat4 expression partial current source peerCardinality stale keys none' => static fn (TestRunner $t) => $t->same([], $plan227()['stat4PeerCardinalityFence']['expressionKeysWithStalePeerCounts']),
    'planner stat4 expression partial current source peerCardinality selected stale keys none' => static fn (TestRunner $t) => $t->same([], $plan227()['selectedPlan']['peerCardinalityStalePeerCountKeys']),
    'planner stat4 expression partial current source peerCardinality selected count rows' => static fn (TestRunner $t) => $t->same($plan227()['stat4PeerCardinalityFence']['selectedPeerCounts'], $plan227()['selectedPlan']['peerCardinalitySelectedPeerCounts']),
    'planner stat4 expression partial current source peerCardinality signature lengths' => static fn (TestRunner $t) => $t->same([64, 64], [strlen($plan227()['stat4PeerCardinalityFence']['peerCardinalitySignature']), strlen($plan227()['stat4PeerCardinalityFence']['proofSignature'])]),
    'planner stat4 expression partial current source peerCardinality selected signature' => static fn (TestRunner $t) => $t->same($plan227()['stat4PeerCardinalityFence']['peerCardinalitySignature'], $plan227()['selectedPlan']['peerCardinalitySignature']),
    'planner stat4 expression partial current source peerCardinality selected proof signature' => static fn (TestRunner $t) => $t->same($plan227()['stat4PeerCardinalityFence']['proofSignature'], $plan227()['selectedPlan']['peerCardinalityProofSignature']),
    'planner stat4 expression partial current source peerCardinality stat4 signature' => static fn (TestRunner $t) => $t->same($plan227()['stat4PeerCardinalityFence']['peerCardinalitySignature'], $plan227()['stat4Fence']['peerCardinalitySignature']),
    'planner stat4 expression partial current source peerCardinality stat4 proof signature' => static fn (TestRunner $t) => $t->same($plan227()['stat4PeerCardinalityFence']['proofSignature'], $plan227()['stat4Fence']['peerCardinalityProofSignature']),
    'planner stat4 expression partial current source peerCardinality cursor appended' => static fn (TestRunner $t) => $t->same('RecheckCurrentStat4PeerCardinality', $plan227()['cursorProgram'][array_key_last($plan227()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source peerCardinality cursor mode' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-peer-cardinality', $plan227()['cursorProgram'][array_key_last($plan227()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source peerCardinality cursor counts' => static fn (TestRunner $t) => $t->same($plan227()['stat4PeerCardinalityFence']['selectedPeerCounts'], $plan227()['cursorProgram'][array_key_last($plan227()['cursorProgram'])]['selectedPeerCounts']),
    'planner stat4 expression partial current source peerCardinality cursor signature' => static fn (TestRunner $t) => $t->same($plan227()['stat4PeerCardinalityFence']['proofSignature'], $plan227()['cursorProgram'][array_key_last($plan227()['cursorProgram'])]['signature']),
    'planner stat4 expression partial current source peerCardinality sample order preserved' => static fn (TestRunner $t) => $t->same(true, $plan227()['stat4SampleOrderFence']['currentSamplesPreserveSelectedScanOrder']),
    'planner stat4 expression partial current source peerCardinality payload fence preserved' => static fn (TestRunner $t) => $t->same(true, $plan227()['expressionPayloadFence']['allMatchedRowsHaveCurrentExpressionPayload']),
    'planner stat4 expression partial current source peerCardinality covering preserved' => static fn (TestRunner $t) => $t->same(true, $plan227()['expressionPayloadFence']['allNeededColumnsCoveredByCurrentIndex']),
    'planner stat4 expression partial current source peerCardinality grouped like preserved' => static fn (TestRunner $t) => $t->same(true, $plan227()['groupedLikePredicateFence']['currentGroupedLikePredicateImplied']),
    'planner stat4 expression partial current source peerCardinality grouped or preserved' => static fn (TestRunner $t) => $t->same(true, $plan227()['groupedPartialOrPredicateFence']['currentGroupedPartialOrPredicateImplied']),
    'planner stat4 expression partial current source peerCardinality projected payload retained' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan227()['projectedRows'][4]['key_value']),
    'planner stat4 expression partial current source peerCardinality dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-peer-cardinality', $plan227()['dependencies'], true)),
    'planner stat4 expression partial current source peerCardinality dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan227()['dependency_closure']),
    'planner stat4 expression partial current source peerCardinality non overlap' => static fn (TestRunner $t) => $t->contains('peer-cardinality validation', $plan227()['non_overlap']),
    'planner stat4 expression partial current source peerCardinality detail' => static fn (TestRunner $t) => $t->contains('PEER CARDINALITY FENCE', $plan227()['detail']),
    'planner stat4 expression partial current source peerCardinality stale neq blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-peer-cardinality-reprepare', $staleNeq227()['status']),
    'planner stat4 expression partial current source peerCardinality stale neq key' => static fn (TestRunner $t) => $t->same(['module_forms'], $staleNeq227()['stat4PeerCardinalityFence']['expressionKeysWithStalePeerCounts']),
    'planner stat4 expression partial current source peerCardinality stale neq selected key' => static fn (TestRunner $t) => $t->same(['module_forms'], $staleNeq227()['selectedPlan']['peerCardinalityStalePeerCountKeys']),
    'planner stat4 expression partial current source peerCardinality stale neq mismatch row' => static fn (TestRunner $t) => $t->same(['expressionKey' => 'module_forms', 'stat4Neq' => 2, 'currentPayloadPeerCount' => 3, 'matchesCurrentPayloadPeers' => false, 'sampleOrdinal' => 2, 'sampleNlt' => 2, 'sampleNdlt' => 2], $staleNeq227()['stat4PeerCardinalityFence']['selectedPeerCounts'][2]),
    'planner stat4 expression partial current source peerCardinality stale payload blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-peer-cardinality-reprepare', $stalePayload227()['status']),
    'planner stat4 expression partial current source peerCardinality stale payload key' => static fn (TestRunner $t) => $t->same(['module_forms'], $stalePayload227()['stat4PeerCardinalityFence']['expressionKeysWithStalePeerCounts']),
    'planner stat4 expression partial current source peerCardinality stale payload count' => static fn (TestRunner $t) => $t->same(2, $stalePayload227()['stat4PeerCardinalityFence']['payloadPeerCounts']['module_forms'] ?? 0),
    'planner stat4 expression partial current source peerCardinality no cursor when blocked' => static fn (TestRunner $t) => $t->same(false, in_array('RecheckCurrentStat4PeerCardinality', array_column($staleNeq227()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source peerCardinality unproved blocked before peer cardinality' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-peer-cardinality-reprepare', $unproved227()['status']),
    'planner stat4 expression partial current source peerCardinality unproved ready false' => static fn (TestRunner $t) => $t->same(false, $unproved227()['selectedPlan']['peerCardinalityReady']),
    'planner stat4 expression partial current source peerCardinality first page ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-peer-cardinality-ready', $plan227(3, 0)['status']),
    'planner stat4 expression partial current source peerCardinality first page keys' => static fn (TestRunner $t) => $t->same(['module_zulu', 'module_seo', 'module_mail'], array_column($plan227(3, 0)['stat4PeerCardinalityFence']['selectedPeerCounts'], 'expressionKey')),
    'planner stat4 expression partial current source peerCardinality peer-only page ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-peer-cardinality-ready', $plan227(3, 3)['status']),
    'planner stat4 expression partial current source peerCardinality peer-only page key' => static fn (TestRunner $t) => $t->same(['module_forms'], array_column($plan227(3, 3)['stat4PeerCardinalityFence']['selectedPeerCounts'], 'expressionKey')),
    'planner stat4 expression partial current source peerCardinality invalid payload list' => static function (TestRunner $t) use ($current227, $plan227): void {
        $bad = $current227();
        $bad['indexes'][0]['stat4ExpressionPayloads'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan227(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source peerCardinality invalid payload entry' => static function (TestRunner $t) use ($current227, $plan227): void {
        $bad = $current227();
        $bad['indexes'][0]['stat4ExpressionPayloads'][0] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan227(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source peerCardinality invalid payload key' => static function (TestRunner $t) use ($current227, $plan227): void {
        $bad = $current227();
        unset($bad['indexes'][0]['stat4ExpressionPayloads'][0]['expressionKey'], $bad['indexes'][0]['stat4ExpressionPayloads'][0]['coveredValues']);
        $t->throws(InvalidArgumentException::class, static fn () => $plan227(5, 1, null, $bad));
    },
];

foreach (range(1, 18) as $case) {
    $tests['planner stat4 expression partial current source peerCardinality repeated peer cardinality fence ' . $case] = static function (TestRunner $t) use ($plan227, $case): void {
        $plan = $plan227(1 + ($case % 5), $case % 4);
        $t->same($plan['stat4PeerCardinalityFence']['peerCardinalitySignature'], $plan['selectedPlan']['peerCardinalitySignature']);
    };
}

return $tests;
