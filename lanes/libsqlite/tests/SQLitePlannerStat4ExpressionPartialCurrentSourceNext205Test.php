<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq205 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull205 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between205 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared205 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-peer-expression-partial-next205',
    'schemaCookie' => 2050,
    'stat4Generation' => 200,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_peer_partial_stat4_next205',
        'rootPage' => 20501,
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

$current205 = static function () use ($prepared205): array {
    $source = $prepared205();
    $source['name'] = 'current-wp-options-stat4-peer-expression-partial-next205';
    $source['schemaCookie'] = 2059;
    $source['stat4Generation'] = 230;
    $source['indexes'][0]['rootPage'] = 20588;
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

$terms205 = static fn (): array => [
    $between205('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq205('autoload', 'yes'),
    $notNull205('option_name'),
];
$plan205 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext205(
    $prepared ?? $prepared205(),
    $current ?? $current205(),
    $terms205(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$staleNeq205 = static function () use ($current205, $plan205): array {
    $current = $current205();
    $current['indexes'][0]['stat4Samples'][2]['neq'] = '2 1';

    return $plan205(5, 1, null, $current);
};
$missingPeerSample205 = static function () use ($current205, $plan205): array {
    $current = $current205();
    array_splice($current['indexes'][0]['stat4Samples'], 2, 1);

    return $plan205(5, 1, null, $current);
};
$badNeq205 = static function () use ($current205, $plan205): array {
    $current = $current205();
    $current['indexes'][0]['stat4Samples'][2]['neq'] = 'bad';

    return $plan205(5, 1, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next205 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next205-ready', $plan205()['status']),
    'planner stat4 expression partial current source next205 inherits boundary ready' => static fn (TestRunner $t) => $t->same(true, $plan205()['selectedPlan']['next203Ready']),
    'planner stat4 expression partial current source next205 selected current' => static fn (TestRunner $t) => $t->same('current', $plan205()['selectedSource']),
    'planner stat4 expression partial current source next205 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_peer_partial_stat4_next205', $plan205()['selectedPlan']['name']),
    'planner stat4 expression partial current source next205 root page' => static fn (TestRunner $t) => $t->same(20588, $plan205()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next205 matched rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan205()['matchedRowids']),
    'planner stat4 expression partial current source next205 peer ready' => static fn (TestRunner $t) => $t->same(true, $plan205()['stat4PeerCardinalityFence']['ready']),
    'planner stat4 expression partial current source next205 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan205()['selectedPlan']['next205Ready']),
    'planner stat4 expression partial current source next205 peer keys' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $plan205()['stat4PeerCardinalityFence']['peerKeys']),
    'planner stat4 expression partial current source next205 selected peer keys' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $plan205()['selectedPlan']['next205PeerKeys']),
    'planner stat4 expression partial current source next205 selected peer rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22], $plan205()['stat4PeerCardinalityFence']['selectedPeerRowids']),
    'planner stat4 expression partial current source next205 missing none' => static fn (TestRunner $t) => $t->same([], $plan205()['stat4PeerCardinalityFence']['missingSampleKeys']),
    'planner stat4 expression partial current source next205 stale none' => static fn (TestRunner $t) => $t->same([], $plan205()['stat4PeerCardinalityFence']['staleNeqKeys']),
    'planner stat4 expression partial current source next205 contiguous none' => static fn (TestRunner $t) => $t->same([], $plan205()['stat4PeerCardinalityFence']['nonContiguousPeerKeys']),
    'planner stat4 expression partial current source next205 check count' => static fn (TestRunner $t) => $t->same(1, count($plan205()['stat4PeerCardinalityFence']['checks'])),
    'planner stat4 expression partial current source next205 check key' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan205()['stat4PeerCardinalityFence']['checks'][0]['expressionKey']),
    'planner stat4 expression partial current source next205 current peer count' => static fn (TestRunner $t) => $t->same(3, $plan205()['stat4PeerCardinalityFence']['checks'][0]['currentPeerCount']),
    'planner stat4 expression partial current source next205 sample neq' => static fn (TestRunner $t) => $t->same(3, $plan205()['stat4PeerCardinalityFence']['checks'][0]['sampleNeq']),
    'planner stat4 expression partial current source next205 sample rowid' => static fn (TestRunner $t) => $t->same(20, $plan205()['stat4PeerCardinalityFence']['checks'][0]['sampleRowid']),
    'planner stat4 expression partial current source next205 selected positions' => static fn (TestRunner $t) => $t->same([2, 3, 4], $plan205()['stat4PeerCardinalityFence']['checks'][0]['selectedPositions']),
    'planner stat4 expression partial current source next205 contiguous flag' => static fn (TestRunner $t) => $t->same(true, $plan205()['stat4PeerCardinalityFence']['checks'][0]['contiguousInWindow']),
    'planner stat4 expression partial current source next205 check ready' => static fn (TestRunner $t) => $t->same(true, $plan205()['stat4PeerCardinalityFence']['checks'][0]['ready']),
    'planner stat4 expression partial current source next205 cursor opcode' => static fn (TestRunner $t) => $t->same('Stat4PeerCardinalityFence', $plan205()['cursorProgram'][array_key_last($plan205()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next205 cursor mode' => static fn (TestRunner $t) => $t->same('next205-current-source-stat4-expression-partial-peer-cardinality', $plan205()['cursorProgram'][array_key_last($plan205()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next205 cursor rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22], $plan205()['cursorProgram'][array_key_last($plan205()['cursorProgram'])]['rowids']),
    'planner stat4 expression partial current source next205 cursor keys' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $plan205()['cursorProgram'][array_key_last($plan205()['cursorProgram'])]['peerKeys']),
    'planner stat4 expression partial current source next205 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan205()['stat4PeerCardinalityFence']['signature'])),
    'planner stat4 expression partial current source next205 selected signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan205()['selectedPlan']['next205PeerSignature'])),
    'planner stat4 expression partial current source next205 stat4 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan205()['stat4Fence']['next205PeerCardinalitySignature'])),
    'planner stat4 expression partial current source next205 detail' => static fn (TestRunner $t) => $t->contains('NEXT205 PEER CARDINALITY FENCE', $plan205()['detail']),
    'planner stat4 expression partial current source next205 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next205', $plan205()['dependencies'], true)),
    'planner stat4 expression partial current source next205 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan205()['dependency_closure']),
    'planner stat4 expression partial current source next205 non overlap' => static fn (TestRunner $t) => $t->contains('duplicate expression-key peer cardinality', $plan205()['non_overlap']),
    'planner stat4 expression partial current source next205 projected payload' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan205()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next205 boundary still checked' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_forms'], $plan205()['stat4BoundaryFence']['boundaryKeys']),
    'planner stat4 expression partial current source next205 stale neq blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-peer-cardinality-reprepare', $staleNeq205()['status']),
    'planner stat4 expression partial current source next205 stale key' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $staleNeq205()['stat4PeerCardinalityFence']['staleNeqKeys']),
    'planner stat4 expression partial current source next205 stale selected flag' => static fn (TestRunner $t) => $t->same(false, $staleNeq205()['selectedPlan']['next205Ready']),
    'planner stat4 expression partial current source next205 stale no cursor append' => static fn (TestRunner $t) => $t->same(false, in_array('Stat4PeerCardinalityFence', array_column($staleNeq205()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next205 missing sample blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-peer-cardinality-reprepare', $missingPeerSample205()['status']),
    'planner stat4 expression partial current source next205 missing sample key' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $missingPeerSample205()['stat4PeerCardinalityFence']['missingSampleKeys']),
    'planner stat4 expression partial current source next205 invalid neq' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, $badNeq205),
    'planner stat4 expression partial current source next205 invalid indexes' => static function (TestRunner $t) use ($current205, $plan205): void {
        $bad = $current205();
        $bad['indexes'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan205(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next205 invalid rowid' => static function (TestRunner $t) use ($current205, $plan205): void {
        $bad = $current205();
        $bad['rows'][0]['rowid'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan205(5, 1, null, $bad));
    },
];

foreach (range(1, 12) as $case) {
    $tests['planner stat4 expression partial current source next205 repeated peer fence ' . $case] = static function (TestRunner $t) use ($plan205, $case): void {
        $plan = $plan205(3 + ($case % 3), $case % 3);
        $t->same(count($plan['stat4PeerCardinalityFence']['peerKeys']), count($plan['stat4PeerCardinalityFence']['checks']));
    };
}

return $tests;
