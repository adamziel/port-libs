<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq181 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull181 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$range181 = static fn (string $expression, string $operator, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => $operator, 'right' => $right];

$prepared181 = static function (array $overrides = []): array {
    return array_replace_recursive([
        'name' => 'prepared-wp-options-stat4-expression-partial-or-next181',
        'schemaCookie' => 1810,
        'stat4Generation' => 31,
        'rows' => [
            ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'old-cache'],
            ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'old-forms'],
            ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'old-seo'],
        ],
        'indexes' => [[
            'name' => 'idx_wp_options_lower_partial_or_next181',
            'rootPage' => 18101,
            'expression' => 'lower(option_name)',
            'expressionColumn' => '__expr_lower_option_name',
            'direction' => 'ASC',
            'collation' => 'BINARY',
            'partialPredicateTerms' => [
                ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
                ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_'],
                ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<', 'right' => 'plugin`'],
            ],
            'partialPredicateAnyTerms' => [
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
                ['left' => ['column' => 'option_name'], 'operator' => '=', 'right' => 'siteurl'],
                ['left' => ['column' => 'option_name'], 'operator' => '=', 'right' => 'home'],
            ],
            'coveringColumns' => ['option_name', 'option_value', 'autoload', 'blog_id'],
            'stat4Samples' => [
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
                ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
                ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
            ],
        ]],
    ], $overrides);
};

$current181 = static function (array $overrides = []) use ($prepared181): array {
    $source = $prepared181([
        'name' => 'current-wp-options-stat4-expression-partial-or-next181',
        'schemaCookie' => 1818,
        'stat4Generation' => 46,
    ]);
    $source['indexes'][0]['rootPage'] = 18188;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
        ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
        ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 40]],
        ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 30]],
    ];
    $source['rows'] = [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-current'],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-current'],
        ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_mail', 'option_value' => 'mail-current'],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-current'],
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_lazy', 'option_value' => 'lazy-current'],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods_twentysix', 'option_value' => 'theme-current'],
        ['rowid' => 70, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_network', 'option_value' => 'network-current'],
    ];

    return array_replace_recursive($source, $overrides);
};

$terms181 = static fn (): array => [
    $range181('LOWER(option_name)', '>=', 'plugin_'),
    $range181('lower( option_name )', '<', 'plugin_t'),
    $eq181('blog_id', 1),
    $eq181('autoload', 'yes'),
    $notNull181('option_name'),
];
$order181 = ['expression' => 'lower(option_name)', 'direction' => 'ASC', 'collation' => 'BINARY'];
$needed181 = ['option_name', 'option_value', 'autoload'];
$plan181 = static fn (?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null, ?array $order = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext181(
    $prepared ?? $prepared181(),
    $current ?? $current181(),
    $terms ?? $terms181(),
    $needed ?? $needed181,
    $order ?? $order181,
);
$fresh181 = static function () use ($current181, $plan181): array {
    $source = $current181();

    return $plan181($source, $source);
};
$unproved181 = static function () use ($terms181, $plan181): array {
    $terms = $terms181();
    $terms[3] = ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'maybe'];

    return $plan181(null, null, $terms);
};
$siteurl181 = static function () use ($terms181, $plan181): array {
    $terms = $terms181();
    $terms[3] = ['left' => ['column' => 'option_name'], 'operator' => '=', 'right' => 'siteurl'];

    return $plan181(null, null, $terms);
};
$desc181 = static fn (): array => $plan181(null, null, null, null, ['expression' => 'lower(option_name)', 'direction' => 'DESC', 'collation' => 'BINARY']);

$tests = [
    'planner stat4 expression partial current source next181 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next181-ready', $plan181()['status']),
    'planner stat4 expression partial current source next181 selected current' => static fn (TestRunner $t) => $t->same('current', $plan181()['selectedSource']),
    'planner stat4 expression partial current source next181 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan181()['stalePreparedStatement']),
    'planner stat4 expression partial current source next181 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan181()['reprepareRequired']),
    'planner stat4 expression partial current source next181 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan181()['schemaCookieChanged']),
    'planner stat4 expression partial current source next181 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan181()['stat4GenerationChanged']),
    'planner stat4 expression partial current source next181 source changed' => static fn (TestRunner $t) => $t->same(true, $plan181()['sourceSignatureChanged']),
    'planner stat4 expression partial current source next181 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_partial_or_next181', $plan181()['selectedPlan']['name']),
    'planner stat4 expression partial current source next181 root page' => static fn (TestRunner $t) => $t->same(18188, $plan181()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next181 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan181()['selectedPlan']['next181Ready']),
    'planner stat4 expression partial current source next181 partial or implied selected' => static fn (TestRunner $t) => $t->same(true, $plan181()['selectedPlan']['next181PartialOrPredicateImplied']),
    'planner stat4 expression partial current source next181 prepared or implied' => static fn (TestRunner $t) => $t->same(true, $plan181()['preparedPlan']['next181PartialOrPredicateImplied']),
    'planner stat4 expression partial current source next181 current or implied' => static fn (TestRunner $t) => $t->same(true, $plan181()['currentPlan']['next181PartialOrPredicateImplied']),
    'planner stat4 expression partial current source next181 matched or left' => static fn (TestRunner $t) => $t->same('column:autoload', $plan181()['selectedPlan']['next181MatchedOrTerm']['leftKey']),
    'planner stat4 expression partial current source next181 matched or operator' => static fn (TestRunner $t) => $t->same('=', $plan181()['selectedPlan']['next181MatchedOrTerm']['operator']),
    'planner stat4 expression partial current source next181 matched or right' => static fn (TestRunner $t) => $t->same('yes', $plan181()['selectedPlan']['next181MatchedOrTerm']['right']),
    'planner stat4 expression partial current source next181 candidate or count' => static fn (TestRunner $t) => $t->same(3, count($plan181()['partialOrPredicate']['selected']['candidateOrTerms'])),
    'planner stat4 expression partial current source next181 matched where term' => static fn (TestRunner $t) => $t->same('column:autoload', $plan181()['partialOrPredicate']['selected']['matchedWhereTerm']['leftKey']),
    'planner stat4 expression partial current source next181 order satisfied' => static fn (TestRunner $t) => $t->same(true, $plan181()['orderBySatisfiedByIndex']),
    'planner stat4 expression partial current source next181 no temp sort' => static fn (TestRunner $t) => $t->same(false, $plan181()['temporarySortRequired']),
    'planner stat4 expression partial current source next181 covering' => static fn (TestRunner $t) => $t->same(true, $plan181()['selectedPlan']['covering']),
    'planner stat4 expression partial current source next181 no table lookup' => static fn (TestRunner $t) => $t->same(false, $plan181()['tableLookupRequired']),
    'planner stat4 expression partial current source next181 matched rowids asc' => static fn (TestRunner $t) => $t->same([10, 20, 40, 30], $plan181()['matchedRowids']),
    'planner stat4 expression partial current source next181 matched keys asc' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $plan181()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next181 excludes lazy no' => static fn (TestRunner $t) => $t->same(false, in_array(50, $plan181()['matchedRowids'], true)),
    'planner stat4 expression partial current source next181 excludes theme' => static fn (TestRunner $t) => $t->same(false, in_array(60, $plan181()['matchedRowids'], true)),
    'planner stat4 expression partial current source next181 excludes blog two' => static fn (TestRunner $t) => $t->same(false, in_array(70, $plan181()['matchedRowids'], true)),
    'planner stat4 expression partial current source next181 stat4 keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $plan181()['selectedPlan']['matchedStat4Keys']),
    'planner stat4 expression partial current source next181 estimated rows' => static fn (TestRunner $t) => $t->same(4, $plan181()['selectedPlan']['estimatedRows']),
    'planner stat4 expression partial current source next181 estimated cost' => static fn (TestRunner $t) => $t->same(4, $plan181()['selectedPlan']['estimatedCost']),
    'planner stat4 expression partial current source next181 prepared rowids' => static fn (TestRunner $t) => $t->same([10, 20, 30], $plan181()['preparedPlan']['matchedRowids']),
    'planner stat4 expression partial current source next181 current rowids' => static fn (TestRunner $t) => $t->same([10, 20, 40, 30], $plan181()['currentPlan']['matchedRowids']),
    'planner stat4 expression partial current source next181 current admitted' => static fn (TestRunner $t) => $t->same([40], $plan181()['currentSourceRowidsAdmittedByOrderFence']),
    'planner stat4 expression partial current source next181 fence generation' => static fn (TestRunner $t) => $t->same(46, $plan181()['stat4Fence']['stat4Generation']),
    'planner stat4 expression partial current source next181 or fence signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan181()['stat4Fence']['next181PartialOrSignature'])),
    'planner stat4 expression partial current source next181 cursor open' => static fn (TestRunner $t) => $t->same('OpenRead', $plan181()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next181 cursor seek asc' => static fn (TestRunner $t) => $t->same('SeekGE', $plan181()['cursorProgram'][2]['opcode']),
    'planner stat4 expression partial current source next181 cursor partial or recheck' => static fn (TestRunner $t) => $t->same('RecheckPartialOrPredicate', $plan181()['cursorProgram'][5]['opcode']),
    'planner stat4 expression partial current source next181 cursor partial and recheck retained' => static fn (TestRunner $t) => $t->same('RecheckPartialPredicate', $plan181()['cursorProgram'][4]['opcode']),
    'planner stat4 expression partial current source next181 cursor covering shifted' => static fn (TestRunner $t) => $t->same('ColumnFromIndex', $plan181()['cursorProgram'][6]['opcode']),
    'planner stat4 expression partial current source next181 cursor rowids shifted' => static fn (TestRunner $t) => $t->same([10, 20, 40, 30], $plan181()['cursorProgram'][7]['rowids']),
    'planner stat4 expression partial current source next181 fresh source' => static fn (TestRunner $t) => $t->same('prepared', $fresh181()['selectedSource']),
    'planner stat4 expression partial current source next181 fresh rowids' => static fn (TestRunner $t) => $t->same([10, 20, 40, 30], $fresh181()['matchedRowids']),
    'planner stat4 expression partial current source next181 alternate or term proves partial' => static fn (TestRunner $t) => $t->same('siteurl', $siteurl181()['selectedPlan']['next181MatchedOrTerm']['right']),
    'planner stat4 expression partial current source next181 alternate or term no rows' => static fn (TestRunner $t) => $t->same('requires-next-stage', $siteurl181()['status']),
    'planner stat4 expression partial current source next181 unproved fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $unproved181()['status']),
    'planner stat4 expression partial current source next181 unproved cursor replan' => static fn (TestRunner $t) => $t->same('Replan', $unproved181()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next181 desc order fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $desc181()['status']),
    'planner stat4 expression partial current source next181 desc requires sort' => static fn (TestRunner $t) => $t->same(true, $desc181()['temporarySortRequired']),
    'planner stat4 expression partial current source next181 detail' => static fn (TestRunner $t) => $t->contains('NEXT181 OR-PREDICATE FENCE', $plan181()['detail']),
    'planner stat4 expression partial current source next181 dependency marker' => static fn (TestRunner $t) => $t->same(['sqlite-sqlplanner-stat4-expression-partial-current-source-next181'], $plan181()['dependencies']),
    'planner stat4 expression partial current source next181 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan181()['dependency_closure']),
    'planner stat4 expression partial current source next181 non overlap' => static fn (TestRunner $t) => $t->contains('OR-predicate implication', $plan181()['non_overlap']),
    'planner stat4 expression partial current source next181 invalid empty terms' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan181(null, null, [])),
    'planner stat4 expression partial current source next181 invalid partial or source' => static function (TestRunner $t) use ($current181, $plan181): void {
        $bad = $current181();
        $bad['indexes'][0]['partialPredicateAnyTerms'] = ['bad'];
        $t->throws(InvalidArgumentException::class, static fn () => $plan181(null, $bad));
    },
];

foreach (range(1, 8) as $case) {
    $tests['planner stat4 expression partial current source next181 repeated or fence ' . $case] = static function (TestRunner $t) use ($plan181, $case): void {
        $plan = $plan181();
        $t->same('stat4-expression-partial-current-source-next181-ready', $plan['status']);
        $t->true(count($plan['matchedRowids']) >= ($case % 4));
    };
}

return $tests;
