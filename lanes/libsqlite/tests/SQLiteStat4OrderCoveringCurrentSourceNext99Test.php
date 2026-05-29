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

$source99 = static function (array $overrides = []): array {
    return $overrides + [
        'name' => 'prepared-plugin-stat4-covering-order-next99',
        'schemaCookie' => 94,
        'stat4Generation' => 22,
        'coveringColumns' => ['autoload', 'blog_id', 'option_name', 'option_value'],
        'indexes' => [[
            'name' => 'idx_wp_options_blog_autoload_name_value_stat4_next99',
            'rootPage' => 9901,
            'estimatedRows' => 180,
            'distinctValues' => ['blog_id' => 2, 'autoload' => 3, 'option_name' => 140],
            'stat4Samples' => [
                ['neq' => '1 5 5 5', 'nlt' => '0 0 0 0', 'ndlt' => '0 0 0 0', 'sample' => [1, 'yes', 'plugin_alpha', 'a:1:{}']],
                ['neq' => '1 8 8 8', 'nlt' => '5 5 5 5', 'ndlt' => '1 1 1 1', 'sample' => [1, 'yes', 'plugin_forms', 'a:2:{}']],
                ['neq' => '1 11 11 11', 'nlt' => '13 13 13 13', 'ndlt' => '2 2 2 2', 'sample' => [1, 'yes', 'plugin_security', 'a:3:{}']],
            ],
            'sql' => "CREATE INDEX idx_wp_options_blog_autoload_name_value_stat4_next99 ON wp_options(blog_id, autoload, option_name, option_value) WHERE option_name >= 'plugin_' AND option_name < 'plugin_z'",
        ], [
            'name' => 'idx_wp_options_blog_name_stat4_next99',
            'rootPage' => 9902,
            'estimatedRows' => 70,
            'stat4Samples' => [
                ['neq' => '1 4', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => [1, 'plugin_forms']],
                ['neq' => '1 5', 'nlt' => '4 4', 'ndlt' => '1 1', 'sample' => [1, 'plugin_security']],
            ],
            'sql' => 'CREATE INDEX idx_wp_options_blog_name_stat4_next99 ON wp_options(blog_id, option_name)',
        ]],
    ];
};

