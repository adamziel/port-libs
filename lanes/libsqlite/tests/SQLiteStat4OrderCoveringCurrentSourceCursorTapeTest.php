<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteStat4OrderCoveringCurrentSourceNextPlan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$between = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$predicate = $and(
    $point('blog_id', 1),
    $point('autoload', 'yes'),
    $range('option_name', '>=', 'plugin_'),
    $range('option_name', '<', 'plugin_z')
);
$orderBy = [['column' => 'option_name'], ['column' => 'option_value']];
$neededColumns = ['option_name', 'option_value', 'autoload'];

$source = static function (array $overrides = []): array {
    return $overrides + [
        'name' => 'prepared-plugin-stat4-covering-order-cursor-tape',
        'schemaCookie' => 94,
        'stat4Generation' => 22,
        'coveringColumns' => ['autoload', 'blog_id', 'option_name', 'option_value'],
        'indexes' => [[
            'name' => 'idx_wp_options_blog_autoload_name_value_stat4_cursor_tape',
            'rootPage' => 9901,
            'estimatedRows' => 180,
            'distinctValues' => ['blog_id' => 2, 'autoload' => 3, 'option_name' => 140],
            'stat4Samples' => [
                ['neq' => '1 5 5 5', 'nlt' => '0 0 0 0', 'ndlt' => '0 0 0 0', 'sample' => [1, 'yes', 'plugin_alpha', 'a:1:{}']],
                ['neq' => '1 8 8 8', 'nlt' => '5 5 5 5', 'ndlt' => '1 1 1 1', 'sample' => [1, 'yes', 'plugin_forms', 'a:2:{}']],
                ['neq' => '1 11 11 11', 'nlt' => '13 13 13 13', 'ndlt' => '2 2 2 2', 'sample' => [1, 'yes', 'plugin_security', 'a:3:{}']],
            ],
            'sql' => "CREATE INDEX idx_wp_options_blog_autoload_name_value_stat4_cursor_tape ON wp_options(blog_id, autoload, option_name, option_value) WHERE option_name >= 'plugin_' AND option_name < 'plugin_z'",
        ], [
            'name' => 'idx_wp_options_blog_name_stat4_cursor_tape',
            'rootPage' => 9902,
            'estimatedRows' => 70,
            'stat4Samples' => [
                ['neq' => '1 4', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => [1, 'plugin_forms']],
                ['neq' => '1 5', 'nlt' => '4 4', 'ndlt' => '1 1', 'sample' => [1, 'plugin_security']],
            ],
            'sql' => 'CREATE INDEX idx_wp_options_blog_name_stat4_cursor_tape ON wp_options(blog_id, option_name)',
        ]],
    ];
};

$currentSource = static function () use ($source): array {
    $data = $source([
        'name' => 'current-plugin-stat4-covering-order-cursor-tape',
        'schemaCookie' => 99,
        'stat4Generation' => 24,
    ]);
    $data['indexes'][0]['rootPage'] = 9910;
    $data['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 2 2 2', 'nlt' => '0 0 0 0', 'ndlt' => '0 0 0 0', 'sample' => [1, 'yes', 'plugin_alpha', 'a:1:{}']],
        ['neq' => '1 3 3 3', 'nlt' => '2 2 2 2', 'ndlt' => '1 1 1 1', 'sample' => [1, 'yes', 'plugin_cache', 'a:2:{}']],
        ['neq' => '1 5 5 5', 'nlt' => '5 5 5 5', 'ndlt' => '2 2 2 2', 'sample' => [1, 'yes', 'plugin_forms', 'a:3:{}']],
        ['neq' => '1 4 4 4', 'nlt' => '10 10 10 10', 'ndlt' => '3 3 3 3', 'sample' => [1, 'yes', 'plugin_security', 'a:4:{}']],
        ['neq' => '1 2 2 2', 'nlt' => '14 14 14 14', 'ndlt' => '4 4 4 4', 'sample' => [1, 'yes', 'plugin_slider', 'a:5:{}']],
    ];

    return $data;
};

