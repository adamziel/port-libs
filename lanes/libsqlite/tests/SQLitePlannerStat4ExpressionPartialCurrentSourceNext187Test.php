<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq187 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull187 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$exprLike187 = static fn (string $expression, string $pattern, string $operator = 'LIKE', string $escape = '\\'): array => ['left' => ['expression' => $expression], 'operator' => $operator, 'right' => $pattern, 'escape' => $escape];
$exprRange187 = static fn (string $expression, string $operator, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => $operator, 'right' => $right];

$prepared187 = static function (array $overrides = []): array {
    return array_replace_recursive([
        'name' => 'prepared-wp-options-stat4-not-like-next187',
        'schemaCookie' => 1870,
        'stat4Generation' => 54,
        'rows' => [
            ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-old'],
            ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old'],
            ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_debug_old', 'option_value' => 'debug-old'],
        ],
        'indexes' => [[
            'name' => 'idx_wp_options_lower_not_like_next187',
            'rootPage' => 18701,
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
                ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_debug_old', 30]],
                ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 20]],
            ],
        ]],
    ], $overrides);
};

$current187 = static function (array $overrides = []) use ($prepared187): array {
    $source = $prepared187([
        'name' => 'current-wp-options-stat4-not-like-next187',
        'schemaCookie' => 1879,
        'stat4Generation' => 77,
    ]);
    $source['indexes'][0]['rootPage'] = 18788;
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
    ];

    return array_replace_recursive($source, $overrides);
};

