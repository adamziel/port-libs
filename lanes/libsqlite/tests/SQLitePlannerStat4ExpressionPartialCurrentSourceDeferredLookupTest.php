<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$expr156 = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$column156 = static fn (string $column): array => ['column' => $column];
$point156 = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$range156 = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$between156 = static fn (array $left, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => $left, 'lower' => $lower, 'upper' => $upper];
$isNotNull156 = static fn (string $column): array => ['operator' => 'IS NOT NULL', 'left' => ['column' => $column]];
$and156 = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$prepared156 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-expression-partial-deferredLookup',
    'schemaCookie' => 1560,
    'stat4Generation' => 20,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_autoload_partial_deferredLookup',
        'rootPage' => 15601,
        'estimatedRows' => 240,
        'stat4Samples' => [
            ['neq' => '2 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 'yes']],
            ['neq' => '5 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 'yes']],
            ['neq' => '3 1', 'nlt' => '7 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 'yes']],
        ],
        'coveringColumns' => ['autoload'],
        'sql' => "CREATE INDEX idx_wp_options_lower_autoload_partial_deferredLookup ON wp_options(lower(option_name), autoload) WHERE autoload = 'yes' AND option_name IS NOT NULL",
    ]],
    'rows' => [
        ['rowid' => 1, 'option_id' => 1, 'option_name' => 'Plugin_Alpha', 'autoload' => 'yes', 'option_value' => 'old-alpha'],
        ['rowid' => 2, 'option_id' => 2, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'old-cache'],
        ['rowid' => 3, 'option_id' => 3, 'option_name' => 'plugin_forms', 'autoload' => 'yes', 'option_value' => 'old-forms'],
        ['rowid' => 4, 'option_id' => 4, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'option_value' => 'old-theme'],
    ],
];

$current156 = static fn (): array => [
    'name' => 'current-wp-options-stat4-expression-partial-deferredLookup',
    'schemaCookie' => 1562,
    'stat4Generation' => 23,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_autoload_partial_deferredLookup',
        'rootPage' => 15631,
        'estimatedRows' => 180,
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 'yes']],
            ['neq' => '4 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 'yes']],
            ['neq' => '2 1', 'nlt' => '5 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 'yes']],
            ['neq' => '1 1', 'nlt' => '7 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 'yes']],
            ['neq' => '1 1', 'nlt' => '8 4', 'ndlt' => '4 4', 'sample' => ['plugin_slider', 'yes']],
        ],
        'coveringColumns' => ['autoload'],
        'sql' => "CREATE INDEX idx_wp_options_lower_autoload_partial_deferredLookup ON wp_options(lower(option_name), autoload) WHERE autoload = 'yes' AND option_name IS NOT NULL",
    ]],
    'rows' => [
        ['rowid' => 11, 'option_id' => 11, 'option_name' => 'Plugin_Alpha', 'autoload' => 'yes', 'option_value' => 'alpha-current'],
        ['rowid' => 12, 'option_id' => 12, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'cache-a'],
        ['rowid' => 13, 'option_id' => 13, 'option_name' => 'Plugin_Cache', 'autoload' => 'yes', 'option_value' => 'cache-b'],
        ['rowid' => 14, 'option_id' => 14, 'option_name' => 'plugin_forms', 'autoload' => 'yes', 'option_value' => 'forms'],
        ['rowid' => 15, 'option_id' => 15, 'option_name' => 'plugin_seo', 'autoload' => 'yes', 'option_value' => 'seo'],
        ['rowid' => 16, 'option_id' => 16, 'option_name' => 'plugin_slider', 'autoload' => 'yes', 'option_value' => 'slider'],
        ['rowid' => 17, 'option_id' => 17, 'option_name' => 'plugin_cache', 'autoload' => 'no', 'option_value' => 'lazy-cache'],
        ['rowid' => 18, 'option_id' => 18, 'option_name' => null, 'autoload' => 'yes', 'option_value' => 'null-name'],
        ['rowid' => 19, 'option_id' => 19, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'option_value' => 'theme'],
    ],
];

