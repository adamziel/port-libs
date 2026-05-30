<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq219 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like219 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull219 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between219 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared219 = static fn (): array => [
    'name' => 'prepared-wp-options-peer-partial-stat4-expression-next219',
    'schemaCookie' => 2190,
    'stat4Generation' => 219,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_peer_stat4_next219',
        'rootPage' => 21901,
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

$current219 = static function (array $overrides = []) use ($prepared219): array {
    $source = $prepared219();
    $source['name'] = 'current-wp-options-peer-partial-stat4-expression-next219';
    $source['schemaCookie'] = 2198;
    $source['stat4Generation'] = 298;
    $source['indexes'][0]['rootPage'] = 21988;
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

$terms219 = static fn (): array => [
    $between219('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq219('autoload', 'yes'),
    $notNull219('option_name'),
    $eq219('blog_id', 1),
    $like219('option_name', 'plugin_%'),
];
$plan219 = static fn (int $limit = 4, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4PeerRunYieldFence(
    $prepared ?? $prepared219(),
    $current ?? $current219(),
    $terms ?? $terms219(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$unproved219 = static function () use ($terms219, $plan219): array {
    $terms = array_values(array_filter($terms219(), static fn (array $term): bool => ($term['operator'] ?? null) !== 'LIKE'));

    return $plan219(4, 1, null, null, $terms);
};

$tests = [
    'planner stat4 expression partial current source next219 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next219-ready', $plan219()['status']),
    'planner stat4 expression partial current source next219 selected current' => static fn (TestRunner $t) => $t->same('current', $plan219()['selectedSource']),
    'planner stat4 expression partial current source next219 inherited next217' => static fn (TestRunner $t) => $t->same(true, $plan219()['selectedPlan']['next217Ready']),
    'planner stat4 expression partial current source next219 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan219()['selectedPlan']['next219Ready']),
    'planner stat4 expression partial current source next219 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_peer_stat4_next219', $plan219()['selectedPlan']['name']),
    'planner stat4 expression partial current source next219 root page' => static fn (TestRunner $t) => $t->same(21988, $plan219()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next219 matched rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21], $plan219()['matchedRowids']),
    'planner stat4 expression partial current source next219 boundary key' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan219()['stat4PeerRunFence']['boundaryKey']),
    'planner stat4 expression partial current source next219 page rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21], $plan219()['stat4PeerRunFence']['pageRowids']),
    'planner stat4 expression partial current source next219 lookahead rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan219()['stat4PeerRunFence']['lookaheadRowids']),
    'planner stat4 expression partial current source next219 boundary rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22], $plan219()['stat4PeerRunFence']['boundaryPeerRowids']),
    'planner stat4 expression partial current source next219 boundary page rowids' => static fn (TestRunner $t) => $t->same([20, 21], $plan219()['stat4PeerRunFence']['boundaryPeerRowidsOnPage']),
    'planner stat4 expression partial current source next219 boundary remaining rowids' => static fn (TestRunner $t) => $t->same([22], $plan219()['stat4PeerRunFence']['boundaryPeerRowidsAfterPage']),
    'planner stat4 expression partial current source next219 boundary continues' => static fn (TestRunner $t) => $t->same(true, $plan219()['stat4PeerRunFence']['boundaryContinuesAfterPage']),
    'planner stat4 expression partial current source next219 next continues peer run' => static fn (TestRunner $t) => $t->same(true, $plan219()['stat4PeerRunFence']['nextRowContinuesBoundaryPeerRun']),
    'planner stat4 expression partial current source next219 next rowid' => static fn (TestRunner $t) => $t->same(22, $plan219()['stat4PeerRunFence']['nextRowid']),
    'planner stat4 expression partial current source next219 next key' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan219()['stat4PeerRunFence']['nextKey']),
    'planner stat4 expression partial current source next219 rejected none' => static fn (TestRunner $t) => $t->same([], $plan219()['stat4PeerRunFence']['rowidsRejectedByPeerFence']),
    'planner stat4 expression partial current source next219 selected boundary key' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan219()['selectedPlan']['next219BoundaryKey']),
    'planner stat4 expression partial current source next219 selected boundary rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22], $plan219()['selectedPlan']['next219BoundaryRowids']),
    'planner stat4 expression partial current source next219 selected boundary page' => static fn (TestRunner $t) => $t->same([20, 21], $plan219()['selectedPlan']['next219BoundaryPageRowids']),
    'planner stat4 expression partial current source next219 selected boundary remaining' => static fn (TestRunner $t) => $t->same([22], $plan219()['selectedPlan']['next219BoundaryRemainingRowids']),
    'planner stat4 expression partial current source next219 signatures length' => static fn (TestRunner $t) => $t->same([64, 64], [strlen($plan219()['stat4PeerRunFence']['peerRunSignature']), strlen($plan219()['stat4PeerRunFence']['proofSignature'])]),
    'planner stat4 expression partial current source next219 selected signature' => static fn (TestRunner $t) => $t->same($plan219()['stat4PeerRunFence']['peerRunSignature'], $plan219()['selectedPlan']['next219PeerRunSignature']),
    'planner stat4 expression partial current source next219 selected proof signature' => static fn (TestRunner $t) => $t->same($plan219()['stat4PeerRunFence']['proofSignature'], $plan219()['selectedPlan']['next219ProofSignature']),
    'planner stat4 expression partial current source next219 stat4 signature' => static fn (TestRunner $t) => $t->same($plan219()['stat4PeerRunFence']['peerRunSignature'], $plan219()['stat4Fence']['next219PeerRunSignature']),
    'planner stat4 expression partial current source next219 stat4 proof signature' => static fn (TestRunner $t) => $t->same($plan219()['stat4PeerRunFence']['proofSignature'], $plan219()['stat4Fence']['next219ProofSignature']),
    'planner stat4 expression partial current source next219 peer row count' => static fn (TestRunner $t) => $t->same(3, count($plan219()['stat4PeerRunFence']['boundaryPeerRows'])),
    'planner stat4 expression partial current source next219 peer positions' => static fn (TestRunner $t) => $t->same([2, 3, 4], array_column($plan219()['stat4PeerRunFence']['boundaryPeerRows'], 'position')),
    'planner stat4 expression partial current source next219 peer on page flags' => static fn (TestRunner $t) => $t->same([true, true, false], array_column($plan219()['stat4PeerRunFence']['boundaryPeerRows'], 'onPage')),
    'planner stat4 expression partial current source next219 peer after flags' => static fn (TestRunner $t) => $t->same([false, false, true], array_column($plan219()['stat4PeerRunFence']['boundaryPeerRows'], 'afterPage')),
    'planner stat4 expression partial current source next219 cursor appended' => static fn (TestRunner $t) => $t->same('RecheckCurrentSourceStat4PeerRun', $plan219()['cursorProgram'][array_key_last($plan219()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next219 cursor mode' => static fn (TestRunner $t) => $t->same('next219-current-source-stat4-expression-partial-peer-run-yield', $plan219()['cursorProgram'][array_key_last($plan219()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next219 cursor boundary key' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan219()['cursorProgram'][array_key_last($plan219()['cursorProgram'])]['boundaryKey']),
    'planner stat4 expression partial current source next219 cursor boundary rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22], $plan219()['cursorProgram'][array_key_last($plan219()['cursorProgram'])]['boundaryPeerRowids']),
    'planner stat4 expression partial current source next219 cursor remaining rowids' => static fn (TestRunner $t) => $t->same([22], $plan219()['cursorProgram'][array_key_last($plan219()['cursorProgram'])]['boundaryPeerRowidsAfterPage']),
    'planner stat4 expression partial current source next219 projected payload' => static fn (TestRunner $t) => $t->same('forms-copy-a', $plan219()['projectedRows'][3]['option_value']),
    'planner stat4 expression partial current source next219 grouped like preserved' => static fn (TestRunner $t) => $t->same(true, $plan219()['groupedLikePredicateFence']['currentGroupedLikePredicateImplied']),
    'planner stat4 expression partial current source next219 grouped or preserved' => static fn (TestRunner $t) => $t->same(true, $plan219()['groupedPartialOrPredicateFence']['currentGroupedPartialOrPredicateImplied']),
    'planner stat4 expression partial current source next219 payload fence preserved' => static fn (TestRunner $t) => $t->same(true, $plan219()['payloadExpressionFence']['allPayloadExpressionKeysMatch']),
    'planner stat4 expression partial current source next219 partial fence preserved' => static fn (TestRunner $t) => $t->same(true, $plan219()['partialPredicateFence']['currentPartialPredicateImplied']),
    'planner stat4 expression partial current source next219 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next219', $plan219()['dependencies'], true)),
    'planner stat4 expression partial current source next219 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan219()['dependency_closure']),
    'planner stat4 expression partial current source next219 non overlap' => static fn (TestRunner $t) => $t->contains('duplicate expression-key peer runs', $plan219()['non_overlap']),
    'planner stat4 expression partial current source next219 detail' => static fn (TestRunner $t) => $t->contains('NEXT219 PEER-RUN YIELD FENCE', $plan219()['detail']),
    'planner stat4 expression partial current source next219 full peer page ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next219-ready', $plan219(5, 1)['status']),
    'planner stat4 expression partial current source next219 full peer page no remaining' => static fn (TestRunner $t) => $t->same([], $plan219(5, 1)['stat4PeerRunFence']['boundaryPeerRowidsAfterPage']),
    'planner stat4 expression partial current source next219 tail page ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next219-ready', $plan219(3, 4)['status']),
    'planner stat4 expression partial current source next219 tail page no continuation' => static fn (TestRunner $t) => $t->same(false, $plan219(3, 4)['stat4PeerRunFence']['boundaryContinuesAfterPage']),
    'planner stat4 expression partial current source next219 unproved blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-peer-run-reprepare', $unproved219()['status']),
    'planner stat4 expression partial current source next219 unproved ready false' => static fn (TestRunner $t) => $t->same(false, $unproved219()['selectedPlan']['next219Ready']),
    'planner stat4 expression partial current source next219 unproved no cursor append' => static fn (TestRunner $t) => $t->same(false, in_array('RecheckCurrentSourceStat4PeerRun', array_column($unproved219()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next219 invalid negative limit' => static function (TestRunner $t) use ($plan219): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan219(-1, 0));
    },
    'planner stat4 expression partial current source next219 invalid negative offset' => static function (TestRunner $t) use ($plan219): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan219(1, -1));
    },
];

foreach (range(1, 18) as $case) {
    $tests['planner stat4 expression partial current source next219 repeated peer run fence ' . $case] = static function (TestRunner $t) use ($plan219, $case): void {
        $plan = $plan219(1 + ($case % 5), $case % 4);
        $t->same($plan['stat4PeerRunFence']['peerRunSignature'], $plan['selectedPlan']['next219PeerRunSignature']);
    };
}

return $tests;
