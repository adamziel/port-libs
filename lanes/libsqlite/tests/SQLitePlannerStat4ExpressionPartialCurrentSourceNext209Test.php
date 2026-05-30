<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq209 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull209 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between209 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared209 = static fn (): array => [
    'name' => 'prepared-wp-options-grouped-partial-or-stat4-expression-next209',
    'schemaCookie' => 2090,
    'stat4Generation' => 209,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_grouped_partial_or_stat4_next209',
        'rootPage' => 20901,
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

$current209 = static function () use ($prepared209): array {
    $source = $prepared209();
    $source['name'] = 'current-wp-options-grouped-partial-or-stat4-expression-next209';
    $source['schemaCookie'] = 2098;
    $source['stat4Generation'] = 255;
    $source['indexes'][0]['rootPage'] = 20988;
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

$terms209 = static fn (): array => [
    $between209('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq209('autoload', 'yes'),
    $notNull209('option_name'),
    $eq209('blog_id', 1),
];
$plan209 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceRepeatedSeekWindowFence(
    $prepared ?? $prepared209(),
    $current ?? $current209(),
    $terms ?? $terms209(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$unproved209 = static function () use ($terms209, $plan209): array {
    $terms = array_values(array_filter($terms209(), static fn (array $term): bool => ($term['left']['column'] ?? null) !== 'blog_id'));

    return $plan209(5, 1, null, null, $terms);
};
$tests = [
    'planner stat4 expression partial current source next209 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next209-ready', $plan209()['status']),
    'planner stat4 expression partial current source next209 selected current' => static fn (TestRunner $t) => $t->same('current', $plan209()['selectedSource']),
    'planner stat4 expression partial current source next209 inherited next195' => static fn (TestRunner $t) => $t->same(true, $plan209()['selectedPlan']['next195Ready']),
    'planner stat4 expression partial current source next209 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan209()['selectedPlan']['next209Ready']),
    'planner stat4 expression partial current source next209 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_grouped_partial_or_stat4_next209', $plan209()['selectedPlan']['name']),
    'planner stat4 expression partial current source next209 root page' => static fn (TestRunner $t) => $t->same(20988, $plan209()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next209 arm count' => static fn (TestRunner $t) => $t->same(2, count($plan209()['groupedPartialOrPredicateFence']['currentGroupedPartialOrPredicateArms'])),
    'planner stat4 expression partial current source next209 first arm term count' => static fn (TestRunner $t) => $t->same(2, count($plan209()['groupedPartialOrPredicateFence']['currentGroupedPartialOrPredicateArms'][0]['terms'])),
    'planner stat4 expression partial current source next209 second arm term count' => static fn (TestRunner $t) => $t->same(1, count($plan209()['groupedPartialOrPredicateFence']['currentGroupedPartialOrPredicateArms'][1]['terms'])),
    'planner stat4 expression partial current source next209 grouped implied' => static fn (TestRunner $t) => $t->same(true, $plan209()['groupedPartialOrPredicateFence']['currentGroupedPartialOrPredicateImplied']),
    'planner stat4 expression partial current source next209 matched arm' => static fn (TestRunner $t) => $t->same(0, $plan209()['groupedPartialOrPredicateFence']['matchedGroupedOrArm']),
    'planner stat4 expression partial current source next209 matched arms' => static fn (TestRunner $t) => $t->same([0], $plan209()['groupedPartialOrPredicateFence']['matchedGroupedOrArms']),
    'planner stat4 expression partial current source next209 selected matched arm' => static fn (TestRunner $t) => $t->same(0, $plan209()['selectedPlan']['next209MatchedGroupedOrArm']),
    'planner stat4 expression partial current source next209 unsupported none' => static fn (TestRunner $t) => $t->same([], $plan209()['groupedPartialOrPredicateFence']['unsupportedCurrentGroupedPartialOrArms']),
    'planner stat4 expression partial current source next209 row proof rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], array_column($plan209()['groupedPartialOrPredicateFence']['rowProofs'], 'rowid')),
    'planner stat4 expression partial current source next209 rows satisfy' => static fn (TestRunner $t) => $t->same(true, $plan209()['groupedPartialOrPredicateFence']['allRowsSatisfyCurrentGroupedPartialOrPredicate']),
    'planner stat4 expression partial current source next209 rejected none' => static fn (TestRunner $t) => $t->same([], $plan209()['groupedPartialOrPredicateFence']['rowidsRejectedByCurrentGroupedPartialOrPredicate']),
    'planner stat4 expression partial current source next209 selected rejected none' => static fn (TestRunner $t) => $t->same([], $plan209()['selectedPlan']['next209RowsRejectedByCurrentGroupedPartialOrPredicate']),
    'planner stat4 expression partial current source next209 row first arm satisfied' => static fn (TestRunner $t) => $t->same(true, $plan209()['groupedPartialOrPredicateFence']['rowProofs'][0]['armResults'][0]['satisfied']),
    'planner stat4 expression partial current source next209 row second arm false' => static fn (TestRunner $t) => $t->same(false, $plan209()['groupedPartialOrPredicateFence']['rowProofs'][0]['armResults'][1]['satisfied']),
    'planner stat4 expression partial current source next209 first arm term satisfaction' => static fn (TestRunner $t) => $t->same([true, true], array_column($plan209()['groupedPartialOrPredicateFence']['rowProofs'][0]['armResults'][0]['termsSatisfied'], 'satisfied')),
    'planner stat4 expression partial current source next209 first arm normalized terms' => static fn (TestRunner $t) => $t->same(['column:autoload', 'column:blog_id'], array_column($plan209()['groupedPartialOrPredicateFence']['currentGroupedPartialOrPredicateArms'][0]['terms'], 'leftKey')),
    'planner stat4 expression partial current source next209 first arm operators' => static fn (TestRunner $t) => $t->same(['=', '='], array_column($plan209()['groupedPartialOrPredicateFence']['currentGroupedPartialOrPredicateArms'][0]['terms'], 'operator')),
    'planner stat4 expression partial current source next209 second arm left key' => static fn (TestRunner $t) => $t->same('column:autoload', $plan209()['groupedPartialOrPredicateFence']['currentGroupedPartialOrPredicateArms'][1]['terms'][0]['leftKey']),
    'planner stat4 expression partial current source next209 proof flags' => static fn (TestRunner $t) => $t->same([true, false], array_column($plan209()['groupedPartialOrPredicateFence']['currentGroupedPartialOrPredicateProofs'], 'implied')),
    'planner stat4 expression partial current source next209 first proof term flags' => static fn (TestRunner $t) => $t->same([true, true], array_column($plan209()['groupedPartialOrPredicateFence']['currentGroupedPartialOrPredicateProofs'][0]['termProofs'], 'implied')),
    'planner stat4 expression partial current source next209 second proof term flags' => static fn (TestRunner $t) => $t->same([false], array_column($plan209()['groupedPartialOrPredicateFence']['currentGroupedPartialOrPredicateProofs'][1]['termProofs'], 'implied')),
    'planner stat4 expression partial current source next209 matched rowids preserved' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan209()['matchedRowids']),
    'planner stat4 expression partial current source next209 matched keys preserved' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_forms', 'plugin_forms'], $plan209()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next209 projected payload' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan209()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next209 payload fence preserved' => static fn (TestRunner $t) => $t->same(true, $plan209()['payloadExpressionFence']['allPayloadExpressionKeysMatch']),
    'planner stat4 expression partial current source next209 conjunctive fence preserved' => static fn (TestRunner $t) => $t->same(true, $plan209()['partialPredicateFence']['currentPartialPredicateImplied']),
    'planner stat4 expression partial current source next209 grouped signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan209()['groupedPartialOrPredicateFence']['currentGroupedOrSignature'])),
    'planner stat4 expression partial current source next209 proof signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan209()['groupedPartialOrPredicateFence']['proofSignature'])),
    'planner stat4 expression partial current source next209 selected signature' => static fn (TestRunner $t) => $t->same($plan209()['groupedPartialOrPredicateFence']['currentGroupedOrSignature'], $plan209()['selectedPlan']['next209CurrentGroupedPartialOrSignature']),
    'planner stat4 expression partial current source next209 stat4 signature' => static fn (TestRunner $t) => $t->same($plan209()['groupedPartialOrPredicateFence']['currentGroupedOrSignature'], $plan209()['stat4Fence']['next209CurrentGroupedPartialOrSignature']),
    'planner stat4 expression partial current source next209 stat4 proof signature' => static fn (TestRunner $t) => $t->same($plan209()['groupedPartialOrPredicateFence']['proofSignature'], $plan209()['stat4Fence']['next209GroupedPartialOrProofSignature']),
    'planner stat4 expression partial current source next209 cursor appended' => static fn (TestRunner $t) => $t->same('RecheckCurrentGroupedPartialOrArm', $plan209()['cursorProgram'][array_key_last($plan209()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next209 cursor mode' => static fn (TestRunner $t) => $t->same('next209-current-source-stat4-expression-partial-grouped-or-arm', $plan209()['cursorProgram'][array_key_last($plan209()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next209 cursor rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan209()['cursorProgram'][array_key_last($plan209()['cursorProgram'])]['rowids']),
    'planner stat4 expression partial current source next209 cursor arm' => static fn (TestRunner $t) => $t->same(0, $plan209()['cursorProgram'][array_key_last($plan209()['cursorProgram'])]['matchedGroupedOrArm']),
    'planner stat4 expression partial current source next209 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next209', $plan209()['dependencies'], true)),
    'planner stat4 expression partial current source next209 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan209()['dependency_closure']),
    'planner stat4 expression partial current source next209 non overlap' => static fn (TestRunner $t) => $t->contains('complete grouped OR arm', $plan209()['non_overlap']),
    'planner stat4 expression partial current source next209 detail' => static fn (TestRunner $t) => $t->contains('NEXT209 GROUPED PARTIAL OR ARM FENCE', $plan209()['detail']),
    'planner stat4 expression partial current source next209 unproved blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-grouped-partial-or-reprepare', $unproved209()['status']),
    'planner stat4 expression partial current source next209 unproved implied false' => static fn (TestRunner $t) => $t->same(false, $unproved209()['groupedPartialOrPredicateFence']['currentGroupedPartialOrPredicateImplied']),
    'planner stat4 expression partial current source next209 unproved unsupported arms' => static fn (TestRunner $t) => $t->same([0, 1], $unproved209()['groupedPartialOrPredicateFence']['unsupportedCurrentGroupedPartialOrArms']),
    'planner stat4 expression partial current source next209 unproved no cursor append' => static fn (TestRunner $t) => $t->same(false, in_array('RecheckCurrentGroupedPartialOrArm', array_column($unproved209()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next209 invalid current indexes' => static function (TestRunner $t) use ($current209, $plan209): void {
        $bad = $current209();
        $bad['indexes'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan209(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next209 invalid arms' => static function (TestRunner $t) use ($current209, $plan209): void {
        $bad = $current209();
        $bad['indexes'][0]['partialGroupedOrPredicateArms'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan209(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next209 invalid arm entry' => static function (TestRunner $t) use ($current209, $plan209): void {
        $bad = $current209();
        $bad['indexes'][0]['partialGroupedOrPredicateArms'][] = ['bad'];
        $t->throws(InvalidArgumentException::class, static fn () => $plan209(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next209 invalid operator' => static function (TestRunner $t) use ($current209, $plan209): void {
        $bad = $current209();
        $bad['indexes'][0]['partialGroupedOrPredicateArms'][0][] = ['left' => ['column' => 'blog_id'], 'operator' => '', 'right' => 1];
        $t->throws(InvalidArgumentException::class, static fn () => $plan209(5, 1, null, $bad));
    },
];

foreach (range(1, 12) as $case) {
    $tests['planner stat4 expression partial current source next209 repeated grouped arm fence ' . $case] = static function (TestRunner $t) use ($plan209, $case): void {
        $plan = $plan209(1 + ($case % 5), $case % 4);
        $t->same(count($plan['matchedRows']), count($plan['groupedPartialOrPredicateFence']['rowProofs']));
    };
}

return $tests;
