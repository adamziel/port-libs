<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq171 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull171 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$exprEq171 = static fn (string $expression, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => '=', 'right' => $right];
$exprGt171 = static fn (string $expression, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => '>', 'right' => $right];

$prepared171 = static function (array $overrides = []): array {
    return array_replace_recursive([
        'name' => 'prepared-wp-options-stat4-unsampled-next171',
        'schemaCookie' => 1710,
        'stat4Generation' => 40,
        'rows' => [
            ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-stale'],
            ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-stale'],
            ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_old', 'option_value' => 'old-stale'],
            ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-stale'],
        ],
        'indexes' => [[
            'name' => 'idx_wp_options_lower_unsampled_next171',
            'rootPage' => 17101,
            'expression' => 'lower(option_name)',
            'expressionColumn' => '__expr_lower_option_name',
            'collation' => 'BINARY',
            'partialPredicateTerms' => [
                ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
                ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
                ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>', 'right' => 'plugin_'],
            ],
            'coveringColumns' => ['option_name', 'option_value', 'autoload', 'blog_id'],
            'stat4Samples' => [
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
                ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
                ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_old', 30]],
                ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 40]],
            ],
        ]],
    ], $overrides);
};

$current171 = static function (array $overrides = []) use ($prepared171): array {
    $source = $prepared171([
        'name' => 'current-wp-options-stat4-unsampled-next171',
        'schemaCookie' => 1717,
        'stat4Generation' => 49,
    ]);
    $source['indexes'][0]['rootPage'] = 17177;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '2 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
        ['neq' => '1 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
        ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_security', 50]],
        ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 40]],
    ];
    $source['rows'] = [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-current'],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-current'],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Search', 'option_value' => 'search-current'],
        ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-current'],
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_search', 'option_value' => 'search-lazy'],
        ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_search', 'option_value' => 'network-search'],
        ['rowid' => 90, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name'],
    ];

    return array_replace_recursive($source, $overrides);
};