$lowerName156 = $expr156('lower', 'option_name');
$predicate156 = static fn () => $and156(
    $point156($column156('autoload'), 'yes'),
    $isNotNull156('option_name'),
    $range156($GLOBALS['lowerName156'], '>=', 'plugin_cache'),
    $range156($GLOBALS['lowerName156'], '<', 'plugin_t'),
);
$order156 = [$lowerName156];
$needed156 = ['option_name', 'option_value', 'option_id'];
$plan156 = static fn (?array $prepared = null, ?array $current = null, ?array $predicate = null, ?array $order = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceDeferredLookup(
    $prepared ?? $prepared156(),
    $current ?? $current156(),
    $predicate ?? $predicate156(),
    $order ?? $GLOBALS['order156'],
    $needed ?? $GLOBALS['needed156'],
);

$GLOBALS['lowerName156'] = $lowerName156;
$GLOBALS['order156'] = $order156;
$GLOBALS['needed156'] = $needed156;

$tests = [
    'planner stat4 expression partial current source deferredLookup status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-deferredLookup-ready', $plan156()['status']),
    'planner stat4 expression partial current source deferredLookup selects current' => static fn (TestRunner $t) => $t->same('current', $plan156()['selectedSource']),
    'planner stat4 expression partial current source deferredLookup marks stale' => static fn (TestRunner $t) => $t->same(true, $plan156()['stalePreparedStatement']),
    'planner stat4 expression partial current source deferredLookup requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan156()['reprepareRequired']),
    'planner stat4 expression partial current source deferredLookup detects cookie' => static fn (TestRunner $t) => $t->same(true, $plan156()['schemaCookieChanged']),
    'planner stat4 expression partial current source deferredLookup detects stat4' => static fn (TestRunner $t) => $t->same(true, $plan156()['stat4GenerationChanged']),
    'planner stat4 expression partial current source deferredLookup detects index signature' => static fn (TestRunner $t) => $t->same(true, $plan156()['indexSignatureChanged']),
    'planner stat4 expression partial current source deferredLookup prepared summary ready' => static fn (TestRunner $t) => $t->same(true, $plan156()['preparedSource']['ready']),
    'planner stat4 expression partial current source deferredLookup current summary ready' => static fn (TestRunner $t) => $t->same(true, $plan156()['currentSource']['ready']),
    'planner stat4 expression partial current source deferredLookup prepared root' => static fn (TestRunner $t) => $t->same(15601, $plan156()['preparedSource']['rootPage']),
    'planner stat4 expression partial current source deferredLookup current root' => static fn (TestRunner $t) => $t->same(15631, $plan156()['currentSource']['rootPage']),
    'planner stat4 expression partial current source deferredLookup selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_autoload_partial_deferredLookup', $plan156()['selectedPlan']['name']),
    'planner stat4 expression partial current source deferredLookup selected root' => static fn (TestRunner $t) => $t->same(15631, $plan156()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source deferredLookup selected partial' => static fn (TestRunner $t) => $t->same(true, $plan156()['selectedPlan']['partial']),
    'planner stat4 expression partial current source deferredLookup selected stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan156()['selectedPlan']['stat4Used']),
    'planner stat4 expression partial current source deferredLookup selected non covering' => static fn (TestRunner $t) => $t->same(false, $plan156()['selectedPlan']['covering']),
    'planner stat4 expression partial current source deferredLookup table lookup required' => static fn (TestRunner $t) => $t->same(true, $plan156()['selectedPlan']['tableLookupRequired']),
    'planner stat4 expression partial current source deferredLookup operator lower range' => static fn (TestRunner $t) => $t->same('range-bounded', $plan156()['selectedPlan']['operator']),
    'planner stat4 expression partial current source deferredLookup column option name' => static fn (TestRunner $t) => $t->same('option_name', $plan156()['selectedPlan']['column']),
    'planner stat4 expression partial current source deferredLookup type lower' => static fn (TestRunner $t) => $t->same('lower', $plan156()['selectedPlan']['type']),
    'planner stat4 expression partial current source deferredLookup range values' => static fn (TestRunner $t) => $t->same(['lower' => 'plugin_cache', 'upper' => 'plugin_t', 'lowerInclusive' => true, 'upperInclusive' => false], $plan156()['selectedPlan']['values']),
    'planner stat4 expression partial current source deferredLookup stat4 matched count' => static fn (TestRunner $t) => $t->same(4, $plan156()['selectedPlan']['stat4MatchedSamples']),
    'planner stat4 expression partial current source deferredLookup stat4 estimate' => static fn (TestRunner $t) => $t->same(8, $plan156()['selectedPlan']['stat4Estimate']),
    'planner stat4 expression partial current source deferredLookup estimated rows' => static fn (TestRunner $t) => $t->same(8, $plan156()['selectedPlan']['estimatedRows']),
    'planner stat4 expression partial current source deferredLookup partial row count' => static fn (TestRunner $t) => $t->same(5, $plan156()['selectedPlan']['partialRowCount']),
    'planner stat4 expression partial current source deferredLookup rowids' => static fn (TestRunner $t) => $t->same([12, 13, 14, 15, 16], $plan156()['selectedPlan']['partialRowids']),
    'planner stat4 expression partial current source deferredLookup expression keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_cache', 'plugin_forms', 'plugin_seo', 'plugin_slider'], array_column($plan156()['partialRows'], 'expressionKey')),
    'planner stat4 expression partial current source deferredLookup first payload name' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan156()['partialRows'][0]['payload']['option_name']),
    'planner stat4 expression partial current source deferredLookup second payload preserves case' => static fn (TestRunner $t) => $t->same('Plugin_Cache', $plan156()['partialRows'][1]['payload']['option_name']),
    'planner stat4 expression partial current source deferredLookup second payload value' => static fn (TestRunner $t) => $t->same('cache-b', $plan156()['partialRows'][1]['payload']['option_value']),
    'planner stat4 expression partial current source deferredLookup excludes autoload no' => static fn (TestRunner $t) => $t->same(false, in_array(17, $plan156()['selectedPlan']['partialRowids'], true)),
    'planner stat4 expression partial current source deferredLookup excludes null option name' => static fn (TestRunner $t) => $t->same(false, in_array(18, $plan156()['selectedPlan']['partialRowids'], true)),
    'planner stat4 expression partial current source deferredLookup excludes outside range' => static fn (TestRunner $t) => $t->same(false, in_array(19, $plan156()['selectedPlan']['partialRowids'], true)),
    'planner stat4 expression partial current source deferredLookup current next first' => static fn (TestRunner $t) => $t->same(13, $plan156()['currentNextRows'][0]['next']['rowid']),
    'planner stat4 expression partial current source deferredLookup current next eof' => static fn (TestRunner $t) => $t->same(null, $plan156()['currentNextRows'][4]['next']),
    'planner stat4 expression partial current source deferredLookup cursor source' => static fn (TestRunner $t) => $t->same('current', $plan156()['cursorTape']['source']),
    'planner stat4 expression partial current source deferredLookup cursor status' => static fn (TestRunner $t) => $t->same('partial-stat4-current-source', $plan156()['cursorTape']['status']),
    'planner stat4 expression partial current source deferredLookup cursor index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_autoload_partial_deferredLookup', $plan156()['cursorTape']['indexName']),
    'planner stat4 expression partial current source deferredLookup cursor rowids' => static fn (TestRunner $t) => $t->same([12, 13, 14, 15, 16], $plan156()['cursorTape']['rowids']),
    'planner stat4 expression partial current source deferredLookup cursor keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_cache', 'plugin_forms', 'plugin_seo', 'plugin_slider'], $plan156()['cursorTape']['expressionKeys']),
    'planner stat4 expression partial current source deferredLookup cursor deferred seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $plan156()['cursorTape']['deferredSeekOpcode']),
    'planner stat4 expression partial current source deferredLookup cursor no sorter' => static fn (TestRunner $t) => $t->same(false, $plan156()['cursorTape']['sorterOpen']),
    'planner stat4 expression partial current source deferredLookup cursor table lookup not elided' => static fn (TestRunner $t) => $t->same(false, $plan156()['cursorTape']['tableLookupElided']),
    'planner stat4 expression partial current source deferredLookup program opens index' => static fn (TestRunner $t) => $t->same('OpenRead', $plan156()['cursorTape']['program'][0]['opcode']),
    'planner stat4 expression partial current source deferredLookup program seeks stat4' => static fn (TestRunner $t) => $t->same('SeekStat4', $plan156()['cursorTape']['program'][1]['opcode']),
    'planner stat4 expression partial current source deferredLookup program deferred table' => static fn (TestRunner $t) => $t->same('DeferredSeek', $plan156()['cursorTape']['program'][2]['opcode']),
    'planner stat4 expression partial current source deferredLookup program reads table column' => static fn (TestRunner $t) => $t->same(['Column', 'table', 'option_name'], [$plan156()['cursorTape']['program'][3]['opcode'], $plan156()['cursorTape']['program'][3]['source'], $plan156()['cursorTape']['program'][3]['column']]),
    'planner stat4 expression partial current source deferredLookup program result count' => static fn (TestRunner $t) => $t->same(5, $plan156()['cursorTape']['program'][6]['rowCount']),
    'planner stat4 expression partial current source deferredLookup program next' => static fn (TestRunner $t) => $t->same('Next', $plan156()['cursorTape']['program'][7]['opcode']),
    'planner stat4 expression partial current source deferredLookup fence cookie' => static fn (TestRunner $t) => $t->same(1562, $plan156()['currentSourceFence']['schemaCookie']),
    'planner stat4 expression partial current source deferredLookup fence stat4' => static fn (TestRunner $t) => $t->same(23, $plan156()['currentSourceFence']['stat4Generation']),
    'planner stat4 expression partial current source deferredLookup fence order' => static fn (TestRunner $t) => $t->same('lower(option_name) ASC', $plan156()['currentSourceFence']['orderSignature']),
    'planner stat4 expression partial current source deferredLookup fence row signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan156()['currentSourceFence']['rowStreamSignature'])),
    'planner stat4 expression partial current source deferredLookup lower boundary current' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan156()['cursorTape']['stat4RangeCurrentNext']['lower']['current']['key']),
    'planner stat4 expression partial current source deferredLookup upper boundary current' => static fn (TestRunner $t) => $t->same('plugin_slider', $plan156()['cursorTape']['stat4RangeCurrentNext']['upper']['current']['key']),
    'planner stat4 expression partial current source deferredLookup detail' => static fn (TestRunner $t) => $t->contains('WITH DEFERRED TABLE LOOKUP', $plan156()['detail']),
    'planner stat4 expression partial current source deferredLookup dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan156()['dependency_closure']),
    'planner stat4 expression partial current source deferredLookup non overlap' => static fn (TestRunner $t) => $t->contains('non-covering partial expression STAT4', $plan156()['non_overlap']),
];

