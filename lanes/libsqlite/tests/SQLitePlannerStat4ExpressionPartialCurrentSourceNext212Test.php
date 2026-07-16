<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq212 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like212 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull212 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between212 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared212 = static fn (): array => [
    'name' => 'prepared-wp-options-grouped-like-partial-stat4-expression-next212',
    'schemaCookie' => 2120,
    'stat4Generation' => 212,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_grouped_like_stat4_next212',
        'rootPage' => 21201,
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

$current212 = static function () use ($prepared212): array {
    $source = $prepared212();
    $source['name'] = 'current-wp-options-grouped-like-partial-stat4-expression-next212';
    $source['schemaCookie'] = 2128;
    $source['stat4Generation'] = 268;
    $source['indexes'][0]['rootPage'] = 21288;
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

$terms212 = static fn (): array => [
    $between212('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq212('autoload', 'yes'),
    $notNull212('option_name'),
    $eq212('blog_id', 1),
    $like212('option_name', 'plugin_%'),
];
$plan212 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceGroupedLikeFence(
    $prepared ?? $prepared212(),
    $current ?? $current212(),
    $terms ?? $terms212(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$unproved212 = static function () use ($terms212, $plan212): array {
    $terms = array_values(array_filter($terms212(), static fn (array $term): bool => ($term['operator'] ?? null) !== 'LIKE'));

    return $plan212(5, 1, null, null, $terms);
};
$escaped212 = static function () use ($current212, $plan212): array {
    $current = $current212();
    $current['indexes'][0]['partialGroupedLikePredicateArms'][0][1]['right'] = 'plugin\\_f%';
    $current['indexes'][0]['partialGroupedLikePredicateArms'][0][1]['escape'] = '\\';
    return $plan212(5, 1, null, $current, [
        ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => 'BETWEEN', 'lower' => 'plugin_alpha', 'upper' => 'plugin_zulu'],
        ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
        ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'plugin\\_f%', 'escape' => '\\'],
    ]);
};

$tests = [
    'planner stat4 expression partial current source next212 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next212-ready', $plan212()['status']),
    'planner stat4 expression partial current source next212 selected current' => static fn (TestRunner $t) => $t->same('current', $plan212()['selectedSource']),
    'planner stat4 expression partial current source next212 inherited next209' => static fn (TestRunner $t) => $t->same(true, $plan212()['selectedPlan']['next209Ready']),
    'planner stat4 expression partial current source next212 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan212()['selectedPlan']['next212Ready']),
    'planner stat4 expression partial current source next212 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_grouped_like_stat4_next212', $plan212()['selectedPlan']['name']),
    'planner stat4 expression partial current source next212 root page' => static fn (TestRunner $t) => $t->same(21288, $plan212()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next212 arm count' => static fn (TestRunner $t) => $t->same(2, count($plan212()['groupedLikePredicateFence']['currentGroupedLikePredicateArms'])),
    'planner stat4 expression partial current source next212 first arm term count' => static fn (TestRunner $t) => $t->same(2, count($plan212()['groupedLikePredicateFence']['currentGroupedLikePredicateArms'][0]['terms'])),
    'planner stat4 expression partial current source next212 grouped implied' => static fn (TestRunner $t) => $t->same(true, $plan212()['groupedLikePredicateFence']['currentGroupedLikePredicateImplied']),
    'planner stat4 expression partial current source next212 matched arm' => static fn (TestRunner $t) => $t->same(0, $plan212()['groupedLikePredicateFence']['matchedGroupedLikeArm']),
    'planner stat4 expression partial current source next212 matched arms' => static fn (TestRunner $t) => $t->same([0], $plan212()['groupedLikePredicateFence']['matchedGroupedLikeArms']),
    'planner stat4 expression partial current source next212 selected matched arm' => static fn (TestRunner $t) => $t->same(0, $plan212()['selectedPlan']['next212MatchedGroupedLikeArm']),
    'planner stat4 expression partial current source next212 unsupported none' => static fn (TestRunner $t) => $t->same([], $plan212()['groupedLikePredicateFence']['unsupportedCurrentGroupedLikeArms']),
    'planner stat4 expression partial current source next212 normalized keys' => static fn (TestRunner $t) => $t->same(['column:blog_id', 'column:option_name'], array_column($plan212()['groupedLikePredicateFence']['currentGroupedLikePredicateArms'][0]['terms'], 'leftKey')),
    'planner stat4 expression partial current source next212 normalized operators' => static fn (TestRunner $t) => $t->same(['=', 'LIKE'], array_column($plan212()['groupedLikePredicateFence']['currentGroupedLikePredicateArms'][0]['terms'], 'operator')),
    'planner stat4 expression partial current source next212 like prefix' => static fn (TestRunner $t) => $t->same('plugin', $plan212()['groupedLikePredicateFence']['currentGroupedLikePredicateArms'][0]['terms'][1]['prefix']),
    'planner stat4 expression partial current source next212 proof flags' => static fn (TestRunner $t) => $t->same([true, false], array_column($plan212()['groupedLikePredicateFence']['currentGroupedLikePredicateProofs'], 'implied')),
    'planner stat4 expression partial current source next212 first proof term flags' => static fn (TestRunner $t) => $t->same([true, true], array_column($plan212()['groupedLikePredicateFence']['currentGroupedLikePredicateProofs'][0]['termProofs'], 'implied')),
    'planner stat4 expression partial current source next212 row proof rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], array_column($plan212()['groupedLikePredicateFence']['rowProofs'], 'rowid')),
    'planner stat4 expression partial current source next212 rows satisfy' => static fn (TestRunner $t) => $t->same(true, $plan212()['groupedLikePredicateFence']['allRowsSatisfyCurrentGroupedLikePredicate']),
    'planner stat4 expression partial current source next212 rejected none' => static fn (TestRunner $t) => $t->same([], $plan212()['groupedLikePredicateFence']['rowidsRejectedByCurrentGroupedLikePredicate']),
    'planner stat4 expression partial current source next212 selected rejected none' => static fn (TestRunner $t) => $t->same([], $plan212()['selectedPlan']['next212RowsRejectedByCurrentGroupedLikePredicate']),
    'planner stat4 expression partial current source next212 mixed case like rows' => static fn (TestRunner $t) => $t->same([true, true], array_column($plan212()['groupedLikePredicateFence']['rowProofs'][1]['armResults'][0]['termsSatisfied'], 'satisfied')),
    'planner stat4 expression partial current source next212 second arm false' => static fn (TestRunner $t) => $t->same(false, $plan212()['groupedLikePredicateFence']['rowProofs'][0]['armResults'][1]['satisfied']),
    'planner stat4 expression partial current source next212 matched rowids preserved' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan212()['matchedRowids']),
    'planner stat4 expression partial current source next212 matched keys preserved' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_forms', 'plugin_forms'], $plan212()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next212 projected payload' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan212()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next212 grouped or preserved' => static fn (TestRunner $t) => $t->same(true, $plan212()['groupedPartialOrPredicateFence']['currentGroupedPartialOrPredicateImplied']),
    'planner stat4 expression partial current source next212 payload fence preserved' => static fn (TestRunner $t) => $t->same(true, $plan212()['payloadExpressionFence']['allPayloadExpressionKeysMatch']),
    'planner stat4 expression partial current source next212 conjunctive fence preserved' => static fn (TestRunner $t) => $t->same(true, $plan212()['partialPredicateFence']['currentPartialPredicateImplied']),
    'planner stat4 expression partial current source next212 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan212()['groupedLikePredicateFence']['currentGroupedLikeSignature'])),
    'planner stat4 expression partial current source next212 proof signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan212()['groupedLikePredicateFence']['proofSignature'])),
    'planner stat4 expression partial current source next212 selected signature' => static fn (TestRunner $t) => $t->same($plan212()['groupedLikePredicateFence']['currentGroupedLikeSignature'], $plan212()['selectedPlan']['next212CurrentGroupedLikeSignature']),
    'planner stat4 expression partial current source next212 stat4 signature' => static fn (TestRunner $t) => $t->same($plan212()['groupedLikePredicateFence']['currentGroupedLikeSignature'], $plan212()['stat4Fence']['next212CurrentGroupedLikeSignature']),
    'planner stat4 expression partial current source next212 stat4 proof signature' => static fn (TestRunner $t) => $t->same($plan212()['groupedLikePredicateFence']['proofSignature'], $plan212()['stat4Fence']['next212GroupedLikeProofSignature']),
    'planner stat4 expression partial current source next212 cursor appended' => static fn (TestRunner $t) => $t->same('RecheckCurrentGroupedLikeArm', $plan212()['cursorProgram'][array_key_last($plan212()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next212 cursor mode' => static fn (TestRunner $t) => $t->same('next212-current-source-stat4-expression-partial-grouped-like-arm', $plan212()['cursorProgram'][array_key_last($plan212()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next212 cursor rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan212()['cursorProgram'][array_key_last($plan212()['cursorProgram'])]['rowids']),
    'planner stat4 expression partial current source next212 cursor arm' => static fn (TestRunner $t) => $t->same(0, $plan212()['cursorProgram'][array_key_last($plan212()['cursorProgram'])]['matchedGroupedLikeArm']),
    'planner stat4 expression partial current source next212 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next212', $plan212()['dependencies'], true)),
    'planner stat4 expression partial current source next212 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan212()['dependency_closure']),
    'planner stat4 expression partial current source next212 non overlap' => static fn (TestRunner $t) => $t->contains('LIKE prefix', $plan212()['non_overlap']),
    'planner stat4 expression partial current source next212 detail' => static fn (TestRunner $t) => $t->contains('NEXT212 GROUPED LIKE PARTIAL ARM FENCE', $plan212()['detail']),
    'planner stat4 expression partial current source next212 unproved blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-grouped-like-reprepare', $unproved212()['status']),
    'planner stat4 expression partial current source next212 unproved implied false' => static fn (TestRunner $t) => $t->same(false, $unproved212()['groupedLikePredicateFence']['currentGroupedLikePredicateImplied']),
    'planner stat4 expression partial current source next212 unproved unsupported arms' => static fn (TestRunner $t) => $t->same([0, 1], $unproved212()['groupedLikePredicateFence']['unsupportedCurrentGroupedLikeArms']),
    'planner stat4 expression partial current source next212 unproved no cursor append' => static fn (TestRunner $t) => $t->same(false, in_array('RecheckCurrentGroupedLikeArm', array_column($unproved212()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next212 escaped prefix blocked by selected rows' => static fn (TestRunner $t) => $t->same('requires-current-source-grouped-like-reprepare', $escaped212()['status']),
    'planner stat4 expression partial current source next212 escaped prefix' => static fn (TestRunner $t) => $t->same('plugin_f', $escaped212()['groupedLikePredicateFence']['currentGroupedLikePredicateArms'][0]['terms'][1]['prefix']),
    'planner stat4 expression partial current source next212 invalid current indexes' => static function (TestRunner $t) use ($current212, $plan212): void {
        $bad = $current212();
        $bad['indexes'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan212(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next212 invalid like arms' => static function (TestRunner $t) use ($current212, $plan212): void {
        $bad = $current212();
        $bad['indexes'][0]['partialGroupedLikePredicateArms'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan212(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next212 invalid like arm entry' => static function (TestRunner $t) use ($current212, $plan212): void {
        $bad = $current212();
        $bad['indexes'][0]['partialGroupedLikePredicateArms'][] = ['bad'];
        $t->throws(InvalidArgumentException::class, static fn () => $plan212(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next212 invalid operator' => static function (TestRunner $t) use ($current212, $plan212): void {
        $bad = $current212();
        $bad['indexes'][0]['partialGroupedLikePredicateArms'][0][] = ['left' => ['column' => 'blog_id'], 'operator' => '', 'right' => 1];
        $t->throws(InvalidArgumentException::class, static fn () => $plan212(5, 1, null, $bad));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next212 repeated grouped like fence ' . $case] = static function (TestRunner $t) use ($plan212, $case): void {
        $plan = $plan212(1 + ($case % 5), $case % 4);
        $t->same(count($plan['matchedRows']), count($plan['groupedLikePredicateFence']['rowProofs']));
    };
}

return $tests;
