<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $expression, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['expression' => $expression], 'right' => $value];
$between = static fn (string $expression, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['expression' => $expression], 'lower' => $lower, 'upper' => $upper];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];
$or = static fn (array ...$terms): array => ['operator' => 'OR', 'terms' => $terms];

$prepared172 = static fn (array $overrides = []): array => $overrides + [
    'name' => 'prepared-wp-options-stat4-expression-partial-next172',
    'schemaCookie' => 1720,
    'stat4Generation' => 50,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_plugin_partial_next172_old',
        'rootPage' => 17201,
        'expression' => 'lower(option_name)',
        'partialPredicate' => $or($eq('autoload', 'yes'), $eq('blog_id', 0)),
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['home', 1]],
            ['neq' => '2 2', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_alpha', 2]],
            ['neq' => '2 2', 'nlt' => '3 3', 'ndlt' => '2 2', 'sample' => ['plugin_cache', 3]],
            ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['siteurl', 4]],
        ],
    ]],
    'rows' => [
        ['rowid' => 1, 'option_name' => 'Home', 'autoload' => 'yes', 'blog_id' => 1],
        ['rowid' => 2, 'option_name' => 'Plugin_Alpha', 'autoload' => 'yes', 'blog_id' => 1],
        ['rowid' => 3, 'option_name' => 'Plugin_Cache', 'autoload' => 'yes', 'blog_id' => 1],
        ['rowid' => 4, 'option_name' => 'SiteURL', 'autoload' => 'yes', 'blog_id' => 1],
    ],
];

$current172 = static function () use ($prepared172, $or, $eq): array {
    $source = $prepared172([
        'name' => 'current-wp-options-stat4-expression-partial-next172',
        'schemaCookie' => 1724,
        'stat4Generation' => 57,
    ]);
    $source['indexes'][0]['name'] = 'idx_wp_options_lower_plugin_partial_next172_current';
    $source['indexes'][0]['rootPage'] = 17211;
    $source['indexes'][0]['partialPredicate'] = $or($eq('autoload', 'yes'), $eq('blog_id', 0));
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['home', 10]],
        ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['network_plugins', 11]],
        ['neq' => '2 2', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_alpha', 12]],
        ['neq' => '3 3', 'nlt' => '4 4', 'ndlt' => '3 3', 'sample' => ['plugin_cache', 13]],
        ['neq' => '1 1', 'nlt' => '7 7', 'ndlt' => '4 4', 'sample' => ['plugin_forms', 14]],
        ['neq' => '1 1', 'nlt' => '8 8', 'ndlt' => '5 5', 'sample' => ['plugin_security', 15]],
        ['neq' => '1 1', 'nlt' => '9 9', 'ndlt' => '6 6', 'sample' => ['siteurl', 16]],
    ];
    $source['rows'] = [
        ['rowid' => 10, 'option_name' => 'Home', 'autoload' => 'yes', 'blog_id' => 1],
        ['rowid' => 11, 'option_name' => 'Network_Plugins', 'autoload' => 'no', 'blog_id' => 0],
        ['rowid' => 12, 'option_name' => 'Plugin_Alpha', 'autoload' => 'yes', 'blog_id' => 1],
        ['rowid' => 13, 'option_name' => 'Plugin_Cache', 'autoload' => 'yes', 'blog_id' => 1],
        ['rowid' => 14, 'option_name' => 'Plugin_Cache_2', 'autoload' => 'yes', 'blog_id' => 1],
        ['rowid' => 15, 'option_name' => 'Plugin_Forms', 'autoload' => 'yes', 'blog_id' => 1],
        ['rowid' => 16, 'option_name' => 'Plugin_Security', 'autoload' => 'yes', 'blog_id' => 1],
        ['rowid' => 17, 'option_name' => 'SiteURL', 'autoload' => 'yes', 'blog_id' => 1],
        ['rowid' => 18, 'option_name' => 'Plugin_Trash', 'autoload' => 'no', 'blog_id' => 1],
    ];

    return $source;
};

