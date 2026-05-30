<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq175 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull175 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$exprLike175 = static fn (string $expression, string $pattern, string $escape = '\\'): array => ['left' => ['expression' => $expression], 'operator' => 'LIKE', 'right' => $pattern, 'escape' => $escape];
$exprRange175 = static fn (string $expression, string $operator, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => $operator, 'right' => $right];

$prepared175 = static function (array $overrides = []): array {
    return array_replace_recursive([
        'name' => 'prepared-wp-options-stat4-like-prefix-next175',
        'schemaCookie' => 1750,
        'stat4Generation' => 110,
        'rows' => [
            ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-old'],
            ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old'],
            ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_old', 'option_value' => 'old-old'],
            ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods_twenty', 'option_value' => 'theme-old'],
        ],
        'indexes' => [[
            'name' => 'idx_wp_options_lower_like_prefix_next175',
            'rootPage' => 17501,
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
                ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
                ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_old', 30]],
                ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['theme_mods_twenty', 40]],
            ],
        ]],
    ], $overrides);
};

$current175 = static function (array $overrides = []) use ($prepared175): array {
    $source = $prepared175([
        'name' => 'current-wp-options-stat4-like-prefix-next175',
        'schemaCookie' => 1759,
        'stat4Generation' => 127,
    ]);
    $source['indexes'][0]['rootPage'] = 17577;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['mu_plugin_loader', 5]],
        ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 10]],
        ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 20]],
        ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_search', 50]],
        ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 60]],
        ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '5 5', 'sample' => ['theme_mods_twenty', 70]],
    ];
    $source['rows'] = [
        ['rowid' => 5, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'mu_plugin_loader', 'option_value' => 'mu-current'],
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-current'],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-current'],
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_search', 'option_value' => 'search-current'],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-current'],
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods_twenty', 'option_value' => 'theme-current'],
        ['rowid' => 80, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_lazy', 'option_value' => 'lazy-current'],
        ['rowid' => 90, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_network', 'option_value' => 'network-current'],
        ['rowid' => 100, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-current'],
    ];

    return array_replace_recursive($source, $overrides);
};

$terms175 = static fn (): array => [
    $exprLike175('LOWER( option_name )', 'plugin\_%'),
    $eq175('blog_id', 1),
    $eq175('autoload', 'yes'),
    $notNull175('option_name'),
    $exprRange175('lower(option_name)', '>=', 'plugin_'),
    $exprRange175('lower(option_name)', '<', 'plugin`'),
];
$needed175 = ['option_name', 'option_value'];
$plan175 = static fn (?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeLikePrefixWindowFence(
    $prepared ?? $prepared175(),
    $current ?? $current175(),
    $terms ?? $terms175(),
    $needed ?? $needed175,
);
$fresh175 = static function () use ($current175, $plan175): array {
    $source = $current175();

    return $plan175($source, $source);
};
$unproved175 = static fn (): array => $plan175(null, null, [
    $exprLike175('lower(option_name)', 'plugin\_%'),
    $eq175('blog_id', 1),
    $eq175('autoload', 'no'),
    $notNull175('option_name'),
    $exprRange175('lower(option_name)', '>=', 'plugin_'),
    $exprRange175('lower(option_name)', '<', 'plugin`'),
]);
$noWindow175 = static function () use ($current175, $plan175, $terms175): array {
    $current = $current175();
    $current['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['theme_mods_twenty', 70]],
    ];

    return $plan175(null, $current, $terms175());
};
$nonCovering175 = static function () use ($current175, $plan175): array {
    $current = $current175();
    $current['indexes'][0]['coveringColumns'] = ['option_name'];

    return $plan175(null, $current);
};

return [
    'planner stat4 expression partial current source next175 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next175-ready', $plan175()['status']),
    'planner stat4 expression partial current source next175 selects current' => static fn (TestRunner $t) => $t->same('current', $plan175()['selectedSource']),
    'planner stat4 expression partial current source next175 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan175()['stalePreparedStatement']),
    'planner stat4 expression partial current source next175 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan175()['reprepareRequired']),
    'planner stat4 expression partial current source next175 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan175()['schemaCookieChanged']),
    'planner stat4 expression partial current source next175 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan175()['stat4GenerationChanged']),
    'planner stat4 expression partial current source next175 source signature changed' => static fn (TestRunner $t) => $t->same(true, $plan175()['sourceSignatureChanged']),
    'planner stat4 expression partial current source next175 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_like_prefix_next175', $plan175()['selectedPlan']['name']),
    'planner stat4 expression partial current source next175 root page' => static fn (TestRunner $t) => $t->same(17577, $plan175()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next175 expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan175()['selectedPlan']['expression']),
    'planner stat4 expression partial current source next175 expression column' => static fn (TestRunner $t) => $t->same('__expr_lower_option_name', $plan175()['selectedPlan']['expressionColumn']),
    'planner stat4 expression partial current source next175 covering' => static fn (TestRunner $t) => $t->same(true, $plan175()['selectedPlan']['covering']),
    'planner stat4 expression partial current source next175 table lookup elided' => static fn (TestRunner $t) => $t->same(false, $plan175()['tableLookupRequired']),
    'planner stat4 expression partial current source next175 partial implied' => static fn (TestRunner $t) => $t->same(true, $plan175()['selectedPlan']['partialPredicateImplied']),
    'planner stat4 expression partial current source next175 stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan175()['selectedPlan']['stat4Used']),
    'planner stat4 expression partial current source next175 prefix' => static fn (TestRunner $t) => $t->same('plugin_', $plan175()['prefix']),
    'planner stat4 expression partial current source next175 upper bound' => static fn (TestRunner $t) => $t->same('plugin`', $plan175()['prefixUpperBound']),
    'planner stat4 expression partial current source next175 window lower' => static fn (TestRunner $t) => $t->same('plugin_', $plan175()['stat4PrefixWindow']['lower']),
    'planner stat4 expression partial current source next175 window upper' => static fn (TestRunner $t) => $t->same('plugin`', $plan175()['stat4PrefixWindow']['upper']),
    'planner stat4 expression partial current source next175 window rowids' => static fn (TestRunner $t) => $t->same([10, 20, 50, 60], $plan175()['stat4PrefixWindow']['rowids']),
    'planner stat4 expression partial current source next175 window keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_search', 'plugin_seo'], $plan175()['stat4PrefixWindow']['keys']),
    'planner stat4 expression partial current source next175 window changed' => static fn (TestRunner $t) => $t->same(true, $plan175()['stat4PrefixWindowChanged']),
    'planner stat4 expression partial current source next175 matched rowids' => static fn (TestRunner $t) => $t->same([10, 20, 50, 60], $plan175()['matchedRowids']),
    'planner stat4 expression partial current source next175 matched keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_search', 'plugin_seo'], $plan175()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next175 current payload wins' => static fn (TestRunner $t) => $t->same('search-current', $plan175()['matchedRows'][2]['payload']['option_value']),
    'planner stat4 expression partial current source next175 excludes mu prefix' => static fn (TestRunner $t) => $t->same(false, in_array(5, $plan175()['matchedRowids'], true)),
    'planner stat4 expression partial current source next175 excludes theme prefix' => static fn (TestRunner $t) => $t->same(false, in_array(70, $plan175()['matchedRowids'], true)),
    'planner stat4 expression partial current source next175 excludes autoload no' => static fn (TestRunner $t) => $t->same(false, in_array(80, $plan175()['matchedRowids'], true)),
    'planner stat4 expression partial current source next175 excludes blog two' => static fn (TestRunner $t) => $t->same(false, in_array(90, $plan175()['matchedRowids'], true)),
    'planner stat4 expression partial current source next175 excludes null name' => static fn (TestRunner $t) => $t->same(false, in_array(100, $plan175()['matchedRowids'], true)),
    'planner stat4 expression partial current source next175 stale blocked' => static fn (TestRunner $t) => $t->same([30], $plan175()['stalePreparedRowidsBlockedByPrefixWindow']),
    'planner stat4 expression partial current source next175 current admitted' => static fn (TestRunner $t) => $t->same([50, 60], $plan175()['currentSourceRowidsAdmittedByPrefixWindow']),
    'planner stat4 expression partial current source next175 estimated rows' => static fn (TestRunner $t) => $t->same(4, $plan175()['selectedPlan']['estimatedRows']),
    'planner stat4 expression partial current source next175 estimated cost' => static fn (TestRunner $t) => $t->same(4, $plan175()['selectedPlan']['estimatedCost']),
    'planner stat4 expression partial current source next175 prepared summary usable' => static fn (TestRunner $t) => $t->same(true, $plan175()['preparedPlan']['usable']),
    'planner stat4 expression partial current source next175 current summary usable' => static fn (TestRunner $t) => $t->same(true, $plan175()['currentPlan']['usable']),
    'planner stat4 expression partial current source next175 prepared rowids' => static fn (TestRunner $t) => $t->same([10, 20, 30], $plan175()['preparedPlan']['matchedRowids']),
    'planner stat4 expression partial current source next175 current rowids' => static fn (TestRunner $t) => $t->same([10, 20, 50, 60], $plan175()['currentPlan']['matchedRowids']),
    'planner stat4 expression partial current source next175 cursor open' => static fn (TestRunner $t) => $t->same('OpenRead', $plan175()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next175 cursor fence' => static fn (TestRunner $t) => $t->same('FenceStat4PrefixWindow', $plan175()['cursorProgram'][1]['opcode']),
    'planner stat4 expression partial current source next175 cursor seek' => static fn (TestRunner $t) => $t->same('SeekGE', $plan175()['cursorProgram'][2]['opcode']),
    'planner stat4 expression partial current source next175 cursor seek key' => static fn (TestRunner $t) => $t->same('plugin_', $plan175()['cursorProgram'][2]['key']),
    'planner stat4 expression partial current source next175 cursor stop' => static fn (TestRunner $t) => $t->same('IdxGE', $plan175()['cursorProgram'][3]['opcode']),
    'planner stat4 expression partial current source next175 cursor stop key' => static fn (TestRunner $t) => $t->same('plugin`', $plan175()['cursorProgram'][3]['key']),
    'planner stat4 expression partial current source next175 cursor residual like' => static fn (TestRunner $t) => $t->same('ResidualLikePrefix', $plan175()['cursorProgram'][4]['opcode']),
    'planner stat4 expression partial current source next175 cursor covering' => static fn (TestRunner $t) => $t->same('ColumnFromIndex', $plan175()['cursorProgram'][5]['opcode']),
    'planner stat4 expression partial current source next175 cursor result' => static fn (TestRunner $t) => $t->same([10, 20, 50, 60], $plan175()['cursorProgram'][6]['rowids']),
    'planner stat4 expression partial current source next175 cursor next' => static fn (TestRunner $t) => $t->same('Next', $plan175()['cursorProgram'][7]['opcode']),
    'planner stat4 expression partial current source next175 fence cookie' => static fn (TestRunner $t) => $t->same(1759, $plan175()['stat4Fence']['schemaCookie']),
    'planner stat4 expression partial current source next175 fence stat4 generation' => static fn (TestRunner $t) => $t->same(127, $plan175()['stat4Fence']['stat4Generation']),
    'planner stat4 expression partial current source next175 fence expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan175()['stat4Fence']['expressionSignature']),
    'planner stat4 expression partial current source next175 fence hashes' => static fn (TestRunner $t) => $t->same([64, 64, 64, 64, 64], array_map('strlen', [$plan175()['stat4Fence']['sourceSignature'], $plan175()['stat4Fence']['partialPredicateSignature'], $plan175()['stat4Fence']['stat4SampleSignature'], $plan175()['stat4Fence']['stat4PrefixWindowSignature'], $plan175()['stat4Fence']['rowStreamSignature']])),
    'planner stat4 expression partial current source next175 fresh source' => static fn (TestRunner $t) => $t->same('prepared', $fresh175()['selectedSource']),
    'planner stat4 expression partial current source next175 fresh ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next175-ready', $fresh175()['status']),
    'planner stat4 expression partial current source next175 unproved fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $unproved175()['status']),
    'planner stat4 expression partial current source next175 no window fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $noWindow175()['status']),
    'planner stat4 expression partial current source next175 noncovering ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next175-ready', $nonCovering175()['status']),
    'planner stat4 expression partial current source next175 noncovering deferred seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $nonCovering175()['cursorProgram'][5]['opcode']),
    'planner stat4 expression partial current source next175 detail' => static fn (TestRunner $t) => $t->contains('NEXT175 LIKE PREFIX WINDOW', $plan175()['detail']),
    'planner stat4 expression partial current source next175 dependency marker' => static fn (TestRunner $t) => $t->same(['sqlite-sqlplanner-stat4-expression-partial-current-source-next175'], $plan175()['dependencies']),
    'planner stat4 expression partial current source next175 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan175()['dependency_closure']),
    'planner stat4 expression partial current source next175 non overlap' => static fn (TestRunner $t) => $t->contains('LIKE prefix window', $plan175()['non_overlap']),
    'planner stat4 expression partial current source next175 invalid empty terms' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan175(null, null, [])),
    'planner stat4 expression partial current source next175 invalid needed columns' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan175(null, null, null, [])),
    'planner stat4 expression partial current source next175 invalid rowid' => static function (TestRunner $t) use ($current175, $plan175): void {
        $bad = $current175();
        $bad['rows'][0]['rowid'] = -1;
        $t->throws(InvalidArgumentException::class, static fn () => $plan175(null, $bad));
    },
    'planner stat4 expression partial current source next175 invalid stat4 sample' => static function (TestRunner $t) use ($current175, $plan175): void {
        $bad = $current175();
        $bad['indexes'][0]['stat4Samples'][0]['sample'] = ['plugin_cache'];
        $t->throws(InvalidArgumentException::class, static fn () => $plan175(null, $bad));
    },
    'planner stat4 expression partial current source next175 invalid escape' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan175(null, null, [
        $exprLike175('lower(option_name)', 'plugin!!_%', '!!'),
        $eq175('blog_id', 1),
        $eq175('autoload', 'yes'),
        $notNull175('option_name'),
    ])),
];