$current99 = static function () use ($source99): array {
    $data = $source99([
        'name' => 'current-plugin-stat4-covering-order-next99',
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

$plan99 = static fn (
    ?array $prepared = null,
    ?array $current = null,
    ?array $predicateOverride = null,
    ?array $orderOverride = null,
    ?array $columnsOverride = null,
): array => SQLiteStat4OrderCoveringCurrentSourceNextPlan::materializeNext99(
    $prepared ?? $source99(),
    $current ?? $current99(),
    $predicateOverride ?? $GLOBALS['predicate_next99'],
    $orderOverride ?? $GLOBALS['order_by_next99'],
    $columnsOverride ?? $GLOBALS['needed_columns_next99'],
);

$GLOBALS['predicate_next99'] = $predicate;
$GLOBALS['order_by_next99'] = $orderBy;
$GLOBALS['needed_columns_next99'] = $neededColumns;

$tests = [
    'planner stat4 order covering current source next99 status ready' => static fn (TestRunner $t) => $t->same('covering-order-current-source-ready', $plan99()['status']),
    'planner stat4 order covering current source next99 selects current' => static fn (TestRunner $t) => $t->same('current', $plan99()['selectedSource']),
    'planner stat4 order covering current source next99 marks reprepare' => static fn (TestRunner $t) => $t->same(true, $plan99()['reprepareRequired']),
    'planner stat4 order covering current source next99 detects schema cookie' => static fn (TestRunner $t) => $t->same(true, $plan99()['schemaCookieChanged']),
    'planner stat4 order covering current source next99 detects stat4 generation' => static fn (TestRunner $t) => $t->same(true, $plan99()['stat4GenerationChanged']),
    'planner stat4 order covering current source next99 detects index signature' => static fn (TestRunner $t) => $t->same(true, $plan99()['indexSignatureChanged']),
    'planner stat4 order covering current source next99 keeps projection stable' => static fn (TestRunner $t) => $t->same(false, $plan99()['projectionChanged']),
    'planner stat4 order covering current source next99 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_blog_autoload_name_value_stat4_next99', $plan99()['selectedPlan']['name']),
    'planner stat4 order covering current source next99 selected root page' => static fn (TestRunner $t) => $t->same(9910, $plan99()['selectedPlan']['rootPage']),
    'planner stat4 order covering current source next99 prepared root page' => static fn (TestRunner $t) => $t->same(9901, $plan99()['preparedSource']['rootPage']),
    'planner stat4 order covering current source next99 current root page' => static fn (TestRunner $t) => $t->same(9910, $plan99()['currentSource']['rootPage']),
    'planner stat4 order covering current source next99 covering order plan' => static fn (TestRunner $t) => $t->same(true, $plan99()['coveringOrderPlan']),
    'planner stat4 order covering current source next99 table lookup elided top' => static fn (TestRunner $t) => $t->same(true, $plan99()['tableLookupElided']),
    'planner stat4 order covering current source next99 temp sort elided top' => static fn (TestRunner $t) => $t->same(true, $plan99()['tempSortElided']),
    'planner stat4 order covering current source next99 cursor source' => static fn (TestRunner $t) => $t->same('current', $plan99()['cursorTape']['source']),
    'planner stat4 order covering current source next99 cursor index name' => static fn (TestRunner $t) => $t->same('idx_wp_options_blog_autoload_name_value_stat4_next99', $plan99()['cursorTape']['indexName']),
    'planner stat4 order covering current source next99 cursor root page' => static fn (TestRunner $t) => $t->same(9910, $plan99()['cursorTape']['rootPage']),
    'planner stat4 order covering current source next99 seek opcode' => static fn (TestRunner $t) => $t->same('SeekGE', $plan99()['cursorTape']['seekOpcode']),
    'planner stat4 order covering current source next99 stop opcode' => static fn (TestRunner $t) => $t->same('IdxGE', $plan99()['cursorTape']['stopOpcode']),
    'planner stat4 order covering current source next99 next opcode' => static fn (TestRunner $t) => $t->same('Next', $plan99()['cursorTape']['nextOpcode']),
    'planner stat4 order covering current source next99 scan direction' => static fn (TestRunner $t) => $t->same('ascending', $plan99()['cursorTape']['scanDirection']),
    'planner stat4 order covering current source next99 range column' => static fn (TestRunner $t) => $t->same('option_name', $plan99()['cursorTape']['rangeColumn']),
    'planner stat4 order covering current source next99 range lower' => static fn (TestRunner $t) => $t->same('plugin_', $plan99()['cursorTape']['rangeLower']),
    'planner stat4 order covering current source next99 range upper' => static fn (TestRunner $t) => $t->same('plugin_z', $plan99()['cursorTape']['rangeUpper']),
    'planner stat4 order covering current source next99 lower not exact' => static fn (TestRunner $t) => $t->same(false, $plan99()['cursorTape']['rangeLowerExact']),
    'planner stat4 order covering current source next99 upper not exact' => static fn (TestRunner $t) => $t->same(false, $plan99()['cursorTape']['rangeUpperExact']),
    'planner stat4 order covering current source next99 segment count' => static fn (TestRunner $t) => $t->same(5, $plan99()['cursorTape']['currentNextCount']),
    'planner stat4 order covering current source next99 matched keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_security', 'plugin_slider'], $plan99()['cursorTape']['matchedKeys']),
    'planner stat4 order covering current source next99 first segment key' => static fn (TestRunner $t) => $t->same('plugin_alpha', $plan99()['cursorTape']['currentNextSegments'][0]['currentKey']),
    'planner stat4 order covering current source next99 first segment next' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan99()['cursorTape']['currentNextSegments'][0]['nextKey']),
    'planner stat4 order covering current source next99 first segment neq' => static fn (TestRunner $t) => $t->same(2, $plan99()['cursorTape']['currentNextSegments'][0]['neq']),
    'planner stat4 order covering current source next99 first segment nlt' => static fn (TestRunner $t) => $t->same(0, $plan99()['cursorTape']['currentNextSegments'][0]['nlt']),
    'planner stat4 order covering current source next99 first segment ndlt' => static fn (TestRunner $t) => $t->same(0, $plan99()['cursorTape']['currentNextSegments'][0]['ndlt']),
    'planner stat4 order covering current source next99 middle segment next' => static fn (TestRunner $t) => $t->same('plugin_security', $plan99()['cursorTape']['currentNextSegments'][2]['nextKey']),
    'planner stat4 order covering current source next99 last segment eof' => static fn (TestRunner $t) => $t->same('eof', $plan99()['cursorTape']['currentNextSegments'][4]['advance']),
    'planner stat4 order covering current source next99 last segment next null' => static fn (TestRunner $t) => $t->same(null, $plan99()['cursorTape']['currentNextSegments'][4]['nextKey']),
    'planner stat4 order covering current source next99 output column count' => static fn (TestRunner $t) => $t->same(3, count($plan99()['cursorTape']['outputColumns'])),
    'planner stat4 order covering current source next99 output column one' => static fn (TestRunner $t) => $t->same(['column' => 'option_name', 'opcode' => 'Column'], $plan99()['cursorTape']['outputColumns'][0]),
    'planner stat4 order covering current source next99 output column two' => static fn (TestRunner $t) => $t->same(['column' => 'option_value', 'opcode' => 'Column'], $plan99()['cursorTape']['outputColumns'][1]),
    'planner stat4 order covering current source next99 deferred seek absent' => static fn (TestRunner $t) => $t->same(null, $plan99()['cursorTape']['deferredSeekOpcode']),
    'planner stat4 order covering current source next99 sorter closed' => static fn (TestRunner $t) => $t->same(false, $plan99()['cursorTape']['sorterOpen']),
    'planner stat4 order covering current source next99 cursor table lookup elided' => static fn (TestRunner $t) => $t->same(true, $plan99()['cursorTape']['tableLookupElided']),
    'planner stat4 order covering current source next99 cursor temp sort elided' => static fn (TestRunner $t) => $t->same(true, $plan99()['cursorTape']['tempSortElided']),
    'planner stat4 order covering current source next99 program opens index' => static fn (TestRunner $t) => $t->same('OpenRead', $plan99()['cursorTape']['program'][0]['opcode']),
    'planner stat4 order covering current source next99 program seeks' => static fn (TestRunner $t) => $t->same('SeekGE', $plan99()['cursorTape']['program'][1]['opcode']),
    'planner stat4 order covering current source next99 program stops' => static fn (TestRunner $t) => $t->same('IdxGE', $plan99()['cursorTape']['program'][2]['opcode']),
    'planner stat4 order covering current source next99 program reads index column' => static fn (TestRunner $t) => $t->same(['opcode' => 'Column', 'source' => 'index', 'column' => 'option_name'], $plan99()['cursorTape']['program'][3]),
    'planner stat4 order covering current source next99 program advances' => static fn (TestRunner $t) => $t->same('Next', $plan99()['cursorTape']['program'][6]['opcode']),
    'planner stat4 order covering current source next99 program has no deferred seek' => static fn (TestRunner $t) => $t->same(false, in_array('DeferredSeek', array_column($plan99()['cursorTape']['program'], 'opcode'), true)),
    'planner stat4 order covering current source next99 program has no sorter' => static fn (TestRunner $t) => $t->same(false, in_array('SorterOpen', array_column($plan99()['cursorTape']['program'], 'opcode'), true)),
    'planner stat4 order covering current source next99 fence schema cookie' => static fn (TestRunner $t) => $t->same(99, $plan99()['currentSourceFence']['schemaCookie']),
    'planner stat4 order covering current source next99 fence stat4 generation' => static fn (TestRunner $t) => $t->same(24, $plan99()['currentSourceFence']['stat4Generation']),
    'planner stat4 order covering current source next99 fence order signature' => static fn (TestRunner $t) => $t->same('option_name ASC,option_value ASC', $plan99()['currentSourceFence']['orderSignature']),
    'planner stat4 order covering current source next99 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan99()['dependency_closure']),
    'planner stat4 order covering current source next99 non overlap' => static fn (TestRunner $t) => $t->contains('cursor tape materialization', $plan99()['non_overlap']),
    'planner stat4 order covering current source next99 detail names current source' => static fn (TestRunner $t) => $t->contains('REPREPARE STAT4 ORDER COVERING USING CURRENT SOURCE current-plugin-stat4-covering-order-next99', $plan99()['detail']),
];

