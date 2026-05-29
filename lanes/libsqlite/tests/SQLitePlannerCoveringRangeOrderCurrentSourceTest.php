<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerCoveringRangeOrderCurrentSourceNextPlan;

$pointcurrent = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$rangecurrent = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$betweencurrent = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$andcurrent = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$preparedSourcecurrent = static fn (): array => [
    'name' => 'prepared-covering-range-order-current-source',
    'schemaCookie' => 118,
    'stat4Generation' => 61,
    'indexes' => [[
        'name' => 'idx_wp_options_blog_autoload_name_value_current_source',
        'rootPage' => 11901,
        'estimatedRows' => 180,
        'stat4Samples' => [
            ['neq' => '1 1 2', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'yes', 'plugin_alpha']],
            ['neq' => '1 1 2', 'nlt' => '2 2 2', 'ndlt' => '1 1 1', 'sample' => [1, 'yes', 'plugin_forms']],
            ['neq' => '1 1 2', 'nlt' => '4 4 4', 'ndlt' => '2 2 2', 'sample' => [1, 'yes', 'plugin_security']],
        ],
        'sql' => "CREATE INDEX idx_wp_options_blog_autoload_name_value_current_source ON wp_options(blog_id, autoload, option_name, option_value, option_id) WHERE autoload = 'yes'",
    ], [
        'name' => 'idx_wp_options_name_only_current_source',
        'rootPage' => 11902,
        'estimatedRows' => 25,
        'stat4Samples' => [
            ['neq' => '1', 'nlt' => '0', 'ndlt' => '0', 'sample' => ['plugin_forms']],
        ],
        'sql' => 'CREATE INDEX idx_wp_options_name_only_current_source ON wp_options(option_name)',
    ]],
];

$currentSourcecurrent = static function () use ($preparedSourcecurrent): array {
    $source = $preparedSourcecurrent();
    $source['name'] = 'current-covering-range-order-current-source';
    $source['schemaCookie'] = 119;
    $source['stat4Generation'] = 62;
    $source['indexes'][0]['rootPage'] = 11910;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'yes', 'plugin_alpha']],
        ['neq' => '1 1 2', 'nlt' => '1 1 1', 'ndlt' => '1 1 1', 'sample' => [1, 'yes', 'plugin_cache']],
        ['neq' => '1 1 3', 'nlt' => '3 3 3', 'ndlt' => '2 2 2', 'sample' => [1, 'yes', 'plugin_forms']],
        ['neq' => '1 1 2', 'nlt' => '6 6 6', 'ndlt' => '3 3 3', 'sample' => [1, 'yes', 'plugin_security']],
        ['neq' => '1 1 1', 'nlt' => '8 8 8', 'ndlt' => '4 4 4', 'sample' => [1, 'yes', 'plugin_slider']],
        ['neq' => '1 1 1', 'nlt' => '9 9 9', 'ndlt' => '5 5 5', 'sample' => [1, 'yes', 'theme_mods']],
    ];

    return $source;
};

$predicatecurrent = $andcurrent(
    $pointcurrent('blog_id', 1),
    $pointcurrent('autoload', 'yes'),
    $rangecurrent('option_name', '>=', 'plugin_'),
    $rangecurrent('option_name', '<', 'plugin_z'),
);
$ordercurrent = [
    ['column' => 'option_name', 'direction' => 'ASC'],
    ['column' => 'option_value', 'direction' => 'ASC'],
];
$neededcurrent = ['option_name', 'option_value', 'option_id'];

$plancurrent = static fn (
    ?array $prepared = null,
    ?array $current = null,
    ?array $predicate = null,
    ?array $order = null,
    ?array $needed = null,
): array => SQLitePlannerCoveringRangeOrderCurrentSourceNextPlan::materializeCoveringRangeOrderCurrentSource(
    $prepared ?? $preparedSourcecurrent(),
    $current ?? $currentSourcecurrent(),
    $predicate ?? $GLOBALS['predicate_current-source'],
    $order ?? $GLOBALS['order_current-source'],
    $needed ?? $GLOBALS['needed_current-source'],
);

$GLOBALS['predicate_current-source'] = $predicatecurrent;
$GLOBALS['order_current-source'] = $ordercurrent;
$GLOBALS['needed_current-source'] = $neededcurrent;

