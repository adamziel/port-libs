<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq250 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like250 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull250 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between250 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$payload250 = static fn (array $row): array => [
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

$prepared250 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-partial-predicate-next250',
    'schemaCookie' => 2500,
    'stat4Generation' => 250,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_stat4_partial_predicate_next250',
        'rootPage' => 25001,
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
            ['neq' => '3 3', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 1, 20]],
            ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 1, 50]],
            ['neq' => '1 1', 'nlt' => '6 6', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 1, 30]],
            ['neq' => '1 1', 'nlt' => '7 7', 'ndlt' => '5 5', 'sample' => ['plugin_zulu', 1, 60]],
        ],
        'stat4ExpressionPayloads' => [],
    ]],
];

$current250 = static function (string $variant = 'ready') use ($prepared250, $payload250): array {
    $source = $prepared250();
    $source['name'] = 'current-wp-options-stat4-partial-predicate-next250';
    $source['schemaCookie'] = 2509;
    $source['stat4Generation'] = 850;
    $source['indexes'][0]['rootPage'] = 25088;
    $source['indexes'][0]['partialPredicateTerms'] = [
        ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => '>=', 'right' => 'plugin_alpha'],
        ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => '<=', 'right' => 'plugin_zulu'],
        ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
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
    if ($variant === 'stale-kind') {
        $source['rows'][4]['autoload'] = 'no';
    }
    if ($variant === 'tightened-upper') {
        $source['indexes'][0]['partialPredicateTerms'][1]['right'] = 'plugin_mail';
    }
    if ($variant === 'missing-row') {
        $source['rows'] = array_values(array_filter($source['rows'], static fn (array $row): bool => $row['rowid'] !== 21));
    }
    if ($variant === 'unsupported-predicate') {
        $source['indexes'][0]['partialPredicateTerms'][] = ['left' => ['column' => 'option_name'], 'operator' => 'GLOB', 'right' => 'plugin_*'];
    }
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map(
        $payload250,
        array_values(array_filter($source['rows'], static fn (array $row): bool => $row['autoload'] === 'yes')),
    );

    return $source;
};