$cursorPlan = static fn (
    ?array $prepared = null,
    ?array $freshSource = null,
    ?array $predicateOverride = null,
    ?array $orderOverride = null,
    ?array $columnsOverride = null,
): array => SQLiteStat4OrderCoveringCurrentSourceNextPlan::materializeCoveringOrderCursorTape(
    $prepared ?? $source(),
    $freshSource ?? $currentSource(),
    $predicateOverride ?? $GLOBALS['predicate_cursor_tape'],
    $orderOverride ?? $GLOBALS['order_by_cursor_tape'],
    $columnsOverride ?? $GLOBALS['needed_columns_cursor_tape'],
);

$GLOBALS['predicate_cursor_tape'] = $predicate;
$GLOBALS['order_by_cursor_tape'] = $orderBy;
$GLOBALS['needed_columns_cursor_tape'] = $neededColumns;

$tests = [
    'planner stat4 order covering current source cursor-tape status ready' => static fn (TestRunner $t) => $t->same('covering-order-current-source-ready', $cursorPlan()['status']),
    'planner stat4 order covering current source cursor-tape selects current' => static fn (TestRunner $t) => $t->same('current', $cursorPlan()['selectedSource']),
    'planner stat4 order covering current source cursor-tape marks reprepare' => static fn (TestRunner $t) => $t->same(true, $cursorPlan()['reprepareRequired']),
    'planner stat4 order covering current source cursor-tape detects schema cookie' => static fn (TestRunner $t) => $t->same(true, $cursorPlan()['schemaCookieChanged']),
    'planner stat4 order covering current source cursor-tape detects stat4 generation' => static fn (TestRunner $t) => $t->same(true, $cursorPlan()['stat4GenerationChanged']),
    'planner stat4 order covering current source cursor-tape detects index signature' => static fn (TestRunner $t) => $t->same(true, $cursorPlan()['indexSignatureChanged']),
    'planner stat4 order covering current source cursor-tape keeps projection stable' => static fn (TestRunner $t) => $t->same(false, $cursorPlan()['projectionChanged']),
    'planner stat4 order covering current source cursor-tape selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_blog_autoload_name_value_stat4_cursor_tape', $cursorPlan()['selectedPlan']['name']),
    'planner stat4 order covering current source cursor-tape selected root page' => static fn (TestRunner $t) => $t->same(9910, $cursorPlan()['selectedPlan']['rootPage']),
    'planner stat4 order covering current source cursor-tape prepared root page' => static fn (TestRunner $t) => $t->same(9901, $cursorPlan()['preparedSource']['rootPage']),
    'planner stat4 order covering current source cursor-tape current root page' => static fn (TestRunner $t) => $t->same(9910, $cursorPlan()['currentSource']['rootPage']),
    'planner stat4 order covering current source cursor-tape covering order plan' => static fn (TestRunner $t) => $t->same(true, $cursorPlan()['coveringOrderPlan']),
    'planner stat4 order covering current source cursor-tape table lookup elided top' => static fn (TestRunner $t) => $t->same(true, $cursorPlan()['tableLookupElided']),
    'planner stat4 order covering current source cursor-tape temp sort elided top' => static fn (TestRunner $t) => $t->same(true, $cursorPlan()['tempSortElided']),
    'planner stat4 order covering current source cursor-tape cursor source' => static fn (TestRunner $t) => $t->same('current', $cursorPlan()['cursorTape']['source']),
    'planner stat4 order covering current source cursor-tape cursor index name' => static fn (TestRunner $t) => $t->same('idx_wp_options_blog_autoload_name_value_stat4_cursor_tape', $cursorPlan()['cursorTape']['indexName']),
    'planner stat4 order covering current source cursor-tape cursor root page' => static fn (TestRunner $t) => $t->same(9910, $cursorPlan()['cursorTape']['rootPage']),
    'planner stat4 order covering current source cursor-tape seek opcode' => static fn (TestRunner $t) => $t->same('SeekGE', $cursorPlan()['cursorTape']['seekOpcode']),
    'planner stat4 order covering current source cursor-tape stop opcode' => static fn (TestRunner $t) => $t->same('IdxGE', $cursorPlan()['cursorTape']['stopOpcode']),
    'planner stat4 order covering current source cursor-tape next opcode' => static fn (TestRunner $t) => $t->same('Next', $cursorPlan()['cursorTape']['nextOpcode']),
    'planner stat4 order covering current source cursor-tape scan direction' => static fn (TestRunner $t) => $t->same('ascending', $cursorPlan()['cursorTape']['scanDirection']),
    'planner stat4 order covering current source cursor-tape range column' => static fn (TestRunner $t) => $t->same('option_name', $cursorPlan()['cursorTape']['rangeColumn']),
    'planner stat4 order covering current source cursor-tape range lower' => static fn (TestRunner $t) => $t->same('plugin_', $cursorPlan()['cursorTape']['rangeLower']),
    'planner stat4 order covering current source cursor-tape range upper' => static fn (TestRunner $t) => $t->same('plugin_z', $cursorPlan()['cursorTape']['rangeUpper']),
    'planner stat4 order covering current source cursor-tape lower not exact' => static fn (TestRunner $t) => $t->same(false, $cursorPlan()['cursorTape']['rangeLowerExact']),
    'planner stat4 order covering current source cursor-tape upper not exact' => static fn (TestRunner $t) => $t->same(false, $cursorPlan()['cursorTape']['rangeUpperExact']),
    'planner stat4 order covering current source cursor-tape segment count' => static fn (TestRunner $t) => $t->same(5, $cursorPlan()['cursorTape']['countedCount']),
    'planner stat4 order covering current source cursor-tape matched keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_security', 'plugin_slider'], $cursorPlan()['cursorTape']['matchedKeys']),
    'planner stat4 order covering current source cursor-tape first segment key' => static fn (TestRunner $t) => $t->same('plugin_alpha', $cursorPlan()['cursorTape']['countedSegments'][0]['currentKey']),
    'planner stat4 order covering current source cursor-tape first segment next' => static fn (TestRunner $t) => $t->same('plugin_cache', $cursorPlan()['cursorTape']['countedSegments'][0]['nextKey']),
    'planner stat4 order covering current source cursor-tape first segment neq' => static fn (TestRunner $t) => $t->same(2, $cursorPlan()['cursorTape']['countedSegments'][0]['neq']),
    'planner stat4 order covering current source cursor-tape first segment nlt' => static fn (TestRunner $t) => $t->same(0, $cursorPlan()['cursorTape']['countedSegments'][0]['nlt']),
    'planner stat4 order covering current source cursor-tape first segment ndlt' => static fn (TestRunner $t) => $t->same(0, $cursorPlan()['cursorTape']['countedSegments'][0]['ndlt']),
    'planner stat4 order covering current source cursor-tape middle segment next' => static fn (TestRunner $t) => $t->same('plugin_security', $cursorPlan()['cursorTape']['countedSegments'][2]['nextKey']),
    'planner stat4 order covering current source cursor-tape last segment eof' => static fn (TestRunner $t) => $t->same('eof', $cursorPlan()['cursorTape']['countedSegments'][4]['advance']),
    'planner stat4 order covering current source cursor-tape last segment next null' => static fn (TestRunner $t) => $t->same(null, $cursorPlan()['cursorTape']['countedSegments'][4]['nextKey']),
    'planner stat4 order covering current source cursor-tape output column count' => static fn (TestRunner $t) => $t->same(3, count($cursorPlan()['cursorTape']['outputColumns'])),
    'planner stat4 order covering current source cursor-tape output column one' => static fn (TestRunner $t) => $t->same(['column' => 'option_name', 'opcode' => 'Column'], $cursorPlan()['cursorTape']['outputColumns'][0]),
    'planner stat4 order covering current source cursor-tape output column two' => static fn (TestRunner $t) => $t->same(['column' => 'option_value', 'opcode' => 'Column'], $cursorPlan()['cursorTape']['outputColumns'][1]),
    'planner stat4 order covering current source cursor-tape deferred seek absent' => static fn (TestRunner $t) => $t->same(null, $cursorPlan()['cursorTape']['deferredSeekOpcode']),
    'planner stat4 order covering current source cursor-tape sorter closed' => static fn (TestRunner $t) => $t->same(false, $cursorPlan()['cursorTape']['sorterOpen']),
    'planner stat4 order covering current source cursor-tape cursor table lookup elided' => static fn (TestRunner $t) => $t->same(true, $cursorPlan()['cursorTape']['tableLookupElided']),
    'planner stat4 order covering current source cursor-tape cursor temp sort elided' => static fn (TestRunner $t) => $t->same(true, $cursorPlan()['cursorTape']['tempSortElided']),
    'planner stat4 order covering current source cursor-tape program opens index' => static fn (TestRunner $t) => $t->same('OpenRead', $cursorPlan()['cursorTape']['program'][0]['opcode']),
    'planner stat4 order covering current source cursor-tape program seeks' => static fn (TestRunner $t) => $t->same('SeekGE', $cursorPlan()['cursorTape']['program'][1]['opcode']),
    'planner stat4 order covering current source cursor-tape program stops' => static fn (TestRunner $t) => $t->same('IdxGE', $cursorPlan()['cursorTape']['program'][2]['opcode']),
    'planner stat4 order covering current source cursor-tape program reads index column' => static fn (TestRunner $t) => $t->same(['opcode' => 'Column', 'source' => 'index', 'column' => 'option_name'], $cursorPlan()['cursorTape']['program'][3]),
    'planner stat4 order covering current source cursor-tape program advances' => static fn (TestRunner $t) => $t->same('Next', $cursorPlan()['cursorTape']['program'][6]['opcode']),
    'planner stat4 order covering current source cursor-tape program has no deferred seek' => static fn (TestRunner $t) => $t->same(false, in_array('DeferredSeek', array_column($cursorPlan()['cursorTape']['program'], 'opcode'), true)),
    'planner stat4 order covering current source cursor-tape program has no sorter' => static fn (TestRunner $t) => $t->same(false, in_array('SorterOpen', array_column($cursorPlan()['cursorTape']['program'], 'opcode'), true)),
    'planner stat4 order covering current source cursor-tape fence schema cookie' => static fn (TestRunner $t) => $t->same(99, $cursorPlan()['currentSourceFence']['schemaCookie']),
    'planner stat4 order covering current source cursor-tape fence stat4 generation' => static fn (TestRunner $t) => $t->same(24, $cursorPlan()['currentSourceFence']['stat4Generation']),
    'planner stat4 order covering current source cursor-tape fence order signature' => static fn (TestRunner $t) => $t->same('option_name ASC,option_value ASC', $cursorPlan()['currentSourceFence']['orderSignature']),
    'planner stat4 order covering current source cursor-tape dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $cursorPlan()['dependency_closure']),
    'planner stat4 order covering current source cursor-tape non overlap' => static fn (TestRunner $t) => $t->contains('cursor tape materialization', $cursorPlan()['non_overlap']),
    'planner stat4 order covering current source cursor-tape detail names current source' => static fn (TestRunner $t) => $t->contains('REPREPARE STAT4 ORDER COVERING USING CURRENT SOURCE current-plugin-stat4-covering-order-cursor-tape', $cursorPlan()['detail']),
];