$tests['planner stat4 expression partial current source deferredLookup reuses prepared when fresh'] = static function (TestRunner $t) use ($plan156, $prepared156): void {
    $source = $prepared156();
    $candidate = $plan156($source, $source);
    $t->same('prepared', $candidate['selectedSource']);
    $t->same(false, $candidate['reprepareRequired']);
    $t->same([2, 3], $candidate['selectedPlan']['partialRowids']);
};

$tests['planner stat4 expression partial current source deferredLookup point predicate narrows rows'] = static function (TestRunner $t) use ($plan156, $and156, $point156, $column156, $isNotNull156, $lowerName156): void {
    $candidate = $plan156(null, null, $and156($point156($column156('autoload'), 'yes'), $isNotNull156('option_name'), $point156($lowerName156, 'plugin_cache')));
    $t->same([12, 13], $candidate['selectedPlan']['partialRowids']);
    $t->same(4, $candidate['selectedPlan']['stat4Estimate']);
    $t->same(2, $candidate['selectedPlan']['partialRowCount']);
};

$tests['planner stat4 expression partial current source deferredLookup between predicate keeps inclusive upper'] = static function (TestRunner $t) use ($plan156, $and156, $point156, $column156, $isNotNull156, $between156, $lowerName156): void {
    $candidate = $plan156(null, null, $and156($point156($column156('autoload'), 'yes'), $isNotNull156('option_name'), $between156($lowerName156, 'plugin_forms', 'plugin_seo')));
    $t->same([14, 15], $candidate['selectedPlan']['partialRowids']);
    $t->same(true, $candidate['cursorTape']['stat4RangeCurrentNext']['upperInclusive']);
};