$predicate172 = $and(
    $eq('autoload', 'yes'),
    $range('lower(option_name)', '>=', 'plugin_'),
    $range('lower(option_name)', '<', 'plugin_t'),
);
$plan172 = static fn (
    ?array $prepared = null,
    ?array $current = null,
    ?array $predicate = null,
): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext172(
    $prepared ?? $prepared172(),
    $current ?? $current172(),
    $predicate ?? $GLOBALS['predicate_next172'],
);
$GLOBALS['predicate_next172'] = $predicate172;

$tests = [
    'planner stat4 expression partial current source next172 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next172-ready', $plan172()['status']),
    'planner stat4 expression partial current source next172 selects current' => static fn (TestRunner $t) => $t->same('current', $plan172()['selectedSource']),
    'planner stat4 expression partial current source next172 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan172()['stalePreparedStatement']),
    'planner stat4 expression partial current source next172 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan172()['reprepareRequired']),
    'planner stat4 expression partial current source next172 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan172()['schemaCookieChanged']),
    'planner stat4 expression partial current source next172 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan172()['stat4GenerationChanged']),
    'planner stat4 expression partial current source next172 source signature changed' => static fn (TestRunner $t) => $t->same(true, $plan172()['sourceSignatureChanged']),
    'planner stat4 expression partial current source next172 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_plugin_partial_next172_current', $plan172()['selectedPlan']['indexName']),
    'planner stat4 expression partial current source next172 selected root page' => static fn (TestRunner $t) => $t->same(17211, $plan172()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next172 expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan172()['selectedPlan']['expression']),
    'planner stat4 expression partial current source next172 partial implied' => static fn (TestRunner $t) => $t->same(true, $plan172()['selectedPlan']['partialPredicateImplied']),
    'planner stat4 expression partial current source next172 stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan172()['selectedPlan']['stat4Used']),
    'planner stat4 expression partial current source next172 current rows filtered' => static fn (TestRunner $t) => $t->same(true, $plan172()['selectedPlan']['currentSourceRowsFiltered']),
    'planner stat4 expression partial current source next172 sample count' => static fn (TestRunner $t) => $t->same(7, $plan172()['selectedPlan']['stat4SampleCount']),
    'planner stat4 expression partial current source next172 matched sample count' => static fn (TestRunner $t) => $t->same(4, $plan172()['selectedPlan']['matchedStat4SampleCount']),
    'planner stat4 expression partial current source next172 matched stat4 keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_security'], $plan172()['selectedPlan']['matchedStat4Keys']),
    'planner stat4 expression partial current source next172 matched row count' => static fn (TestRunner $t) => $t->same(5, $plan172()['selectedPlan']['matchedRowCount']),
    'planner stat4 expression partial current source next172 matched rowids' => static fn (TestRunner $t) => $t->same([12, 13, 14, 15, 16], $plan172()['selectedPlan']['matchedRowids']),
    'planner stat4 expression partial current source next172 matched keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_cache_2', 'plugin_forms', 'plugin_security'], $plan172()['selectedPlan']['matchedKeys']),
    'planner stat4 expression partial current source next172 estimate rows bounded' => static fn (TestRunner $t) => $t->same(5, $plan172()['selectedPlan']['estimatedRows']),
    'planner stat4 expression partial current source next172 cost includes sample gap' => static fn (TestRunner $t) => $t->same(8, $plan172()['selectedPlan']['estimatedCost']),
    'planner stat4 expression partial current source next172 prepared summary ready' => static fn (TestRunner $t) => $t->same(true, $plan172()['preparedSource']['ready']),
    'planner stat4 expression partial current source next172 current summary ready' => static fn (TestRunner $t) => $t->same(true, $plan172()['currentSource']['ready']),
    'planner stat4 expression partial current source next172 prepared rows' => static fn (TestRunner $t) => $t->same(2, $plan172()['preparedSource']['matchedRowCount']),
    'planner stat4 expression partial current source next172 current rows' => static fn (TestRunner $t) => $t->same(5, $plan172()['currentSource']['matchedRowCount']),
    'planner stat4 expression partial current source next172 fence cookie' => static fn (TestRunner $t) => $t->same(1724, $plan172()['stat4Fence']['schemaCookie']),
    'planner stat4 expression partial current source next172 fence generation' => static fn (TestRunner $t) => $t->same(57, $plan172()['stat4Fence']['stat4Generation']),
    'planner stat4 expression partial current source next172 fence has sample signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan172()['stat4Fence']['sampleSignature'])),
    'planner stat4 expression partial current source next172 cursor source' => static fn (TestRunner $t) => $t->same('current', $plan172()['cursorTape']['source']),
    'planner stat4 expression partial current source next172 cursor index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_plugin_partial_next172_current', $plan172()['cursorTape']['indexName']),
    'planner stat4 expression partial current source next172 seek opcode' => static fn (TestRunner $t) => $t->same('SeekGE', $plan172()['cursorTape']['seekOpcode']),
    'planner stat4 expression partial current source next172 stop opcode' => static fn (TestRunner $t) => $t->same('IdxGE', $plan172()['cursorTape']['stopOpcode']),
    'planner stat4 expression partial current source next172 cursor rowids' => static fn (TestRunner $t) => $t->same([12, 13, 14, 15, 16], $plan172()['cursorTape']['rowids']),
    'planner stat4 expression partial current source next172 cursor keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_cache_2', 'plugin_forms', 'plugin_security'], $plan172()['cursorTape']['keys']),
    'planner stat4 expression partial current source next172 program opens current index' => static fn (TestRunner $t) => $t->same(['opcode' => 'OpenRead', 'target' => 'index', 'rootPage' => 17211, 'source' => 'current'], $plan172()['cursorTape']['program'][0]),
    'planner stat4 expression partial current source next172 program rechecks partial' => static fn (TestRunner $t) => $t->same(['opcode' => 'RecheckPartialPredicate', 'implied' => true], $plan172()['cursorTape']['program'][1]),
    'planner stat4 expression partial current source next172 program seek key' => static fn (TestRunner $t) => $t->same('plugin_', $plan172()['cursorTape']['program'][2]['key']),
    'planner stat4 expression partial current source next172 program stop key' => static fn (TestRunner $t) => $t->same('plugin_t', $plan172()['cursorTape']['program'][3]['key']),
    'planner stat4 expression partial current source next172 first current next sample' => static fn (TestRunner $t) => $t->same('network_plugins', $plan172()['selectedPlan']['stat4CurrentNext'][0]['next']['key']),
    'planner stat4 expression partial current source next172 first matched next sample' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan172()['selectedPlan']['matchedStat4CurrentNext'][0]['next']['key']),
    'planner stat4 expression partial current source next172 last matched eof' => static fn (TestRunner $t) => $t->same(null, $plan172()['selectedPlan']['matchedStat4CurrentNext'][3]['next']),
    'planner stat4 expression partial current source next172 range first sample' => static fn (TestRunner $t) => $t->same('plugin_alpha', $plan172()['selectedPlan']['stat4RangeFence']['first']['key']),
    'planner stat4 expression partial current source next172 range last sample' => static fn (TestRunner $t) => $t->same('plugin_security', $plan172()['selectedPlan']['stat4RangeFence']['last']['key']),
    'planner stat4 expression partial current source next172 lower not exact' => static fn (TestRunner $t) => $t->same(false, $plan172()['selectedPlan']['stat4RangeFence']['lowerExact']),
    'planner stat4 expression partial current source next172 upper not exact' => static fn (TestRunner $t) => $t->same(false, $plan172()['selectedPlan']['stat4RangeFence']['upperExact']),
    'planner stat4 expression partial current source next172 table lookup deferred' => static fn (TestRunner $t) => $t->same(true, $plan172()['tableLookupDeferred']),
    'planner stat4 expression partial current source next172 residual predicate required' => static fn (TestRunner $t) => $t->same(true, $plan172()['residualPredicateRequired']),
    'planner stat4 expression partial current source next172 detail reprepare' => static fn (TestRunner $t) => $t->contains('REPREPARE STAT4 PARTIAL EXPRESSION CURRENT SOURCE', $plan172()['detail']),
    'planner stat4 expression partial current source next172 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan172()['dependency_closure']),
    'planner stat4 expression partial current source next172 non overlap' => static fn (TestRunner $t) => $t->contains('stale STAT4 expression partial-index selectivity', $plan172()['non_overlap']),
];

