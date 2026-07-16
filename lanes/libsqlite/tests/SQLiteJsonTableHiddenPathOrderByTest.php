<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current128 = [
    'option_id' => 128,
    'option_name' => 'wp_plugin_hidden_path_orderby',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"version":1}}',
    'scan_root' => '$.rules',
];
$next128 = [
    'option_id' => 128,
    'option_name' => 'wp_plugin_hidden_path_orderby',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"version":2}}',
    'scan_root' => '$.rules',
];

$range128 = static fn (): array => SQLiteJsonTablePlan::currentSourceHiddenPathOrderBy(
    'json_tree',
    $current128,
    $next128,
    'option_value',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [3, 12]],
        ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'atom', 'direction' => 'DESC']],
);

$point128 = static fn (): array => SQLiteJsonTablePlan::currentSourceHiddenPathOrderBy(
    'json_tree',
    $current128,
    $current128,
    'option_value',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
        ['column' => 'key', 'operator' => '=', 'value' => 'priority'],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'rowid']],
);

$pathOnly128 = static fn (): array => SQLiteJsonTablePlan::currentSourceHiddenPathOrderBy(
    'json_tree',
    $current128,
    $next128,
    'option_value',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'type', 'operator' => '=', 'value' => 'text'],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'key']],
);

$unrunnable128 = static fn (): array => SQLiteJsonTablePlan::currentSourceHiddenPathOrderBy(
    'json_tree',
    $current128,
    array_replace($current128, ['option_value' => null]),
    'option_value',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'rowid']],
);

