<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq210 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull210 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between210 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared210 = static fn (): array => [
    'name' => 'prepared-wp-options-peer-rowid-stat4-expression-next210',
    'schemaCookie' => 2100,
    'stat4Generation' => 210,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_peer_rowid_stat4_next210',
        'rootPage' => 21001,
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
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
        ],
    ]],
];

$current210 = static function (array $rowOverrides = []) use ($prepared210): array {
    $source = $prepared210();
    $source['name'] = 'current-wp-options-peer-rowid-stat4-expression-next210';
    $source['schemaCookie'] = 2108;
    $source['stat4Generation'] = 256;
    $source['indexes'][0]['rootPage'] = 21088;
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
    foreach ($rowOverrides as $key => $value) {
        $source[$key] = $value;
    }

    return $source;
};

$terms210 = static fn (): array => [
    $between210('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq210('autoload', 'yes'),
    $notNull210('option_name'),
    $eq210('blog_id', 1),
];
$plan210 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext210(
    $prepared ?? $prepared210(),
    $current ?? $current210(),
    $terms ?? $terms210(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$unproved210 = static function () use ($terms210, $plan210): array {
    $terms = array_values(array_filter($terms210(), static fn (array $term): bool => ($term['left']['column'] ?? null) !== 'blog_id'));

    return $plan210(5, 1, null, null, $terms);
};
$noPeers210 = static function () use ($current210, $plan210): array {
    $current = $current210();
    $current['rows'] = array_values(array_filter(
        $current['rows'],
        static fn (array $row): bool => !in_array((int) $row['rowid'], [21, 22], true),
    ));
    $current['indexes'][0]['stat4Samples'][2]['neq'] = '1 1';

    return $plan210(5, 1, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next210 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next210-ready', $plan210()['status']),
    'planner stat4 expression partial current source next210 selected current' => static fn (TestRunner $t) => $t->same('current', $plan210()['selectedSource']),
    'planner stat4 expression partial current source next210 inherited next209' => static fn (TestRunner $t) => $t->same(true, $plan210()['selectedPlan']['next209Ready']),
    'planner stat4 expression partial current source next210 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan210()['selectedPlan']['next210Ready']),
    'planner stat4 expression partial current source next210 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_peer_rowid_stat4_next210', $plan210()['selectedPlan']['name']),
    'planner stat4 expression partial current source next210 root page' => static fn (TestRunner $t) => $t->same(21088, $plan210()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next210 matched rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan210()['matchedRowids']),
    'planner stat4 expression partial current source next210 matched keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_forms', 'plugin_forms'], $plan210()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next210 duplicate peers present' => static fn (TestRunner $t) => $t->same(true, $plan210()['expressionPeerOrderFence']['hasDuplicateExpressionPeers']),
    'planner stat4 expression partial current source next210 duplicate peer keys' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $plan210()['expressionPeerOrderFence']['duplicateExpressionPeerKeys']),
    'planner stat4 expression partial current source next210 selected peer keys' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $plan210()['selectedPlan']['next210DuplicateExpressionPeerKeys']),
    'planner stat4 expression partial current source next210 peer group count' => static fn (TestRunner $t) => $t->same(1, count($plan210()['expressionPeerOrderFence']['duplicateExpressionPeerGroups'])),
    'planner stat4 expression partial current source next210 peer group key' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan210()['expressionPeerOrderFence']['duplicateExpressionPeerGroups'][0]['expressionKey']),
    'planner stat4 expression partial current source next210 peer rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22], $plan210()['expressionPeerOrderFence']['duplicateExpressionPeerGroups'][0]['rowids']),
    'planner stat4 expression partial current source next210 expected peer order' => static fn (TestRunner $t) => $t->same([20, 21, 22], $plan210()['expressionPeerOrderFence']['duplicateExpressionPeerGroups'][0]['expectedRowidOrder']),
    'planner stat4 expression partial current source next210 peer positions' => static fn (TestRunner $t) => $t->same([2, 3, 4], $plan210()['expressionPeerOrderFence']['duplicateExpressionPeerGroups'][0]['positions']),
    'planner stat4 expression partial current source next210 peers ordered' => static fn (TestRunner $t) => $t->same(true, $plan210()['expressionPeerOrderFence']['duplicateExpressionPeerGroups'][0]['orderedByRowid']),
    'planner stat4 expression partial current source next210 all peers ordered' => static fn (TestRunner $t) => $t->same(true, $plan210()['expressionPeerOrderFence']['allDuplicateExpressionPeersInRowidOrder']),
    'planner stat4 expression partial current source next210 rejected none' => static fn (TestRunner $t) => $t->same([], $plan210()['expressionPeerOrderFence']['rowidsRejectedByPeerOrderFence']),
    'planner stat4 expression partial current source next210 selected rejected none' => static fn (TestRunner $t) => $t->same([], $plan210()['selectedPlan']['next210RowsRejectedByPeerOrderFence']),
    'planner stat4 expression partial current source next210 peer signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan210()['expressionPeerOrderFence']['peerOrderSignature'])),
    'planner stat4 expression partial current source next210 proof signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan210()['expressionPeerOrderFence']['proofSignature'])),
    'planner stat4 expression partial current source next210 selected signature' => static fn (TestRunner $t) => $t->same($plan210()['expressionPeerOrderFence']['peerOrderSignature'], $plan210()['selectedPlan']['next210ExpressionPeerOrderSignature']),
    'planner stat4 expression partial current source next210 stat4 signature' => static fn (TestRunner $t) => $t->same($plan210()['expressionPeerOrderFence']['peerOrderSignature'], $plan210()['stat4Fence']['next210ExpressionPeerOrderSignature']),
    'planner stat4 expression partial current source next210 stat4 proof signature' => static fn (TestRunner $t) => $t->same($plan210()['expressionPeerOrderFence']['proofSignature'], $plan210()['stat4Fence']['next210ExpressionPeerProofSignature']),
    'planner stat4 expression partial current source next210 cursor appended' => static fn (TestRunner $t) => $t->same('RecheckDuplicateExpressionPeerRowidOrder', $plan210()['cursorProgram'][array_key_last($plan210()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next210 cursor mode' => static fn (TestRunner $t) => $t->same('next210-current-source-stat4-expression-partial-peer-rowid-order', $plan210()['cursorProgram'][array_key_last($plan210()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next210 cursor keys' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $plan210()['cursorProgram'][array_key_last($plan210()['cursorProgram'])]['duplicateExpressionPeerKeys']),
    'planner stat4 expression partial current source next210 cursor rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22], $plan210()['cursorProgram'][array_key_last($plan210()['cursorProgram'])]['rowids']),
    'planner stat4 expression partial current source next210 cursor signature' => static fn (TestRunner $t) => $t->same($plan210()['expressionPeerOrderFence']['proofSignature'], $plan210()['cursorProgram'][array_key_last($plan210()['cursorProgram'])]['signature']),
    'planner stat4 expression partial current source next210 grouped fence preserved' => static fn (TestRunner $t) => $t->same(true, $plan210()['groupedPartialOrPredicateFence']['currentGroupedPartialOrPredicateImplied']),
    'planner stat4 expression partial current source next210 grouped rowids preserved' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], array_column($plan210()['groupedPartialOrPredicateFence']['rowProofs'], 'rowid')),
    'planner stat4 expression partial current source next210 partial fence preserved' => static fn (TestRunner $t) => $t->same(true, $plan210()['partialPredicateFence']['currentPartialPredicateImplied']),
    'planner stat4 expression partial current source next210 payload fence preserved' => static fn (TestRunner $t) => $t->same(true, $plan210()['payloadExpressionFence']['allPayloadExpressionKeysMatch']),
    'planner stat4 expression partial current source next210 projected duplicate payload' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan210()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next210 detail' => static fn (TestRunner $t) => $t->contains('NEXT210 DUPLICATE EXPRESSION PEER ROWID FENCE', $plan210()['detail']),
    'planner stat4 expression partial current source next210 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next210', $plan210()['dependencies'], true)),
    'planner stat4 expression partial current source next210 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan210()['dependency_closure']),
    'planner stat4 expression partial current source next210 non overlap' => static fn (TestRunner $t) => $t->contains('rowid tie-break ordering', $plan210()['non_overlap']),
    'planner stat4 expression partial current source next210 unproved blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-expression-peer-reprepare', $unproved210()['status']),
    'planner stat4 expression partial current source next210 unproved inherited status' => static fn (TestRunner $t) => $t->same(false, $unproved210()['selectedPlan']['next210Ready']),
    'planner stat4 expression partial current source next210 unproved no cursor append' => static fn (TestRunner $t) => $t->same(false, in_array('RecheckDuplicateExpressionPeerRowidOrder', array_column($unproved210()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next210 no peers blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-expression-peer-reprepare', $noPeers210()['status']),
    'planner stat4 expression partial current source next210 no peers flag' => static fn (TestRunner $t) => $t->same(false, $noPeers210()['expressionPeerOrderFence']['hasDuplicateExpressionPeers']),
    'planner stat4 expression partial current source next210 no peers groups' => static fn (TestRunner $t) => $t->same([], $noPeers210()['expressionPeerOrderFence']['duplicateExpressionPeerGroups']),
    'planner stat4 expression partial current source next210 no peers no cursor append' => static fn (TestRunner $t) => $t->same(false, in_array('RecheckDuplicateExpressionPeerRowidOrder', array_column($noPeers210()['cursorProgram'], 'opcode'), true)),
];

foreach (range(1, 12) as $case) {
    $tests['planner stat4 expression partial current source next210 repeated peer fence ' . $case] = static function (TestRunner $t) use ($plan210, $case): void {
        $plan = $plan210(1 + ($case % 5), $case % 4);
        $t->same($plan['expressionPeerOrderFence']['duplicateExpressionPeerKeys'], $plan['selectedPlan']['next210DuplicateExpressionPeerKeys']);
    };
}

return $tests;