$samePlan = static fn (): array => $cursorPlan(
    $source(),
    $source(['name' => 'current-same-plugin-stat4-covering-order-cursor-tape'])
);
$tests['planner stat4 order covering current source cursor-tape reuses prepared when fresh'] = static fn (TestRunner $t) => $t->same('prepared', $samePlan()['selectedSource']);
$tests['planner stat4 order covering current source cursor-tape fresh source status ready'] = static fn (TestRunner $t) => $t->same('covering-order-current-source-ready', $samePlan()['status']);
$tests['planner stat4 order covering current source cursor-tape fresh segment count'] = static fn (TestRunner $t) => $t->same(3, $samePlan()['cursorTape']['countedCount']);
$tests['planner stat4 order covering current source cursor-tape fresh matched keys'] = static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_forms', 'plugin_security'], $samePlan()['cursorTape']['matchedKeys']);

$descPlan = static fn (): array => $cursorPlan(null, null, null, [['column' => 'option_name', 'direction' => 'DESC']]);
$tests['planner stat4 order covering current source cursor-tape desc requires next stage'] = static fn (TestRunner $t) => $t->same('requires-next-stage', $descPlan()['status']);
$tests['planner stat4 order covering current source cursor-tape desc direction'] = static fn (TestRunner $t) => $t->same('descending', $descPlan()['cursorTape']['scanDirection']);
$tests['planner stat4 order covering current source cursor-tape desc opcode'] = static fn (TestRunner $t) => $t->same('Prev', $descPlan()['cursorTape']['nextOpcode']);
$tests['planner stat4 order covering current source cursor-tape desc deferred seek'] = static fn (TestRunner $t) => $t->same('DeferredSeek', $descPlan()['cursorTape']['deferredSeekOpcode']);
$tests['planner stat4 order covering current source cursor-tape desc sorter opens'] = static fn (TestRunner $t) => $t->same(true, $descPlan()['cursorTape']['sorterOpen']);
$tests['planner stat4 order covering current source cursor-tape desc program includes sorter'] = static fn (TestRunner $t) => $t->same(true, in_array('SorterOpen', array_column($descPlan()['cursorTape']['program'], 'opcode'), true));

