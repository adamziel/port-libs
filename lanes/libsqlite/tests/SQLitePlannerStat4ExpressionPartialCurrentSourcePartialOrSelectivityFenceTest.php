<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq208 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull208 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between208 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared208 = static fn (): array => [
    'name' => 'prepared-wp-options-partial-or-selectivity-stat4-expression-next208',
    'schemaCookie' => 2080,
    'stat4Generation' => 208,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_partial_or_stat4_next208',
        'rootPage' => 20801,
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
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'critical'],
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
        ],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
        ],
    ]],
];

$current208 = static function () use ($prepared208): array {
    $source = $prepared208();
    $source['name'] = 'current-wp-options-partial-or-selectivity-stat4-expression-next208';
    $source['schemaCookie'] = 2088;
    $source['stat4Generation'] = 254;
    $source['indexes'][0]['rootPage'] = 20888;
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

$terms208 = static fn (): array => [
    $between208('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq208('autoload', 'yes'),
    $notNull208('option_name'),
    $eq208('blog_id', 1),
];
$plan208 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePartialOrSelectivityFence(
    $prepared ?? $prepared208(),
    $current ?? $current208(),
    $terms ?? $terms208(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$unanchored208 = static function () use ($current208, $plan208): array {
    $current = $current208();
    array_splice($current['indexes'][0]['stat4Samples'], 4, 1);

    return $plan208(5, 1, null, $current);
};
$missingSampleRow208 = static function () use ($current208, $plan208): array {
    $current = $current208();
    $current['indexes'][0]['stat4Samples'][2]['sample'] = ['plugin_forms', 99];

    return $plan208(5, 1, null, $current);
};
$nonMonotonic208 = static function () use ($current208, $plan208): array {
    $current = $current208();
    $current['indexes'][0]['stat4Samples'][4]['nlt'] = '1 1';

    return $plan208(5, 1, null, $current);
};
$unprovedOr208 = static function () use ($terms208, $plan208): array {
    $terms = array_values(array_filter($terms208(), static fn (array $term): bool => ($term['left']['column'] ?? null) !== 'blog_id'));

    return $plan208(5, 1, null, null, $terms);
};

$tests = [
    'planner stat4 expression partial current source next208 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next208-ready', $plan208()['status']),
    'planner stat4 expression partial current source next208 selected current' => static fn (TestRunner $t) => $t->same('current', $plan208()['selectedSource']),
    'planner stat4 expression partial current source next208 inherited next206' => static fn (TestRunner $t) => $t->same(true, $plan208()['selectedPlan']['next206Ready']),
    'planner stat4 expression partial current source next208 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan208()['selectedPlan']['next208Ready']),
    'planner stat4 expression partial current source next208 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_partial_or_stat4_next208', $plan208()['selectedPlan']['name']),
    'planner stat4 expression partial current source next208 root page' => static fn (TestRunner $t) => $t->same(20888, $plan208()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next208 matched rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan208()['matchedRowids']),
    'planner stat4 expression partial current source next208 matched keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_forms', 'plugin_forms'], $plan208()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next208 fence ready' => static fn (TestRunner $t) => $t->same(true, $plan208()['stat4SelectivityFence']['ready']),
    'planner stat4 expression partial current source next208 matched or arm' => static fn (TestRunner $t) => $t->same(1, $plan208()['stat4SelectivityFence']['matchedOrArm']),
    'planner stat4 expression partial current source next208 selected matched arm' => static fn (TestRunner $t) => $t->same(1, $plan208()['selectedPlan']['next208MatchedOrArm']),
    'planner stat4 expression partial current source next208 selected expression keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms'], $plan208()['stat4SelectivityFence']['selectedExpressionKeys']),
    'planner stat4 expression partial current source next208 selected sample keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms'], $plan208()['stat4SelectivityFence']['selectedSampleKeys']),
    'planner stat4 expression partial current source next208 selected plan sample keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms'], $plan208()['selectedPlan']['next208SelectedSampleKeys']),
    'planner stat4 expression partial current source next208 sample rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20], $plan208()['stat4SelectivityFence']['selectedSampleRowids']),
    'planner stat4 expression partial current source next208 no unanchored keys' => static fn (TestRunner $t) => $t->same([], $plan208()['stat4SelectivityFence']['unanchoredSelectedKeys']),
    'planner stat4 expression partial current source next208 no missing rowids' => static fn (TestRunner $t) => $t->same([], $plan208()['stat4SelectivityFence']['rowidsMissingSampleWindow']),
    'planner stat4 expression partial current source next208 monotonic nlt' => static fn (TestRunner $t) => $t->same(true, $plan208()['stat4SelectivityFence']['monotonicNlt']),
    'planner stat4 expression partial current source next208 actual rows' => static fn (TestRunner $t) => $t->same(5, $plan208()['stat4SelectivityFence']['actualWindowRows']),
    'planner stat4 expression partial current source next208 selected actual rows' => static fn (TestRunner $t) => $t->same(5, $plan208()['selectedPlan']['next208ActualWindowRows']),
    'planner stat4 expression partial current source next208 estimated rows' => static fn (TestRunner $t) => $t->same(5, $plan208()['stat4SelectivityFence']['estimatedRowsFromStat4']),
    'planner stat4 expression partial current source next208 selected estimated rows' => static fn (TestRunner $t) => $t->same(5, $plan208()['selectedPlan']['next208EstimatedRowsFromStat4']),
    'planner stat4 expression partial current source next208 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan208()['stat4SelectivityFence']['signature'])),
    'planner stat4 expression partial current source next208 window signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan208()['stat4SelectivityFence']['sampleWindowSignature'])),
    'planner stat4 expression partial current source next208 stat4 signature propagated' => static fn (TestRunner $t) => $t->same($plan208()['stat4SelectivityFence']['signature'], $plan208()['stat4Fence']['next208SelectivitySignature']),
    'planner stat4 expression partial current source next208 stat4 window signature propagated' => static fn (TestRunner $t) => $t->same($plan208()['stat4SelectivityFence']['sampleWindowSignature'], $plan208()['stat4Fence']['next208SampleWindowSignature']),
    'planner stat4 expression partial current source next208 cursor appended' => static fn (TestRunner $t) => $t->same('VerifyStat4SelectivityWindow', $plan208()['cursorProgram'][array_key_last($plan208()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next208 cursor mode' => static fn (TestRunner $t) => $t->same('next208-current-source-stat4-expression-partial-or-selectivity', $plan208()['cursorProgram'][array_key_last($plan208()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next208 cursor rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20], $plan208()['cursorProgram'][array_key_last($plan208()['cursorProgram'])]['selectedSampleRowids']),
    'planner stat4 expression partial current source next208 cursor actual rows' => static fn (TestRunner $t) => $t->same(5, $plan208()['cursorProgram'][array_key_last($plan208()['cursorProgram'])]['actualWindowRows']),
    'planner stat4 expression partial current source next208 projected duplicate payload' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan208()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next208 or fence still ready' => static fn (TestRunner $t) => $t->same(true, $plan208()['partialOrPredicateFence']['allRowsSatisfyCurrentPartialOrPredicate']),
    'planner stat4 expression partial current source next208 payload fence still ready' => static fn (TestRunner $t) => $t->same(true, $plan208()['payloadExpressionFence']['allPayloadExpressionKeysMatch']),
    'planner stat4 expression partial current source next208 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next208', $plan208()['dependencies'], true)),
    'planner stat4 expression partial current source next208 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan208()['dependency_closure']),
    'planner stat4 expression partial current source next208 non overlap' => static fn (TestRunner $t) => $t->contains('matched partial-OR arm', $plan208()['non_overlap']),
    'planner stat4 expression partial current source next208 detail' => static fn (TestRunner $t) => $t->contains('NEXT208 OR-ARM SELECTIVITY FENCE', $plan208()['detail']),
    'planner stat4 expression partial current source next208 unanchored blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-selectivity-reprepare', $unanchored208()['status']),
    'planner stat4 expression partial current source next208 unanchored key' => static fn (TestRunner $t) => $t->same(['plugin_seo'], $unanchored208()['stat4SelectivityFence']['unanchoredSelectedKeys']),
    'planner stat4 expression partial current source next208 unanchored cursor not appended' => static fn (TestRunner $t) => $t->same(false, in_array('VerifyStat4SelectivityWindow', array_column($unanchored208()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next208 missing sample row blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-selectivity-reprepare', $missingSampleRow208()['status']),
    'planner stat4 expression partial current source next208 missing sample rowids delegated' => static fn (TestRunner $t) => $t->same([], $missingSampleRow208()['stat4SelectivityFence']['rowidsMissingSampleWindow']),
    'planner stat4 expression partial current source next208 non monotonic blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-selectivity-reprepare', $nonMonotonic208()['status']),
    'planner stat4 expression partial current source next208 non monotonic flag' => static fn (TestRunner $t) => $t->same(false, $nonMonotonic208()['stat4SelectivityFence']['monotonicNlt']),
    'planner stat4 expression partial current source next208 unproved or inherited blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-selectivity-reprepare', $unprovedOr208()['status']),
    'planner stat4 expression partial current source next208 invalid current indexes' => static function (TestRunner $t) use ($current208, $plan208): void {
        $bad = $current208();
        $bad['indexes'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan208(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next208 invalid sample stat' => static function (TestRunner $t) use ($current208, $plan208): void {
        $bad = $current208();
        $bad['indexes'][0]['stat4Samples'][0]['neq'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan208(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next208 invalid sample rowid' => static function (TestRunner $t) use ($current208, $plan208): void {
        $bad = $current208();
        $bad['indexes'][0]['stat4Samples'][0]['sample'][1] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan208(5, 1, null, $bad));
    },
];

foreach (range(1, 10) as $case) {
    $tests['planner stat4 expression partial current source next208 repeated selectivity fence ' . $case] = static function (TestRunner $t) use ($plan208, $case): void {
        $plan = $plan208(1 + ($case % 5), $case % 4);
        $t->same(count(array_unique($plan['matchedExpressionKeys'])), count($plan['stat4SelectivityFence']['selectedExpressionKeys']));
    };
}

return $tests;
