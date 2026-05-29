<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq217 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like217 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull217 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between217 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared217 = static fn (): array => [
    'name' => 'prepared-wp-options-yield-partial-stat4-expression-next217',
    'schemaCookie' => 2170,
    'stat4Generation' => 217,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_yield_stat4_next217',
        'rootPage' => 21701,
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

$current217 = static function (array $rowOverrides = []) use ($prepared217): array {
    $source = $prepared217();
    $source['name'] = 'current-wp-options-yield-partial-stat4-expression-next217';
    $source['schemaCookie'] = 2178;
    $source['stat4Generation'] = 278;
    $source['indexes'][0]['rootPage'] = 21788;
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

$terms217 = static fn (): array => [
    $between217('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq217('autoload', 'yes'),
    $notNull217('option_name'),
    $eq217('blog_id', 1),
    $like217('option_name', 'plugin_%'),
];
$plan217 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext217(
    $prepared ?? $prepared217(),
    $current ?? $current217(),
    $terms ?? $terms217(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$unproved217 = static function () use ($terms217, $plan217): array {
    $terms = array_values(array_filter($terms217(), static fn (array $term): bool => ($term['operator'] ?? null) !== 'LIKE'));

    return $plan217(5, 1, null, null, $terms);
};

$tests = [
    'planner stat4 expression partial current source next217 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next217-ready', $plan217()['status']),
    'planner stat4 expression partial current source next217 selected current' => static fn (TestRunner $t) => $t->same('current', $plan217()['selectedSource']),
    'planner stat4 expression partial current source next217 inherited next212' => static fn (TestRunner $t) => $t->same(true, $plan217()['selectedPlan']['next212Ready']),
    'planner stat4 expression partial current source next217 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan217()['selectedPlan']['next217Ready']),
    'planner stat4 expression partial current source next217 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_yield_stat4_next217', $plan217()['selectedPlan']['name']),
    'planner stat4 expression partial current source next217 root page' => static fn (TestRunner $t) => $t->same(21788, $plan217()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next217 matched rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan217()['matchedRowids']),
    'planner stat4 expression partial current source next217 page rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan217()['stat4YieldFence']['pageRowids']),
    'planner stat4 expression partial current source next217 lookahead rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22, 40], $plan217()['stat4YieldFence']['lookaheadRowids']),
    'planner stat4 expression partial current source next217 prefix proof' => static fn (TestRunner $t) => $t->same(true, $plan217()['stat4YieldFence']['currentPageMatchesLookaheadPrefix']),
    'planner stat4 expression partial current source next217 has next row' => static fn (TestRunner $t) => $t->same(true, $plan217()['stat4YieldFence']['hasNextLookaheadRow']),
    'planner stat4 expression partial current source next217 resume after rowid' => static fn (TestRunner $t) => $t->same(22, $plan217()['stat4YieldFence']['resumeAfterRowid']),
    'planner stat4 expression partial current source next217 next rowid' => static fn (TestRunner $t) => $t->same(40, $plan217()['stat4YieldFence']['nextRowid']),
    'planner stat4 expression partial current source next217 selected resume after rowid' => static fn (TestRunner $t) => $t->same(22, $plan217()['selectedPlan']['next217ResumeAfterRowid']),
    'planner stat4 expression partial current source next217 selected next rowid' => static fn (TestRunner $t) => $t->same(40, $plan217()['selectedPlan']['next217NextRowid']),
    'planner stat4 expression partial current source next217 rejected none' => static fn (TestRunner $t) => $t->same([], $plan217()['stat4YieldFence']['rowidsRejectedByYieldFence']),
    'planner stat4 expression partial current source next217 selected rejected none' => static fn (TestRunner $t) => $t->same([], $plan217()['selectedPlan']['next217RowsRejectedByYieldFence']),
    'planner stat4 expression partial current source next217 pair count' => static fn (TestRunner $t) => $t->same(6, count($plan217()['stat4YieldFence']['currentNextPairs'])),
    'planner stat4 expression partial current source next217 first pair relation' => static fn (TestRunner $t) => $t->same('first', $plan217()['stat4YieldFence']['currentNextPairs'][0]['relation']),
    'planner stat4 expression partial current source next217 descending relation' => static fn (TestRunner $t) => $t->same('descending-expression', $plan217()['stat4YieldFence']['currentNextPairs'][1]['relation']),
    'planner stat4 expression partial current source next217 peer relation' => static fn (TestRunner $t) => $t->same('peer-rowid', $plan217()['stat4YieldFence']['currentNextPairs'][3]['relation']),
    'planner stat4 expression partial current source next217 all ordered' => static fn (TestRunner $t) => $t->same(true, $plan217()['stat4YieldFence']['currentNextPairsPreserveExpressionOrder']),
    'planner stat4 expression partial current source next217 pair rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22, 40], array_column($plan217()['stat4YieldFence']['currentNextPairs'], 'rowid')),
    'planner stat4 expression partial current source next217 pair keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_forms', 'plugin_forms', 'plugin_cache'], array_column($plan217()['stat4YieldFence']['currentNextPairs'], 'expressionKey')),
    'planner stat4 expression partial current source next217 signatures length' => static fn (TestRunner $t) => $t->same([64, 64], [strlen($plan217()['stat4YieldFence']['currentNextYieldSignature']), strlen($plan217()['stat4YieldFence']['proofSignature'])]),
    'planner stat4 expression partial current source next217 selected signature' => static fn (TestRunner $t) => $t->same($plan217()['stat4YieldFence']['currentNextYieldSignature'], $plan217()['selectedPlan']['next217CurrentNextYieldSignature']),
    'planner stat4 expression partial current source next217 selected proof signature' => static fn (TestRunner $t) => $t->same($plan217()['stat4YieldFence']['proofSignature'], $plan217()['selectedPlan']['next217ProofSignature']),
    'planner stat4 expression partial current source next217 stat4 signature' => static fn (TestRunner $t) => $t->same($plan217()['stat4YieldFence']['currentNextYieldSignature'], $plan217()['stat4Fence']['next217CurrentNextYieldSignature']),
    'planner stat4 expression partial current source next217 stat4 proof signature' => static fn (TestRunner $t) => $t->same($plan217()['stat4YieldFence']['proofSignature'], $plan217()['stat4Fence']['next217ProofSignature']),
    'planner stat4 expression partial current source next217 cursor appended' => static fn (TestRunner $t) => $t->same('RecheckCurrentNextStat4Yield', $plan217()['cursorProgram'][array_key_last($plan217()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next217 cursor mode' => static fn (TestRunner $t) => $t->same('next217-current-source-stat4-expression-partial-current-next-yield', $plan217()['cursorProgram'][array_key_last($plan217()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next217 cursor rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22, 40], $plan217()['cursorProgram'][array_key_last($plan217()['cursorProgram'])]['rowids']),
    'planner stat4 expression partial current source next217 cursor resume rowid' => static fn (TestRunner $t) => $t->same(22, $plan217()['cursorProgram'][array_key_last($plan217()['cursorProgram'])]['resumeAfterRowid']),
    'planner stat4 expression partial current source next217 cursor next rowid' => static fn (TestRunner $t) => $t->same(40, $plan217()['cursorProgram'][array_key_last($plan217()['cursorProgram'])]['nextRowid']),
    'planner stat4 expression partial current source next217 grouped like preserved' => static fn (TestRunner $t) => $t->same(true, $plan217()['groupedLikePredicateFence']['currentGroupedLikePredicateImplied']),
    'planner stat4 expression partial current source next217 grouped or preserved' => static fn (TestRunner $t) => $t->same(true, $plan217()['groupedPartialOrPredicateFence']['currentGroupedPartialOrPredicateImplied']),
    'planner stat4 expression partial current source next217 payload fence preserved' => static fn (TestRunner $t) => $t->same(true, $plan217()['payloadExpressionFence']['allPayloadExpressionKeysMatch']),
    'planner stat4 expression partial current source next217 partial fence preserved' => static fn (TestRunner $t) => $t->same(true, $plan217()['partialPredicateFence']['currentPartialPredicateImplied']),
    'planner stat4 expression partial current source next217 projected lookahead page payload' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan217()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next217 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next217', $plan217()['dependencies'], true)),
    'planner stat4 expression partial current source next217 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan217()['dependency_closure']),
    'planner stat4 expression partial current source next217 non overlap' => static fn (TestRunner $t) => $t->contains('current page and next lookahead row', $plan217()['non_overlap']),
    'planner stat4 expression partial current source next217 detail' => static fn (TestRunner $t) => $t->contains('NEXT217 CURRENT/NEXT YIELD FENCE', $plan217()['detail']),
    'planner stat4 expression partial current source next217 unproved blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-yield-reprepare', $unproved217()['status']),
    'planner stat4 expression partial current source next217 unproved ready false' => static fn (TestRunner $t) => $t->same(false, $unproved217()['selectedPlan']['next217Ready']),
    'planner stat4 expression partial current source next217 unproved no cursor append' => static fn (TestRunner $t) => $t->same(false, in_array('RecheckCurrentNextStat4Yield', array_column($unproved217()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next217 no next lookahead at tail' => static fn (TestRunner $t) => $t->same(false, $plan217(5, 3)['stat4YieldFence']['hasNextLookaheadRow']),
    'planner stat4 expression partial current source next217 tail still ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next217-ready', $plan217(5, 3)['status']),
    'planner stat4 expression partial current source next217 invalid negative limit' => static function (TestRunner $t) use ($plan217): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan217(-1, 0));
    },
    'planner stat4 expression partial current source next217 invalid negative offset' => static function (TestRunner $t) use ($plan217): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan217(1, -1));
    },
];

foreach (range(1, 18) as $case) {
    $tests['planner stat4 expression partial current source next217 repeated yield fence ' . $case] = static function (TestRunner $t) use ($plan217, $case): void {
        $plan = $plan217(1 + ($case % 5), $case % 4);
        $t->same($plan['stat4YieldFence']['currentNextYieldSignature'], $plan['selectedPlan']['next217CurrentNextYieldSignature']);
    };
}

return $tests;
