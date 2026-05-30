<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq190 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull190 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$exprLike190 = static fn (string $expression, string $pattern): array => ['left' => ['expression' => $expression], 'operator' => 'LIKE', 'right' => $pattern, 'escape' => '\\'];
$exprNotIn190 = static fn (string $expression, array $values): array => ['left' => ['expression' => $expression], 'operator' => 'NOT IN', 'right' => ['values' => $values]];
$exprRange190 = static fn (string $expression, string $operator, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => $operator, 'right' => $right];

$prepared190 = static function (array $overrides = []): array {
    return array_replace_recursive([
        'name' => 'prepared-wp-options-stat4-not-in-next190',
        'schemaCookie' => 1900,
        'stat4Generation' => 64,
        'rows' => [
            ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-old'],
            ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old'],
            ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_debug_trace', 'option_value' => 'debug-old'],
        ],
        'indexes' => [[
            'name' => 'idx_wp_options_lower_not_in_next190',
            'rootPage' => 19001,
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
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
                ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_debug_trace', 30]],
                ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 20]],
            ],
        ]],
    ], $overrides);
};

$current190 = static function (array $overrides = []) use ($prepared190): array {
    $source = $prepared190([
        'name' => 'current-wp-options-stat4-not-in-next190',
        'schemaCookie' => 1909,
        'stat4Generation' => 86,
    ]);
    $source['indexes'][0]['rootPage'] = 19088;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
        ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_debug_trace', 40]],
        ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 20]],
        ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 50]],
        ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '4 4', 'sample' => ['plugin_tmp_cache', 60]],
        ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '5 5', 'sample' => ['theme_mods', 70]],
    ];
    $source['rows'] = [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-current'],
        ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_debug_trace', 'option_value' => 'debug-current'],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-current'],
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_mail', 'option_value' => 'mail-current'],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_tmp_cache', 'option_value' => 'tmp-current'],
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods', 'option_value' => 'theme-current'],
        ['rowid' => 80, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_lazy', 'option_value' => 'lazy-current'],
        ['rowid' => 90, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-current'],
    ];

    return array_replace_recursive($source, $overrides);
};