$samePlan = static fn (): array => $plan99(
    $source99(),
    $source99(['name' => 'current-same-plugin-stat4-covering-order-next99'])
);
$tests['planner stat4 order covering current source next99 reuses prepared when fresh'] = static fn (TestRunner $t) => $t->same('prepared', $samePlan()['selectedSource']);
$tests['planner stat4 order covering current source next99 fresh source status ready'] = static fn (TestRunner $t) => $t->same('covering-order-current-source-ready', $samePlan()['status']);
$tests['planner stat4 order covering current source next99 fresh segment count'] = static fn (TestRunner $t) => $t->same(3, $samePlan()['cursorTape']['currentNextCount']);
$tests['planner stat4 order covering current source next99 fresh matched keys'] = static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_forms', 'plugin_security'], $samePlan()['cursorTape']['matchedKeys']);

$descPlan = static fn (): array => $plan99(null, null, null, [['column' => 'option_name', 'direction' => 'DESC']]);
$tests['planner stat4 order covering current source next99 desc requires next stage'] = static fn (TestRunner $t) => $t->same('requires-next-stage', $descPlan()['status']);
$tests['planner stat4 order covering current source next99 desc direction'] = static fn (TestRunner $t) => $t->same('descending', $descPlan()['cursorTape']['scanDirection']);
$tests['planner stat4 order covering current source next99 desc opcode'] = static fn (TestRunner $t) => $t->same('Prev', $descPlan()['cursorTape']['nextOpcode']);
$tests['planner stat4 order covering current source next99 desc deferred seek'] = static fn (TestRunner $t) => $t->same('DeferredSeek', $descPlan()['cursorTape']['deferredSeekOpcode']);
$tests['planner stat4 order covering current source next99 desc sorter opens'] = static fn (TestRunner $t) => $t->same(true, $descPlan()['cursorTape']['sorterOpen']);
$tests['planner stat4 order covering current source next99 desc program includes sorter'] = static fn (TestRunner $t) => $t->same(true, in_array('SorterOpen', array_column($descPlan()['cursorTape']['program'], 'opcode'), true));

