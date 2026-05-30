<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq200 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull200 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$exprLike200 = static fn (string $expression, string $pattern): array => ['left' => ['expression' => $expression], 'operator' => 'LIKE', 'right' => $pattern, 'escape' => '\\'];
$exprNotBetween200 = static fn (string $expression, mixed $lower, mixed $upper, string $collation = 'BINARY'): array => ['left' => ['expression' => $expression], 'operator' => 'NOT BETWEEN', 'values' => [['literal' => $lower], ['literal' => $upper]], 'collation' => $collation];
$columnNotBetween200 = static fn (string $column, mixed $lower, mixed $upper, string $collation = 'BINARY'): array => ['left' => ['column' => $column], 'operator' => 'NOT BETWEEN', 'lower' => ['literal' => $lower], 'upper' => ['literal' => $upper], 'collation' => $collation];
$exprRange200 = static fn (string $expression, string $operator, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => $operator, 'right' => $right];

$prepared200 = static function (array $overrides = []): array {
    return array_replace_recursive([
        'name' => 'prepared-wp-options-stat4-not-between-next200',
        'schemaCookie' => 2000,
        'stat4Generation' => 75,
        'rows' => [
            ['rowid' => 11, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'aa-cache-old'],
            ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'mm-forms-old'],
            ['rowid' => 33, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_search', 'option_value' => 'zz-search-old'],
        ],
        'indexes' => [[
            'name' => 'idx_wp_options_lower_not_between_next200',
            'rootPage' => 20001,
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
                ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 22]],
                ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_search', 33]],
            ],
        ]],
    ], $overrides);
};

