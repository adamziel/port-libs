<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq196 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull196 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between196 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared196 = static fn (): array => [
    'name' => 'prepared-wp-options-peer-order-stat4-expression-partial-next196',
    'schemaCookie' => 1960,
    'stat4Generation' => 161,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_peer_partial_stat4_next196',
        'rootPage' => 19601,
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
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
        ],
    ]],
];

$current196 = static function () use ($prepared196): array {
    $source = $prepared196();
    $source['name'] = 'current-wp-options-peer-order-stat4-expression-partial-next196';
    $source['schemaCookie'] = 1969;
    $source['stat4Generation'] = 188;
    $source['indexes'][0]['rootPage'] = 19688;
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
    ];

    return $source;
};

$terms196 = static fn (): array => [
    $between196('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq196('autoload', 'yes'),
    $notNull196('option_name'),
];
$plan196 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceResidualWhereFence(
    $prepared ?? $prepared196(),
    $current ?? $current196(),
    $terms ?? $terms196(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$outOfOrder196 = static function () use ($current196, $plan196): array {
    $current = $current196();
    $current['rows'][3] = ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22];
    $current['rows'][4] = ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21];
    $current['rows'][5] = ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20];

    return $plan196(5, 1, null, $current);
};
$nullPeer196 = static function () use ($current196, $plan196): array {
    $current = $current196();
    $current['rows'][3]['option_name'] = null;

    return $plan196(5, 1, null, $current);
};
$badExpression196 = static function () use ($current196, $plan196): array {
    $current = $current196();
    $current['indexes'][0]['expression'] = 'upper(option_name)';

    return $plan196(5, 1, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next196 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next196-ready', $plan196()['status']),
    'planner stat4 expression partial current source next196 selected current' => static fn (TestRunner $t) => $t->same('current', $plan196()['selectedSource']),
    'planner stat4 expression partial current source next196 inherited next192 ready' => static fn (TestRunner $t) => $t->same(true, $plan196()['selectedPlan']['next192Ready']),
    'planner stat4 expression partial current source next196 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_peer_partial_stat4_next196', $plan196()['selectedPlan']['name']),
    'planner stat4 expression partial current source next196 root page' => static fn (TestRunner $t) => $t->same(19688, $plan196()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next196 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan196()['selectedPlan']['next196Ready']),
    'planner stat4 expression partial current source next196 expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan196()['peerOrderFence']['expression']),
    'planner stat4 expression partial current source next196 selected expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan196()['selectedPlan']['next196Expression']),
    'planner stat4 expression partial current source next196 descending flag' => static fn (TestRunner $t) => $t->same(true, $plan196()['peerOrderFence']['descending']),
    'planner stat4 expression partial current source next196 matched rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan196()['matchedRowids']),
    'planner stat4 expression partial current source next196 checked rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan196()['peerOrderFence']['checkedRowids']),
    'planner stat4 expression partial current source next196 duplicate peer keys' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $plan196()['peerOrderFence']['duplicatePeerKeys']),
    'planner stat4 expression partial current source next196 peer order stable' => static fn (TestRunner $t) => $t->same(true, $plan196()['peerOrderFence']['peerOrderStable']),
    'planner stat4 expression partial current source next196 no out of order rowids' => static fn (TestRunner $t) => $t->same([], $plan196()['peerOrderFence']['outOfOrderPeerRowids']),
    'planner stat4 expression partial current source next196 no null expression rowids' => static fn (TestRunner $t) => $t->same([], $plan196()['peerOrderFence']['nullExpressionRowids']),
    'planner stat4 expression partial current source next196 peer count' => static fn (TestRunner $t) => $t->same(3, count($plan196()['peerOrderFence']['peers'])),
    'planner stat4 expression partial current source next196 peer forms rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22], $plan196()['peerOrderFence']['peers'][2]['rowids']),
    'planner stat4 expression partial current source next196 peer forms expected rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22], $plan196()['peerOrderFence']['peers'][2]['expectedRowids']),
    'planner stat4 expression partial current source next196 peer forms source order rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22], $plan196()['peerOrderFence']['peers'][2]['sourceOrderRowids']),
    'planner stat4 expression partial current source next196 peer forms duplicate' => static fn (TestRunner $t) => $t->same(true, $plan196()['peerOrderFence']['peers'][2]['duplicatePeer']),
    'planner stat4 expression partial current source next196 details count' => static fn (TestRunner $t) => $t->same(5, count($plan196()['peerOrderFence']['details'])),
    'planner stat4 expression partial current source next196 first key' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan196()['peerOrderFence']['details'][0]['expressionKey']),
    'planner stat4 expression partial current source next196 mixed case key' => static fn (TestRunner $t) => $t->same('plugin_mail', $plan196()['peerOrderFence']['details'][1]['expressionKey']),
    'planner stat4 expression partial current source next196 uppercase key' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan196()['peerOrderFence']['details'][4]['expressionKey']),
    'planner stat4 expression partial current source next196 projected peer payload' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan196()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next196 covering remains elided' => static fn (TestRunner $t) => $t->same(true, $plan196()['tableLookupElided']),
    'planner stat4 expression partial current source next196 cursor opcode' => static fn (TestRunner $t) => $t->same('Stat4ExpressionPeerOrderFence', $plan196()['cursorProgram'][array_key_last($plan196()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next196 cursor mode' => static fn (TestRunner $t) => $t->same('next196-current-source-stat4-expression-partial-peer-order', $plan196()['cursorProgram'][array_key_last($plan196()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next196 cursor rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan196()['cursorProgram'][array_key_last($plan196()['cursorProgram'])]['rowids']),
    'planner stat4 expression partial current source next196 cursor duplicate keys' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $plan196()['cursorProgram'][array_key_last($plan196()['cursorProgram'])]['duplicatePeerKeys']),
    'planner stat4 expression partial current source next196 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan196()['peerOrderFence']['signature'])),
    'planner stat4 expression partial current source next196 selected signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan196()['selectedPlan']['next196PeerOrderSignature'])),
    'planner stat4 expression partial current source next196 stat4 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan196()['stat4Fence']['next196PeerOrderSignature'])),
    'planner stat4 expression partial current source next196 detail' => static fn (TestRunner $t) => $t->contains('NEXT196 PEER ORDER FENCE', $plan196()['detail']),
    'planner stat4 expression partial current source next196 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next196', $plan196()['dependencies'], true)),
    'planner stat4 expression partial current source next196 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan196()['dependency_closure']),
    'planner stat4 expression partial current source next196 non overlap' => static fn (TestRunner $t) => $t->contains('duplicate expression-key peers', $plan196()['non_overlap']),
    'planner stat4 expression partial current source next196 out of order blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-peer-order-reprepare', $outOfOrder196()['status']),
    'planner stat4 expression partial current source next196 out of order rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22], $outOfOrder196()['peerOrderFence']['outOfOrderPeerRowids']),
    'planner stat4 expression partial current source next196 out of order source order rowids' => static fn (TestRunner $t) => $t->same([22, 21, 20], $outOfOrder196()['peerOrderFence']['peers'][2]['sourceOrderRowids']),
    'planner stat4 expression partial current source next196 out of order selected rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22], $outOfOrder196()['selectedPlan']['next196OutOfOrderPeerRowids']),
    'planner stat4 expression partial current source next196 out of order no cursor append' => static fn (TestRunner $t) => $t->same(false, in_array('Stat4ExpressionPeerOrderFence', array_column($outOfOrder196()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next196 null peer blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-peer-order-reprepare', $nullPeer196()['status']),
    'planner stat4 expression partial current source next196 invalid expression' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, $badExpression196),
    'planner stat4 expression partial current source next196 invalid indexes' => static function (TestRunner $t) use ($current196, $plan196): void {
        $bad = $current196();
        $bad['indexes'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan196(1, 0, null, $bad));
    },
    'planner stat4 expression partial current source next196 invalid source rowid' => static function (TestRunner $t) use ($current196, $plan196): void {
        $bad = $current196();
        $bad['rows'][3]['rowid'] = 'rowid';
        $t->throws(InvalidArgumentException::class, static fn () => $plan196(5, 1, null, $bad));
    },
];

foreach (range(1, 12) as $case) {
    $tests['planner stat4 expression partial current source next196 repeated peer fence ' . $case] = static function (TestRunner $t) use ($plan196, $case): void {
        $plan = $plan196(1 + ($case % 5), $case % 4);
        $t->same(count($plan['matchedRows']), count($plan['peerOrderFence']['details']));
    };
}

return $tests;