$tests['planner stat4 expression partial current source deferredLookup missing partial term falls back'] = static function (TestRunner $t) use ($plan156, $range156, $lowerName156): void {
    $candidate = $plan156(null, null, $range156($lowerName156, '>=', 'plugin_cache'));
    $t->same('requires-next-stage', $candidate['status']);
    $t->same('no-partial-stat4-plan', $candidate['cursorTape']['status']);
};

$tests['planner stat4 expression partial current source deferredLookup missing output column still table lookup'] = static function (TestRunner $t) use ($plan156): void {
    $candidate = $plan156(null, null, null, null, ['option_value', 'missing_column']);
    $t->same(null, $candidate['partialRows'][0]['payload']['missing_column']);
    $t->same(true, $candidate['selectedPlan']['tableLookupRequired']);
};

$tests['planner stat4 expression partial current source deferredLookup validates needed columns'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan156(null, null, null, null, []));
$tests['planner stat4 expression partial current source deferredLookup validates source rows'] = static function (TestRunner $t) use ($plan156, $current156): void {
    $current = $current156();
    $current['rows'][] = 'bad';
    $t->throws(InvalidArgumentException::class, static fn () => $plan156(null, $current));
};
$tests['planner stat4 expression partial current source deferredLookup validates source indexes'] = static function (TestRunner $t) use ($plan156, $current156): void {
    $current = $current156();
    $current['indexes'] = ['bad'];
    $t->throws(InvalidArgumentException::class, static fn () => $plan156(null, $current));
};
$tests['planner stat4 expression partial current source deferredLookup validates order direction'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan156(null, null, null, [['function' => 'lower', 'column' => 'option_name', 'direction' => 'SIDEWAYS']]));

return $tests;