$current200 = static function (array $overrides = []) use ($prepared200): array {
    $source = $prepared200([
        'name' => 'current-wp-options-stat4-not-between-next200',
        'schemaCookie' => 2011,
        'stat4Generation' => 96,
    ]);
    $source['indexes'][0]['rootPage'] = 20088;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 11]],
        ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_debug_trace', 44]],
        ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 22]],
        ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 55]],
        ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '4 4', 'sample' => ['plugin_search', 66]],
        ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '5 5', 'sample' => ['plugin_seo', 77]],
    ];
    $source['rows'] = [
        ['rowid' => 11, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'aa-cache-current'],
        ['rowid' => 44, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_debug_trace', 'option_value' => 'bb-debug-current'],
        ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'mm-forms-current'],
        ['rowid' => 55, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_mail', 'option_value' => 'nn-mail-current'],
        ['rowid' => 66, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_search', 'option_value' => 'zz-search-current'],
        ['rowid' => 77, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'zz-seo-current'],
        ['rowid' => 88, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods', 'option_value' => 'theme-current'],
        ['rowid' => 99, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_lazy', 'option_value' => 'lazy-current'],
    ];

    return array_replace_recursive($source, $overrides);
};

$terms200 = static fn (): array => [
    $exprLike200('LOWER( option_name )', 'plugin\_%'),
    $exprNotBetween200('lower(option_name)', 'plugin_debug', 'plugin_mail'),
    $columnNotBetween200('option_value', 'aa', 'cc'),
    $eq200('blog_id', 1),
    $eq200('autoload', 'yes'),
    $notNull200('option_name'),
    $exprRange200('lower(option_name)', '>=', 'plugin_'),
    $exprRange200('lower(option_name)', '<', 'plugin`'),
];
$needed200 = ['option_name', 'option_value'];
$plan200 = static fn (?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceHistogramFence(
    $prepared ?? $prepared200(),
    $current ?? $current200(),
    $terms ?? $terms200(),
    $needed ?? $needed200,
);
$fresh200 = static function () use ($current200, $plan200): array {
    $source = $current200();

    return $plan200($source, $source);
};
$noReject200 = static function () use ($terms200, $exprNotBetween200, $columnNotBetween200, $plan200): array {
    $terms = $terms200();
    $terms[1] = $exprNotBetween200('lower(option_name)', 'plugin_aaa', 'plugin_aab');
    $terms[2] = $columnNotBetween200('option_value', 'xx', 'xy');

    return $plan200(null, null, $terms);
};
$allRejected200 = static function () use ($terms200, $exprNotBetween200, $plan200): array {
    $terms = $terms200();
    $terms[1] = $exprNotBetween200('lower(option_name)', 'plugin_', 'plugin`');

    return $plan200(null, null, $terms);
};
$badBounds200 = static function () use ($terms200, $plan200): array {
    $terms = $terms200();
    $terms[1]['values'] = [['literal' => 'plugin_a']];

    return $plan200(null, null, $terms);
};
$nocase200 = static function () use ($current200, $terms200, $exprNotBetween200, $plan200): array {
    $current = $current200();
    $current['rows'][4]['option_name'] = 'Plugin_Search';
    $current['indexes'][0]['stat4Samples'][4]['sample'][0] = 'Plugin_Search';
    $terms = $terms200();
    $terms[1] = $exprNotBetween200('lower(option_name)', 'PLUGIN_SEARCH', 'PLUGIN_SEARCH', 'NOCASE');
    $terms[2]['lower'] = ['literal' => 'aa'];
    $terms[2]['upper'] = ['literal' => 'ab'];

    return $plan200(null, $current, $terms);
};

$tests = [
    'planner stat4 expression partial current source next200 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next200-ready', $plan200()['status']),
    'planner stat4 expression partial current source next200 selected current' => static fn (TestRunner $t) => $t->same('current', $plan200()['selectedSource']),
    'planner stat4 expression partial current source next200 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan200()['stalePreparedStatement']),
    'planner stat4 expression partial current source next200 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan200()['reprepareRequired']),
    'planner stat4 expression partial current source next200 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan200()['schemaCookieChanged']),
    'planner stat4 expression partial current source next200 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan200()['stat4GenerationChanged']),
    'planner stat4 expression partial current source next200 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_not_between_next200', $plan200()['selectedPlan']['name']),
    'planner stat4 expression partial current source next200 root page' => static fn (TestRunner $t) => $t->same(20088, $plan200()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next200 covering' => static fn (TestRunner $t) => $t->same(true, $plan200()['selectedPlan']['covering']),
    'planner stat4 expression partial current source next200 table lookup elided' => static fn (TestRunner $t) => $t->same(false, $plan200()['tableLookupRequired']),
    'planner stat4 expression partial current source next200 before residual rowids' => static fn (TestRunner $t) => $t->same([11, 44, 22, 55, 66, 77], $plan200()['matchedRowidsBeforeNotBetweenResidual']),
    'planner stat4 expression partial current source next200 prepared before residual' => static fn (TestRunner $t) => $t->same([11, 22, 33], $plan200()['preparedPlan']['matchedRowids']),
    'planner stat4 expression partial current source next200 current before residual' => static fn (TestRunner $t) => $t->same([11, 44, 22, 55, 66, 77], $plan200()['currentPlan']['matchedRowids']),
    'planner stat4 expression partial current source next200 after residual rowids' => static fn (TestRunner $t) => $t->same([66, 77], $plan200()['matchedRowids']),
    'planner stat4 expression partial current source next200 after residual keys' => static fn (TestRunner $t) => $t->same(['plugin_search', 'plugin_seo'], $plan200()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next200 rejects cache rowid' => static fn (TestRunner $t) => $t->same(true, in_array(11, $plan200()['notBetweenResidualRowidsRejected'], true)),
    'planner stat4 expression partial current source next200 rejects debug rowid' => static fn (TestRunner $t) => $t->same(true, in_array(44, $plan200()['notBetweenResidualRowidsRejected'], true)),
    'planner stat4 expression partial current source next200 rejects forms rowid' => static fn (TestRunner $t) => $t->same(true, in_array(22, $plan200()['notBetweenResidualRowidsRejected'], true)),
    'planner stat4 expression partial current source next200 rejects mail rowid' => static fn (TestRunner $t) => $t->same(true, in_array(55, $plan200()['notBetweenResidualRowidsRejected'], true)),
    'planner stat4 expression partial current source next200 accepts search rowid' => static fn (TestRunner $t) => $t->same(true, in_array(66, $plan200()['notBetweenResidualRowidsAccepted'], true)),
    'planner stat4 expression partial current source next200 accepts seo rowid' => static fn (TestRunner $t) => $t->same(true, in_array(77, $plan200()['notBetweenResidualRowidsAccepted'], true)),
    'planner stat4 expression partial current source next200 theme outside prefix' => static fn (TestRunner $t) => $t->same(false, in_array(88, $plan200()['matchedRowidsBeforeNotBetweenResidual'], true)),
    'planner stat4 expression partial current source next200 autoload no excluded' => static fn (TestRunner $t) => $t->same(false, in_array(99, $plan200()['matchedRowidsBeforeNotBetweenResidual'], true)),
    'planner stat4 expression partial current source next200 current payload wins' => static fn (TestRunner $t) => $t->same('zz-search-current', $plan200()['matchedRows'][0]['payload']['option_value']),
    'planner stat4 expression partial current source next200 residual count' => static fn (TestRunner $t) => $t->same(2, $plan200()['selectedPlan']['notBetweenResidualCount']),
    'planner stat4 expression partial current source next200 residual retained' => static fn (TestRunner $t) => $t->same(true, $plan200()['selectedPlan']['notBetweenResidualRetained']),
    'planner stat4 expression partial current source next200 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan200()['selectedPlan']['next200Ready']),
    'planner stat4 expression partial current source next200 estimated rows after residual' => static fn (TestRunner $t) => $t->same(2, $plan200()['selectedPlan']['estimatedRowsAfterNotBetweenResidual']),
    'planner stat4 expression partial current source next200 estimated cost after residual' => static fn (TestRunner $t) => $t->same(2, $plan200()['selectedPlan']['estimatedCostAfterNotBetweenResidual']),
    'planner stat4 expression partial current source next200 expression residual key' => static fn (TestRunner $t) => $t->same('expression:lower(option_name)', $plan200()['notBetweenResiduals'][0]['leftKey']),
    'planner stat4 expression partial current source next200 expression residual lower' => static fn (TestRunner $t) => $t->same('plugin_debug', $plan200()['notBetweenResiduals'][0]['lower']),
    'planner stat4 expression partial current source next200 expression residual upper' => static fn (TestRunner $t) => $t->same('plugin_mail', $plan200()['notBetweenResiduals'][0]['upper']),
    'planner stat4 expression partial current source next200 column residual key' => static fn (TestRunner $t) => $t->same('column:option_value', $plan200()['notBetweenResiduals'][1]['leftKey']),
    'planner stat4 expression partial current source next200 column residual lower' => static fn (TestRunner $t) => $t->same('aa', $plan200()['notBetweenResiduals'][1]['lower']),
    'planner stat4 expression partial current source next200 column residual upper' => static fn (TestRunner $t) => $t->same('cc', $plan200()['notBetweenResiduals'][1]['upper']),
    'planner stat4 expression partial current source next200 stat4 window rowids' => static fn (TestRunner $t) => $t->same([11, 44, 22, 55, 66, 77], $plan200()['stat4PrefixWindow']['rowids']),
    'planner stat4 expression partial current source next200 stat4 window keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_debug_trace', 'plugin_forms', 'plugin_mail', 'plugin_search', 'plugin_seo'], $plan200()['stat4PrefixWindow']['keys']),
    'planner stat4 expression partial current source next200 residual predicate required' => static fn (TestRunner $t) => $t->same(true, $plan200()['residualPredicateRequired']),
    'planner stat4 expression partial current source next200 cursor open' => static fn (TestRunner $t) => $t->same('OpenRead', $plan200()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next200 cursor fence' => static fn (TestRunner $t) => $t->same('FenceStat4PrefixWindow', $plan200()['cursorProgram'][1]['opcode']),
    'planner stat4 expression partial current source next200 cursor residual inserted' => static fn (TestRunner $t) => $t->same('RecheckNotBetweenResidual', $plan200()['cursorProgram'][5]['opcode']),
    'planner stat4 expression partial current source next200 cursor residual rowids' => static fn (TestRunner $t) => $t->same([66, 77], $plan200()['cursorProgram'][5]['rowids']),
    'planner stat4 expression partial current source next200 cursor residual ranges' => static fn (TestRunner $t) => $t->same(2, count($plan200()['cursorProgram'][5]['ranges'])),
    'planner stat4 expression partial current source next200 cursor result filtered' => static function (TestRunner $t) use ($plan200): void {
        $resultOps = array_values(array_filter($plan200()['cursorProgram'], static fn (array $op): bool => ($op['opcode'] ?? null) === 'ResultRow'));
        $t->same([66, 77], $resultOps[0]['rowids'] ?? null);
    },
    'planner stat4 expression partial current source next200 fence residual hash' => static fn (TestRunner $t) => $t->same(64, strlen($plan200()['stat4Fence']['next200NotBetweenResidualSignature'])),
    'planner stat4 expression partial current source next200 fence row stream hash' => static fn (TestRunner $t) => $t->same(64, strlen($plan200()['stat4Fence']['rowStreamSignatureAfterNotBetweenResidual'])),
    'planner stat4 expression partial current source next200 stale prefix changed' => static fn (TestRunner $t) => $t->same(true, $plan200()['stat4PrefixWindowChanged']),
    'planner stat4 expression partial current source next200 stale prepared blocked' => static fn (TestRunner $t) => $t->same([33], $plan200()['stalePreparedRowidsBlockedByPrefixWindow']),
    'planner stat4 expression partial current source next200 current admitted' => static fn (TestRunner $t) => $t->same([44, 55, 66, 77], $plan200()['currentSourceRowidsAdmittedByPrefixWindow']),
    'planner stat4 expression partial current source next200 fresh source' => static fn (TestRunner $t) => $t->same('prepared', $fresh200()['selectedSource']),
    'planner stat4 expression partial current source next200 fresh ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next200-ready', $fresh200()['status']),
    'planner stat4 expression partial current source next200 fresh filtered rowids' => static fn (TestRunner $t) => $t->same([66, 77], $fresh200()['matchedRowids']),
    'planner stat4 expression partial current source next200 no rejected row fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $noReject200()['status']),
    'planner stat4 expression partial current source next200 no rejected cursor replan' => static fn (TestRunner $t) => $t->same('Replan', $noReject200()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next200 all rejected fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $allRejected200()['status']),
    'planner stat4 expression partial current source next200 all rejected cursor replan' => static fn (TestRunner $t) => $t->same('Replan', $allRejected200()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next200 nocase rejects uppercase search' => static fn (TestRunner $t) => $t->same(false, in_array(66, $nocase200()['matchedRowids'], true)),
    'planner stat4 expression partial current source next200 nocase keeps seo' => static fn (TestRunner $t) => $t->same(true, in_array(77, $nocase200()['matchedRowids'], true)),
    'planner stat4 expression partial current source next200 detail' => static fn (TestRunner $t) => $t->contains('NEXT200 NOT-BETWEEN RESIDUAL', $plan200()['detail']),
    'planner stat4 expression partial current source next200 dependency marker' => static fn (TestRunner $t) => $t->same(['sqlite-sqlplanner-stat4-expression-partial-current-source-next200'], $plan200()['dependencies']),
    'planner stat4 expression partial current source next200 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan200()['dependency_closure']),
    'planner stat4 expression partial current source next200 non overlap' => static fn (TestRunner $t) => $t->contains('NOT BETWEEN residual', $plan200()['non_overlap']),
    'planner stat4 expression partial current source next200 invalid empty terms' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan200(null, null, [])),
    'planner stat4 expression partial current source next200 invalid no residual' => static function (TestRunner $t) use ($plan200, $terms200): void {
        $terms = array_values(array_filter($terms200(), static fn (array $term): bool => strtoupper((string) $term['operator']) !== 'NOT BETWEEN'));
        $t->throws(InvalidArgumentException::class, static fn () => $plan200(null, null, $terms));
    },
    'planner stat4 expression partial current source next200 invalid bounds' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, $badBounds200),
];

foreach (range(1, 6) as $case) {
    $tests['planner stat4 expression partial current source next200 repeated residual fence ' . $case] = static function (TestRunner $t) use ($plan200, $case): void {
        $plan = $plan200();
        $t->same('stat4-expression-partial-current-source-next200-ready', $plan['status']);
        $t->true(count($plan['matchedRowids']) >= ($case % 2));
    };
}

return $tests;