$freshPlan = static fn (): array => $plan172($prepared172(), $prepared172(['name' => 'current-same-stat4-expression-partial-next172']));
$tests['planner stat4 expression partial current source next172 fresh reuses prepared'] = static fn (TestRunner $t) => $t->same('prepared', $freshPlan()['selectedSource']);
$tests['planner stat4 expression partial current source next172 fresh no reprepare'] = static fn (TestRunner $t) => $t->same(false, $freshPlan()['reprepareRequired']);
$tests['planner stat4 expression partial current source next172 fresh rowids'] = static fn (TestRunner $t) => $t->same([2, 3], $freshPlan()['selectedPlan']['matchedRowids']);

$betweenPlan = static fn (): array => $plan172(null, null, $and($eq('autoload', 'yes'), $between('lower(option_name)', 'plugin_alpha', 'plugin_forms')));
$tests['planner stat4 expression partial current source next172 between inclusive status'] = static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next172-ready', $betweenPlan()['status']);
$tests['planner stat4 expression partial current source next172 between rowids'] = static fn (TestRunner $t) => $t->same([12, 13, 14, 15], $betweenPlan()['selectedPlan']['matchedRowids']);
$tests['planner stat4 expression partial current source next172 between upper exact'] = static fn (TestRunner $t) => $t->same(true, $betweenPlan()['selectedPlan']['stat4RangeFence']['upperExact']);