$terms187 = static fn (): array => [
    $exprLike187('LOWER( option_name )', 'plugin\_%'),
    $exprLike187('lower(option_name)', 'plugin\_debug%', 'NOT LIKE'),
    $exprLike187('lower(option_name)', 'plugin\_tmp%', 'NOT LIKE'),
    $eq187('blog_id', 1),
    $eq187('autoload', 'yes'),
    $notNull187('option_name'),
    $exprRange187('lower(option_name)', '>=', 'plugin_'),
    $exprRange187('lower(option_name)', '<', 'plugin`'),
];
$needed187 = ['option_name', 'option_value'];
$plan187 = static fn (?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceNotLikeResidualFence(
    $prepared ?? $prepared187(),
    $current ?? $current187(),
    $terms ?? $terms187(),
    $needed ?? $needed187,
);
$fresh187 = static function () use ($current187, $plan187): array {
    $source = $current187();

    return $plan187($source, $source);
};
$noReject187 = static function () use ($current187, $plan187, $terms187): array {
    $current = $current187();
    $current['rows'][1]['option_name'] = 'plugin_debugless';
    $current['rows'][4]['option_name'] = 'plugin_tempcache';
    $current['indexes'][0]['stat4Samples'][1]['sample'][0] = 'plugin_debugless';
    $current['indexes'][0]['stat4Samples'][4]['sample'][0] = 'plugin_tempcache';
    $terms = $terms187();
    $terms[1]['right'] = 'plugin\_debug\_%';
    $terms[2]['right'] = 'plugin\_tmp\_%';

    return $plan187(null, $current, $terms);
};
$invalidEscape187 = static function () use ($plan187, $terms187): array {
    $terms = $terms187();
    $terms[1]['escape'] = '!!';

    return $plan187(null, null, $terms);
};

$tests = [
    'planner stat4 expression partial current source next187 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next187-ready', $plan187()['status']),
    'planner stat4 expression partial current source next187 selected current' => static fn (TestRunner $t) => $t->same('current', $plan187()['selectedSource']),
    'planner stat4 expression partial current source next187 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan187()['stalePreparedStatement']),
    'planner stat4 expression partial current source next187 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan187()['reprepareRequired']),
    'planner stat4 expression partial current source next187 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan187()['schemaCookieChanged']),
    'planner stat4 expression partial current source next187 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan187()['stat4GenerationChanged']),
    'planner stat4 expression partial current source next187 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_not_like_next187', $plan187()['selectedPlan']['name']),
    'planner stat4 expression partial current source next187 root page' => static fn (TestRunner $t) => $t->same(18788, $plan187()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next187 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan187()['selectedPlan']['next187Ready']),
    'planner stat4 expression partial current source next187 residual retained' => static fn (TestRunner $t) => $t->same(true, $plan187()['selectedPlan']['notLikeResidualRetained']),
    'planner stat4 expression partial current source next187 residual count' => static fn (TestRunner $t) => $t->same(2, $plan187()['selectedPlan']['notLikeResidualCount']),
    'planner stat4 expression partial current source next187 before residual rowids' => static fn (TestRunner $t) => $t->same([10, 40, 20, 50, 60], $plan187()['matchedRowidsBeforeNotLikeResidual']),
    'planner stat4 expression partial current source next187 after residual rowids' => static fn (TestRunner $t) => $t->same([10, 20, 50], $plan187()['matchedRowids']),
    'planner stat4 expression partial current source next187 after residual keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mail'], $plan187()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next187 rejects debug rowid' => static fn (TestRunner $t) => $t->same(true, in_array(40, $plan187()['notLikeResidualRowidsRejected'], true)),
    'planner stat4 expression partial current source next187 rejects tmp rowid' => static fn (TestRunner $t) => $t->same(true, in_array(60, $plan187()['notLikeResidualRowidsRejected'], true)),
    'planner stat4 expression partial current source next187 accepts cache rowid' => static fn (TestRunner $t) => $t->same(true, in_array(10, $plan187()['notLikeResidualRowidsAccepted'], true)),
    'planner stat4 expression partial current source next187 accepts forms rowid' => static fn (TestRunner $t) => $t->same(true, in_array(20, $plan187()['notLikeResidualRowidsAccepted'], true)),
    'planner stat4 expression partial current source next187 accepts mail rowid' => static fn (TestRunner $t) => $t->same(true, in_array(50, $plan187()['notLikeResidualRowidsAccepted'], true)),
    'planner stat4 expression partial current source next187 current payload wins' => static fn (TestRunner $t) => $t->same('mail-current', $plan187()['matchedRows'][2]['payload']['option_value']),
    'planner stat4 expression partial current source next187 residual pattern one' => static fn (TestRunner $t) => $t->same('plugin\_debug%', $plan187()['notLikeResiduals'][0]['pattern']),
    'planner stat4 expression partial current source next187 residual pattern two' => static fn (TestRunner $t) => $t->same('plugin\_tmp%', $plan187()['notLikeResiduals'][1]['pattern']),
    'planner stat4 expression partial current source next187 residual left key' => static fn (TestRunner $t) => $t->same('expression:lower(option_name)', $plan187()['notLikeResiduals'][0]['leftKey']),
    'planner stat4 expression partial current source next187 estimated rows after residual' => static fn (TestRunner $t) => $t->same(3, $plan187()['selectedPlan']['estimatedRowsAfterNotLikeResidual']),
    'planner stat4 expression partial current source next187 estimated cost after residual' => static fn (TestRunner $t) => $t->same(3, $plan187()['selectedPlan']['estimatedCostAfterNotLikeResidual']),
    'planner stat4 expression partial current source next187 base prefix preserved' => static fn (TestRunner $t) => $t->same('plugin_', $plan187()['prefix']),
    'planner stat4 expression partial current source next187 base upper preserved' => static fn (TestRunner $t) => $t->same('plugin`', $plan187()['prefixUpperBound']),
    'planner stat4 expression partial current source next187 stat4 window still broad' => static fn (TestRunner $t) => $t->same([10, 40, 20, 50, 60], $plan187()['stat4PrefixWindow']['rowids']),
    'planner stat4 expression partial current source next187 table lookup elided' => static fn (TestRunner $t) => $t->same(false, $plan187()['tableLookupRequired']),
    'planner stat4 expression partial current source next187 residual predicate required' => static fn (TestRunner $t) => $t->same(true, $plan187()['residualPredicateRequired']),
    'planner stat4 expression partial current source next187 cursor open' => static fn (TestRunner $t) => $t->same('OpenRead', $plan187()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next187 cursor fence' => static fn (TestRunner $t) => $t->same('FenceStat4PrefixWindow', $plan187()['cursorProgram'][1]['opcode']),
    'planner stat4 expression partial current source next187 cursor residual inserted' => static fn (TestRunner $t) => $t->same('RecheckNotLikeResidual', $plan187()['cursorProgram'][5]['opcode']),
    'planner stat4 expression partial current source next187 cursor residual patterns' => static fn (TestRunner $t) => $t->same(['plugin\_debug%', 'plugin\_tmp%'], $plan187()['cursorProgram'][5]['patterns']),
    'planner stat4 expression partial current source next187 cursor residual rowids' => static fn (TestRunner $t) => $t->same([10, 20, 50], $plan187()['cursorProgram'][5]['rowids']),
    'planner stat4 expression partial current source next187 cursor result filtered' => static function (TestRunner $t) use ($plan187): void {
        $resultOps = array_values(array_filter($plan187()['cursorProgram'], static fn (array $op): bool => ($op['opcode'] ?? null) === 'ResultRow'));
        $t->same([10, 20, 50], $resultOps[0]['rowids'] ?? null);
    },
    'planner stat4 expression partial current source next187 fence residual hash' => static fn (TestRunner $t) => $t->same(64, strlen($plan187()['stat4Fence']['next187NotLikeResidualSignature'])),
    'planner stat4 expression partial current source next187 fence row stream hash' => static fn (TestRunner $t) => $t->same(64, strlen($plan187()['stat4Fence']['rowStreamSignatureAfterNotLikeResidual'])),
    'planner stat4 expression partial current source next187 prepared before residual' => static fn (TestRunner $t) => $t->same([10, 30, 20], $plan187()['preparedPlan']['matchedRowids']),
    'planner stat4 expression partial current source next187 current before residual' => static fn (TestRunner $t) => $t->same([10, 40, 20, 50, 60], $plan187()['currentPlan']['matchedRowids']),
    'planner stat4 expression partial current source next187 fresh source' => static fn (TestRunner $t) => $t->same('prepared', $fresh187()['selectedSource']),
    'planner stat4 expression partial current source next187 fresh ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next187-ready', $fresh187()['status']),
    'planner stat4 expression partial current source next187 fresh filtered rowids' => static fn (TestRunner $t) => $t->same([10, 20, 50], $fresh187()['matchedRowids']),
    'planner stat4 expression partial current source next187 no rejected row fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $noReject187()['status']),
    'planner stat4 expression partial current source next187 no rejected cursor replan' => static fn (TestRunner $t) => $t->same('Replan', $noReject187()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next187 detail' => static fn (TestRunner $t) => $t->contains('NEXT187 NOT-LIKE RESIDUAL', $plan187()['detail']),
    'planner stat4 expression partial current source next187 dependency marker' => static fn (TestRunner $t) => $t->same(['sqlite-sqlplanner-stat4-expression-partial-current-source-next187'], $plan187()['dependencies']),
    'planner stat4 expression partial current source next187 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan187()['dependency_closure']),
    'planner stat4 expression partial current source next187 non overlap' => static fn (TestRunner $t) => $t->contains('NOT LIKE exclusion', $plan187()['non_overlap']),
    'planner stat4 expression partial current source next187 invalid empty terms' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan187(null, null, [])),
    'planner stat4 expression partial current source next187 invalid no residual' => static function (TestRunner $t) use ($plan187, $terms187): void {
        $terms = array_values(array_filter($terms187(), static fn (array $term): bool => strtoupper((string) $term['operator']) !== 'NOT LIKE'));
        $t->throws(InvalidArgumentException::class, static fn () => $plan187(null, null, $terms));
    },
    'planner stat4 expression partial current source next187 invalid escape' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, $invalidEscape187),
    'planner stat4 expression partial current source next187 invalid pattern' => static function (TestRunner $t) use ($plan187, $terms187): void {
        $terms = $terms187();
        $terms[1]['right'] = '';
        $t->throws(InvalidArgumentException::class, static fn () => $plan187(null, null, $terms));
    },
];

foreach (range(1, 6) as $case) {
    $tests['planner stat4 expression partial current source next187 repeated residual fence ' . $case] = static function (TestRunner $t) use ($plan187, $case): void {
        $plan = $plan187();
        $t->same('stat4-expression-partial-current-source-next187-ready', $plan['status']);
        $t->true(count($plan['matchedRowids']) >= ($case % 3));
    };
}

return $tests;