$betweenPlan = static fn (): array => $cursorPlan(
    null,
    null,
    $and($point('blog_id', 1), $point('autoload', 'yes'), $between('option_name', 'plugin_cache', 'plugin_security')),
);
$tests['planner stat4 order covering current source cursor-tape between stop inclusive'] = static fn (TestRunner $t) => $t->same('IdxGT', $betweenPlan()['cursorTape']['stopOpcode']);
$tests['planner stat4 order covering current source cursor-tape between lower exact'] = static fn (TestRunner $t) => $t->same(true, $betweenPlan()['cursorTape']['rangeLowerExact']);
$tests['planner stat4 order covering current source cursor-tape between upper exact'] = static fn (TestRunner $t) => $t->same(true, $betweenPlan()['cursorTape']['rangeUpperExact']);
$tests['planner stat4 order covering current source cursor-tape between matched keys'] = static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_security'], $betweenPlan()['cursorTape']['matchedKeys']);

$openLowerPlan = static fn (): array => $cursorPlan(
    null,
    null,
    $and($point('blog_id', 1), $point('autoload', 'yes'), $range('option_name', '>', 'plugin_cache')),
);
$tests['planner stat4 order covering current source cursor-tape open lower seek gt'] = static fn (TestRunner $t) => $t->same('SeekGT', $openLowerPlan()['cursorTape']['seekOpcode']);
$tests['planner stat4 order covering current source cursor-tape open lower key'] = static fn (TestRunner $t) => $t->same('plugin_cache', $openLowerPlan()['cursorTape']['rangeLower']);
$tests['planner stat4 order covering current source cursor-tape open lower matched excludes boundary'] = static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_security'], $openLowerPlan()['cursorTape']['matchedKeys']);

$missingColumnPlan = static fn (): array => $cursorPlan(null, null, null, null, ['option_name', 'missing_meta']);
$tests['planner stat4 order covering current source cursor-tape missing covering requires next stage'] = static fn (TestRunner $t) => $t->same('requires-next-stage', $missingColumnPlan()['status']);
$tests['planner stat4 order covering current source cursor-tape missing covering deferred seek'] = static fn (TestRunner $t) => $t->same('DeferredSeek', $missingColumnPlan()['cursorTape']['deferredSeekOpcode']);
$tests['planner stat4 order covering current source cursor-tape missing covering table source'] = static fn (TestRunner $t) => $t->same('table', $missingColumnPlan()['cursorTape']['program'][4]['source']);

$tests['planner stat4 order covering current source cursor-tape validates output columns'] = static function (TestRunner $t) use ($source, $currentSource): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4OrderCoveringCurrentSourceNextPlan::materializeCoveringOrderCursorTape($source(), $currentSource(), $GLOBALS['predicate_cursor_tape'], $GLOBALS['order_by_cursor_tape'], ['']));
};

return $tests;
