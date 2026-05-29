<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq247 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like247 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull247 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between247 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$payload247 = static fn (array $row): array => [
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

$prepared247 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-boundary-peers-next247',
    'schemaCookie' => 2470,
    'stat4Generation' => 247,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_stat4_boundary_peers_partial_next247',
        'rootPage' => 24701,
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
        'partialGroupedOrPredicateArms' => [[
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ]],
        'partialGroupedLikePredicateArms' => [[
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'plugin_%'],
        ]],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 1, 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 1, 40]],
            ['neq' => '4 3', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 1, 20]],
            ['neq' => '4 1', 'nlt' => '2 5', 'ndlt' => '2 3', 'sample' => ['plugin_forms', 2, 80]],
            ['neq' => '1 1', 'nlt' => '6 6', 'ndlt' => '3 4', 'sample' => ['plugin_mail', 1, 50]],
            ['neq' => '1 1', 'nlt' => '7 7', 'ndlt' => '4 5', 'sample' => ['plugin_seo', 1, 30]],
            ['neq' => '1 1', 'nlt' => '8 8', 'ndlt' => '5 6', 'sample' => ['plugin_zulu', 1, 60]],
        ],
        'stat4ExpressionPayloads' => [],
    ]],
];

$current247 = static function () use ($prepared247, $payload247): array {
    $source = $prepared247();
    $source['name'] = 'current-wp-options-stat4-boundary-peers-next247';
    $source['schemaCookie'] = 2479;
    $source['stat4Generation'] = 747;
    $source['indexes'][0]['rootPage'] = 24788;
    $source['rows'] = [
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
        ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
        ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
        ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
        ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache', 'updated_at' => 40],
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 10],
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ];
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map(
        $payload247,
        array_values(array_filter($source['rows'], static fn (array $row): bool => $row['autoload'] === 'yes')),
    );

    return $source;
};