$networkPlan = static fn (): array => $plan172(null, null, $and($eq('blog_id', 0), $range('lower(option_name)', '>=', 'network'), $range('lower(option_name)', '<', 'network_z')));
$tests['planner stat4 expression partial current source next172 or arm implication'] = static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next172-ready', $networkPlan()['status']);
$tests['planner stat4 expression partial current source next172 network rowid'] = static fn (TestRunner $t) => $t->same([11], $networkPlan()['selectedPlan']['matchedRowids']);

$missingPartial = static fn (): array => $plan172(null, null, $and($range('lower(option_name)', '>=', 'plugin_'), $range('lower(option_name)', '<', 'plugin_t')));
$tests['planner stat4 expression partial current source next172 missing partial requires next'] = static fn (TestRunner $t) => $t->same('requires-next-stage', $missingPartial()['status']);
$tests['planner stat4 expression partial current source next172 missing partial no cursor'] = static fn (TestRunner $t) => $t->same(null, $missingPartial()['cursorTape']['indexName']);

$tests['planner stat4 expression partial current source next172 validates schema cookie'] = static function (TestRunner $t) use ($prepared172, $current172): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext172($prepared172(['schemaCookie' => -1]), $current172(), $GLOBALS['predicate_next172']));
};
$tests['planner stat4 expression partial current source next172 validates stat4 sample'] = static function (TestRunner $t) use ($prepared172, $current172): void {
    $bad = $current172();
    $bad['indexes'][0]['stat4Samples'][0]['sample'] = [];
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext172($prepared172(), $bad, $GLOBALS['predicate_next172']));
};
$tests['planner stat4 expression partial current source next172 validates rows'] = static function (TestRunner $t) use ($prepared172, $current172): void {
    $bad = $current172();
    $bad['rows'][] = 'bad-row';
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext172($prepared172(), $bad, $GLOBALS['predicate_next172']));
};

return $tests;
