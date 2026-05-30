<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq195 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull195 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between195 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared195 = static fn (): array => [
    'name' => 'prepared-wp-options-partial-predicate-stat4-expression-next195',
    'schemaCookie' => 1950,
    'stat4Generation' => 121,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_partial_predicate_stat4_next195',
        'rootPage' => 19501,
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

$current195 = static function () use ($prepared195): array {
    $source = $prepared195();
    $source['name'] = 'current-wp-options-partial-predicate-stat4-expression-next195';
    $source['schemaCookie'] = 1958;
    $source['stat4Generation'] = 144;
    $source['indexes'][0]['rootPage'] = 19588;
    $source['indexes'][0]['partialPredicateTerms'][] = ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1];
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

$terms195 = static fn (): array => [
    $between195('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq195('autoload', 'yes'),
    $eq195('blog_id', 1),
    $notNull195('option_name'),
];
$plan195 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourcePartialPredicateFence(
    $prepared ?? $prepared195(),
    $current ?? $current195(),
    $terms ?? $terms195(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$unproved195 = static function () use ($terms195, $plan195): array {
    $terms = array_values(array_filter($terms195(), static fn (array $term): bool => ($term['left']['column'] ?? null) !== 'blog_id'));

    return $plan195(5, 1, null, null, $terms);
};
$unchanged195 = static function () use ($current195, $plan195): array {
    $current = $current195();
    $prepared = $current;
    $prepared['name'] = 'prepared-same-partial-predicate-next195';
    $prepared['schemaCookie'] = 1957;
    $prepared['stat4Generation'] = 143;

    return $plan195(5, 1, $prepared, $current);
};
$unsupported195 = static function () use ($current195, $plan195): array {
    $current = $current195();
    $current['indexes'][0]['partialPredicateTerms'][] = ['left' => ['column' => 'option_value'], 'operator' => 'LIKE', 'right' => 'forms%'];

    return $plan195(5, 1, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next195 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next195-ready', $plan195()['status']),
    'planner stat4 expression partial current source next195 selected current' => static fn (TestRunner $t) => $t->same('current', $plan195()['selectedSource']),
    'planner stat4 expression partial current source next195 base payload ready' => static fn (TestRunner $t) => $t->same(true, $plan195()['selectedPlan']['next191Ready']),
    'planner stat4 expression partial current source next195 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan195()['selectedPlan']['next195Ready']),
    'planner stat4 expression partial current source next195 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_partial_predicate_stat4_next195', $plan195()['selectedPlan']['name']),
    'planner stat4 expression partial current source next195 root page' => static fn (TestRunner $t) => $t->same(19588, $plan195()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next195 partial changed' => static fn (TestRunner $t) => $t->same(true, $plan195()['partialPredicateFence']['partialPredicateChanged']),
    'planner stat4 expression partial current source next195 selected partial changed' => static fn (TestRunner $t) => $t->same(true, $plan195()['selectedPlan']['next195PartialPredicateChanged']),
    'planner stat4 expression partial current source next195 prepared term count' => static fn (TestRunner $t) => $t->same(4, count($plan195()['partialPredicateFence']['preparedPartialPredicateTerms'])),
    'planner stat4 expression partial current source next195 current term count' => static fn (TestRunner $t) => $t->same(5, count($plan195()['partialPredicateFence']['currentPartialPredicateTerms'])),
    'planner stat4 expression partial current source next195 current includes blog term' => static fn (TestRunner $t) => $t->same(true, in_array('column:blog_id', array_column($plan195()['partialPredicateFence']['currentPartialPredicateTerms'], 'leftKey'), true)),
    'planner stat4 expression partial current source next195 predicate implied' => static fn (TestRunner $t) => $t->same(true, $plan195()['partialPredicateFence']['currentPartialPredicateImplied']),
    'planner stat4 expression partial current source next195 unsupported none' => static fn (TestRunner $t) => $t->same([], $plan195()['partialPredicateFence']['unsupportedCurrentPartialTerms']),
    'planner stat4 expression partial current source next195 row proof count' => static fn (TestRunner $t) => $t->same(5, count($plan195()['partialPredicateFence']['rowProofs'])),
    'planner stat4 expression partial current source next195 row proof rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], array_column($plan195()['partialPredicateFence']['rowProofs'], 'rowid')),
    'planner stat4 expression partial current source next195 rows satisfy' => static fn (TestRunner $t) => $t->same(true, $plan195()['partialPredicateFence']['allRowsSatisfyCurrentPartialPredicate']),
    'planner stat4 expression partial current source next195 rejected none' => static fn (TestRunner $t) => $t->same([], $plan195()['partialPredicateFence']['rowidsRejectedByCurrentPartialPredicate']),
    'planner stat4 expression partial current source next195 selected rejected none' => static fn (TestRunner $t) => $t->same([], $plan195()['selectedPlan']['next195RowsRejectedByCurrentPartialPredicate']),
    'planner stat4 expression partial current source next195 matched rowids preserved' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan195()['matchedRowids']),
    'planner stat4 expression partial current source next195 matched keys preserved' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_forms', 'plugin_forms'], $plan195()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next195 projected payload' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan195()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next195 payload fence preserved' => static fn (TestRunner $t) => $t->same(true, $plan195()['payloadExpressionFence']['allPayloadExpressionKeysMatch']),
    'planner stat4 expression partial current source next195 peer fence preserved' => static fn (TestRunner $t) => $t->same(['plugin_forms' => [20, 21, 22]], $plan195()['peerFence']['peerRowids']),
    'planner stat4 expression partial current source next195 proof includes blog id' => static fn (TestRunner $t) => $t->same(true, in_array('column:blog_id', array_map(static fn (array $proof): string => $proof['term']['leftKey'], $plan195()['partialPredicateFence']['currentPartialPredicateProofs']), true)),
    'planner stat4 expression partial current source next195 proof all implied' => static fn (TestRunner $t) => $t->same([true, true, true, true, true], array_column($plan195()['partialPredicateFence']['currentPartialPredicateProofs'], 'implied')),
    'planner stat4 expression partial current source next195 row term count' => static fn (TestRunner $t) => $t->same(5, count($plan195()['partialPredicateFence']['rowProofs'][0]['termResults'])),
    'planner stat4 expression partial current source next195 row terms satisfied' => static fn (TestRunner $t) => $t->same([true, true, true, true, true], array_column($plan195()['partialPredicateFence']['rowProofs'][0]['termResults'], 'satisfied')),
    'planner stat4 expression partial current source next195 prepared signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan195()['partialPredicateFence']['preparedSignature'])),
    'planner stat4 expression partial current source next195 current signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan195()['partialPredicateFence']['currentSignature'])),
    'planner stat4 expression partial current source next195 proof signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan195()['partialPredicateFence']['proofSignature'])),
    'planner stat4 expression partial current source next195 selected prepared signature' => static fn (TestRunner $t) => $t->same($plan195()['partialPredicateFence']['preparedSignature'], $plan195()['selectedPlan']['next195PreparedPartialPredicateSignature']),
    'planner stat4 expression partial current source next195 selected current signature' => static fn (TestRunner $t) => $t->same($plan195()['partialPredicateFence']['currentSignature'], $plan195()['selectedPlan']['next195CurrentPartialPredicateSignature']),
    'planner stat4 expression partial current source next195 stat4 signature' => static fn (TestRunner $t) => $t->same($plan195()['partialPredicateFence']['currentSignature'], $plan195()['stat4Fence']['next195CurrentPartialPredicateSignature']),
    'planner stat4 expression partial current source next195 stat4 proof signature' => static fn (TestRunner $t) => $t->same($plan195()['partialPredicateFence']['proofSignature'], $plan195()['stat4Fence']['next195PartialPredicateProofSignature']),
    'planner stat4 expression partial current source next195 cursor appended' => static fn (TestRunner $t) => $t->same('RecheckCurrentPartialPredicate', $plan195()['cursorProgram'][array_key_last($plan195()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next195 cursor mode' => static fn (TestRunner $t) => $t->same('next195-current-source-stat4-expression-partial-where', $plan195()['cursorProgram'][array_key_last($plan195()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next195 cursor rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan195()['cursorProgram'][array_key_last($plan195()['cursorProgram'])]['rowids']),
    'planner stat4 expression partial current source next195 cursor changed flag' => static fn (TestRunner $t) => $t->same(true, $plan195()['cursorProgram'][array_key_last($plan195()['cursorProgram'])]['partialPredicateChanged']),
    'planner stat4 expression partial current source next195 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next195', $plan195()['dependencies'], true)),
    'planner stat4 expression partial current source next195 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan195()['dependency_closure']),
    'planner stat4 expression partial current source next195 non overlap' => static fn (TestRunner $t) => $t->contains('changed partial WHERE predicate', $plan195()['non_overlap']),
    'planner stat4 expression partial current source next195 detail' => static fn (TestRunner $t) => $t->contains('NEXT195 PARTIAL PREDICATE FENCE', $plan195()['detail']),
    'planner stat4 expression partial current source next195 unproved blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-partial-predicate-reprepare', $unproved195()['status']),
    'planner stat4 expression partial current source next195 unproved flag' => static fn (TestRunner $t) => $t->same(false, $unproved195()['partialPredicateFence']['currentPartialPredicateImplied']),
    'planner stat4 expression partial current source next195 unproved unsupported blog' => static fn (TestRunner $t) => $t->same('column:blog_id', $unproved195()['partialPredicateFence']['unsupportedCurrentPartialTerms'][0]['leftKey']),
    'planner stat4 expression partial current source next195 unproved no cursor append' => static fn (TestRunner $t) => $t->same(false, in_array('RecheckCurrentPartialPredicate', array_column($unproved195()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next195 unproved rows still satisfy' => static fn (TestRunner $t) => $t->same(true, $unproved195()['partialPredicateFence']['allRowsSatisfyCurrentPartialPredicate']),
    'planner stat4 expression partial current source next195 unproved row rejects none' => static fn (TestRunner $t) => $t->same([], $unproved195()['partialPredicateFence']['rowidsRejectedByCurrentPartialPredicate']),
    'planner stat4 expression partial current source next195 unchanged blocked by source freshness' => static fn (TestRunner $t) => $t->same('requires-current-source-partial-predicate-reprepare', $unchanged195()['status']),
    'planner stat4 expression partial current source next195 unchanged flag false' => static fn (TestRunner $t) => $t->same(false, $unchanged195()['partialPredicateFence']['partialPredicateChanged']),
    'planner stat4 expression partial current source next195 unsupported blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-partial-predicate-reprepare', $unsupported195()['status']),
    'planner stat4 expression partial current source next195 unsupported operator' => static fn (TestRunner $t) => $t->same('LIKE', $unsupported195()['partialPredicateFence']['unsupportedCurrentPartialTerms'][0]['operator']),
    'planner stat4 expression partial current source next195 invalid current indexes' => static function (TestRunner $t) use ($current195, $plan195): void {
        $bad = $current195();
        $bad['indexes'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan195(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next195 invalid prepared predicate' => static function (TestRunner $t) use ($prepared195, $plan195): void {
        $bad = $prepared195();
        $bad['indexes'][0]['partialPredicateTerms'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan195(5, 1, $bad));
    },
    'planner stat4 expression partial current source next195 invalid current term' => static function (TestRunner $t) use ($current195, $plan195): void {
        $bad = $current195();
        $bad['indexes'][0]['partialPredicateTerms'][] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan195(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next195 invalid operator' => static function (TestRunner $t) use ($current195, $plan195): void {
        $bad = $current195();
        $bad['indexes'][0]['partialPredicateTerms'][] = ['left' => ['column' => 'blog_id'], 'operator' => '', 'right' => 1];
        $t->throws(InvalidArgumentException::class, static fn () => $plan195(5, 1, null, $bad));
    },
];

foreach (range(1, 12) as $case) {
    $tests['planner stat4 expression partial current source next195 repeated predicate fence ' . $case] = static function (TestRunner $t) use ($plan195, $case): void {
        $plan = $plan195(1 + ($case % 5), $case % 4);
        $t->same(count($plan['matchedRows']), count($plan['partialPredicateFence']['rowProofs']));
    };
}

return $tests;
