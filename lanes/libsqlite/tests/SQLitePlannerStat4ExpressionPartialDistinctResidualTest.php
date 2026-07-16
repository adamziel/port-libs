<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq194 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull194 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$exprLike194 = static fn (string $expression, string $pattern): array => ['left' => ['expression' => $expression], 'operator' => 'LIKE', 'right' => $pattern, 'escape' => '\\'];
$exprDistinct194 = static fn (string $expression, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => 'IS DISTINCT FROM', 'right' => ['literal' => $right]];
$columnDistinct194 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => 'IS DISTINCT FROM', 'right' => ['literal' => $right]];
$exprRange194 = static fn (string $expression, string $operator, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => $operator, 'right' => $right];

$prepared194 = static function (array $overrides = []): array {
    return array_replace_recursive([
        'name' => 'prepared-wp-options-stat4-distinct-next194',
        'schemaCookie' => 1940,
        'stat4Generation' => 70,
        'rows' => [
            ['rowid' => 11, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-old'],
            ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old'],
            ['rowid' => 33, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_debug_trace', 'option_value' => 'debug-old'],
        ],
        'indexes' => [[
            'name' => 'idx_wp_options_lower_distinct_next194',
            'rootPage' => 19401,
            'expression' => 'lower(option_name)',
            'expressionColumn' => '__expr_lower_option_name',
            'collation' => 'BINARY',
            'partialPredicateTerms' => [
                ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
                ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
                ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_'],
                ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<', 'right' => 'plugin`'],
            ],
            'coveringColumns' => ['option_name', 'option_value', 'autoload', 'blog_id'],
            'stat4Samples' => [
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 11]],
                ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_debug_trace', 33]],
                ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 22]],
            ],
        ]],
    ], $overrides);
};

$current194 = static function (array $overrides = []) use ($prepared194): array {
    $source = $prepared194([
        'name' => 'current-wp-options-stat4-distinct-next194',
        'schemaCookie' => 1951,
        'stat4Generation' => 91,
    ]);
    $source['indexes'][0]['rootPage'] = 19488;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 11]],
        ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_debug_trace', 44]],
        ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 22]],
        ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 55]],
        ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '4 4', 'sample' => ['plugin_nullish', 66]],
    ];
    $source['rows'] = [
        ['rowid' => 11, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-current'],
        ['rowid' => 44, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_debug_trace', 'option_value' => 'debug-current'],
        ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-current'],
        ['rowid' => 55, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_mail', 'option_value' => 'mail-current'],
        ['rowid' => 66, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_nullish', 'option_value' => null],
        ['rowid' => 77, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods', 'option_value' => 'theme-current'],
        ['rowid' => 88, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_lazy', 'option_value' => 'lazy-current'],
    ];

    return array_replace_recursive($source, $overrides);
};

$terms194 = static fn (): array => [
    $exprLike194('LOWER( option_name )', 'plugin\_%'),
    $exprDistinct194('lower(option_name)', 'plugin_debug_trace'),
    $columnDistinct194('option_value', null),
    $eq194('blog_id', 1),
    $eq194('autoload', 'yes'),
    $notNull194('option_name'),
    $exprRange194('lower(option_name)', '>=', 'plugin_'),
    $exprRange194('lower(option_name)', '<', 'plugin`'),
];
$needed194 = ['option_name', 'option_value'];
$plan194 = static fn (?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeDistinctResidual(
    $prepared ?? $prepared194(),
    $current ?? $current194(),
    $terms ?? $terms194(),
    $needed ?? $needed194,
);
$fresh194 = static function () use ($current194, $plan194): array {
    $source = $current194();

    return $plan194($source, $source);
};
$noReject194 = static function () use ($terms194, $exprDistinct194, $columnDistinct194, $plan194): array {
    $terms = $terms194();
    $terms[1] = $exprDistinct194('lower(option_name)', 'plugin_missing');
    $terms[2] = $columnDistinct194('option_value', 'missing');

    return $plan194(null, null, $terms);
};
$allRejected194 = static function () use ($terms194, $exprDistinct194, $columnDistinct194, $plan194): array {
    $terms = $terms194();
    $terms[1] = $exprDistinct194('lower(option_name)', 'plugin_missing');
    $terms[2] = $columnDistinct194('autoload', 'yes');

    return $plan194(null, null, $terms);
};

$tests = [
    'planner stat4 expression partial current source next194 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next194-ready', $plan194()['status']),
    'planner stat4 expression partial current source next194 selected current' => static fn (TestRunner $t) => $t->same('current', $plan194()['selectedSource']),
    'planner stat4 expression partial current source next194 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan194()['stalePreparedStatement']),
    'planner stat4 expression partial current source next194 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan194()['reprepareRequired']),
    'planner stat4 expression partial current source next194 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan194()['schemaCookieChanged']),
    'planner stat4 expression partial current source next194 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan194()['stat4GenerationChanged']),
    'planner stat4 expression partial current source next194 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_distinct_next194', $plan194()['selectedPlan']['name']),
    'planner stat4 expression partial current source next194 root page' => static fn (TestRunner $t) => $t->same(19488, $plan194()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next194 covering' => static fn (TestRunner $t) => $t->same(true, $plan194()['selectedPlan']['covering']),
    'planner stat4 expression partial current source next194 table lookup elided' => static fn (TestRunner $t) => $t->same(false, $plan194()['tableLookupRequired']),
    'planner stat4 expression partial current source next194 base prefix preserved' => static fn (TestRunner $t) => $t->same('plugin_', $plan194()['prefix']),
    'planner stat4 expression partial current source next194 base upper preserved' => static fn (TestRunner $t) => $t->same('plugin`', $plan194()['prefixUpperBound']),
    'planner stat4 expression partial current source next194 before residual rowids' => static fn (TestRunner $t) => $t->same([11, 44, 22, 55, 66], $plan194()['matchedRowidsBeforeIsDistinctResidual']),
    'planner stat4 expression partial current source next194 prepared before residual' => static fn (TestRunner $t) => $t->same([11, 33, 22], $plan194()['preparedPlan']['matchedRowids']),
    'planner stat4 expression partial current source next194 current before residual' => static fn (TestRunner $t) => $t->same([11, 44, 22, 55, 66], $plan194()['currentPlan']['matchedRowids']),
    'planner stat4 expression partial current source next194 after residual rowids' => static fn (TestRunner $t) => $t->same([11, 22, 55], $plan194()['matchedRowids']),
    'planner stat4 expression partial current source next194 after residual keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mail'], $plan194()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next194 rejects distinct equal expression rowid' => static fn (TestRunner $t) => $t->same(true, in_array(44, $plan194()['isDistinctFromResidualRowidsRejected'], true)),
    'planner stat4 expression partial current source next194 rejects null-safe equal rowid' => static fn (TestRunner $t) => $t->same(true, in_array(66, $plan194()['isDistinctFromResidualRowidsRejected'], true)),
    'planner stat4 expression partial current source next194 accepts cache rowid' => static fn (TestRunner $t) => $t->same(true, in_array(11, $plan194()['isDistinctFromResidualRowidsAccepted'], true)),
    'planner stat4 expression partial current source next194 accepts forms rowid' => static fn (TestRunner $t) => $t->same(true, in_array(22, $plan194()['isDistinctFromResidualRowidsAccepted'], true)),
    'planner stat4 expression partial current source next194 accepts mail rowid' => static fn (TestRunner $t) => $t->same(true, in_array(55, $plan194()['isDistinctFromResidualRowidsAccepted'], true)),
    'planner stat4 expression partial current source next194 theme outside prefix' => static fn (TestRunner $t) => $t->same(false, in_array(77, $plan194()['matchedRowidsBeforeIsDistinctResidual'], true)),
    'planner stat4 expression partial current source next194 autoload no excluded' => static fn (TestRunner $t) => $t->same(false, in_array(88, $plan194()['matchedRowidsBeforeIsDistinctResidual'], true)),
    'planner stat4 expression partial current source next194 current payload wins' => static fn (TestRunner $t) => $t->same('mail-current', $plan194()['matchedRows'][2]['payload']['option_value']),
    'planner stat4 expression partial current source next194 residual count' => static fn (TestRunner $t) => $t->same(2, $plan194()['selectedPlan']['isDistinctFromResidualCount']),
    'planner stat4 expression partial current source next194 residual retained' => static fn (TestRunner $t) => $t->same(true, $plan194()['selectedPlan']['isDistinctFromResidualRetained']),
    'planner stat4 expression partial current source next194 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan194()['selectedPlan']['next194Ready']),
    'planner stat4 expression partial current source next194 estimated rows after residual' => static fn (TestRunner $t) => $t->same(3, $plan194()['selectedPlan']['estimatedRowsAfterIsDistinctResidual']),
    'planner stat4 expression partial current source next194 estimated cost after residual' => static fn (TestRunner $t) => $t->same(3, $plan194()['selectedPlan']['estimatedCostAfterIsDistinctResidual']),
    'planner stat4 expression partial current source next194 expression residual key' => static fn (TestRunner $t) => $t->same('expression:lower(option_name)', $plan194()['isDistinctFromResiduals'][0]['leftKey']),
    'planner stat4 expression partial current source next194 expression residual literal' => static fn (TestRunner $t) => $t->same('plugin_debug_trace', $plan194()['isDistinctFromResiduals'][0]['right']),
    'planner stat4 expression partial current source next194 column residual key' => static fn (TestRunner $t) => $t->same('column:option_value', $plan194()['isDistinctFromResiduals'][1]['leftKey']),
    'planner stat4 expression partial current source next194 column residual null literal' => static fn (TestRunner $t) => $t->same(null, $plan194()['isDistinctFromResiduals'][1]['right']),
    'planner stat4 expression partial current source next194 stat4 window rowids' => static fn (TestRunner $t) => $t->same([11, 44, 22, 55, 66], $plan194()['stat4PrefixWindow']['rowids']),
    'planner stat4 expression partial current source next194 stat4 window keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_debug_trace', 'plugin_forms', 'plugin_mail', 'plugin_nullish'], $plan194()['stat4PrefixWindow']['keys']),
    'planner stat4 expression partial current source next194 residual predicate required' => static fn (TestRunner $t) => $t->same(true, $plan194()['residualPredicateRequired']),
    'planner stat4 expression partial current source next194 cursor open' => static fn (TestRunner $t) => $t->same('OpenRead', $plan194()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next194 cursor fence' => static fn (TestRunner $t) => $t->same('FenceStat4PrefixWindow', $plan194()['cursorProgram'][1]['opcode']),
    'planner stat4 expression partial current source next194 cursor residual inserted' => static fn (TestRunner $t) => $t->same('RecheckIsDistinctFromResidual', $plan194()['cursorProgram'][5]['opcode']),
    'planner stat4 expression partial current source next194 cursor residual rowids' => static fn (TestRunner $t) => $t->same([11, 22, 55], $plan194()['cursorProgram'][5]['rowids']),
    'planner stat4 expression partial current source next194 cursor residual comparisons' => static fn (TestRunner $t) => $t->same(2, count($plan194()['cursorProgram'][5]['comparisons'])),
    'planner stat4 expression partial current source next194 cursor result filtered' => static function (TestRunner $t) use ($plan194): void {
        $resultOps = array_values(array_filter($plan194()['cursorProgram'], static fn (array $op): bool => ($op['opcode'] ?? null) === 'ResultRow'));
        $t->same([11, 22, 55], $resultOps[0]['rowids'] ?? null);
    },
    'planner stat4 expression partial current source next194 fence residual hash' => static fn (TestRunner $t) => $t->same(64, strlen($plan194()['stat4Fence']['next194IsDistinctFromResidualSignature'])),
    'planner stat4 expression partial current source next194 fence row stream hash' => static fn (TestRunner $t) => $t->same(64, strlen($plan194()['stat4Fence']['rowStreamSignatureAfterIsDistinctFromResidual'])),
    'planner stat4 expression partial current source next194 stale prefix changed' => static fn (TestRunner $t) => $t->same(true, $plan194()['stat4PrefixWindowChanged']),
    'planner stat4 expression partial current source next194 stale prepared blocked' => static fn (TestRunner $t) => $t->same([33], $plan194()['stalePreparedRowidsBlockedByPrefixWindow']),
    'planner stat4 expression partial current source next194 current admitted' => static fn (TestRunner $t) => $t->same([44, 55, 66], $plan194()['currentSourceRowidsAdmittedByPrefixWindow']),
    'planner stat4 expression partial current source next194 fresh source' => static fn (TestRunner $t) => $t->same('prepared', $fresh194()['selectedSource']),
    'planner stat4 expression partial current source next194 fresh ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next194-ready', $fresh194()['status']),
    'planner stat4 expression partial current source next194 fresh filtered rowids' => static fn (TestRunner $t) => $t->same([11, 22, 55], $fresh194()['matchedRowids']),
    'planner stat4 expression partial current source next194 no rejected row fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $noReject194()['status']),
    'planner stat4 expression partial current source next194 no rejected cursor replan' => static fn (TestRunner $t) => $t->same('Replan', $noReject194()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next194 all rejected fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $allRejected194()['status']),
    'planner stat4 expression partial current source next194 all rejected cursor replan' => static fn (TestRunner $t) => $t->same('Replan', $allRejected194()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next194 detail' => static fn (TestRunner $t) => $t->contains('NEXT194 IS-DISTINCT-FROM RESIDUAL', $plan194()['detail']),
    'planner stat4 expression partial current source next194 dependency marker' => static fn (TestRunner $t) => $t->same(['sqlite-sqlplanner-stat4-expression-partial-current-source-next194'], $plan194()['dependencies']),
    'planner stat4 expression partial current source next194 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan194()['dependency_closure']),
    'planner stat4 expression partial current source next194 non overlap' => static fn (TestRunner $t) => $t->contains('IS DISTINCT FROM residual', $plan194()['non_overlap']),
    'planner stat4 expression partial current source next194 invalid empty terms' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan194(null, null, [])),
    'planner stat4 expression partial current source next194 invalid no residual' => static function (TestRunner $t) use ($plan194, $terms194): void {
        $terms = array_values(array_filter($terms194(), static fn (array $term): bool => strtoupper((string) $term['operator']) !== 'IS DISTINCT FROM'));
        $t->throws(InvalidArgumentException::class, static fn () => $plan194(null, null, $terms));
    },
];

foreach (range(1, 8) as $case) {
    $tests['planner stat4 expression partial current source next194 repeated distinct fence ' . $case] = static function (TestRunner $t) use ($plan194, $case): void {
        $plan = $plan194();
        $t->same('stat4-expression-partial-current-source-next194-ready', $plan['status']);
        $t->true(count($plan['matchedRowids']) >= ($case % 3));
    };
}

return $tests;