$terms171 = static fn (): array => [
    $exprEq171('LOWER( option_name )', 'plugin_search'),
    $eq171('blog_id', 1),
    $eq171('autoload', 'yes'),
    $notNull171('option_name'),
    $exprGt171('lower(option_name)', 'plugin_'),
];
$needed171 = ['option_name', 'option_value'];
$plan171 = static fn (?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext171(
    $prepared ?? $prepared171(),
    $current ?? $current171(),
    $terms ?? $terms171(),
    $needed ?? $needed171,
);
$fresh171 = static function () use ($prepared171, $plan171): array {
    $source = $prepared171();
    $source['rows'][] = ['rowid' => 55, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_search', 'option_value' => 'search-prepared'];

    return $plan171($source, $source);
};
$exactSample171 = static function () use ($current171, $plan171): array {
    $current = $current171();
    $current['indexes'][0]['stat4Samples'][] = ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '4 4', 'sample' => ['plugin_search', 60]];

    return $plan171(null, $current);
};
$unproved171 = static fn (): array => $plan171(null, null, [
    $exprEq171('lower(option_name)', 'plugin_search'),
    $eq171('blog_id', 1),
    $eq171('autoload', 'no'),
    $notNull171('option_name'),
    $exprGt171('lower(option_name)', 'plugin_'),
]);
$nonCovering171 = static function () use ($current171, $plan171): array {
    $current = $current171();
    $current['indexes'][0]['coveringColumns'] = ['option_name'];

    return $plan171(null, $current);
};
$beforeFirst171 = static fn (): array => $plan171(null, null, [
    $exprEq171('lower(option_name)', 'plugin_alpha'),
    $eq171('blog_id', 1),
    $eq171('autoload', 'yes'),
    $notNull171('option_name'),
    $exprGt171('lower(option_name)', 'plugin_'),
]);

return [
    'planner stat4 expression partial current source next171 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next171-ready', $plan171()['status']),
    'planner stat4 expression partial current source next171 selects current' => static fn (TestRunner $t) => $t->same('current', $plan171()['selectedSource']),
    'planner stat4 expression partial current source next171 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan171()['stalePreparedStatement']),
    'planner stat4 expression partial current source next171 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan171()['reprepareRequired']),
    'planner stat4 expression partial current source next171 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan171()['schemaCookieChanged']),
    'planner stat4 expression partial current source next171 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan171()['stat4GenerationChanged']),
    'planner stat4 expression partial current source next171 source signature changed' => static fn (TestRunner $t) => $t->same(true, $plan171()['sourceSignatureChanged']),
    'planner stat4 expression partial current source next171 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_unsampled_next171', $plan171()['selectedPlan']['name']),
    'planner stat4 expression partial current source next171 root page' => static fn (TestRunner $t) => $t->same(17177, $plan171()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next171 expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan171()['selectedPlan']['expression']),
    'planner stat4 expression partial current source next171 expression column' => static fn (TestRunner $t) => $t->same('__expr_lower_option_name', $plan171()['selectedPlan']['expressionColumn']),
    'planner stat4 expression partial current source next171 covering' => static fn (TestRunner $t) => $t->same(true, $plan171()['selectedPlan']['covering']),
    'planner stat4 expression partial current source next171 table lookup elided' => static fn (TestRunner $t) => $t->same(false, $plan171()['tableLookupRequired']),
    'planner stat4 expression partial current source next171 partial implied' => static fn (TestRunner $t) => $t->same(true, $plan171()['selectedPlan']['partialPredicateImplied']),
    'planner stat4 expression partial current source next171 stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan171()['selectedPlan']['stat4Used']),
    'planner stat4 expression partial current source next171 exact sample absent' => static fn (TestRunner $t) => $t->same(false, $plan171()['selectedPlan']['exactStat4SamplePresent']),
    'planner stat4 expression partial current source next171 unsampled key' => static fn (TestRunner $t) => $t->same('plugin_search', $plan171()['unsampledEqualityKey']),
    'planner stat4 expression partial current source next171 bracket kind' => static fn (TestRunner $t) => $t->same('between-samples', $plan171()['stat4Bracket']['kind']),
    'planner stat4 expression partial current source next171 bracket left' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan171()['stat4Bracket']['left']['key']),
    'planner stat4 expression partial current source next171 bracket right' => static fn (TestRunner $t) => $t->same('plugin_security', $plan171()['stat4Bracket']['right']['key']),
    'planner stat4 expression partial current source next171 bracket rowids' => static fn (TestRunner $t) => $t->same([20, 50], [$plan171()['stat4Bracket']['left']['rowid'], $plan171()['stat4Bracket']['right']['rowid']]),
    'planner stat4 expression partial current source next171 bracket changed' => static fn (TestRunner $t) => $t->same(true, $plan171()['stat4BracketChanged']),
    'planner stat4 expression partial current source next171 matched rowids' => static fn (TestRunner $t) => $t->same([60], $plan171()['matchedRowids']),
    'planner stat4 expression partial current source next171 matched expression keys' => static fn (TestRunner $t) => $t->same(['plugin_search'], $plan171()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next171 current payload wins' => static fn (TestRunner $t) => $t->same('search-current', $plan171()['matchedRows'][0]['payload']['option_value']),
    'planner stat4 expression partial current source next171 excludes autoload no' => static fn (TestRunner $t) => $t->same(false, in_array(70, $plan171()['matchedRowids'], true)),
    'planner stat4 expression partial current source next171 excludes blog two' => static fn (TestRunner $t) => $t->same(false, in_array(80, $plan171()['matchedRowids'], true)),
    'planner stat4 expression partial current source next171 excludes null name' => static fn (TestRunner $t) => $t->same(false, in_array(90, $plan171()['matchedRowids'], true)),
    'planner stat4 expression partial current source next171 stale rowids blocked' => static fn (TestRunner $t) => $t->same([], $plan171()['stalePreparedRowidsBlockedByBracket']),
    'planner stat4 expression partial current source next171 current rowids admitted' => static fn (TestRunner $t) => $t->same([60], $plan171()['currentSourceRowidsAdmittedByBracket']),
    'planner stat4 expression partial current source next171 estimated rows' => static fn (TestRunner $t) => $t->same(1, $plan171()['selectedPlan']['estimatedRows']),
    'planner stat4 expression partial current source next171 estimated cost' => static fn (TestRunner $t) => $t->same(1, $plan171()['selectedPlan']['estimatedCost']),
    'planner stat4 expression partial current source next171 prepared summary unusable' => static fn (TestRunner $t) => $t->same(false, $plan171()['preparedPlan']['usable']),
    'planner stat4 expression partial current source next171 current summary usable' => static fn (TestRunner $t) => $t->same(true, $plan171()['currentPlan']['usable']),
    'planner stat4 expression partial current source next171 summary admitted row' => static fn (TestRunner $t) => $t->same([60], $plan171()['currentPlan']['matchedRowids']),
    'planner stat4 expression partial current source next171 cursor open' => static fn (TestRunner $t) => $t->same('OpenRead', $plan171()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next171 cursor fence' => static fn (TestRunner $t) => $t->same('FenceStat4Bracket', $plan171()['cursorProgram'][1]['opcode']),
    'planner stat4 expression partial current source next171 cursor lower' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan171()['cursorProgram'][2]['key']),
    'planner stat4 expression partial current source next171 cursor upper' => static fn (TestRunner $t) => $t->same('plugin_security', $plan171()['cursorProgram'][3]['key']),
    'planner stat4 expression partial current source next171 cursor probe key' => static fn (TestRunner $t) => $t->same('plugin_search', $plan171()['cursorProgram'][4]['key']),
    'planner stat4 expression partial current source next171 cursor covering' => static fn (TestRunner $t) => $t->same('ColumnFromIndex', $plan171()['cursorProgram'][5]['opcode']),
    'planner stat4 expression partial current source next171 cursor result' => static fn (TestRunner $t) => $t->same([60], $plan171()['cursorProgram'][6]['rowids']),
    'planner stat4 expression partial current source next171 cursor next' => static fn (TestRunner $t) => $t->same('Next', $plan171()['cursorProgram'][7]['opcode']),
    'planner stat4 expression partial current source next171 fence cookie' => static fn (TestRunner $t) => $t->same(1717, $plan171()['stat4Fence']['schemaCookie']),
    'planner stat4 expression partial current source next171 fence stat4 generation' => static fn (TestRunner $t) => $t->same(49, $plan171()['stat4Fence']['stat4Generation']),
    'planner stat4 expression partial current source next171 fence expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan171()['stat4Fence']['expressionSignature']),
    'planner stat4 expression partial current source next171 fence hashes' => static fn (TestRunner $t) => $t->same([64, 64, 64, 64, 64], array_map('strlen', [$plan171()['stat4Fence']['sourceSignature'], $plan171()['stat4Fence']['partialPredicateSignature'], $plan171()['stat4Fence']['stat4SampleSignature'], $plan171()['stat4Fence']['stat4BracketSignature'], $plan171()['stat4Fence']['rowStreamSignature']])),
    'planner stat4 expression partial current source next171 detail' => static fn (TestRunner $t) => $t->contains('NEXT171 UNSAMPLED EQUALITY BRACKET', $plan171()['detail']),
    'planner stat4 expression partial current source next171 dependency marker' => static fn (TestRunner $t) => $t->same(['sqlite-sqlplanner-stat4-expression-partial-current-source-next171'], $plan171()['dependencies']),
    'planner stat4 expression partial current source next171 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan171()['dependency_closure']),
    'planner stat4 expression partial current source next171 non overlap' => static fn (TestRunner $t) => $t->contains('unsampled equality key', $plan171()['non_overlap']),
    'planner stat4 expression partial current source next171 fresh prepared ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next171-ready', $fresh171()['status']),
    'planner stat4 expression partial current source next171 fresh source' => static fn (TestRunner $t) => $t->same('prepared', $fresh171()['selectedSource']),
    'planner stat4 expression partial current source next171 fresh rowid' => static fn (TestRunner $t) => $t->same([55], $fresh171()['matchedRowids']),
    'planner stat4 expression partial current source next171 exact sample fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $exactSample171()['status']),
    'planner stat4 expression partial current source next171 unproved fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $unproved171()['status']),
    'planner stat4 expression partial current source next171 noncovering ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next171-ready', $nonCovering171()['status']),
    'planner stat4 expression partial current source next171 noncovering deferred seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $nonCovering171()['cursorProgram'][5]['opcode']),
    'planner stat4 expression partial current source next171 before first fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $beforeFirst171()['status']),
    'planner stat4 expression partial current source next171 invalid empty terms' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan171(null, null, [])),
    'planner stat4 expression partial current source next171 invalid needed columns' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan171(null, null, null, [])),
    'planner stat4 expression partial current source next171 invalid rowid' => static function (TestRunner $t) use ($current171, $plan171): void {
        $bad = $current171();
        $bad['rows'][0]['rowid'] = -1;
        $t->throws(InvalidArgumentException::class, static fn () => $plan171(null, $bad));
    },
    'planner stat4 expression partial current source next171 invalid stat4 sample' => static function (TestRunner $t) use ($current171, $plan171): void {
        $bad = $current171();
        $bad['indexes'][0]['stat4Samples'][0]['sample'] = ['plugin_cache'];
        $t->throws(InvalidArgumentException::class, static fn () => $plan171(null, $bad));
    },
];
