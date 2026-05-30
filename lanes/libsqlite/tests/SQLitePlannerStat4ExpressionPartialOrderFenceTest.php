<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq178 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull178 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$range178 = static fn (string $expression, string $operator, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => $operator, 'right' => $right];

$prepared178 = static function (array $overrides = []): array {
    return array_replace_recursive([
        'name' => 'prepared-wp-options-stat4-expression-partial-order-next178',
        'schemaCookie' => 1780,
        'stat4Generation' => 201,
        'rows' => [
            ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-old', 'updated_at' => 10],
            ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
            ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ],
        'indexes' => [[
            'name' => 'idx_wp_options_lower_plugin_desc_next178',
            'rootPage' => 17801,
            'expression' => 'lower(option_name)',
            'expressionColumn' => '__expr_lower_option_name',
            'direction' => 'DESC',
            'collation' => 'BINARY',
            'partialPredicateTerms' => [
                ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
                ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
                ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_'],
                ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<', 'right' => 'plugin`'],
            ],
            'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
            'stat4Samples' => [
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
                ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
                ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
            ],
        ]],
    ], $overrides);
};

$current178 = static function (array $overrides = []) use ($prepared178): array {
    $source = $prepared178([
        'name' => 'current-wp-options-stat4-expression-partial-order-next178',
        'schemaCookie' => 1788,
        'stat4Generation' => 219,
    ]);
    $source['indexes'][0]['rootPage'] = 17888;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
        ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
        ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 50]],
        ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 30]],
        ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '4 4', 'sample' => ['theme_mods', 60]],
    ];
    $source['rows'] = [
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail-current', 'updated_at' => 50],
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-current', 'updated_at' => 15],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-current', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-current', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods_twenty', 'option_value' => 'theme-current', 'updated_at' => 60],
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_lazy', 'option_value' => 'lazy-current', 'updated_at' => 70],
        ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_network', 'option_value' => 'network-current', 'updated_at' => 80],
        ['rowid' => 90, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-current', 'updated_at' => 90],
    ];

    return array_replace_recursive($source, $overrides);
};

$terms178 = static fn (): array => [
    $range178('LOWER( option_name )', '>=', 'plugin_'),
    $range178('lower(option_name)', '<', 'plugin_t'),
    $eq178('blog_id', 1),
    $eq178('autoload', 'yes'),
    $notNull178('option_name'),
];
$order178 = ['expression' => 'LOWER( option_name )', 'direction' => 'DESC', 'collation' => 'BINARY'];
$needed178 = ['option_name', 'option_value', 'updated_at'];
$plan178 = static fn (?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null, ?array $order = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4OrderFence(
    $prepared ?? $prepared178(),
    $current ?? $current178(),
    $terms ?? $terms178(),
    $needed ?? $needed178,
    $order ?? $order178,
);
$fresh178 = static function () use ($current178, $plan178): array {
    $source = $current178();

    return $plan178($source, $source);
};
$ascOrder178 = static fn (): array => $plan178(null, null, null, null, ['expression' => 'lower(option_name)', 'direction' => 'ASC', 'collation' => 'BINARY']);
$nonCovering178 = static function () use ($current178, $plan178): array {
    $current = $current178();
    $current['indexes'][0]['coveringColumns'] = ['option_name'];

    return $plan178(null, $current);
};
$unproved178 = static fn (): array => $plan178(null, null, [
    $range178('LOWER( option_name )', '>=', 'plugin_'),
    $range178('lower(option_name)', '<', 'plugin_t'),
    $eq178('blog_id', 1),
    $eq178('autoload', 'no'),
    $notNull178('option_name'),
]);
$noSamples178 = static function () use ($current178, $plan178): array {
    $current = $current178();
    $current['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['theme_mods', 60]],
    ];

    return $plan178(null, $current);
};

return [
    'planner stat4 expression partial current source next178 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next178-ready', $plan178()['status']),
    'planner stat4 expression partial current source next178 selects current' => static fn (TestRunner $t) => $t->same('current', $plan178()['selectedSource']),
    'planner stat4 expression partial current source next178 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan178()['stalePreparedStatement']),
    'planner stat4 expression partial current source next178 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan178()['reprepareRequired']),
    'planner stat4 expression partial current source next178 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan178()['schemaCookieChanged']),
    'planner stat4 expression partial current source next178 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan178()['stat4GenerationChanged']),
    'planner stat4 expression partial current source next178 source changed' => static fn (TestRunner $t) => $t->same(true, $plan178()['sourceSignatureChanged']),
    'planner stat4 expression partial current source next178 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_plugin_desc_next178', $plan178()['selectedPlan']['name']),
    'planner stat4 expression partial current source next178 root page current' => static fn (TestRunner $t) => $t->same(17888, $plan178()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next178 expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan178()['selectedPlan']['expression']),
    'planner stat4 expression partial current source next178 direction' => static fn (TestRunner $t) => $t->same('DESC', $plan178()['selectedPlan']['direction']),
    'planner stat4 expression partial current source next178 collation' => static fn (TestRunner $t) => $t->same('BINARY', $plan178()['selectedPlan']['collation']),
    'planner stat4 expression partial current source next178 partial implied' => static fn (TestRunner $t) => $t->same(true, $plan178()['selectedPlan']['partialPredicateImplied']),
    'planner stat4 expression partial current source next178 stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan178()['selectedPlan']['stat4Used']),
    'planner stat4 expression partial current source next178 order satisfied' => static fn (TestRunner $t) => $t->same(true, $plan178()['orderBySatisfiedByIndex']),
    'planner stat4 expression partial current source next178 no temp sort' => static fn (TestRunner $t) => $t->same(false, $plan178()['temporarySortRequired']),
    'planner stat4 expression partial current source next178 covering' => static fn (TestRunner $t) => $t->same(true, $plan178()['selectedPlan']['covering']),
    'planner stat4 expression partial current source next178 no table lookup' => static fn (TestRunner $t) => $t->same(false, $plan178()['tableLookupRequired']),
    'planner stat4 expression partial current source next178 matched rowids desc' => static fn (TestRunner $t) => $t->same([30, 50, 20, 10], $plan178()['matchedRowids']),
    'planner stat4 expression partial current source next178 matched keys desc' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_cache'], $plan178()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next178 current payload wins' => static fn (TestRunner $t) => $t->same('mail-current', $plan178()['matchedRows'][1]['payload']['option_value']),
    'planner stat4 expression partial current source next178 excludes theme' => static fn (TestRunner $t) => $t->same(false, in_array(60, $plan178()['matchedRowids'], true)),
    'planner stat4 expression partial current source next178 excludes autoload no' => static fn (TestRunner $t) => $t->same(false, in_array(70, $plan178()['matchedRowids'], true)),
    'planner stat4 expression partial current source next178 excludes blog two' => static fn (TestRunner $t) => $t->same(false, in_array(80, $plan178()['matchedRowids'], true)),
    'planner stat4 expression partial current source next178 excludes null option name' => static fn (TestRunner $t) => $t->same(false, in_array(90, $plan178()['matchedRowids'], true)),
    'planner stat4 expression partial current source next178 stat4 keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $plan178()['selectedPlan']['matchedStat4Keys']),
    'planner stat4 expression partial current source next178 stat4 rowids' => static fn (TestRunner $t) => $t->same([10, 20, 50, 30], $plan178()['selectedPlan']['matchedStat4Rowids']),
    'planner stat4 expression partial current source next178 estimated rows' => static fn (TestRunner $t) => $t->same(4, $plan178()['selectedPlan']['estimatedRows']),
    'planner stat4 expression partial current source next178 estimated cost' => static fn (TestRunner $t) => $t->same(4, $plan178()['selectedPlan']['estimatedCost']),
    'planner stat4 expression partial current source next178 prepared rowids' => static fn (TestRunner $t) => $t->same([30, 20, 10], $plan178()['preparedPlan']['matchedRowids']),
    'planner stat4 expression partial current source next178 current rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 10], $plan178()['currentPlan']['matchedRowids']),
    'planner stat4 expression partial current source next178 stale blocked empty' => static fn (TestRunner $t) => $t->same([], $plan178()['stalePreparedRowidsBlockedByOrderFence']),
    'planner stat4 expression partial current source next178 current admitted' => static fn (TestRunner $t) => $t->same([50], $plan178()['currentSourceRowidsAdmittedByOrderFence']),
    'planner stat4 expression partial current source next178 order fence changed' => static fn (TestRunner $t) => $t->same(true, $plan178()['orderFenceChanged']),
    'planner stat4 expression partial current source next178 fence cookie' => static fn (TestRunner $t) => $t->same(1788, $plan178()['stat4Fence']['schemaCookie']),
    'planner stat4 expression partial current source next178 fence generation' => static fn (TestRunner $t) => $t->same(219, $plan178()['stat4Fence']['stat4Generation']),
    'planner stat4 expression partial current source next178 fence hashes' => static fn (TestRunner $t) => $t->same([64, 64, 64, 64, 64], array_map('strlen', [$plan178()['stat4Fence']['sourceSignature'], $plan178()['stat4Fence']['rangeSignature'], $plan178()['stat4Fence']['orderSignature'], $plan178()['stat4Fence']['stat4SampleSignature'], $plan178()['stat4Fence']['rowStreamSignature']])),
    'planner stat4 expression partial current source next178 selected ready' => static fn (TestRunner $t) => $t->same(true, $plan178()['selectedPlan']['next178Ready']),
    'planner stat4 expression partial current source next178 selected signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan178()['selectedPlan']['next178OrderFenceSignature'])),
    'planner stat4 expression partial current source next178 cursor open' => static fn (TestRunner $t) => $t->same('OpenRead', $plan178()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next178 cursor direction' => static fn (TestRunner $t) => $t->same('DESC', $plan178()['cursorProgram'][0]['direction']),
    'planner stat4 expression partial current source next178 cursor fence' => static fn (TestRunner $t) => $t->same('FenceStat4Order', $plan178()['cursorProgram'][1]['opcode']),
    'planner stat4 expression partial current source next178 cursor seek desc' => static fn (TestRunner $t) => $t->same('SeekLT', $plan178()['cursorProgram'][2]['opcode']),
    'planner stat4 expression partial current source next178 cursor seek upper' => static fn (TestRunner $t) => $t->same('plugin_t', $plan178()['cursorProgram'][2]['key']),
    'planner stat4 expression partial current source next178 cursor stop desc' => static fn (TestRunner $t) => $t->same('IdxLE', $plan178()['cursorProgram'][3]['opcode']),
    'planner stat4 expression partial current source next178 cursor stop lower' => static fn (TestRunner $t) => $t->same('plugin_', $plan178()['cursorProgram'][3]['key']),
    'planner stat4 expression partial current source next178 cursor recheck' => static fn (TestRunner $t) => $t->same('RecheckPartialPredicate', $plan178()['cursorProgram'][4]['opcode']),
    'planner stat4 expression partial current source next178 cursor covering' => static fn (TestRunner $t) => $t->same('ColumnFromIndex', $plan178()['cursorProgram'][5]['opcode']),
    'planner stat4 expression partial current source next178 cursor result rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 10], $plan178()['cursorProgram'][6]['rowids']),
    'planner stat4 expression partial current source next178 cursor prev' => static fn (TestRunner $t) => $t->same('Prev', $plan178()['cursorProgram'][7]['opcode']),
    'planner stat4 expression partial current source next178 fresh source' => static fn (TestRunner $t) => $t->same('prepared', $fresh178()['selectedSource']),
    'planner stat4 expression partial current source next178 fresh no reprepare' => static fn (TestRunner $t) => $t->same(false, $fresh178()['reprepareRequired']),
    'planner stat4 expression partial current source next178 fresh rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 10], $fresh178()['matchedRowids']),
    'planner stat4 expression partial current source next178 asc order fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $ascOrder178()['status']),
    'planner stat4 expression partial current source next178 asc order requires sort' => static fn (TestRunner $t) => $t->same(true, $ascOrder178()['temporarySortRequired']),
    'planner stat4 expression partial current source next178 asc order replan opcode' => static fn (TestRunner $t) => $t->same('Replan', $ascOrder178()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next178 noncovering ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next178-ready', $nonCovering178()['status']),
    'planner stat4 expression partial current source next178 noncovering table lookup' => static fn (TestRunner $t) => $t->same(true, $nonCovering178()['tableLookupRequired']),
    'planner stat4 expression partial current source next178 noncovering deferred seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $nonCovering178()['cursorProgram'][5]['opcode']),
    'planner stat4 expression partial current source next178 unproved fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $unproved178()['status']),
    'planner stat4 expression partial current source next178 no samples fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $noSamples178()['status']),
    'planner stat4 expression partial current source next178 detail' => static fn (TestRunner $t) => $t->contains('NEXT178 ORDER FENCE', $plan178()['detail']),
    'planner stat4 expression partial current source next178 dependency marker' => static fn (TestRunner $t) => $t->same(['sqlite-sqlplanner-stat4-expression-partial-current-source-next178'], $plan178()['dependencies']),
    'planner stat4 expression partial current source next178 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan178()['dependency_closure']),
    'planner stat4 expression partial current source next178 non overlap' => static fn (TestRunner $t) => $t->contains('current-source ORDER fence', $plan178()['non_overlap']),
    'planner stat4 expression partial current source next178 invalid empty terms' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan178(null, null, [])),
    'planner stat4 expression partial current source next178 invalid needed columns' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan178(null, null, null, [])),
    'planner stat4 expression partial current source next178 invalid direction' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan178(null, null, null, null, ['expression' => 'lower(option_name)', 'direction' => 'SIDEWAYS'])),
    'planner stat4 expression partial current source next178 invalid rowid' => static function (TestRunner $t) use ($current178, $plan178): void {
        $bad = $current178();
        $bad['rows'][0]['rowid'] = -1;
        $t->throws(InvalidArgumentException::class, static fn () => $plan178(null, $bad));
    },
    'planner stat4 expression partial current source next178 invalid sample' => static function (TestRunner $t) use ($current178, $plan178): void {
        $bad = $current178();
        $bad['indexes'][0]['stat4Samples'][0]['sample'] = ['plugin_cache'];
        $t->throws(InvalidArgumentException::class, static fn () => $plan178(null, $bad));
    },
    'planner stat4 expression partial current source next178 invalid stat4 integer' => static function (TestRunner $t) use ($current178, $plan178): void {
        $bad = $current178();
        $bad['indexes'][0]['stat4Samples'][0]['neq'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan178(null, $bad));
    },
];