$freshcurrent = static fn (): array => $plancurrent($preparedSourcecurrent(), $preparedSourcecurrent());
$missingCoveringcurrent = static fn (): array => $plancurrent(null, null, null, null, ['option_name', 'autoload', 'missing_meta']);
$blockSortcurrent = static fn (): array => $plancurrent(null, null, null, [['column' => 'option_value', 'direction' => 'ASC']]);
$betweencurrentPlan = static fn (): array => $plancurrent(null, null, $andcurrent($pointcurrent('blog_id', 1), $pointcurrent('autoload', 'yes'), $betweencurrent('option_name', 'plugin_cache', 'plugin_security')));
$openLowercurrent = static fn (): array => $plancurrent(null, null, $andcurrent($pointcurrent('blog_id', 1), $pointcurrent('autoload', 'yes'), $rangecurrent('option_name', '>', 'plugin_cache'), $rangecurrent('option_name', '<', 'plugin_security')));
$noPartialcurrent = static fn (): array => $plancurrent(null, null, $andcurrent($pointcurrent('blog_id', 1), $pointcurrent('autoload', 'no'), $rangecurrent('option_name', '>=', 'plugin_')));

$tests = [
    'planner covering range order current source status ready' => static fn (TestRunner $t) => $t->same('covering-range-order-current-source-ready', $plancurrent()['status']),
    'planner covering range order current source selects current' => static fn (TestRunner $t) => $t->same('current', $plancurrent()['selectedSource']),
    'planner covering range order current source stale prepared' => static fn (TestRunner $t) => $t->same(true, $plancurrent()['stalePreparedStatement']),
    'planner covering range order current source requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plancurrent()['reprepareRequired']),
    'planner covering range order current source schema changed' => static fn (TestRunner $t) => $t->same(true, $plancurrent()['schemaCookieChanged']),
    'planner covering range order current source stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plancurrent()['stat4GenerationChanged']),
    'planner covering range order current source signature changed' => static fn (TestRunner $t) => $t->same(true, $plancurrent()['indexSignatureChanged']),
    'planner covering range order current source selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_blog_autoload_name_value_current_source', $plancurrent()['selectedPlan']['name']),
    'planner covering range order current source selected root' => static fn (TestRunner $t) => $t->same(11910, $plancurrent()['selectedPlan']['rootPage']),
    'planner covering range order current source prepared root' => static fn (TestRunner $t) => $t->same(11901, $plancurrent()['preparedSource']['rootPage']),
    'planner covering range order current source current root' => static fn (TestRunner $t) => $t->same(11910, $plancurrent()['currentSource']['rootPage']),
    'planner covering range order current source columns' => static fn (TestRunner $t) => $t->same(['blog_id', 'autoload', 'option_name', 'option_value', 'option_id'], $plancurrent()['selectedPlan']['columns']),
    'planner covering range order current source equality prefix' => static fn (TestRunner $t) => $t->same(2, $plancurrent()['selectedPlan']['equalityPrefix']),
    'planner covering range order current source used columns' => static fn (TestRunner $t) => $t->same(['blog_id', 'autoload', 'option_name'], $plancurrent()['selectedPlan']['usedColumns']),
    'planner covering range order current source range column' => static fn (TestRunner $t) => $t->same('option_name', $plancurrent()['selectedPlan']['rangeColumn']),
    'planner covering range order current source range lower' => static fn (TestRunner $t) => $t->same('plugin_', $plancurrent()['selectedPlan']['rangeLower']),
    'planner covering range order current source range upper' => static fn (TestRunner $t) => $t->same('plugin_z', $plancurrent()['selectedPlan']['rangeUpper']),
    'planner covering range order current source lower inclusive' => static fn (TestRunner $t) => $t->same(true, $plancurrent()['selectedPlan']['lowerInclusive']),
    'planner covering range order current source upper exclusive' => static fn (TestRunner $t) => $t->same(false, $plancurrent()['selectedPlan']['upperInclusive']),
    'planner covering range order current source partial true' => static fn (TestRunner $t) => $t->same(true, $plancurrent()['selectedPlan']['partial']),
    'planner covering range order current source partial implied' => static fn (TestRunner $t) => $t->same(true, $plancurrent()['selectedPlan']['partialPredicateImplied']),
    'planner covering range order current source covering true' => static fn (TestRunner $t) => $t->same(true, $plancurrent()['selectedPlan']['covering']),
    'planner covering range order current source order satisfied' => static fn (TestRunner $t) => $t->same(true, $plancurrent()['selectedPlan']['orderBySatisfied']),
    'planner covering range order current source no block sort' => static fn (TestRunner $t) => $t->same(false, $plancurrent()['selectedPlan']['blockSortRequired']),
    'planner covering range order current source table lookup false' => static fn (TestRunner $t) => $t->same(false, $plancurrent()['selectedPlan']['tableLookupRequired']),
    'planner covering range order current source residual empty' => static fn (TestRunner $t) => $t->same([], $plancurrent()['selectedPlan']['residualColumns']),
    'planner covering range order current source stat4 used' => static fn (TestRunner $t) => $t->same(true, $plancurrent()['selectedPlan']['stat4Used']),
    'planner covering range order current source stat4 matched count' => static fn (TestRunner $t) => $t->same(5, $plancurrent()['selectedPlan']['stat4MatchedSamples']),
    'planner covering range order current source estimated rows' => static fn (TestRunner $t) => $t->same(9, $plancurrent()['selectedPlan']['estimatedRows']),
    'planner covering range order current source current next count' => static fn (TestRunner $t) => $t->same(5, count($plancurrent()['selectedPlan']['stat4CurrentNext'])),
    'planner covering range order current source matched keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_security', 'plugin_slider'], $plancurrent()['cursorTape']['matchedKeys']),
    'planner covering range order current source first next key' => static fn (TestRunner $t) => $t->same('plugin_cache', $plancurrent()['cursorTape']['currentNextSegments'][0]['next']['key']),
    'planner covering range order current source middle neq' => static fn (TestRunner $t) => $t->same(3, $plancurrent()['cursorTape']['currentNextSegments'][2]['neq']),
    'planner covering range order current source last eof' => static fn (TestRunner $t) => $t->same('eof', $plancurrent()['cursorTape']['currentNextSegments'][4]['advance']),
    'planner covering range order current source cursor source' => static fn (TestRunner $t) => $t->same('current', $plancurrent()['cursorTape']['source']),
    'planner covering range order current source cursor index' => static fn (TestRunner $t) => $t->same('idx_wp_options_blog_autoload_name_value_current_source', $plancurrent()['cursorTape']['indexName']),
    'planner covering range order current source cursor root' => static fn (TestRunner $t) => $t->same(11910, $plancurrent()['cursorTape']['rootPage']),
    'planner covering range order current source seek ge' => static fn (TestRunner $t) => $t->same('SeekGE', $plancurrent()['cursorTape']['seekOpcode']),
    'planner covering range order current source stop ge exclusive' => static fn (TestRunner $t) => $t->same('IdxGE', $plancurrent()['cursorTape']['stopOpcode']),
    'planner covering range order current source next opcode' => static fn (TestRunner $t) => $t->same('Next', $plancurrent()['cursorTape']['nextOpcode']),
    'planner covering range order current source ascending' => static fn (TestRunner $t) => $t->same('ascending', $plancurrent()['cursorTape']['scanDirection']),
    'planner covering range order current source output columns from index' => static fn (TestRunner $t) => $t->same([['column' => 'option_name', 'opcode' => 'Column', 'source' => 'index'], ['column' => 'option_value', 'opcode' => 'Column', 'source' => 'index'], ['column' => 'option_id', 'opcode' => 'Column', 'source' => 'index']], $plancurrent()['cursorTape']['outputColumns']),
    'planner covering range order current source no deferred seek' => static fn (TestRunner $t) => $t->same(null, $plancurrent()['cursorTape']['deferredSeekOpcode']),
    'planner covering range order current source sorter closed' => static fn (TestRunner $t) => $t->same(false, $plancurrent()['cursorTape']['sorterOpen']),
    'planner covering range order current source table lookup elided' => static fn (TestRunner $t) => $t->same(true, $plancurrent()['tableLookupElided']),
    'planner covering range order current source temp sort elided' => static fn (TestRunner $t) => $t->same(true, $plancurrent()['tempSortElided']),
    'planner covering range order current source program opens index' => static fn (TestRunner $t) => $t->same('OpenRead', $plancurrent()['cursorTape']['program'][0]['opcode']),
    'planner covering range order current source program seeks range' => static fn (TestRunner $t) => $t->same(['opcode' => 'SeekGE', 'source' => 'index', 'column' => 'option_name', 'key' => 'plugin_'], $plancurrent()['cursorTape']['program'][1]),
    'planner covering range order current source program stops range' => static fn (TestRunner $t) => $t->same(['opcode' => 'IdxGE', 'source' => 'index', 'column' => 'option_name', 'key' => 'plugin_z'], $plancurrent()['cursorTape']['program'][2]),
    'planner covering range order current source program reads option id' => static fn (TestRunner $t) => $t->same(['opcode' => 'Column', 'source' => 'index', 'column' => 'option_id'], $plancurrent()['cursorTape']['program'][5]),
    'planner covering range order current source program advances' => static fn (TestRunner $t) => $t->same('Next', $plancurrent()['cursorTape']['program'][6]['opcode']),
    'planner covering range order current source fence cookie' => static fn (TestRunner $t) => $t->same(119, $plancurrent()['currentSourceFence']['schemaCookie']),
    'planner covering range order current source fence stat4' => static fn (TestRunner $t) => $t->same(62, $plancurrent()['currentSourceFence']['stat4Generation']),
    'planner covering range order current source order signature' => static fn (TestRunner $t) => $t->same('option_name ASC, option_value ASC', $plancurrent()['currentSourceFence']['orderSignature']),
    'planner covering range order current source signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plancurrent()['currentSourceFence']['indexSignature'])),
    'planner covering range order current source predicate signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plancurrent()['currentSourceFence']['predicateSignature'])),
    'planner covering range order current source detail current' => static fn (TestRunner $t) => $t->contains('REPREPARE COVERING RANGE ORDER USING current-covering-range-order-current-source', $plancurrent()['detail']),
    'planner covering range order current source dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plancurrent()['dependency_closure']),
    'planner covering range order current source non overlap' => static fn (TestRunner $t) => $t->contains('ordinary multicolumn covering range ORDER BY', $plancurrent()['non_overlap']),
    'planner covering range order current source fresh reuses prepared' => static fn (TestRunner $t) => $t->same('prepared', $freshcurrent()['selectedSource']),
    'planner covering range order current source fresh no reprepare' => static fn (TestRunner $t) => $t->same(false, $freshcurrent()['reprepareRequired']),
    'planner covering range order current source fresh matched samples' => static fn (TestRunner $t) => $t->same(3, $freshcurrent()['selectedPlan']['stat4MatchedSamples']),
    'planner covering range order current source missing covering requires next stage' => static fn (TestRunner $t) => $t->same('requires-next-stage', $missingCoveringcurrent()['status']),
    'planner covering range order current source missing covering deferred seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $missingCoveringcurrent()['cursorTape']['deferredSeekOpcode']),
    'planner covering range order current source missing covering table column source' => static fn (TestRunner $t) => $t->same('table', $missingCoveringcurrent()['cursorTape']['outputColumns'][2]['source']),
    'planner covering range order current source block sort requires next stage' => static fn (TestRunner $t) => $t->same('requires-next-stage', $blockSortcurrent()['status']),
    'planner covering range order current source block sort recorded' => static fn (TestRunner $t) => $t->same(true, $blockSortcurrent()['selectedPlan']['blockSortRequired']),
    'planner covering range order current source block sort opens sorter' => static fn (TestRunner $t) => $t->same(true, $blockSortcurrent()['cursorTape']['sorterOpen']),
    'planner covering range order current source between inclusive stop gt' => static fn (TestRunner $t) => $t->same('IdxGT', $betweencurrentPlan()['cursorTape']['stopOpcode']),
    'planner covering range order current source between matched keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_security'], $betweencurrentPlan()['cursorTape']['matchedKeys']),
    'planner covering range order current source open lower seek gt' => static fn (TestRunner $t) => $t->same('SeekGT', $openLowercurrent()['cursorTape']['seekOpcode']),
    'planner covering range order current source open lower excludes boundary' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $openLowercurrent()['cursorTape']['matchedKeys']),
    'planner covering range order current source partial not implied falls to plain' => static fn (TestRunner $t) => $t->same('idx_wp_options_name_only_current_source', $noPartialcurrent()['selectedPlan']['name']),
    'planner covering range order current source validates order direction' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plancurrent(null, null, null, [['column' => 'option_name', 'direction' => 'SIDEWAYS']])),
    'planner covering range order current source validates output columns' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plancurrent(null, null, null, null, [''])),
    'planner covering range order current source validates source indexes' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plancurrent(null, ['name' => 'bad', 'schemaCookie' => 1, 'stat4Generation' => 1, 'indexes' => ['bad' => []]])),
    'planner covering range order current source validates schema cookie' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plancurrent(['name' => 'bad', 'schemaCookie' => -1, 'stat4Generation' => 1, 'indexes' => []])),
];

return $tests;
