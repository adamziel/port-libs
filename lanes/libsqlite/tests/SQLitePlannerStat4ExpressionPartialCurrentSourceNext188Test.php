<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq188 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull188 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between188 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared188 = static fn (): array => [
    'name' => 'prepared-wp-options-peer-stat4-expression-partial-next188',
    'schemaCookie' => 1880,
    'stat4Generation' => 121,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_peer_partial_stat4_next188',
        'rootPage' => 18801,
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

$current188 = static function () use ($prepared188): array {
    $source = $prepared188();
    $source['name'] = 'current-wp-options-peer-stat4-expression-partial-next188';
    $source['schemaCookie'] = 1888;
    $source['stat4Generation'] = 144;
    $source['indexes'][0]['rootPage'] = 18888;
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

    return $source;
};

$terms188 = static fn (): array => [
    $between188('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq188('autoload', 'yes'),
    $notNull188('option_name'),
];
$plan188 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceDuplicatePeerFence(
    $prepared ?? $prepared188(),
    $current ?? $current188(),
    $terms ?? $terms188(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$noPeer188 = static fn (): array => $plan188(3, 0);
$unbracketed188 = static function () use ($current188, $plan188): array {
    $current = $current188();
    array_pop($current['indexes'][0]['stat4Samples']);

    return $plan188(2, 0, null, $current);
};
$badPeer188 = static function () use ($current188, $plan188): array {
    $current = $current188();
    foreach ($current['rows'] as &$row) {
        if (($row['rowid'] ?? null) === 21) {
            $row['rowid'] = 20;
        }
    }
    unset($row);

    return $plan188(5, 1, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next188 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next188-ready', $plan188()['status']),
    'planner stat4 expression partial current source next188 inherits selected current' => static fn (TestRunner $t) => $t->same('current', $plan188()['selectedSource']),
    'planner stat4 expression partial current source next188 base status replaced' => static fn (TestRunner $t) => $t->same(true, $plan188()['selectedPlan']['next185Ready']),
    'planner stat4 expression partial current source next188 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan188()['selectedPlan']['next188Ready']),
    'planner stat4 expression partial current source next188 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_peer_partial_stat4_next188', $plan188()['selectedPlan']['name']),
    'planner stat4 expression partial current source next188 root page' => static fn (TestRunner $t) => $t->same(18888, $plan188()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next188 matched rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan188()['matchedRowids']),
    'planner stat4 expression partial current source next188 matched keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_forms', 'plugin_forms'], $plan188()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next188 duplicate keys' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $plan188()['peerFence']['duplicateExpressionKeys']),
    'planner stat4 expression partial current source next188 selected duplicate keys' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $plan188()['selectedPlan']['next188DuplicateExpressionKeys']),
    'planner stat4 expression partial current source next188 peer rowids' => static fn (TestRunner $t) => $t->same(['plugin_forms' => [20, 21, 22]], $plan188()['peerFence']['peerRowids']),
    'planner stat4 expression partial current source next188 selected peer rowids' => static fn (TestRunner $t) => $t->same(['plugin_forms' => [20, 21, 22]], $plan188()['selectedPlan']['next188PeerRowids']),
    'planner stat4 expression partial current source next188 no ambiguous peers' => static fn (TestRunner $t) => $t->same([], $plan188()['peerFence']['ambiguousPeerKeys']),
    'planner stat4 expression partial current source next188 selected no ambiguous peers' => static fn (TestRunner $t) => $t->same([], $plan188()['selectedPlan']['next188AmbiguousPeerKeys']),
    'planner stat4 expression partial current source next188 deterministic tiebreak' => static fn (TestRunner $t) => $t->same(true, $plan188()['peerFence']['deterministicRowidTiebreak']),
    'planner stat4 expression partial current source next188 all bracketed' => static fn (TestRunner $t) => $t->same(true, $plan188()['peerFence']['allPeersBracketedByStat4']),
    'planner stat4 expression partial current source next188 peer detail count' => static fn (TestRunner $t) => $t->same(5, count($plan188()['peerFence']['peerDetails'])),
    'planner stat4 expression partial current source next188 first detail sample anchor' => static fn (TestRunner $t) => $t->same(true, $plan188()['peerFence']['peerDetails'][0]['sampleAnchor']),
    'planner stat4 expression partial current source next188 duplicate anchor rowid' => static fn (TestRunner $t) => $t->same(20, $plan188()['peerFence']['peerDetails'][2]['anchorRowid']),
    'planner stat4 expression partial current source next188 duplicate non sample' => static fn (TestRunner $t) => $t->same(false, $plan188()['peerFence']['peerDetails'][3]['sampleAnchor']),
    'planner stat4 expression partial current source next188 duplicate lower bracket' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan188()['peerFence']['peerDetails'][3]['lowerSampleKey']),
    'planner stat4 expression partial current source next188 duplicate upper bracket' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan188()['peerFence']['peerDetails'][3]['upperSampleKey']),
    'planner stat4 expression partial current source next188 mail bracket anchor' => static fn (TestRunner $t) => $t->same(50, $plan188()['peerFence']['peerDetails'][1]['anchorRowid']),
    'planner stat4 expression partial current source next188 provenance still current' => static fn (TestRunner $t) => $t->same(['current', 'current', 'current', 'current', 'current'], array_column($plan188()['currentSourceRowProvenance'], 'source')),
    'planner stat4 expression partial current source next188 provenance sample keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', null, null], array_column($plan188()['currentSourceRowProvenance'], 'sampleKey')),
    'planner stat4 expression partial current source next188 projected duplicate payload' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan188()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next188 cursor appended' => static fn (TestRunner $t) => $t->same('Stat4PeerRowidFence', $plan188()['cursorProgram'][array_key_last($plan188()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next188 cursor duplicate keys' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $plan188()['cursorProgram'][array_key_last($plan188()['cursorProgram'])]['duplicateExpressionKeys']),
    'planner stat4 expression partial current source next188 cursor peer rowids' => static fn (TestRunner $t) => $t->same(['plugin_forms' => [20, 21, 22]], $plan188()['cursorProgram'][array_key_last($plan188()['cursorProgram'])]['peerRowids']),
    'planner stat4 expression partial current source next188 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan188()['peerFence']['peerSignature'])),
    'planner stat4 expression partial current source next188 stat4 fence signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan188()['stat4Fence']['next188PeerSignature'])),
    'planner stat4 expression partial current source next188 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next188', $plan188()['dependencies'], true)),
    'planner stat4 expression partial current source next188 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan188()['dependency_closure']),
    'planner stat4 expression partial current source next188 non overlap' => static fn (TestRunner $t) => $t->contains('duplicate expression-key peers', $plan188()['non_overlap']),
    'planner stat4 expression partial current source next188 detail' => static fn (TestRunner $t) => $t->contains('NEXT188 PEER ROWID FENCE', $plan188()['detail']),
    'planner stat4 expression partial current source next188 no peer window ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next188-ready', $noPeer188()['status']),
    'planner stat4 expression partial current source next188 no peer duplicate keys' => static fn (TestRunner $t) => $t->same([], $noPeer188()['peerFence']['duplicateExpressionKeys']),
    'planner stat4 expression partial current source next188 no peer cursor rowids' => static fn (TestRunner $t) => $t->same([], $noPeer188()['cursorProgram'][array_key_last($noPeer188()['cursorProgram'])]['peerRowids']),
    'planner stat4 expression partial current source next188 unbracketed blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-peer-reprepare', $unbracketed188()['status']),
    'planner stat4 expression partial current source next188 unbracketed ambiguous key' => static fn (TestRunner $t) => $t->same(['plugin_zulu'], $unbracketed188()['peerFence']['ambiguousPeerKeys']),
    'planner stat4 expression partial current source next188 unbracketed no cursor append' => static fn (TestRunner $t) => $t->same(false, in_array('Stat4PeerRowidFence', array_column($unbracketed188()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next188 bad peer blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-peer-reprepare', $badPeer188()['status']),
    'planner stat4 expression partial current source next188 bad peer ambiguous' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $badPeer188()['peerFence']['ambiguousPeerKeys']),
    'planner stat4 expression partial current source next188 bad peer rowids' => static fn (TestRunner $t) => $t->same(['plugin_forms' => [20, 20, 22]], $badPeer188()['peerFence']['peerRowids']),
    'planner stat4 expression partial current source next188 invalid indexes' => static function (TestRunner $t) use ($current188, $plan188): void {
        $bad = $current188();
        $bad['indexes'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan188(1, 0, null, $bad));
    },
    'planner stat4 expression partial current source next188 invalid sample rowid' => static function (TestRunner $t) use ($current188, $plan188): void {
        $bad = $current188();
        $bad['indexes'][0]['stat4Samples'][0]['sample'][1] = 'rowid';
        $t->throws(InvalidArgumentException::class, static fn () => $plan188(1, 0, null, $bad));
    },
    'planner stat4 expression partial current source next188 invalid matched rowid' => static function (TestRunner $t) use ($current188, $plan188): void {
        $bad = $current188();
        $bad['rows'][0]['rowid'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan188(1, 0, null, $bad));
    },
];

foreach (range(1, 14) as $case) {
    $tests['planner stat4 expression partial current source next188 repeated peer fence ' . $case] = static function (TestRunner $t) use ($plan188, $case): void {
        $plan = $plan188(1 + ($case % 5), $case % 4);
        $t->same(count($plan['matchedRows']), count($plan['peerFence']['peerDetails']));
    };
}

return $tests;