$terms190 = static fn (): array => [
    $exprLike190('LOWER( option_name )', 'plugin\_%'),
    $exprNotIn190('lower(option_name)', ['plugin_debug_trace', 'plugin_tmp_cache']),
    $eq190('blog_id', 1),
    $eq190('autoload', 'yes'),
    $notNull190('option_name'),
    $exprRange190('lower(option_name)', '>=', 'plugin_'),
    $exprRange190('lower(option_name)', '<', 'plugin`'),
];
$needed190 = ['option_name', 'option_value'];
$plan190 = static fn (?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceTrailingPayloadFence(
    $prepared ?? $prepared190(),
    $current ?? $current190(),
    $terms ?? $terms190(),
    $needed ?? $needed190,
);
$fresh190 = static function () use ($current190, $plan190): array {
    $source = $current190();

    return $plan190($source, $source);
};
$nullPoison190 = static function () use ($terms190, $exprNotIn190, $plan190): array {
    $terms = $terms190();
    $terms[1] = $exprNotIn190('lower(option_name)', ['plugin_debug_trace', null]);

    return $plan190(null, null, $terms);
};
$noReject190 = static function () use ($terms190, $exprNotIn190, $plan190): array {
    $terms = $terms190();
    $terms[1] = $exprNotIn190('lower(option_name)', ['plugin_missing']);

    return $plan190(null, null, $terms);
};
$invalidEmpty190 = static function () use ($terms190, $exprNotIn190, $plan190): array {
    $terms = $terms190();
    $terms[1] = $exprNotIn190('lower(option_name)', []);

    return $plan190(null, null, $terms);
};

$tests = [
    'planner stat4 expression partial current source next190 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next190-ready', $plan190()['status']),
    'planner stat4 expression partial current source next190 selected current' => static fn (TestRunner $t) => $t->same('current', $plan190()['selectedSource']),
    'planner stat4 expression partial current source next190 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan190()['stalePreparedStatement']),
    'planner stat4 expression partial current source next190 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan190()['reprepareRequired']),
    'planner stat4 expression partial current source next190 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan190()['schemaCookieChanged']),
    'planner stat4 expression partial current source next190 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan190()['stat4GenerationChanged']),
    'planner stat4 expression partial current source next190 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_not_in_next190', $plan190()['selectedPlan']['name']),
    'planner stat4 expression partial current source next190 root page' => static fn (TestRunner $t) => $t->same(19088, $plan190()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next190 covering' => static fn (TestRunner $t) => $t->same(true, $plan190()['selectedPlan']['covering']),
    'planner stat4 expression partial current source next190 table lookup elided' => static fn (TestRunner $t) => $t->same(false, $plan190()['tableLookupRequired']),
    'planner stat4 expression partial current source next190 base prefix preserved' => static fn (TestRunner $t) => $t->same('plugin_', $plan190()['prefix']),
    'planner stat4 expression partial current source next190 base upper preserved' => static fn (TestRunner $t) => $t->same('plugin`', $plan190()['prefixUpperBound']),
    'planner stat4 expression partial current source next190 before residual rowids' => static fn (TestRunner $t) => $t->same([10, 40, 20, 50, 60], $plan190()['matchedRowidsBeforeNotInResidual']),
    'planner stat4 expression partial current source next190 before residual current plan' => static fn (TestRunner $t) => $t->same([10, 40, 20, 50, 60], $plan190()['currentPlan']['matchedRowids']),
    'planner stat4 expression partial current source next190 prepared before residual' => static fn (TestRunner $t) => $t->same([10, 30, 20], $plan190()['preparedPlan']['matchedRowids']),
    'planner stat4 expression partial current source next190 after residual rowids' => static fn (TestRunner $t) => $t->same([10, 20, 50], $plan190()['matchedRowids']),
    'planner stat4 expression partial current source next190 after residual keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mail'], $plan190()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next190 rejects debug rowid' => static fn (TestRunner $t) => $t->same(true, in_array(40, $plan190()['notInResidualRowidsRejected'], true)),
    'planner stat4 expression partial current source next190 rejects tmp rowid' => static fn (TestRunner $t) => $t->same(true, in_array(60, $plan190()['notInResidualRowidsRejected'], true)),
    'planner stat4 expression partial current source next190 accepts cache rowid' => static fn (TestRunner $t) => $t->same(true, in_array(10, $plan190()['notInResidualRowidsAccepted'], true)),
    'planner stat4 expression partial current source next190 accepts forms rowid' => static fn (TestRunner $t) => $t->same(true, in_array(20, $plan190()['notInResidualRowidsAccepted'], true)),
    'planner stat4 expression partial current source next190 accepts mail rowid' => static fn (TestRunner $t) => $t->same(true, in_array(50, $plan190()['notInResidualRowidsAccepted'], true)),
    'planner stat4 expression partial current source next190 theme outside prefix' => static fn (TestRunner $t) => $t->same(false, in_array(70, $plan190()['matchedRowidsBeforeNotInResidual'], true)),
    'planner stat4 expression partial current source next190 autoload no excluded' => static fn (TestRunner $t) => $t->same(false, in_array(80, $plan190()['matchedRowidsBeforeNotInResidual'], true)),
    'planner stat4 expression partial current source next190 null name excluded by partial' => static fn (TestRunner $t) => $t->same(false, in_array(90, $plan190()['matchedRowidsBeforeNotInResidual'], true)),
    'planner stat4 expression partial current source next190 current payload wins' => static fn (TestRunner $t) => $t->same('mail-current', $plan190()['matchedRows'][2]['payload']['option_value']),
    'planner stat4 expression partial current source next190 residual count' => static fn (TestRunner $t) => $t->same(1, $plan190()['selectedPlan']['notInResidualCount']),
    'planner stat4 expression partial current source next190 residual retained' => static fn (TestRunner $t) => $t->same(true, $plan190()['selectedPlan']['notInResidualRetained']),
    'planner stat4 expression partial current source next190 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan190()['selectedPlan']['next190Ready']),
    'planner stat4 expression partial current source next190 estimated rows after residual' => static fn (TestRunner $t) => $t->same(3, $plan190()['selectedPlan']['estimatedRowsAfterNotInResidual']),
    'planner stat4 expression partial current source next190 estimated cost after residual' => static fn (TestRunner $t) => $t->same(3, $plan190()['selectedPlan']['estimatedCostAfterNotInResidual']),
    'planner stat4 expression partial current source next190 residual left key' => static fn (TestRunner $t) => $t->same('expression:lower(option_name)', $plan190()['notInResiduals'][0]['leftKey']),
    'planner stat4 expression partial current source next190 residual values' => static fn (TestRunner $t) => $t->same(['plugin_debug_trace', 'plugin_tmp_cache'], $plan190()['notInResiduals'][0]['values']),
    'planner stat4 expression partial current source next190 residual no null poison' => static fn (TestRunner $t) => $t->same(false, $plan190()['notInResidualHasNullPoison']),
    'planner stat4 expression partial current source next190 stat4 window rowids' => static fn (TestRunner $t) => $t->same([10, 40, 20, 50, 60], $plan190()['stat4PrefixWindow']['rowids']),
    'planner stat4 expression partial current source next190 stat4 window keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_debug_trace', 'plugin_forms', 'plugin_mail', 'plugin_tmp_cache'], $plan190()['stat4PrefixWindow']['keys']),
    'planner stat4 expression partial current source next190 residual predicate required' => static fn (TestRunner $t) => $t->same(true, $plan190()['residualPredicateRequired']),
    'planner stat4 expression partial current source next190 cursor open' => static fn (TestRunner $t) => $t->same('OpenRead', $plan190()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next190 cursor fence' => static fn (TestRunner $t) => $t->same('FenceStat4PrefixWindow', $plan190()['cursorProgram'][1]['opcode']),
    'planner stat4 expression partial current source next190 cursor residual inserted' => static fn (TestRunner $t) => $t->same('RecheckNotInResidual', $plan190()['cursorProgram'][5]['opcode']),
    'planner stat4 expression partial current source next190 cursor residual values' => static fn (TestRunner $t) => $t->same([['plugin_debug_trace', 'plugin_tmp_cache']], $plan190()['cursorProgram'][5]['valueSets']),
    'planner stat4 expression partial current source next190 cursor residual rowids' => static fn (TestRunner $t) => $t->same([10, 20, 50], $plan190()['cursorProgram'][5]['rowids']),
    'planner stat4 expression partial current source next190 cursor result filtered' => static function (TestRunner $t) use ($plan190): void {
        $resultOps = array_values(array_filter($plan190()['cursorProgram'], static fn (array $op): bool => ($op['opcode'] ?? null) === 'ResultRow'));
        $t->same([10, 20, 50], $resultOps[0]['rowids'] ?? null);
    },
    'planner stat4 expression partial current source next190 fence residual hash' => static fn (TestRunner $t) => $t->same(64, strlen($plan190()['stat4Fence']['next190NotInResidualSignature'])),
    'planner stat4 expression partial current source next190 fence row stream hash' => static fn (TestRunner $t) => $t->same(64, strlen($plan190()['stat4Fence']['rowStreamSignatureAfterNotInResidual'])),
    'planner stat4 expression partial current source next190 stale prefix changed' => static fn (TestRunner $t) => $t->same(true, $plan190()['stat4PrefixWindowChanged']),
    'planner stat4 expression partial current source next190 stale prepared blocked' => static fn (TestRunner $t) => $t->same([30], $plan190()['stalePreparedRowidsBlockedByPrefixWindow']),
    'planner stat4 expression partial current source next190 current admitted' => static fn (TestRunner $t) => $t->same([40, 50, 60], $plan190()['currentSourceRowidsAdmittedByPrefixWindow']),
    'planner stat4 expression partial current source next190 fresh source' => static fn (TestRunner $t) => $t->same('prepared', $fresh190()['selectedSource']),
    'planner stat4 expression partial current source next190 fresh ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next190-ready', $fresh190()['status']),
    'planner stat4 expression partial current source next190 fresh filtered rowids' => static fn (TestRunner $t) => $t->same([10, 20, 50], $fresh190()['matchedRowids']),
    'planner stat4 expression partial current source next190 null poison fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $nullPoison190()['status']),
    'planner stat4 expression partial current source next190 null poison flag' => static fn (TestRunner $t) => $t->same(true, $nullPoison190()['notInResidualHasNullPoison']),
    'planner stat4 expression partial current source next190 null poison cursor replan' => static fn (TestRunner $t) => $t->same('Replan', $nullPoison190()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next190 no rejected row fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $noReject190()['status']),
    'planner stat4 expression partial current source next190 no rejected cursor replan' => static fn (TestRunner $t) => $t->same('Replan', $noReject190()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next190 detail' => static fn (TestRunner $t) => $t->contains('NEXT190 NOT-IN RESIDUAL', $plan190()['detail']),
    'planner stat4 expression partial current source next190 dependency marker' => static fn (TestRunner $t) => $t->same(['sqlite-sqlplanner-stat4-expression-partial-current-source-next190'], $plan190()['dependencies']),
    'planner stat4 expression partial current source next190 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan190()['dependency_closure']),
    'planner stat4 expression partial current source next190 non overlap' => static fn (TestRunner $t) => $t->contains('NOT IN exclusion', $plan190()['non_overlap']),
    'planner stat4 expression partial current source next190 invalid empty terms' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan190(null, null, [])),
    'planner stat4 expression partial current source next190 invalid no residual' => static function (TestRunner $t) use ($plan190, $terms190): void {
        $terms = array_values(array_filter($terms190(), static fn (array $term): bool => strtoupper((string) $term['operator']) !== 'NOT IN'));
        $t->throws(InvalidArgumentException::class, static fn () => $plan190(null, null, $terms));
    },
    'planner stat4 expression partial current source next190 invalid empty list' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, $invalidEmpty190),
    'planner stat4 expression partial current source next190 invalid scalar right side' => static function (TestRunner $t) use ($plan190, $terms190): void {
        $terms = $terms190();
        $terms[1]['right'] = 'plugin_debug_trace';
        $t->throws(InvalidArgumentException::class, static fn () => $plan190(null, null, $terms));
    },
];

foreach (range(1, 6) as $case) {
    $tests['planner stat4 expression partial current source next190 repeated residual fence ' . $case] = static function (TestRunner $t) use ($plan190, $case): void {
        $plan = $plan190();
        $t->same('stat4-expression-partial-current-source-next190-ready', $plan['status']);
        $t->true(count($plan['matchedRowids']) >= ($case % 3));
    };
}

return $tests;
