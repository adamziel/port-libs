<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq229 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like229 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull229 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between229 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$payload229 = static fn (array $row): array => [
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

$prepared229 = static fn (): array => [
    'name' => 'prepared-app-settings-stat4-selectivity-selectivity',
    'schemaCookie' => 2290,
    'stat4Generation' => 229,
    'rows' => [
        ['rowid' => 10, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'module_alpha', 'key_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'module_forms', 'key_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'tenant_id' => 1, 'load_policy' => 'eager', 'key_name' => 'module_seo', 'key_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_app_settings_stat4_selectivity_selectivity',
        'rootPage' => 22901,
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

$current229 = static function () use ($prepared229, $payload229): array {
    $source = $prepared229();
    $source['name'] = 'current-app-settings-stat4-selectivity-selectivity';
    $source['schemaCookie'] = 2299;
    $source['stat4Generation'] = 309;
    $source['indexes'][0]['rootPage'] = 22988;
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
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload229, array_slice($source['rows'], 0, 8));

    return $source;
};

$terms229 = static fn (): array => [
    $between229('LOWER(key_name)', 'module_alpha', 'module_zulu'),
    $eq229('load_policy', 'eager'),
    $notNull229('key_name'),
    $eq229('tenant_id', 1),
    $like229('key_name', 'module_%'),
];
$plan229 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4ExpressionPartialSelectivity(
    $prepared ?? $prepared229(),
    $current ?? $current229(),
    $terms ?? $terms229(),
    $needed ?? ['key_name', 'key_value', 'updated_at', 'tenant_id'],
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
    'planner stat4 expression partial current source selectivity status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-selectivity-ready', $plan229()['status']),
    'planner stat4 expression partial current source selectivity selected current' => static fn (TestRunner $t) => $t->same('current', $plan229()['selectedSource']),
    'planner stat4 expression partial current source selectivity inherited next224' => static fn (TestRunner $t) => $t->same(true, $plan229()['selectedPlan']['next224Ready']),
    'planner stat4 expression partial current source selectivity ready flag' => static fn (TestRunner $t) => $t->same(true, $plan229()['selectedPlan']['selectivityReady']),
    'planner stat4 expression partial current source selectivity selected index' => static fn (TestRunner $t) => $t->same('idx_app_settings_stat4_selectivity_selectivity', $plan229()['selectedPlan']['name']),
    'planner stat4 expression partial current source selectivity root page' => static fn (TestRunner $t) => $t->same(22988, $plan229()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source selectivity matched rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan229()['matchedRowids']),
    'planner stat4 expression partial current source selectivity estimated rows' => static fn (TestRunner $t) => $t->same(9, $plan229()['stat4SelectivityFence']['estimatedRows']),
    'planner stat4 expression partial current source selectivity max nlt' => static fn (TestRunner $t) => $t->same(6, $plan229()['stat4SelectivityFence']['maxSampleNlt']),
    'planner stat4 expression partial current source selectivity max neq' => static fn (TestRunner $t) => $t->same(3, $plan229()['stat4SelectivityFence']['maxSampleNeq']),
    'planner stat4 expression partial current source selectivity matched count' => static fn (TestRunner $t) => $t->same(5, $plan229()['stat4SelectivityFence']['matchedRowCount']),
    'planner stat4 expression partial current source selectivity limit' => static fn (TestRunner $t) => $t->same(5, $plan229()['stat4SelectivityFence']['limit']),
    'planner stat4 expression partial current source selectivity offset' => static fn (TestRunner $t) => $t->same(1, $plan229()['stat4SelectivityFence']['offset']),
    'planner stat4 expression partial current source selectivity page window' => static fn (TestRunner $t) => $t->same(['start' => 1, 'end' => 6], $plan229()['stat4SelectivityFence']['pageWindow']),
    'planner stat4 expression partial current source selectivity selected estimated rows' => static fn (TestRunner $t) => $t->same(9, $plan229()['selectedPlan']['selectivityEstimatedRows']),
    'planner stat4 expression partial current source selectivity selected matched rows' => static fn (TestRunner $t) => $t->same(5, $plan229()['selectedPlan']['selectivityMatchedRows']),
    'planner stat4 expression partial current source selectivity selected page window' => static fn (TestRunner $t) => $t->same(['start' => 1, 'end' => 6], $plan229()['selectedPlan']['selectivityPageWindow']),
    'planner stat4 expression partial current source selectivity brackets matched' => static fn (TestRunner $t) => $t->same(true, $plan229()['stat4SelectivityFence']['currentStat4CardinalityBracketsMatchedRows']),
    'planner stat4 expression partial current source selectivity window in estimate' => static fn (TestRunner $t) => $t->same(true, $plan229()['stat4SelectivityFence']['pageWindowWithinCurrentStat4Estimate']),
    'planner stat4 expression partial current source selectivity peer counts cover' => static fn (TestRunner $t) => $t->same(true, $plan229()['stat4SelectivityFence']['samplePeerCountsCoverMatchedPeers']),
    'planner stat4 expression partial current source selectivity rejected none' => static fn (TestRunner $t) => $t->same([], $plan229()['stat4SelectivityFence']['rowidsRejectedBySelectivityFence']),
    'planner stat4 expression partial current source selectivity selected rejected none' => static fn (TestRunner $t) => $t->same([], $plan229()['selectedPlan']['selectivityRowsRejectedBySelectivityFence']),
    'planner stat4 expression partial current source selectivity peer keys' => static fn (TestRunner $t) => $t->same(['module_seo', 'module_mail', 'module_forms'], array_column($plan229()['stat4SelectivityFence']['peerSelectivityProofs'], 'expressionKey')),
    'planner stat4 expression partial current source selectivity peer counts' => static fn (TestRunner $t) => $t->same([1, 1, 3], array_column($plan229()['stat4SelectivityFence']['peerSelectivityProofs'], 'matchedPeerCount')),
    'planner stat4 expression partial current source selectivity peer neq' => static fn (TestRunner $t) => $t->same([1, 1, 3], array_column($plan229()['stat4SelectivityFence']['peerSelectivityProofs'], 'sampleNeq')),
    'planner stat4 expression partial current source selectivity peer flags' => static fn (TestRunner $t) => $t->same([true, true, true], array_column($plan229()['stat4SelectivityFence']['peerSelectivityProofs'], 'sampleCoversPeers')),
    'planner stat4 expression partial current source selectivity forms peer rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22], $plan229()['stat4SelectivityFence']['peerSelectivityProofs'][2]['matchedPeerRowids']),
    'planner stat4 expression partial current source selectivity signatures length' => static fn (TestRunner $t) => $t->same([64, 64], [strlen($plan229()['stat4SelectivityFence']['selectivitySignature']), strlen($plan229()['stat4SelectivityFence']['proofSignature'])]),
    'planner stat4 expression partial current source selectivity selected signature' => static fn (TestRunner $t) => $t->same($plan229()['stat4SelectivityFence']['selectivitySignature'], $plan229()['selectedPlan']['selectivitySignature']),
    'planner stat4 expression partial current source selectivity stat4 signature' => static fn (TestRunner $t) => $t->same($plan229()['stat4SelectivityFence']['selectivitySignature'], $plan229()['stat4Fence']['selectivitySignature']),
    'planner stat4 expression partial current source selectivity stat4 proof signature' => static fn (TestRunner $t) => $t->same($plan229()['stat4SelectivityFence']['proofSignature'], $plan229()['stat4Fence']['selectivityProofSignature']),
    'planner stat4 expression partial current source selectivity cursor appended' => static fn (TestRunner $t) => $t->same('RecheckCurrentStat4Selectivity', $plan229()['cursorProgram'][array_key_last($plan229()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source selectivity cursor mode' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-selectivity', $plan229()['cursorProgram'][array_key_last($plan229()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source selectivity cursor estimated rows' => static fn (TestRunner $t) => $t->same(9, $plan229()['cursorProgram'][array_key_last($plan229()['cursorProgram'])]['estimatedRows']),
    'planner stat4 expression partial current source selectivity cursor matched rows' => static fn (TestRunner $t) => $t->same(5, $plan229()['cursorProgram'][array_key_last($plan229()['cursorProgram'])]['matchedRows']),
    'planner stat4 expression partial current source selectivity cursor page window' => static fn (TestRunner $t) => $t->same(['start' => 1, 'end' => 6], $plan229()['cursorProgram'][array_key_last($plan229()['cursorProgram'])]['pageWindow']),
    'planner stat4 expression partial current source selectivity sample order preserved' => static fn (TestRunner $t) => $t->same(true, $plan229()['stat4SampleOrderFence']['currentSamplesPreserveSelectedScanOrder']),
    'planner stat4 expression partial current source selectivity payload fence preserved' => static fn (TestRunner $t) => $t->same(true, $plan229()['expressionPayloadFence']['allMatchedRowsHaveCurrentExpressionPayload']),
    'planner stat4 expression partial current source selectivity covering preserved' => static fn (TestRunner $t) => $t->same(true, $plan229()['expressionPayloadFence']['allNeededColumnsCoveredByCurrentIndex']),
    'planner stat4 expression partial current source selectivity grouped like preserved' => static fn (TestRunner $t) => $t->same(true, $plan229()['groupedLikePredicateFence']['currentGroupedLikePredicateImplied']),
    'planner stat4 expression partial current source selectivity projected payload retained' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan229()['projectedRows'][4]['key_value']),
    'planner stat4 expression partial current source selectivity dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-selectivity', $plan229()['dependencies'], true)),
    'planner stat4 expression partial current source selectivity dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan229()['dependency_closure']),
    'planner stat4 expression partial current source selectivity non overlap' => static fn (TestRunner $t) => $t->contains('selectivity and peer-count cardinality', $plan229()['non_overlap']),
    'planner stat4 expression partial current source selectivity detail' => static fn (TestRunner $t) => $t->contains('SELECTIVITY FENCE', $plan229()['detail']),
    'planner stat4 expression partial current source selectivity underestimated blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-selectivity-reprepare', $underestimated229()['status']),
    'planner stat4 expression partial current source selectivity underestimated estimate' => static fn (TestRunner $t) => $t->same(2, $underestimated229()['stat4SelectivityFence']['estimatedRows']),
    'planner stat4 expression partial current source selectivity underestimated rejects rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $underestimated229()['stat4SelectivityFence']['rowidsRejectedBySelectivityFence']),
    'planner stat4 expression partial current source selectivity underestimated no cursor append' => static fn (TestRunner $t) => $t->same(false, in_array('RecheckCurrentStat4Selectivity', array_column($underestimated229()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source selectivity peer underestimate blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-selectivity-reprepare', $peerUnderestimated229()['status']),
    'planner stat4 expression partial current source selectivity peer underestimate flag' => static fn (TestRunner $t) => $t->same(false, $peerUnderestimated229()['stat4SelectivityFence']['samplePeerCountsCoverMatchedPeers']),
    'planner stat4 expression partial current source selectivity peer underestimate rejected rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22], $peerUnderestimated229()['stat4SelectivityFence']['rowidsRejectedBySelectivityFence']),
    'planner stat4 expression partial current source selectivity peer underestimate proof' => static fn (TestRunner $t) => $t->same(false, $peerUnderestimated229()['stat4SelectivityFence']['peerSelectivityProofs'][2]['sampleCoversPeers']),
    'planner stat4 expression partial current source selectivity zero limit rejects empty proof' => static function (TestRunner $t) use ($plan229): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan229(0, 0));
    },
    'planner stat4 expression partial current source selectivity tail window blocked by order fence' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-selectivity-reprepare', $plan229(2, 5)['status']),
    'planner stat4 expression partial current source selectivity tail window' => static fn (TestRunner $t) => $t->same(['start' => 5, 'end' => 7], $plan229(2, 5)['stat4SelectivityFence']['pageWindow']),
    'planner stat4 expression partial current source selectivity invalid negative limit' => static function (TestRunner $t) use ($plan229): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan229(-1, 0));
    },
    'planner stat4 expression partial current source selectivity invalid negative offset' => static function (TestRunner $t) use ($plan229): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan229(1, -1));
    },
];

foreach (range(1, 20) as $case) {
    $tests['planner stat4 expression partial current source selectivity repeated selectivity fence ' . $case] = static function (TestRunner $t) use ($plan229, $case): void {
        $plan = $plan229(1 + ($case % 5), $case % 4);
        $t->same($plan['stat4SelectivityFence']['selectivitySignature'], $plan['selectedPlan']['selectivitySignature']);
    };
}

return $tests;