$betweenPlan = static fn (): array => $plan99(
    null,
    null,
    $and($point('blog_id', 1), $point('autoload', 'yes'), $between('option_name', 'plugin_cache', 'plugin_security')),
);
$tests['planner stat4 order covering current source next99 between stop inclusive'] = static fn (TestRunner $t) => $t->same('IdxGT', $betweenPlan()['cursorTape']['stopOpcode']);
$tests['planner stat4 order covering current source next99 between lower exact'] = static fn (TestRunner $t) => $t->same(true, $betweenPlan()['cursorTape']['rangeLowerExact']);
$tests['planner stat4 order covering current source next99 between upper exact'] = static fn (TestRunner $t) => $t->same(true, $betweenPlan()['cursorTape']['rangeUpperExact']);
$tests['planner stat4 order covering current source next99 between matched keys'] = static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_security'], $betweenPlan()['cursorTape']['matchedKeys']);

$openLowerPlan = static fn (): array => $plan99(
    null,
    null,
    $and($point('blog_id', 1), $point('autoload', 'yes'), $range('option_name', '>', 'plugin_cache')),
);
$tests['planner stat4 order covering current source next99 open lower seek gt'] = static fn (TestRunner $t) => $t->same('SeekGT', $openLowerPlan()['cursorTape']['seekOpcode']);
$tests['planner stat4 order covering current source next99 open lower key'] = static fn (TestRunner $t) => $t->same('plugin_cache', $openLowerPlan()['cursorTape']['rangeLower']);
$tests['planner stat4 order covering current source next99 open lower matched excludes boundary'] = static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_security'], $openLowerPlan()['cursorTape']['matchedKeys']);

$missingColumnPlan = static fn (): array => $plan99(null, null, null, null, ['option_name', 'missing_meta']);
$tests['planner stat4 order covering current source next99 missing covering requires next stage'] = static fn (TestRunner $t) => $t->same('requires-next-stage', $missingColumnPlan()['status']);
$tests['planner stat4 order covering current source next99 missing covering deferred seek'] = static fn (TestRunner $t) => $t->same('DeferredSeek', $missingColumnPlan()['cursorTape']['deferredSeekOpcode']);
$tests['planner stat4 order covering current source next99 missing covering table source'] = static fn (TestRunner $t) => $t->same('table', $missingColumnPlan()['cursorTape']['program'][4]['source']);

$tests['planner stat4 order covering current source next99 validates output columns'] = static function (TestRunner $t) use ($source99, $current99): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4OrderCoveringCurrentSourceNextPlan::materializeNext99($source99(), $current99(), $GLOBALS['predicate_next99'], $GLOBALS['order_by_next99'], ['']));
};

return $tests;
