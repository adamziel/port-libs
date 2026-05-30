<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq184 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull184 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$range184 = static fn (string $expression, string $operator, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => $operator, 'right' => $right];

$prepared184 = static function (array $overrides = []): array {
    return array_replace_recursive([
        'name' => 'prepared-wp-options-stat4-expression-partial-in-next184',
        'schemaCookie' => 1840,
        'stat4Generation' => 37,
        'rows' => [
            ['rowid' => 11, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'old-cache'],
            ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'old-forms'],
            ['rowid' => 31, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'old-seo'],
        ],
        'indexes' => [[
            'name' => 'idx_wp_options_lower_partial_in_next184',
            'rootPage' => 18401,
            'expression' => 'lower(option_name)',
            'expressionColumn' => '__expr_lower_option_name',
            'direction' => 'ASC',
            'collation' => 'BINARY',
            'partialPredicateTerms' => [
                ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
                ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_'],
                ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<', 'right' => 'plugin`'],
            ],
            'partialPredicateInTerms' => [
                ['left' => ['column' => 'autoload'], 'values' => ['yes', 'auto-on', 'eager']],
            ],
            'coveringColumns' => ['option_name', 'option_value', 'autoload', 'blog_id'],
            'stat4Samples' => [
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 11]],
                ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 21]],
                ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 31]],
            ],
        ]],
    ], $overrides);
};

$current184 = static function (array $overrides = []) use ($prepared184): array {
    $source = $prepared184([
        'name' => 'current-wp-options-stat4-expression-partial-in-next184',
        'schemaCookie' => 1848,
        'stat4Generation' => 54,
    ]);
    $source['indexes'][0]['rootPage'] = 18488;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 11]],
        ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 21]],
        ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 41]],
        ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 31]],
    ];
    $source['rows'] = [
        ['rowid' => 11, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-current'],
        ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-current'],
        ['rowid' => 41, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_mail', 'option_value' => 'mail-current'],
        ['rowid' => 31, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-current'],
        ['rowid' => 51, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_lazy', 'option_value' => 'lazy-current'],
        ['rowid' => 61, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods_twentysix', 'option_value' => 'theme-current'],
        ['rowid' => 71, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_network', 'option_value' => 'network-current'],
    ];

    return array_replace_recursive($source, $overrides);
};

$terms184 = static fn (): array => [
    $range184('LOWER(option_name)', '>=', 'plugin_'),
    $range184('lower( option_name )', '<', 'plugin_t'),
    $eq184('blog_id', 1),
    $eq184('autoload', 'yes'),
    $notNull184('option_name'),
];
$order184 = ['expression' => 'lower(option_name)', 'direction' => 'ASC', 'collation' => 'BINARY'];
$needed184 = ['option_name', 'option_value', 'autoload'];
$plan184 = static fn (?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null, ?array $order = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourcePartialInProofFence(
    $prepared ?? $prepared184(),
    $current ?? $current184(),
    $terms ?? $terms184(),
    $needed ?? $needed184,
    $order ?? $order184,
);
$fresh184 = static function () use ($current184, $plan184): array {
    $source = $current184();

    return $plan184($source, $source);
};
$autoOn184 = static function () use ($terms184, $plan184): array {
    $terms = $terms184();
    $terms[3] = ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'auto-on'];

    return $plan184(null, null, $terms);
};
$unproved184 = static function () use ($terms184, $plan184): array {
    $terms = $terms184();
    $terms[3] = ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'no'];

    return $plan184(null, null, $terms);
};
$desc184 = static fn (): array => $plan184(null, null, null, null, ['expression' => 'lower(option_name)', 'direction' => 'DESC', 'collation' => 'BINARY']);

$tests = [
    'planner stat4 expression partial current source next184 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next184-ready', $plan184()['status']),
    'planner stat4 expression partial current source next184 selected current' => static fn (TestRunner $t) => $t->same('current', $plan184()['selectedSource']),
    'planner stat4 expression partial current source next184 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan184()['stalePreparedStatement']),
    'planner stat4 expression partial current source next184 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan184()['reprepareRequired']),
    'planner stat4 expression partial current source next184 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan184()['schemaCookieChanged']),
    'planner stat4 expression partial current source next184 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan184()['stat4GenerationChanged']),
    'planner stat4 expression partial current source next184 source changed' => static fn (TestRunner $t) => $t->same(true, $plan184()['sourceSignatureChanged']),
    'planner stat4 expression partial current source next184 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_partial_in_next184', $plan184()['selectedPlan']['name']),
    'planner stat4 expression partial current source next184 root page' => static fn (TestRunner $t) => $t->same(18488, $plan184()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next184 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan184()['selectedPlan']['next184Ready']),
    'planner stat4 expression partial current source next184 selected in implied' => static fn (TestRunner $t) => $t->same(true, $plan184()['selectedPlan']['next184PartialInPredicateImplied']),
    'planner stat4 expression partial current source next184 prepared in implied' => static fn (TestRunner $t) => $t->same(true, $plan184()['preparedPlan']['next184PartialInPredicateImplied']),
    'planner stat4 expression partial current source next184 current in implied' => static fn (TestRunner $t) => $t->same(true, $plan184()['currentPlan']['next184PartialInPredicateImplied']),
    'planner stat4 expression partial current source next184 matched in column' => static fn (TestRunner $t) => $t->same('column:autoload', $plan184()['selectedPlan']['next184MatchedInColumn']),
    'planner stat4 expression partial current source next184 matched in value' => static fn (TestRunner $t) => $t->same('yes', $plan184()['selectedPlan']['next184MatchedInValue']),
    'planner stat4 expression partial current source next184 candidate in count' => static fn (TestRunner $t) => $t->same(1, count($plan184()['partialInPredicate']['selected']['candidateInPredicates'])),
    'planner stat4 expression partial current source next184 candidate values' => static fn (TestRunner $t) => $t->same(['yes', 'auto-on', 'eager'], $plan184()['partialInPredicate']['selected']['candidateInPredicates'][0]['values']),
    'planner stat4 expression partial current source next184 matched where term column' => static fn (TestRunner $t) => $t->same('column:autoload', $plan184()['partialInPredicate']['selected']['matchedWhereTerm']['leftKey']),
    'planner stat4 expression partial current source next184 matched where term operator' => static fn (TestRunner $t) => $t->same('=', $plan184()['partialInPredicate']['selected']['matchedWhereTerm']['operator']),
    'planner stat4 expression partial current source next184 order satisfied' => static fn (TestRunner $t) => $t->same(true, $plan184()['orderBySatisfiedByIndex']),
    'planner stat4 expression partial current source next184 no temp sort' => static fn (TestRunner $t) => $t->same(false, $plan184()['temporarySortRequired']),
    'planner stat4 expression partial current source next184 covering' => static fn (TestRunner $t) => $t->same(true, $plan184()['selectedPlan']['covering']),
    'planner stat4 expression partial current source next184 no table lookup' => static fn (TestRunner $t) => $t->same(false, $plan184()['tableLookupRequired']),
    'planner stat4 expression partial current source next184 residual predicate retained' => static fn (TestRunner $t) => $t->same(true, $plan184()['residualPredicateRequired']),
    'planner stat4 expression partial current source next184 matched rowids asc' => static fn (TestRunner $t) => $t->same([11, 21, 41, 31], $plan184()['matchedRowids']),
    'planner stat4 expression partial current source next184 matched keys asc' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $plan184()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next184 excludes lazy no' => static fn (TestRunner $t) => $t->same(false, in_array(51, $plan184()['matchedRowids'], true)),
    'planner stat4 expression partial current source next184 excludes theme' => static fn (TestRunner $t) => $t->same(false, in_array(61, $plan184()['matchedRowids'], true)),
    'planner stat4 expression partial current source next184 excludes blog two' => static fn (TestRunner $t) => $t->same(false, in_array(71, $plan184()['matchedRowids'], true)),
    'planner stat4 expression partial current source next184 stat4 keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $plan184()['selectedPlan']['matchedStat4Keys']),
    'planner stat4 expression partial current source next184 estimated rows' => static fn (TestRunner $t) => $t->same(4, $plan184()['selectedPlan']['estimatedRows']),
    'planner stat4 expression partial current source next184 estimated cost' => static fn (TestRunner $t) => $t->same(4, $plan184()['selectedPlan']['estimatedCost']),
    'planner stat4 expression partial current source next184 prepared rowids' => static fn (TestRunner $t) => $t->same([11, 21, 31], $plan184()['preparedPlan']['matchedRowids']),
    'planner stat4 expression partial current source next184 current rowids' => static fn (TestRunner $t) => $t->same([11, 21, 41, 31], $plan184()['currentPlan']['matchedRowids']),
    'planner stat4 expression partial current source next184 current admitted' => static fn (TestRunner $t) => $t->same([41], $plan184()['currentSourceRowidsAdmittedByOrderFence']),
    'planner stat4 expression partial current source next184 fence generation' => static fn (TestRunner $t) => $t->same(54, $plan184()['stat4Fence']['stat4Generation']),
    'planner stat4 expression partial current source next184 in fence signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan184()['stat4Fence']['next184PartialInSignature'])),
    'planner stat4 expression partial current source next184 cursor open' => static fn (TestRunner $t) => $t->same('OpenRead', $plan184()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next184 cursor seek asc' => static fn (TestRunner $t) => $t->same('SeekGE', $plan184()['cursorProgram'][2]['opcode']),
    'planner stat4 expression partial current source next184 cursor partial in recheck' => static fn (TestRunner $t) => $t->same('RecheckPartialInPredicate', $plan184()['cursorProgram'][5]['opcode']),
    'planner stat4 expression partial current source next184 cursor partial and recheck retained' => static fn (TestRunner $t) => $t->same('RecheckPartialPredicate', $plan184()['cursorProgram'][4]['opcode']),
    'planner stat4 expression partial current source next184 cursor covering shifted' => static fn (TestRunner $t) => $t->same('ColumnFromIndex', $plan184()['cursorProgram'][6]['opcode']),
    'planner stat4 expression partial current source next184 cursor rowids shifted' => static fn (TestRunner $t) => $t->same([11, 21, 41, 31], $plan184()['cursorProgram'][7]['rowids']),
    'planner stat4 expression partial current source next184 fresh source' => static fn (TestRunner $t) => $t->same('prepared', $fresh184()['selectedSource']),
    'planner stat4 expression partial current source next184 fresh rowids' => static fn (TestRunner $t) => $t->same([11, 21, 41, 31], $fresh184()['matchedRowids']),
    'planner stat4 expression partial current source next184 alternate in value proves partial' => static fn (TestRunner $t) => $t->same('auto-on', $autoOn184()['selectedPlan']['next184MatchedInValue']),
    'planner stat4 expression partial current source next184 alternate in value has no rows' => static fn (TestRunner $t) => $t->same('requires-next-stage', $autoOn184()['status']),
    'planner stat4 expression partial current source next184 unproved fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $unproved184()['status']),
    'planner stat4 expression partial current source next184 unproved cursor replan' => static fn (TestRunner $t) => $t->same('Replan', $unproved184()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next184 desc order fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $desc184()['status']),
    'planner stat4 expression partial current source next184 desc requires sort' => static fn (TestRunner $t) => $t->same(true, $desc184()['temporarySortRequired']),
    'planner stat4 expression partial current source next184 detail' => static fn (TestRunner $t) => $t->contains('NEXT184 IN-PREDICATE FENCE', $plan184()['detail']),
    'planner stat4 expression partial current source next184 dependency marker' => static fn (TestRunner $t) => $t->same(['sqlite-sqlplanner-stat4-expression-partial-current-source-next184'], $plan184()['dependencies']),
    'planner stat4 expression partial current source next184 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan184()['dependency_closure']),
    'planner stat4 expression partial current source next184 non overlap' => static fn (TestRunner $t) => $t->contains('IN-predicate implication', $plan184()['non_overlap']),
    'planner stat4 expression partial current source next184 invalid empty terms' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan184(null, null, [])),
    'planner stat4 expression partial current source next184 invalid partial in source' => static function (TestRunner $t) use ($current184, $plan184): void {
        $bad = $current184();
        $bad['indexes'][0]['partialPredicateInTerms'] = ['bad'];
        $t->throws(InvalidArgumentException::class, static fn () => $plan184(null, $bad));
    },
    'planner stat4 expression partial current source next184 invalid partial in values' => static function (TestRunner $t) use ($current184, $plan184): void {
        $bad = $current184();
        $bad['indexes'][0]['partialPredicateInTerms'][0]['values'] = [];
        $t->throws(InvalidArgumentException::class, static fn () => $plan184(null, $bad));
    },
];

foreach (range(1, 10) as $case) {
    $tests['planner stat4 expression partial current source next184 repeated in fence ' . $case] = static function (TestRunner $t) use ($plan184, $case): void {
        $plan = $plan184();
        $t->same('stat4-expression-partial-current-source-next184-ready', $plan['status']);
        $t->true(count($plan['matchedRowids']) >= ($case % 4));
    };
}

return $tests;