$terms250 = static fn (): array => [
    $between250('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq250('autoload', 'yes'),
    $notNull250('option_name'),
    $eq250('blog_id', 1),
    $like250('option_name', 'plugin_%'),
];

$plan250 = static fn (string $variant = 'ready', int $limit = 5, int $offset = 1): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext250(
    $prepared250(),
    $current250($variant),
    $terms250(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);

$tests = [
    'planner stat4 expression partial current source next250 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next250-ready', $plan250()['status']),
    'planner stat4 expression partial current source next250 inherits stat4BoundaryPeer' => static fn (TestRunner $t) => $t->same(true, $plan250()['selectedPlan']['stat4BoundaryPeerReady']),
    'planner stat4 expression partial current source next250 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_stat4_partial_predicate_next250', $plan250()['selectedPlan']['name']),
    'planner stat4 expression partial current source next250 root page' => static fn (TestRunner $t) => $t->same(25088, $plan250()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next250 matched rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan250()['matchedRowids']),
    'planner stat4 expression partial current source next250 predicate term count' => static fn (TestRunner $t) => $t->same(4, $plan250()['stat4CurrentPartialPredicateFence']['partialPredicateTermCount']),
    'planner stat4 expression partial current source next250 predicate rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan250()['stat4CurrentPartialPredicateFence']['predicateMatchedRowids']),
    'planner stat4 expression partial current source next250 no mismatches' => static fn (TestRunner $t) => $t->same([], $plan250()['stat4CurrentPartialPredicateFence']['predicateMismatchRowids']),
    'planner stat4 expression partial current source next250 no missing rows' => static fn (TestRunner $t) => $t->same([], $plan250()['stat4CurrentPartialPredicateFence']['missingCurrentRowids']),
    'planner stat4 expression partial current source next250 all satisfy' => static fn (TestRunner $t) => $t->same(true, $plan250()['stat4CurrentPartialPredicateFence']['allYieldedRowsSatisfyCurrentPartialPredicate']),
    'planner stat4 expression partial current source next250 selected ready' => static fn (TestRunner $t) => $t->same(true, $plan250()['selectedPlan']['next250Ready']),
    'planner stat4 expression partial current source next250 selected term count' => static fn (TestRunner $t) => $t->same(4, $plan250()['selectedPlan']['next250PartialPredicateTermCount']),
    'planner stat4 expression partial current source next250 selected signature' => static fn (TestRunner $t) => $t->same($plan250()['stat4CurrentPartialPredicateFence']['proofSignature'], $plan250()['selectedPlan']['next250ProofSignature']),
    'planner stat4 expression partial current source next250 stat fence ready' => static fn (TestRunner $t) => $t->same(true, $plan250()['stat4Fence']['next250CurrentPartialPredicateReady']),
    'planner stat4 expression partial current source next250 stat fence signature' => static fn (TestRunner $t) => $t->same($plan250()['stat4CurrentPartialPredicateFence']['proofSignature'], $plan250()['stat4Fence']['next250CurrentPartialPredicateSignature']),
    'planner stat4 expression partial current source next250 cursor appended' => static fn (TestRunner $t) => $t->same('VerifyCurrentPartialPredicate', $plan250()['cursorProgram'][array_key_last($plan250()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next250 cursor mode' => static fn (TestRunner $t) => $t->same('next250-current-source-stat4-expression-partial-predicate', $plan250()['cursorProgram'][array_key_last($plan250()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next250 cursor rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan250()['cursorProgram'][array_key_last($plan250()['cursorProgram'])]['predicateMatchedRowids']),
    'planner stat4 expression partial current source next250 first proof rowid' => static fn (TestRunner $t) => $t->same(30, $plan250()['stat4CurrentPartialPredicateFence']['rowProofs'][0]['rowid']),
    'planner stat4 expression partial current source next250 first proof lower term' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan250()['stat4CurrentPartialPredicateFence']['rowProofs'][0]['termResults'][0]['leftValue']),
    'planner stat4 expression partial current source next250 first proof autoload term' => static fn (TestRunner $t) => $t->same('yes', $plan250()['stat4CurrentPartialPredicateFence']['rowProofs'][0]['termResults'][2]['leftValue']),
    'planner stat4 expression partial current source next250 projected payload retained' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan250()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next250 boundary peers preserved' => static fn (TestRunner $t) => $t->same([30, 20, 21, 22], $plan250()['stat4BoundaryPeerFence']['currentBoundaryPeerRowids']),
    'planner stat4 expression partial current source next250 detail' => static fn (TestRunner $t) => $t->contains('NEXT250 PARTIAL PREDICATE FENCE', $plan250()['detail']),
    'planner stat4 expression partial current source next250 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next250', $plan250()['dependencies'], true)),
    'planner stat4 expression partial current source next250 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan250()['dependency_closure']),
    'planner stat4 expression partial current source next250 non overlap' => static fn (TestRunner $t) => $t->contains('partial-index predicate rowid fencing', $plan250()['non_overlap']),
    'planner stat4 expression partial current source next250 stale non-window row remains ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next250-ready', $plan250('stale-kind')['status']),
    'planner stat4 expression partial current source next250 stale autoload mismatch' => static fn (TestRunner $t) => $t->same([], $plan250('stale-kind')['stat4CurrentPartialPredicateFence']['predicateMismatchRowids']),
    'planner stat4 expression partial current source next250 stale selected still ready' => static fn (TestRunner $t) => $t->same(true, $plan250('stale-kind')['selectedPlan']['next250Ready']),
    'planner stat4 expression partial current source next250 stale cursor appended' => static fn (TestRunner $t) => $t->same(true, in_array('VerifyCurrentPartialPredicate', array_column($plan250('stale-kind')['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next250 tightened upper blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-partial-predicate-reprepare', $plan250('tightened-upper')['status']),
    'planner stat4 expression partial current source next250 tightened upper mismatches' => static fn (TestRunner $t) => $t->same([], $plan250('tightened-upper')['stat4CurrentPartialPredicateFence']['predicateMismatchRowids']),
    'planner stat4 expression partial current source next250 missing non-window row remains ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next250-ready', $plan250('missing-row')['status']),
    'planner stat4 expression partial current source next250 unsupported partial predicate throws' => static function (TestRunner $t) use ($plan250): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan250('unsupported-predicate'));
    },
    'planner stat4 expression partial current source next250 invalid limit' => static function (TestRunner $t) use ($plan250): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan250('ready', -1, 0));
    },
    'planner stat4 expression partial current source next250 invalid offset' => static function (TestRunner $t) use ($plan250): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan250('ready', 1, -1));
    },
];

foreach (range(1, 22) as $case) {
    $tests['planner stat4 expression partial current source next250 repeated predicate proof ' . $case] = static function (TestRunner $t) use ($plan250, $case): void {
        $plan = $plan250('ready', 1 + ($case % 5), $case % 3);
        $t->same($plan['stat4CurrentPartialPredicateFence']['proofSignature'], $plan['selectedPlan']['next250ProofSignature']);
    };
}

return $tests;
