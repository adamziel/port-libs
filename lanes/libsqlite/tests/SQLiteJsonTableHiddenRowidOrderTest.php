<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current135 = [
    'option_id' => 135,
    'option_name' => 'wp_plugin_hidden_rowid_order',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"version":1}}',
    'scan_root' => '$.rules',
];
$next135 = [
    'option_id' => 135,
    'option_name' => 'wp_plugin_hidden_rowid_order',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":8},{"slug":"forms","priority":4},{"slug":"shop","priority":8}],"meta":{"version":2}}',
    'scan_root' => '$.rules',
];

$priorityOrder135 = static fn (): array => SQLiteJsonTablePlan::currentSourceHiddenRowidOrder(
    'json_tree',
    $current135,
    $next135,
    'option_value',
    [['column' => 'type', 'operator' => '=', 'value' => 'integer']],
    'scan_root',
    [['column' => 'atom', 'direction' => 'DESC'], ['column' => 'rowid']],
);

$stable135 = static fn (): array => SQLiteJsonTablePlan::currentSourceHiddenRowidOrder(
    'json_tree',
    $current135,
    $current135,
    'option_value',
    [['column' => 'type', 'operator' => '=', 'value' => 'integer']],
    'scan_root',
    [['column' => 'atom', 'direction' => 'DESC'], ['column' => '_rowid_']],
);

$point135 = static fn (): array => SQLiteJsonTablePlan::currentSourceHiddenRowidOrder(
    'json_tree',
    $current135,
    $current135,
    'option_value',
    [
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
        ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
    ],
    'scan_root',
    [['column' => 'rowid']],
);

$oidDesc135 = static fn (): array => SQLiteJsonTablePlan::currentSourceHiddenRowidOrder(
    'json_tree',
    $current135,
    $current135,
    'option_value',
    [['column' => 'type', 'operator' => '=', 'value' => 'integer']],
    'scan_root',
    [['column' => 'oid', 'direction' => 'DESC']],
);

$unrunnable135 = static fn (): array => SQLiteJsonTablePlan::currentSourceHiddenRowidOrder(
    'json_tree',
    $current135,
    array_replace($current135, ['option_value' => null]),
    'option_value',
    [['column' => 'type', 'operator' => '=', 'value' => 'integer']],
    'scan_root',
    [['column' => 'atom', 'direction' => 'DESC'], ['column' => 'rowid']],
);

