<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerCoveringRangeOrderCurrentSourceNextPlan;

$point119 = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range119 = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$between119 = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$and119 = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$preparedSource119 = static fn (): array => [
    'name' => 'prepared-covering-range-order-next119',
    'schemaCookie' => 118,
    'stat4Generation' => 61,
    'indexes' => [[
        'name' => 'idx_wp_options_blog_autoload_name_value_next119',
        'rootPage' => 11901,
        'estimatedRows' => 180,
        'stat4Samples' => [
            ['neq' => '1 1 2', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'yes', 'plugin_alpha']],
            ['neq' => '1 1 2', 'nlt' => '2 2 2', 'ndlt' => '1 1 1', 'sample' => [1, 'yes', 'plugin_forms']],
            ['neq' => '1 1 2', 'nlt' => '4 4 4', 'ndlt' => '2 2 2', 'sample' => [1, 'yes', 'plugin_security']],
        ],
        'sql' => "CREATE INDEX idx_wp_options_blog_autoload_name_value_next119 ON wp_options(blog_id, autoload, option_name, option_value, option_id) WHERE autoload = 'yes'",
    ], [
        'name' => 'idx_wp_options_name_only_next119',
        'rootPage' => 11902,
        'estimatedRows' => 25,
        'stat4Samples' => [
            ['neq' => '1', 'nlt' => '0', 'ndlt' => '0', 'sample' => ['plugin_forms']],
        ],
        'sql' => 'CREATE INDEX idx_wp_options_name_only_next119 ON wp_options(option_name)',
    ]],
];