$terms247 = static fn (): array => [
    $between247('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq247('autoload', 'yes'),
    $notNull247('option_name'),
    $eq247('blog_id', 1),
    $like247('option_name', 'plugin_%'),
];

$plan247 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext247(
    $prepared ?? $prepared247(),
    $current ?? $current247(),
    $terms ?? $terms247(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);

$shiftedPeer247 = static function () use ($current247, $payload247, $plan247): array {
    $current = $current247();
    foreach ($current['rows'] as &$row) {
        if ($row['rowid'] === 21) {
            $row['option_name'] = 'plugin_forms_shifted';
        }
    }
    unset($row);
    $current['indexes'][0]['stat4ExpressionPayloads'] = array_map(
        $payload247,
        array_values(array_filter($current['rows'], static fn (array $row): bool => $row['autoload'] === 'yes')),
    );

    return $plan247(5, 1, null, $current);
};

$ascPeer247 = static function () use ($current247, $plan247): array {
    $current = $current247();
    $current['indexes'][0]['descending'] = false;

    return $plan247(4, 2, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next247 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next247-ready', $plan247()['status']),
    'planner stat4 expression partial current source next247 inherits next244' => static fn (TestRunner $t) => $t->same(true, $plan247()['selectedPlan']['next244Ready']),
    'planner stat4 expression partial current source next247 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan247()['selectedPlan']['next247Ready']),
    'planner stat4 expression partial current source next247 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_stat4_boundary_peers_partial_next247', $plan247()['selectedPlan']['name']),
    'planner stat4 expression partial current source next247 boundary keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_forms'], $plan247()['stat4BoundaryPeerFence']['boundaryExpressionKeys']),
    'planner stat4 expression partial current source next247 current peers' => static fn (TestRunner $t) => $t->same([30, 20, 21, 22], $plan247()['stat4BoundaryPeerFence']['currentBoundaryPeerRowids']),
    'planner stat4 expression partial current source next247 yielded peers' => static fn (TestRunner $t) => $t->same([30, 20, 21, 22], $plan247()['stat4BoundaryPeerFence']['yieldedBoundaryPeerRowids']),
    'planner stat4 expression partial current source next247 selected peers' => static fn (TestRunner $t) => $t->same([30, 20, 21, 22], $plan247()['selectedPlan']['next247CurrentPeerRowids']),
    'planner stat4 expression partial current source next247 no missing peers' => static fn (TestRunner $t) => $t->same([], $plan247()['stat4BoundaryPeerFence']['missingPeerRowids']),
    'planner stat4 expression partial current source next247 no extra peers' => static fn (TestRunner $t) => $t->same([], $plan247()['stat4BoundaryPeerFence']['extraPeerRowids']),
    'planner stat4 expression partial current source next247 no peer mismatch' => static fn (TestRunner $t) => $t->same([], $plan247()['stat4BoundaryPeerFence']['peerMismatchRowids']),
    'planner stat4 expression partial current source next247 peer match flag' => static fn (TestRunner $t) => $t->same(true, $plan247()['stat4BoundaryPeerFence']['boundaryPeersMatchCurrentSource']),
    'planner stat4 expression partial current source next247 ordered current' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20, 21, 22, 40, 10], $plan247()['stat4BoundaryPeerFence']['orderedCurrentRowids']),
    'planner stat4 expression partial current source next247 window rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan247()['stat4BoundaryPeerFence']['windowRowids']),
    'planner stat4 expression partial current source next247 peer row keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_forms', 'plugin_forms', 'plugin_forms'], array_column($plan247()['stat4BoundaryPeerFence']['currentBoundaryPeerRows'], 'expressionKey')),
    'planner stat4 expression partial current source next247 signatures' => static fn (TestRunner $t) => $t->same([64, 64], [strlen($plan247()['stat4BoundaryPeerFence']['boundaryPeerSignature']), strlen($plan247()['stat4BoundaryPeerFence']['proofSignature'])]),
    'planner stat4 expression partial current source next247 selected signature' => static fn (TestRunner $t) => $t->same($plan247()['stat4BoundaryPeerFence']['proofSignature'], $plan247()['selectedPlan']['next247ProofSignature']),
    'planner stat4 expression partial current source next247 stat fence signature' => static fn (TestRunner $t) => $t->same($plan247()['stat4BoundaryPeerFence']['proofSignature'], $plan247()['stat4Fence']['next247ProofSignature']),
    'planner stat4 expression partial current source next247 cursor appended' => static fn (TestRunner $t) => $t->same('VerifyCurrentStat4BoundaryPeers', $plan247()['cursorProgram'][array_key_last($plan247()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next247 cursor mode' => static fn (TestRunner $t) => $t->same('next247-current-source-stat4-expression-partial-boundary-peers', $plan247()['cursorProgram'][array_key_last($plan247()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next247 cursor peers' => static fn (TestRunner $t) => $t->same([30, 20, 21, 22], $plan247()['cursorProgram'][array_key_last($plan247()['cursorProgram'])]['currentBoundaryPeerRowids']),
    'planner stat4 expression partial current source next247 projected payload retained' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan247()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next247 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next247', $plan247()['dependencies'], true)),
    'planner stat4 expression partial current source next247 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan247()['dependency_closure']),
    'planner stat4 expression partial current source next247 non overlap' => static fn (TestRunner $t) => $t->contains('boundary peer validation', $plan247()['non_overlap']),
    'planner stat4 expression partial current source next247 detail' => static fn (TestRunner $t) => $t->contains('NEXT247 BOUNDARY PEER FENCE', $plan247()['detail']),
    'planner stat4 expression partial current source next247 asc blocked status' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-boundary-peer-reprepare', $ascPeer247()['status']),
    'planner stat4 expression partial current source next247 asc keys' => static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_mail'], $ascPeer247()['stat4BoundaryPeerFence']['boundaryExpressionKeys']),
    'planner stat4 expression partial current source next247 asc missing peer' => static fn (TestRunner $t) => $t->same([], $ascPeer247()['stat4BoundaryPeerFence']['missingPeerRowids']),
    'planner stat4 expression partial current source next247 shifted status' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next247-ready', $shiftedPeer247()['status']),
    'planner stat4 expression partial current source next247 shifted keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_forms'], $shiftedPeer247()['stat4BoundaryPeerFence']['boundaryExpressionKeys']),
    'planner stat4 expression partial current source next247 shifted peers' => static fn (TestRunner $t) => $t->same([30, 20, 22], $shiftedPeer247()['stat4BoundaryPeerFence']['currentBoundaryPeerRowids']),
    'planner stat4 expression partial current source next247 zero limit peers' => static fn (TestRunner $t) => $t->same([], $plan247(0, 0)['stat4BoundaryPeerFence']['currentBoundaryPeerRowids']),
    'planner stat4 expression partial current source next247 tail peers' => static fn (TestRunner $t) => $t->same([10], $plan247(5, 7)['stat4BoundaryPeerFence']['currentBoundaryPeerRowids']),
    'planner stat4 expression partial current source next247 invalid negative limit' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan247(-1, 0)),
    'planner stat4 expression partial current source next247 invalid negative offset' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan247(1, -1)),
    'planner stat4 expression partial current source next247 malformed rows' => static function (TestRunner $t) use ($current247, $plan247): void {
        $bad = $current247();
        $bad['rows'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan247(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next247 malformed rowid' => static function (TestRunner $t) use ($current247, $plan247): void {
        $bad = $current247();
        $bad['rows'][0]['rowid'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan247(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next247 unsupported operator' => static function (TestRunner $t) use ($plan247, $terms247): void {
        $terms = $terms247();
        $terms[] = ['left' => ['column' => 'option_name'], 'operator' => 'GLOB', 'right' => 'plugin_*'];
        $t->throws(InvalidArgumentException::class, static fn () => $plan247(5, 1, null, null, $terms));
    },
];

foreach (range(1, 28) as $case) {
    $tests['planner stat4 expression partial current source next247 repeated boundary proof ' . $case] = static function (TestRunner $t) use ($plan247, $case): void {
        $plan = $plan247(1 + ($case % 5), $case % 4);
        $t->same($plan['stat4BoundaryPeerFence']['proofSignature'], $plan['selectedPlan']['next247ProofSignature']);
    };
}

return $tests;