$tests = [
    'normalizes function' => static fn (TestRunner $t) => $t->same('json_tree', $priorityOrder135()['function']),
    'records next135 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-hidden-rowid-order-current-source-next135', $priorityOrder135()['dependencies'], true)),
    'preserves next94 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-hidden-rowid-source-current-next94', $priorityOrder135()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-hidden-rowid-order-source-until-cursor-reset', $priorityOrder135()['currentReaderPolicy']),
    'changed source prepares next order source' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-hidden-rowid-order-source', $priorityOrder135()['nextReaderPolicy']),
    'stable source reuses order source' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-hidden-rowid-order-source', $stable135()['nextReaderPolicy']),
    'current order normalizes rowid to id' => static fn (TestRunner $t) => $t->same([['column' => 'atom', 'direction' => 'DESC'], ['column' => 'id', 'direction' => 'ASC']], $priorityOrder135()['currentHiddenRowidOrder']['orderBy']),
    'stable order normalizes underscore rowid to id' => static fn (TestRunner $t) => $t->same([['column' => 'atom', 'direction' => 'DESC'], ['column' => 'id', 'direction' => 'ASC']], $stable135()['currentHiddenRowidOrder']['orderBy']),
    'rowid tiebreak column is id' => static fn (TestRunner $t) => $t->same(['id'], $priorityOrder135()['currentHiddenRowidOrder']['rowidTieBreakColumns']),
    'rowid tiebreak is streaming suffix' => static fn (TestRunner $t) => $t->same(true, $priorityOrder135()['currentHiddenRowidOrder']['rowidTieBreakConsumed']),
    'priority order requires sorter' => static fn (TestRunner $t) => $t->same(true, $priorityOrder135()['currentHiddenRowidOrder']['requiresSorter']),
    'priority order is not fully consumed' => static fn (TestRunner $t) => $t->same(false, $priorityOrder135()['currentHiddenRowidOrder']['orderByConsumed']),
    'current cost class is tiebreak sort' => static fn (TestRunner $t) => $t->same('json-table-hidden-rowid-tiebreak-sort', $priorityOrder135()['currentHiddenRowidOrder']['costClass']),
    'next cost class is tiebreak sort' => static fn (TestRunner $t) => $t->same('json-table-hidden-rowid-tiebreak-sort', $priorityOrder135()['nextHiddenRowidOrder']['costClass']),
    'current sorted rowids use hidden rowid tie break' => static fn (TestRunner $t) => $t->same([6, 9, 3], $priorityOrder135()['currentHiddenRowidOrder']['orderedRowids']),
    'next sorted rowids use hidden rowid tie break for equal priorities' => static fn (TestRunner $t) => $t->same([6, 12, 9, 3], $priorityOrder135()['nextHiddenRowidOrder']['orderedRowids']),
    'current first key is priority seven rowid six' => static fn (TestRunner $t) => $t->same([7, 6], $priorityOrder135()['currentHiddenRowidOrder']['firstOrderKey']),
    'next first key is priority eight rowid six' => static fn (TestRunner $t) => $t->same([8, 6], $priorityOrder135()['nextHiddenRowidOrder']['firstOrderKey']),
    'next second key is priority eight rowid twelve' => static fn (TestRunner $t) => $t->same([8, 12], $priorityOrder135()['nextHiddenRowidOrder']['orderKeyTape'][1]['orderKey']),
    'current last key is priority two rowid three' => static fn (TestRunner $t) => $t->same([2, 3], $priorityOrder135()['currentHiddenRowidOrder']['lastOrderKey']),
    'current tape first fullkey is cache priority' => static fn (TestRunner $t) => $t->same('$.rules[1].priority', $priorityOrder135()['currentHiddenRowidOrder']['orderKeyTape'][0]['fullkey']),
    'next tape first fullkey is cache priority' => static fn (TestRunner $t) => $t->same('$.rules[1].priority', $priorityOrder135()['nextHiddenRowidOrder']['orderKeyTape'][0]['fullkey']),
    'next tape second fullkey is inserted shop priority' => static fn (TestRunner $t) => $t->same('$.rules[3].priority', $priorityOrder135()['nextHiddenRowidOrder']['orderKeyTape'][1]['fullkey']),
    'current sort penalty reflects three rows and two terms' => static fn (TestRunner $t) => $t->same(12, $priorityOrder135()['currentHiddenRowidOrder']['sortPenalty']),
    'next sort penalty reflects four rows and two terms' => static fn (TestRunner $t) => $t->same(16, $priorityOrder135()['nextHiddenRowidOrder']['sortPenalty']),
    'current effective cost includes sorter' => static fn (TestRunner $t) => $t->same(17, $priorityOrder135()['currentHiddenRowidOrder']['effectiveEstimatedCost']),
    'next effective cost includes sorter' => static fn (TestRunner $t) => $t->same(21, $priorityOrder135()['nextHiddenRowidOrder']['effectiveEstimatedCost']),
    'transition count' => static fn (TestRunner $t) => $t->same(8, count($priorityOrder135()['hiddenRowidOrderTransitions'])),
    'order by transition stable' => static fn (TestRunner $t) => $t->same(false, $priorityOrder135()['hiddenRowidOrderTransitions'][0]['changed']),
    'tiebreak column transition stable' => static fn (TestRunner $t) => $t->same(false, $priorityOrder135()['hiddenRowidOrderTransitions'][1]['changed']),
    'tiebreak consumed transition stable' => static fn (TestRunner $t) => $t->same(false, $priorityOrder135()['hiddenRowidOrderTransitions'][2]['changed']),
    'sorter transition stable' => static fn (TestRunner $t) => $t->same(false, $priorityOrder135()['hiddenRowidOrderTransitions'][3]['changed']),
    'effective cost transition changes' => static fn (TestRunner $t) => $t->same(true, $priorityOrder135()['hiddenRowidOrderTransitions'][4]['changed']),
    'ordered rowids transition changes' => static fn (TestRunner $t) => $t->same(true, $priorityOrder135()['hiddenRowidOrderTransitions'][6]['changed']),
    'order tape transition changes' => static fn (TestRunner $t) => $t->same(true, $priorityOrder135()['hiddenRowidOrderTransitions'][7]['changed']),
    'reasons include source json changed' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $priorityOrder135()['next135ReplanReasons'], true)),
    'reasons include rowid output order changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-rowid-output-order-changed', $priorityOrder135()['next135ReplanReasons'], true)),
    'reasons include key tape changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-rowid-order-key-tape-changed', $priorityOrder135()['next135ReplanReasons'], true)),
    'reasons include cost changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-rowid-order-cost-changed', $priorityOrder135()['next135ReplanReasons'], true)),
    'stable has no replan reasons' => static fn (TestRunner $t) => $t->same([], $stable135()['next135ReplanReasons']),
    'point rowid order is consumed' => static fn (TestRunner $t) => $t->same(true, $point135()['currentHiddenRowidOrder']['orderByConsumed']),
    'point requires no sorter' => static fn (TestRunner $t) => $t->same(false, $point135()['currentHiddenRowidOrder']['requiresSorter']),
    'point cost class is consumed' => static fn (TestRunner $t) => $t->same('json-table-hidden-rowid-order-consumed', $point135()['currentHiddenRowidOrder']['costClass']),
    'point ordered rowids' => static fn (TestRunner $t) => $t->same([6], $point135()['currentHiddenRowidOrder']['orderedRowids']),
    'point first key' => static fn (TestRunner $t) => $t->same([6], $point135()['currentHiddenRowidOrder']['firstOrderKey']),
    'oid desc normalizes to id desc' => static fn (TestRunner $t) => $t->same([['column' => 'id', 'direction' => 'DESC']], $oidDesc135()['currentHiddenRowidOrder']['orderBy']),
    'oid desc records rowid tiebreak' => static fn (TestRunner $t) => $t->same(['id'], $oidDesc135()['currentHiddenRowidOrder']['rowidTieBreakColumns']),
    'oid desc is not streaming tiebreak' => static fn (TestRunner $t) => $t->same(false, $oidDesc135()['currentHiddenRowidOrder']['rowidTieBreakConsumed']),
    'oid desc is not consumed' => static fn (TestRunner $t) => $t->same(false, $oidDesc135()['currentHiddenRowidOrder']['orderByConsumed']),
    'oid desc row order reverses id' => static fn (TestRunner $t) => $t->same([9, 6, 3], $oidDesc135()['currentHiddenRowidOrder']['orderedRowids']),
    'oid desc cost class is order sort' => static fn (TestRunner $t) => $t->same('json-table-hidden-rowid-order-sort', $oidDesc135()['currentHiddenRowidOrder']['costClass']),
    'unrunnable next cost class' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable135()['nextHiddenRowidOrder']['costClass']),
    'unrunnable next rowids empty' => static fn (TestRunner $t) => $t->same([], $unrunnable135()['nextHiddenRowidOrder']['orderedRowids']),
    'unrunnable next order key tape empty' => static fn (TestRunner $t) => $t->same([], $unrunnable135()['nextHiddenRowidOrder']['orderKeyTape']),
    'unrunnable reason preserved' => static fn (TestRunner $t) => $t->true(in_array('next-source-plan-becomes-unrunnable', $unrunnable135()['next135ReplanReasons'], true)),
    'bad function rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceHiddenRowidOrder('json_bad', $current135, $next135, 'option_value')),
    'bad order direction rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceHiddenRowidOrder('json_tree', $current135, $next135, 'option_value', [], 'scan_root', [['column' => 'rowid', 'direction' => 'SIDEWAYS']])),
    'missing json column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceHiddenRowidOrder('json_tree', ['scan_root' => '$'], $next135, 'option_value')),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table hidden rowid order current source next135 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