$currentSource119 = static function () use ($preparedSource119): array {
    $source = $preparedSource119();
    $source['name'] = 'current-covering-range-order-next119';
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

$predicate119 = $and119(
    $point119('blog_id', 1),
    $point119('autoload', 'yes'),
    $range119('option_name', '>=', 'plugin_'),
    $range119('option_name', '<', 'plugin_z'),
);
$order119 = [
    ['column' => 'option_name', 'direction' => 'ASC'],
    ['column' => 'option_value', 'direction' => 'ASC'],
];
$needed119 = ['option_name', 'option_value', 'option_id'];

$plan119 = static fn (
    ?array $prepared = null,
    ?array $current = null,
    ?array $predicate = null,
    ?array $order = null,
    ?array $needed = null,
): array => SQLitePlannerCoveringRangeOrderCurrentSourceNextPlan::materializeNext119(
    $prepared ?? $preparedSource119(),
    $current ?? $currentSource119(),
    $predicate ?? $GLOBALS['predicate_next119'],
    $order ?? $GLOBALS['order_next119'],
    $needed ?? $GLOBALS['needed_next119'],
);

$GLOBALS['predicate_next119'] = $predicate119;
$GLOBALS['order_next119'] = $order119;
$GLOBALS['needed_next119'] = $needed119;

$fresh119 = static fn (): array => $plan119($preparedSource119(), $preparedSource119());
$missingCovering119 = static fn (): array => $plan119(null, null, null, null, ['option_name', 'autoload', 'missing_meta']);
$blockSort119 = static fn (): array => $plan119(null, null, null, [['column' => 'option_value', 'direction' => 'ASC']]);
$between119Plan = static fn (): array => $plan119(null, null, $and119($point119('blog_id', 1), $point119('autoload', 'yes'), $between119('option_name', 'plugin_cache', 'plugin_security')));
$openLower119 = static fn (): array => $plan119(null, null, $and119($point119('blog_id', 1), $point119('autoload', 'yes'), $range119('option_name', '>', 'plugin_cache'), $range119('option_name', '<', 'plugin_security')));
$noPartial119 = static fn (): array => $plan119(null, null, $and119($point119('blog_id', 1), $point119('autoload', 'no'), $range119('option_name', '>=', 'plugin_')));

$tests = [
    'planner covering range order current source next119 status ready' => static fn (TestRunner $t) => $t->same('covering-range-order-current-source-ready', $plan119()['status']),
    'planner covering range order current source next119 selects current' => static fn (TestRunner $t) => $t->same('current', $plan119()['selectedSource']),
    'planner covering range order current source next119 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan119()['stalePreparedStatement']),
    'planner covering range order current source next119 requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan119()['reprepareRequired']),
    'planner covering range order current source next119 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan119()['schemaCookieChanged']),
    'planner covering range order current source next119 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan119()['stat4GenerationChanged']),
    'planner covering range order current source next119 signature changed' => static fn (TestRunner $t) => $t->same(true, $plan119()['indexSignatureChanged']),
    'planner covering range order current source next119 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_blog_autoload_name_value_next119', $plan119()['selectedPlan']['name']),
    'planner covering range order current source next119 selected root' => static fn (TestRunner $t) => $t->same(11910, $plan119()['selectedPlan']['rootPage']),
    'planner covering range order current source next119 prepared root' => static fn (TestRunner $t) => $t->same(11901, $plan119()['preparedSource']['rootPage']),
    'planner covering range order current source next119 current root' => static fn (TestRunner $t) => $t->same(11910, $plan119()['currentSource']['rootPage']),
    'planner covering range order current source next119 columns' => static fn (TestRunner $t) => $t->same(['blog_id', 'autoload', 'option_name', 'option_value', 'option_id'], $plan119()['selectedPlan']['columns']),
    'planner covering range order current source next119 equality prefix' => static fn (TestRunner $t) => $t->same(2, $plan119()['selectedPlan']['equalityPrefix']),
    'planner covering range order current source next119 used columns' => static fn (TestRunner $t) => $t->same(['blog_id', 'autoload', 'option_name'], $plan119()['selectedPlan']['usedColumns']),
    'planner covering range order current source next119 range column' => static fn (TestRunner $t) => $t->same('option_name', $plan119()['selectedPlan']['rangeColumn']),
    'planner covering range order current source next119 range lower' => static fn (TestRunner $t) => $t->same('plugin_', $plan119()['selectedPlan']['rangeLower']),
    'planner covering range order current source next119 range upper' => static fn (TestRunner $t) => $t->same('plugin_z', $plan119()['selectedPlan']['rangeUpper']),
    'planner covering range order current source next119 lower inclusive' => static fn (TestRunner $t) => $t->same(true, $plan119()['selectedPlan']['lowerInclusive']),
    'planner covering range order current source next119 upper exclusive' => static fn (TestRunner $t) => $t->same(false, $plan119()['selectedPlan']['upperInclusive']),
    'planner covering range order current source next119 partial true' => static fn (TestRunner $t) => $t->same(true, $plan119()['selectedPlan']['partial']),
    'planner covering range order current source next119 partial implied' => static fn (TestRunner $t) => $t->same(true, $plan119()['selectedPlan']['partialPredicateImplied']),
    'planner covering range order current source next119 covering true' => static fn (TestRunner $t) => $t->same(true, $plan119()['selectedPlan']['covering']),
    'planner covering range order current source next119 order satisfied' => static fn (TestRunner $t) => $t->same(true, $plan119()['selectedPlan']['orderBySatisfied']),
    'planner covering range order current source next119 no block sort' => static fn (TestRunner $t) => $t->same(false, $plan119()['selectedPlan']['blockSortRequired']),
    'planner covering range order current source next119 table lookup false' => static fn (TestRunner $t) => $t->same(false, $plan119()['selectedPlan']['tableLookupRequired']),
    'planner covering range order current source next119 residual empty' => static fn (TestRunner $t) => $t->same([], $plan119()['selectedPlan']['residualColumns']),
    'planner covering range order current source next119 stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan119()['selectedPlan']['stat4Used']),
    'planner covering range order current source next119 stat4 matched count' => static fn (TestRunner $t) => $t->same(5, $plan119()['selectedPlan']['stat4MatchedSamples']),
    'planner covering range order current source next119 estimated rows' => static fn (TestRunner $t) => $t->same(9, $plan119()['selectedPlan']['estimatedRows']),
    'planner covering range order current source next119 current next count' => static fn (TestRunner $t) => $t->same(5, count($plan119()['selectedPlan']['stat4CurrentNext'])),
    'planner covering range order current source next119 matched keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_security', 'plugin_slider'], $plan119()['cursorTape']['matchedKeys']),
    'planner covering range order current source next119 first next key' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan119()['cursorTape']['currentNextSegments'][0]['next']['key']),
    'planner covering range order current source next119 middle neq' => static fn (TestRunner $t) => $t->same(3, $plan119()['cursorTape']['currentNextSegments'][2]['neq']),
    'planner covering range order current source next119 last eof' => static fn (TestRunner $t) => $t->same('eof', $plan119()['cursorTape']['currentNextSegments'][4]['advance']),
    'planner covering range order current source next119 cursor source' => static fn (TestRunner $t) => $t->same('current', $plan119()['cursorTape']['source']),
    'planner covering range order current source next119 cursor index' => static fn (TestRunner $t) => $t->same('idx_wp_options_blog_autoload_name_value_next119', $plan119()['cursorTape']['indexName']),
    'planner covering range order current source next119 cursor root' => static fn (TestRunner $t) => $t->same(11910, $plan119()['cursorTape']['rootPage']),
    'planner covering range order current source next119 seek ge' => static fn (TestRunner $t) => $t->same('SeekGE', $plan119()['cursorTape']['seekOpcode']),
    'planner covering range order current source next119 stop ge exclusive' => static fn (TestRunner $t) => $t->same('IdxGE', $plan119()['cursorTape']['stopOpcode']),
    'planner covering range order current source next119 next opcode' => static fn (TestRunner $t) => $t->same('Next', $plan119()['cursorTape']['nextOpcode']),
    'planner covering range order current source next119 ascending' => static fn (TestRunner $t) => $t->same('ascending', $plan119()['cursorTape']['scanDirection']),
    'planner covering range order current source next119 output columns from index' => static fn (TestRunner $t) => $t->same([['column' => 'option_name', 'opcode' => 'Column', 'source' => 'index'], ['column' => 'option_value', 'opcode' => 'Column', 'source' => 'index'], ['column' => 'option_id', 'opcode' => 'Column', 'source' => 'index']], $plan119()['cursorTape']['outputColumns']),
    'planner covering range order current source next119 no deferred seek' => static fn (TestRunner $t) => $t->same(null, $plan119()['cursorTape']['deferredSeekOpcode']),
    'planner covering range order current source next119 sorter closed' => static fn (TestRunner $t) => $t->same(false, $plan119()['cursorTape']['sorterOpen']),
    'planner covering range order current source next119 table lookup elided' => static fn (TestRunner $t) => $t->same(true, $plan119()['tableLookupElided']),
    'planner covering range order current source next119 temp sort elided' => static fn (TestRunner $t) => $t->same(true, $plan119()['tempSortElided']),
    'planner covering range order current source next119 program opens index' => static fn (TestRunner $t) => $t->same('OpenRead', $plan119()['cursorTape']['program'][0]['opcode']),
    'planner covering range order current source next119 program seeks range' => static fn (TestRunner $t) => $t->same(['opcode' => 'SeekGE', 'source' => 'index', 'column' => 'option_name', 'key' => 'plugin_'], $plan119()['cursorTape']['program'][1]),
    'planner covering range order current source next119 program stops range' => static fn (TestRunner $t) => $t->same(['opcode' => 'IdxGE', 'source' => 'index', 'column' => 'option_name', 'key' => 'plugin_z'], $plan119()['cursorTape']['program'][2]),
    'planner covering range order current source next119 program reads option id' => static fn (TestRunner $t) => $t->same(['opcode' => 'Column', 'source' => 'index', 'column' => 'option_id'], $plan119()['cursorTape']['program'][5]),
    'planner covering range order current source next119 program advances' => static fn (TestRunner $t) => $t->same('Next', $plan119()['cursorTape']['program'][6]['opcode']),
    'planner covering range order current source next119 fence cookie' => static fn (TestRunner $t) => $t->same(119, $plan119()['currentSourceFence']['schemaCookie']),
    'planner covering range order current source next119 fence stat4' => static fn (TestRunner $t) => $t->same(62, $plan119()['currentSourceFence']['stat4Generation']),
    'planner covering range order current source next119 order signature' => static fn (TestRunner $t) => $t->same('option_name ASC, option_value ASC', $plan119()['currentSourceFence']['orderSignature']),
    'planner covering range order current source next119 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan119()['currentSourceFence']['indexSignature'])),
    'planner covering range order current source next119 predicate signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan119()['currentSourceFence']['predicateSignature'])),
    'planner covering range order current source next119 detail current' => static fn (TestRunner $t) => $t->contains('REPREPARE COVERING RANGE ORDER USING current-covering-range-order-next119', $plan119()['detail']),
    'planner covering range order current source next119 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan119()['dependency_closure']),
    'planner covering range order current source next119 non overlap' => static fn (TestRunner $t) => $t->contains('ordinary multicolumn covering range ORDER BY', $plan119()['non_overlap']),
    'planner covering range order current source next119 fresh reuses prepared' => static fn (TestRunner $t) => $t->same('prepared', $fresh119()['selectedSource']),
    'planner covering range order current source next119 fresh no reprepare' => static fn (TestRunner $t) => $t->same(false, $fresh119()['reprepareRequired']),
    'planner covering range order current source next119 fresh matched samples' => static fn (TestRunner $t) => $t->same(3, $fresh119()['selectedPlan']['stat4MatchedSamples']),
    'planner covering range order current source next119 missing covering requires next stage' => static fn (TestRunner $t) => $t->same('requires-next-stage', $missingCovering119()['status']),
    'planner covering range order current source next119 missing covering deferred seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $missingCovering119()['cursorTape']['deferredSeekOpcode']),
    'planner covering range order current source next119 missing covering table column source' => static fn (TestRunner $t) => $t->same('table', $missingCovering119()['cursorTape']['outputColumns'][2]['source']),
    'planner covering range order current source next119 block sort requires next stage' => static fn (TestRunner $t) => $t->same('requires-next-stage', $blockSort119()['status']),
    'planner covering range order current source next119 block sort recorded' => static fn (TestRunner $t) => $t->same(true, $blockSort119()['selectedPlan']['blockSortRequired']),
    'planner covering range order current source next119 block sort opens sorter' => static fn (TestRunner $t) => $t->same(true, $blockSort119()['cursorTape']['sorterOpen']),
    'planner covering range order current source next119 between inclusive stop gt' => static fn (TestRunner $t) => $t->same('IdxGT', $between119Plan()['cursorTape']['stopOpcode']),
    'planner covering range order current source next119 between matched keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_security'], $between119Plan()['cursorTape']['matchedKeys']),
    'planner covering range order current source next119 open lower seek gt' => static fn (TestRunner $t) => $t->same('SeekGT', $openLower119()['cursorTape']['seekOpcode']),
    'planner covering range order current source next119 open lower excludes boundary' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $openLower119()['cursorTape']['matchedKeys']),
    'planner covering range order current source next119 partial not implied falls to plain' => static fn (TestRunner $t) => $t->same('idx_wp_options_name_only_next119', $noPartial119()['selectedPlan']['name']),
    'planner covering range order current source next119 validates order direction' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan119(null, null, null, [['column' => 'option_name', 'direction' => 'SIDEWAYS']])),
    'planner covering range order current source next119 validates output columns' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan119(null, null, null, null, [''])),
    'planner covering range order current source next119 validates source indexes' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan119(null, ['name' => 'bad', 'schemaCookie' => 1, 'stat4Generation' => 1, 'indexes' => ['bad' => []]])),
    'planner covering range order current source next119 validates schema cookie' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan119(['name' => 'bad', 'schemaCookie' => -1, 'stat4Generation' => 1, 'indexes' => []])),
];

return $tests;