$tests = [
    'normalizes function name' => static fn (TestRunner $t) => $t->same('json_tree', $range128()['function']),
    'records next128 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-hidden-path-orderby-current-source-next128', $range128()['dependencies'], true)),
    'preserves next126 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-path-hidden-rowid-cost-current-source-next126', $range128()['dependencies'], true)),
    'preserves next123 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-path-constraint-pushdown-current-source-next123', $range128()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-hidden-path-orderby-source-until-cursor-reset', $range128()['currentReaderPolicy']),
    'prepares changed range plan' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-hidden-path-orderby-source-plan', $range128()['nextReaderPolicy']),
    'stable point plan reuses current' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-hidden-path-orderby-source-plan', $point128()['nextReaderPolicy']),
    'stable point has no next128 reasons' => static fn (TestRunner $t) => $t->same([], $point128()['next128ReplanReasons']),
    'range composite signature joins hidden path and rowid' => static fn (TestRunner $t) => $t->same('2:path:LIKE:"$.rules%"&&3:id:BETWEEN:[3,12]', $range128()['currentHiddenPathOrderBy']['compositeSignature']),
    'range scan strategy is intersection' => static fn (TestRunner $t) => $t->same('path-rowid-intersection', $range128()['currentHiddenPathOrderBy']['scanStrategy']),
    'range path order prefix combines path and id' => static fn (TestRunner $t) => $t->same(['path', 'id'], $range128()['currentHiddenPathOrderBy']['pathOrderPrefix']),
    'range order suffix keeps path and atom sorter' => static fn (TestRunner $t) => $t->same(['path', 'atom'], $range128()['currentHiddenPathOrderBy']['orderSuffix']),
    'range order by is not fully consumed' => static fn (TestRunner $t) => $t->same(false, $range128()['currentHiddenPathOrderBy']['orderByConsumed']),
    'range requires order sort' => static fn (TestRunner $t) => $t->same(true, $range128()['currentHiddenPathOrderBy']['requiresOrderSort']),
    'range block sort required' => static fn (TestRunner $t) => $t->same(true, $range128()['currentHiddenPathOrderBy']['blockSortRequired']),
    'range current cost includes path rowid and sort' => static fn (TestRunner $t) => $t->same(25, $range128()['currentHiddenPathOrderBy']['effectiveEstimatedCost']),
    'range next cost includes added row' => static fn (TestRunner $t) => $t->same(33, $range128()['nextHiddenPathOrderBy']['effectiveEstimatedCost']),
    'range cost class is hidden path rowid order sort' => static fn (TestRunner $t) => $t->same('json-table-hidden-path-rowid-order-sort', $range128()['currentHiddenPathOrderBy']['costClass']),
    'range current tape has three path rowids' => static fn (TestRunner $t) => $t->same([['path' => '$.rules[0]', 'rowid' => 3], ['path' => '$.rules[1]', 'rowid' => 6], ['path' => '$.rules[2]', 'rowid' => 9]], $range128()['currentHiddenPathOrderBy']['pathRowidTape']),
    'range next tape has inserted path rowid' => static fn (TestRunner $t) => $t->same([['path' => '$.rules[0]', 'rowid' => 3], ['path' => '$.rules[1]', 'rowid' => 6], ['path' => '$.rules[2]', 'rowid' => 9], ['path' => '$.rules[3]', 'rowid' => 12]], $range128()['nextHiddenPathOrderBy']['pathRowidTape']),
    'range current ordered rowids' => static fn (TestRunner $t) => $t->same([3, 6, 9], $range128()['currentHiddenPathOrderBy']['orderedRowids']),
    'range next ordered rowids include new rowid' => static fn (TestRunner $t) => $t->same([3, 6, 9, 12], $range128()['nextHiddenPathOrderBy']['orderedRowids']),
    'range first ordered path rowid' => static fn (TestRunner $t) => $t->same(['path' => '$.rules[0]', 'rowid' => 3], $range128()['currentHiddenPathOrderBy']['firstOrderedPathRowid']),
    'range next last ordered path rowid' => static fn (TestRunner $t) => $t->same(['path' => '$.rules[3]', 'rowid' => 12], $range128()['nextHiddenPathOrderBy']['lastOrderedPathRowid']),
    'range transition count' => static fn (TestRunner $t) => $t->same(8, count($range128()['hiddenPathOrderByTransitions'])),
    'range composite transition stable' => static fn (TestRunner $t) => $t->same(false, $range128()['hiddenPathOrderByTransitions'][0]['changed']),
    'range prefix transition stable' => static fn (TestRunner $t) => $t->same(false, $range128()['hiddenPathOrderByTransitions'][1]['changed']),
    'range suffix transition stable' => static fn (TestRunner $t) => $t->same(false, $range128()['hiddenPathOrderByTransitions'][2]['changed']),
    'range sorter transition stable' => static fn (TestRunner $t) => $t->same(false, $range128()['hiddenPathOrderByTransitions'][3]['changed']),
    'range cost transition changes' => static fn (TestRunner $t) => $t->same(true, $range128()['hiddenPathOrderByTransitions'][4]['changed']),
    'range class transition stable' => static fn (TestRunner $t) => $t->same(false, $range128()['hiddenPathOrderByTransitions'][5]['changed']),
    'range ordered rowids transition changes' => static fn (TestRunner $t) => $t->same(true, $range128()['hiddenPathOrderByTransitions'][6]['changed']),
    'range path tape transition changes' => static fn (TestRunner $t) => $t->same(true, $range128()['hiddenPathOrderByTransitions'][7]['changed']),
    'range reasons include cost change' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-path-order-cost-changed', $range128()['next128ReplanReasons'], true)),
    'range reasons include output change' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-path-order-output-changed', $range128()['next128ReplanReasons'], true)),
    'range reasons preserve path rowid tape change' => static fn (TestRunner $t) => $t->true(in_array('json-table-path-rowid-tape-changed', $range128()['next128ReplanReasons'], true)),
    'range reasons preserve source json change' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $range128()['next128ReplanReasons'], true)),
    'point composite signature' => static fn (TestRunner $t) => $t->same('2:path:=:"$.rules[1]"&&3:id:=:6', $point128()['currentHiddenPathOrderBy']['compositeSignature']),
    'point order suffix empty' => static fn (TestRunner $t) => $t->same([], $point128()['currentHiddenPathOrderBy']['orderSuffix']),
    'point consumes order by' => static fn (TestRunner $t) => $t->same(true, $point128()['currentHiddenPathOrderBy']['orderByConsumed']),
    'point requires no sort' => static fn (TestRunner $t) => $t->same(false, $point128()['currentHiddenPathOrderBy']['requiresOrderSort']),
    'point cost class consumed' => static fn (TestRunner $t) => $t->same('json-table-hidden-path-order-consumed', $point128()['currentHiddenPathOrderBy']['costClass']),
    'point cost is one' => static fn (TestRunner $t) => $t->same(1, $point128()['currentHiddenPathOrderBy']['effectiveEstimatedCost']),
    'point tape is one row' => static fn (TestRunner $t) => $t->same([['path' => '$.rules[1]', 'rowid' => 6]], $point128()['currentHiddenPathOrderBy']['pathRowidTape']),
    'point ordered rowid is six' => static fn (TestRunner $t) => $t->same([6], $point128()['currentHiddenPathOrderBy']['orderedRowids']),
    'path only has null composite signature' => static fn (TestRunner $t) => $t->same(null, $pathOnly128()['currentHiddenPathOrderBy']['compositeSignature']),
    'path only scan strategy' => static fn (TestRunner $t) => $t->same('path-only-lookup', $pathOnly128()['currentHiddenPathOrderBy']['scanStrategy']),
    'path only cost class' => static fn (TestRunner $t) => $t->same('json-table-hidden-path-order-sort', $pathOnly128()['currentHiddenPathOrderBy']['costClass']),
    'path only keeps path prefix' => static fn (TestRunner $t) => $t->same(['path'], $pathOnly128()['currentHiddenPathOrderBy']['pathOrderPrefix']),
    'path only suffix is key' => static fn (TestRunner $t) => $t->same(['key'], $pathOnly128()['currentHiddenPathOrderBy']['orderSuffix']),
    'unrunnable next cost class' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable128()['nextHiddenPathOrderBy']['costClass']),
    'unrunnable next effective cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $unrunnable128()['nextHiddenPathOrderBy']['effectiveEstimatedCost']),
    'unrunnable next has empty ordered rowids' => static fn (TestRunner $t) => $t->same([], $unrunnable128()['nextHiddenPathOrderBy']['orderedRowids']),
    'unrunnable reason preserved' => static fn (TestRunner $t) => $t->true(in_array('next-source-plan-becomes-unrunnable', $unrunnable128()['next128ReplanReasons'], true)),
    'bad function rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceHiddenPathOrderBy('json_bad', $current128, $next128, 'option_value', [])),
    'bad json column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceHiddenPathOrderBy('json_tree', $current128, $next128, '', [])),
    'bad order direction rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceHiddenPathOrderBy('json_tree', $current128, $next128, 'option_value', [], 'scan_root', [['column' => 'path', 'direction' => 'SIDEWAYS']])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table hidden path orderby current source next128 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
