<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq206 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull206 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between206 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared206 = static fn (): array => [
    'name' => 'prepared-wp-options-partial-or-stat4-expression-next206',
    'schemaCookie' => 2060,
    'stat4Generation' => 206,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_partial_or_stat4_next206',
        'rootPage' => 20601,
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
        'partialOrPredicateTerms' => [
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'critical'],
        ],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
        ],
    ]],
];

$current206 = static function () use ($prepared206): array {
    $source = $prepared206();
    $source['name'] = 'current-wp-options-partial-or-stat4-expression-next206';
    $source['schemaCookie'] = 2068;
    $source['stat4Generation'] = 244;
    $source['indexes'][0]['rootPage'] = 20688;
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

$terms206 = static fn (): array => [
    $between206('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq206('autoload', 'yes'),
    $notNull206('option_name'),
    $eq206('blog_id', 1),
];
$plan206 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourcePartialOrPayloadFence(
    $prepared ?? $prepared206(),
    $current ?? $current206(),
    $terms ?? $terms206(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$unproved206 = static function () use ($terms206, $plan206): array {
    $terms = array_values(array_filter($terms206(), static fn (array $term): bool => ($term['left']['column'] ?? null) !== 'blog_id'));

    return $plan206(5, 1, null, null, $terms);
};

$tests = [
    'planner stat4 expression partial current source next206 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next206-ready', $plan206()['status']),
    'planner stat4 expression partial current source next206 selected current' => static fn (TestRunner $t) => $t->same('current', $plan206()['selectedSource']),
    'planner stat4 expression partial current source next206 inherited next195' => static fn (TestRunner $t) => $t->same(true, $plan206()['selectedPlan']['next195Ready']),
    'planner stat4 expression partial current source next206 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan206()['selectedPlan']['next206Ready']),
    'planner stat4 expression partial current source next206 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_partial_or_stat4_next206', $plan206()['selectedPlan']['name']),
    'planner stat4 expression partial current source next206 root page' => static fn (TestRunner $t) => $t->same(20688, $plan206()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next206 or term count' => static fn (TestRunner $t) => $t->same(2, count($plan206()['partialOrPredicateFence']['currentPartialOrPredicateTerms'])),
    'planner stat4 expression partial current source next206 or implied' => static fn (TestRunner $t) => $t->same(true, $plan206()['partialOrPredicateFence']['currentPartialOrPredicateImplied']),
    'planner stat4 expression partial current source next206 matched or arm' => static fn (TestRunner $t) => $t->same(1, $plan206()['partialOrPredicateFence']['matchedOrArm']),
    'planner stat4 expression partial current source next206 matched or arms' => static fn (TestRunner $t) => $t->same([1], $plan206()['partialOrPredicateFence']['matchedOrArms']),
    'planner stat4 expression partial current source next206 selected matched arm' => static fn (TestRunner $t) => $t->same(1, $plan206()['selectedPlan']['next206MatchedOrArm']),
    'planner stat4 expression partial current source next206 unsupported none' => static fn (TestRunner $t) => $t->same([], $plan206()['partialOrPredicateFence']['unsupportedCurrentPartialOrTerms']),
    'planner stat4 expression partial current source next206 row proof rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], array_column($plan206()['partialOrPredicateFence']['rowProofs'], 'rowid')),
    'planner stat4 expression partial current source next206 rows satisfy' => static fn (TestRunner $t) => $t->same(true, $plan206()['partialOrPredicateFence']['allRowsSatisfyCurrentPartialOrPredicate']),
    'planner stat4 expression partial current source next206 rejected none' => static fn (TestRunner $t) => $t->same([], $plan206()['partialOrPredicateFence']['rowidsRejectedByCurrentPartialOrPredicate']),
    'planner stat4 expression partial current source next206 selected rejected none' => static fn (TestRunner $t) => $t->same([], $plan206()['selectedPlan']['next206RowsRejectedByCurrentPartialOrPredicate']),
    'planner stat4 expression partial current source next206 row first term results' => static fn (TestRunner $t) => $t->same([false, true], array_column($plan206()['partialOrPredicateFence']['rowProofs'][0]['termResults'], 'satisfied')),
    'planner stat4 expression partial current source next206 row term arms' => static fn (TestRunner $t) => $t->same([0, 1], array_column($plan206()['partialOrPredicateFence']['rowProofs'][0]['termResults'], 'orArm')),
    'planner stat4 expression partial current source next206 matched rowids preserved' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan206()['matchedRowids']),
    'planner stat4 expression partial current source next206 matched keys preserved' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_forms', 'plugin_forms'], $plan206()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next206 projected payload' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan206()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next206 payload fence preserved' => static fn (TestRunner $t) => $t->same(true, $plan206()['payloadExpressionFence']['allPayloadExpressionKeysMatch']),
    'planner stat4 expression partial current source next206 conjunctive fence preserved' => static fn (TestRunner $t) => $t->same(true, $plan206()['partialPredicateFence']['currentPartialPredicateImplied']),
    'planner stat4 expression partial current source next206 proof all flags' => static fn (TestRunner $t) => $t->same([false, true], array_column($plan206()['partialOrPredicateFence']['currentPartialOrPredicateProofs'], 'implied')),
    'planner stat4 expression partial current source next206 proof left keys' => static fn (TestRunner $t) => $t->same(['column:autoload', 'column:blog_id'], array_map(static fn (array $proof): string => $proof['term']['leftKey'], $plan206()['partialOrPredicateFence']['currentPartialOrPredicateProofs'])),
    'planner stat4 expression partial current source next206 or signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan206()['partialOrPredicateFence']['currentOrSignature'])),
    'planner stat4 expression partial current source next206 proof signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan206()['partialOrPredicateFence']['proofSignature'])),
    'planner stat4 expression partial current source next206 selected signature' => static fn (TestRunner $t) => $t->same($plan206()['partialOrPredicateFence']['currentOrSignature'], $plan206()['selectedPlan']['next206CurrentPartialOrSignature']),
    'planner stat4 expression partial current source next206 stat4 signature' => static fn (TestRunner $t) => $t->same($plan206()['partialOrPredicateFence']['currentOrSignature'], $plan206()['stat4Fence']['next206CurrentPartialOrSignature']),
    'planner stat4 expression partial current source next206 stat4 proof signature' => static fn (TestRunner $t) => $t->same($plan206()['partialOrPredicateFence']['proofSignature'], $plan206()['stat4Fence']['next206PartialOrProofSignature']),
    'planner stat4 expression partial current source next206 cursor appended' => static fn (TestRunner $t) => $t->same('RecheckCurrentPartialOrPredicate', $plan206()['cursorProgram'][array_key_last($plan206()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next206 cursor mode' => static fn (TestRunner $t) => $t->same('next206-current-source-stat4-expression-partial-or-where', $plan206()['cursorProgram'][array_key_last($plan206()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next206 cursor rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan206()['cursorProgram'][array_key_last($plan206()['cursorProgram'])]['rowids']),
    'planner stat4 expression partial current source next206 cursor arm' => static fn (TestRunner $t) => $t->same(1, $plan206()['cursorProgram'][array_key_last($plan206()['cursorProgram'])]['matchedOrArm']),
    'planner stat4 expression partial current source next206 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next206', $plan206()['dependencies'], true)),
    'planner stat4 expression partial current source next206 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan206()['dependency_closure']),
    'planner stat4 expression partial current source next206 non overlap' => static fn (TestRunner $t) => $t->contains('one OR arm', $plan206()['non_overlap']),
    'planner stat4 expression partial current source next206 detail' => static fn (TestRunner $t) => $t->contains('NEXT206 PARTIAL OR PREDICATE FENCE', $plan206()['detail']),
    'planner stat4 expression partial current source next206 unproved blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-partial-or-reprepare', $unproved206()['status']),
    'planner stat4 expression partial current source next206 unproved implied false' => static fn (TestRunner $t) => $t->same(false, $unproved206()['partialOrPredicateFence']['currentPartialOrPredicateImplied']),
    'planner stat4 expression partial current source next206 unproved unsupported count' => static fn (TestRunner $t) => $t->same(2, count($unproved206()['partialOrPredicateFence']['unsupportedCurrentPartialOrTerms'])),
    'planner stat4 expression partial current source next206 unproved no cursor append' => static fn (TestRunner $t) => $t->same(false, in_array('RecheckCurrentPartialOrPredicate', array_column($unproved206()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next206 unproved rejected selected none' => static fn (TestRunner $t) => $t->same([], $unproved206()['selectedPlan']['next206RowsRejectedByCurrentPartialOrPredicate']),
    'planner stat4 expression partial current source next206 normalized first arm' => static fn (TestRunner $t) => $t->same('column:autoload', $plan206()['partialOrPredicateFence']['currentPartialOrPredicateTerms'][0]['leftKey']),
    'planner stat4 expression partial current source next206 normalized second arm' => static fn (TestRunner $t) => $t->same('column:blog_id', $plan206()['partialOrPredicateFence']['currentPartialOrPredicateTerms'][1]['leftKey']),
    'planner stat4 expression partial current source next206 invalid current indexes' => static function (TestRunner $t) use ($current206, $plan206): void {
        $bad = $current206();
        $bad['indexes'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan206(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next206 invalid or terms' => static function (TestRunner $t) use ($current206, $plan206): void {
        $bad = $current206();
        $bad['indexes'][0]['partialOrPredicateTerms'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan206(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next206 invalid or entry' => static function (TestRunner $t) use ($current206, $plan206): void {
        $bad = $current206();
        $bad['indexes'][0]['partialOrPredicateTerms'][] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan206(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next206 invalid or operator' => static function (TestRunner $t) use ($current206, $plan206): void {
        $bad = $current206();
        $bad['indexes'][0]['partialOrPredicateTerms'][] = ['left' => ['column' => 'blog_id'], 'operator' => '', 'right' => 1];
        $t->throws(InvalidArgumentException::class, static fn () => $plan206(5, 1, null, $bad));
    },
];

foreach (range(1, 12) as $case) {
    $tests['planner stat4 expression partial current source next206 repeated or fence ' . $case] = static function (TestRunner $t) use ($plan206, $case): void {
        $plan = $plan206(1 + ($case % 5), $case % 4);
        $t->same(count($plan['matchedRows']), count($plan['partialOrPredicateFence']['rowProofs']));
    };
}

return $tests;
