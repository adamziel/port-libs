<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current113 = [
    'option_id' => 11,
    'option_name' => 'wp_plugin_rules',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]}',
    'scan_root' => '$.rules',
];
$next113 = [
    'option_id' => 11,
    'option_name' => 'wp_plugin_rules',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4},{"slug":"shop","priority":5}]}',
    'scan_root' => '$.rules',
];
$constraints113 = [
    ['column' => 'type', 'operator' => '=', 'value' => 'object'],
];

$keyDesc113 = static fn (): array => SQLiteJsonTablePlan::currentSourceConstraintCostOrder(
    'json_each',
    $current113,
    $next113,
    'option_value',
    $constraints113,
    'scan_root',
    [['column' => 'key', 'direction' => 'DESC']],
);

$idAsc113 = static fn (): array => SQLiteJsonTablePlan::currentSourceConstraintCostOrder(
    'json_tree',
    $current113,
    $next113,
    'option_value',
    [['column' => 'type', 'operator' => 'IN', 'value' => ['object', 'integer']]],
    'scan_root',
    [['column' => 'rowid']],
);

$stable113 = static fn (): array => SQLiteJsonTablePlan::currentSourceConstraintCostOrder(
    'json_each',
    $current113,
    $current113,
    'option_value',
    $constraints113,
    'scan_root',
    [['column' => 'key', 'direction' => 'DESC']],
);

$limit113 = static fn (): array => SQLiteJsonTablePlan::currentSourceConstraintCostOrder(
    'json_each',
    $current113,
    $next113,
    'option_value',
    array_merge($constraints113, [['column' => 'limit', 'operator' => '=', 'value' => 1]]),
    'scan_root',
    [['column' => 'key', 'direction' => 'DESC']],
);

$unrunnable113 = static fn (): array => SQLiteJsonTablePlan::currentSourceConstraintCostOrder(
    'json_each',
    $current113,
    array_replace($current113, ['option_value' => null]),
    'option_value',
    $constraints113,
    'scan_root',
    [['column' => 'key', 'direction' => 'DESC']],
);

$tests = [
    'normalizes function name' => static fn (TestRunner $t) => $t->same('json_each', $keyDesc113()['function']),
    'records next113 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-current-source-constraint-cost-order', $keyDesc113()['dependencies'], true)),
    'preserves next86 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-current-source-constraint-planner', $keyDesc113()['dependencies'], true)),
    'pins current cost order reader' => static fn (TestRunner $t) => $t->same('pin-current-json-table-cost-order-source-until-cursor-reset', $keyDesc113()['currentReaderPolicy']),
    'prepares next cost order plan' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-cost-order-source-plan', $keyDesc113()['nextReaderPolicy']),
    'stable cost order reuses plan' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-cost-order-source-plan', $stable113()['nextReaderPolicy']),
    'stable cost order has no next113 reasons' => static fn (TestRunner $t) => $t->same([], $stable113()['costOrderReplanReasons']),
    'key desc current order term normalized' => static fn (TestRunner $t) => $t->same([['column' => 'key', 'direction' => 'DESC']], $keyDesc113()['currentCostOrder']['orderBy']),
    'key desc current order is not consumed' => static fn (TestRunner $t) => $t->same(false, $keyDesc113()['currentCostOrder']['orderByConsumed']),
    'key desc next order is not consumed' => static fn (TestRunner $t) => $t->same(false, $keyDesc113()['nextCostOrder']['orderByConsumed']),
    'key desc current requires sorter' => static fn (TestRunner $t) => $t->same(true, $keyDesc113()['currentCostOrder']['requiresSorter']),
    'key desc next requires sorter' => static fn (TestRunner $t) => $t->same(true, $keyDesc113()['nextCostOrder']['requiresSorter']),
    'key desc current sort penalty reflects three rows' => static fn (TestRunner $t) => $t->same(6, $keyDesc113()['currentCostOrder']['sortPenalty']),
    'key desc next sort penalty reflects four rows' => static fn (TestRunner $t) => $t->same(8, $keyDesc113()['nextCostOrder']['sortPenalty']),
    'key desc current base cost is visible constrained' => static fn (TestRunner $t) => $t->same(5, $keyDesc113()['currentCostOrder']['baseEstimatedCost']),
    'key desc next base cost is visible constrained' => static fn (TestRunner $t) => $t->same(5, $keyDesc113()['nextCostOrder']['baseEstimatedCost']),
    'key desc current effective cost includes sorter' => static fn (TestRunner $t) => $t->same(11, $keyDesc113()['currentCostOrder']['effectiveEstimatedCost']),
    'key desc next effective cost includes sorter' => static fn (TestRunner $t) => $t->same(13, $keyDesc113()['nextCostOrder']['effectiveEstimatedCost']),
    'key desc current cost class is sort required' => static fn (TestRunner $t) => $t->same('runnable-json-table-sort-required', $keyDesc113()['currentCostOrder']['costClass']),
    'key desc next cost class is sort required' => static fn (TestRunner $t) => $t->same('runnable-json-table-sort-required', $keyDesc113()['nextCostOrder']['costClass']),
    'key desc current row order is descending keys by id' => static fn (TestRunner $t) => $t->same([3, 2, 1], $keyDesc113()['currentCostOrder']['rowOrder']),
    'key desc next row order includes inserted source row' => static fn (TestRunner $t) => $t->same([4, 3, 2, 1], $keyDesc113()['nextCostOrder']['rowOrder']),
    'key desc current first key is two' => static fn (TestRunner $t) => $t->same([2], $keyDesc113()['currentCostOrder']['firstOrderKey']),
    'key desc next first key is three' => static fn (TestRunner $t) => $t->same([3], $keyDesc113()['nextCostOrder']['firstOrderKey']),
    'key desc current last key is zero' => static fn (TestRunner $t) => $t->same([0], $keyDesc113()['currentCostOrder']['lastOrderKey']),
    'key desc next last key is zero' => static fn (TestRunner $t) => $t->same([0], $keyDesc113()['nextCostOrder']['lastOrderKey']),
    'transition count records order cost state' => static fn (TestRunner $t) => $t->same(6, count($keyDesc113()['costOrderTransitions'])),
    'order by transition is stable' => static fn (TestRunner $t) => $t->same(false, $keyDesc113()['costOrderTransitions'][0]['changed']),
    'sorter transition is stable' => static fn (TestRunner $t) => $t->same(false, $keyDesc113()['costOrderTransitions'][2]['changed']),
    'effective cost transition changes' => static fn (TestRunner $t) => $t->same(true, $keyDesc113()['costOrderTransitions'][3]['changed']),
    'row order transition changes' => static fn (TestRunner $t) => $t->same(true, $keyDesc113()['costOrderTransitions'][5]['changed']),
    'cost class transition remains stable' => static fn (TestRunner $t) => $t->same(false, $keyDesc113()['costOrderTransitions'][4]['changed']),
    'next113 reasons include source json change' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $keyDesc113()['costOrderReplanReasons'], true)),
    'next113 reasons include cost class changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-cost-class-changed', $keyDesc113()['costOrderReplanReasons'], true)),
    'next113 reasons include output order changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-output-order-changed', $keyDesc113()['costOrderReplanReasons'], true)),
    'rowid order normalizes to id' => static fn (TestRunner $t) => $t->same([['column' => 'id', 'direction' => 'ASC']], $idAsc113()['currentCostOrder']['orderBy']),
    'rowid order is consumed for current' => static fn (TestRunner $t) => $t->same(true, $idAsc113()['currentCostOrder']['orderByConsumed']),
    'rowid order is consumed for next' => static fn (TestRunner $t) => $t->same(true, $idAsc113()['nextCostOrder']['orderByConsumed']),
    'rowid current needs no sorter' => static fn (TestRunner $t) => $t->same(false, $idAsc113()['currentCostOrder']['requiresSorter']),
    'rowid next needs no sorter' => static fn (TestRunner $t) => $t->same(false, $idAsc113()['nextCostOrder']['requiresSorter']),
    'rowid current sort penalty is zero' => static fn (TestRunner $t) => $t->same(0, $idAsc113()['currentCostOrder']['sortPenalty']),
    'rowid next sort penalty is zero' => static fn (TestRunner $t) => $t->same(0, $idAsc113()['nextCostOrder']['sortPenalty']),
    'rowid current cost class is streaming' => static fn (TestRunner $t) => $t->same('runnable-json-table-streaming-order', $idAsc113()['currentCostOrder']['costClass']),
    'rowid next cost class is streaming' => static fn (TestRunner $t) => $t->same('runnable-json-table-streaming-order', $idAsc113()['nextCostOrder']['costClass']),
    'rowid current order starts with root id' => static fn (TestRunner $t) => $t->same(1, $idAsc113()['currentCostOrder']['rowOrder'][0]),
    'rowid next order starts with root id' => static fn (TestRunner $t) => $t->same(1, $idAsc113()['nextCostOrder']['rowOrder'][0]),
    'rowid order emits no sorter reason' => static fn (TestRunner $t) => $t->same(false, in_array('json-table-sorter-requirement-changed', $idAsc113()['costOrderReplanReasons'], true)),
    'limit one current needs no sorter for single row' => static fn (TestRunner $t) => $t->same(false, $limit113()['currentCostOrder']['requiresSorter']),
    'limit one next needs no sorter for single row' => static fn (TestRunner $t) => $t->same(false, $limit113()['nextCostOrder']['requiresSorter']),
    'limit one current cost is narrow scan' => static fn (TestRunner $t) => $t->same('runnable-json-table-narrow-visible-scan', $limit113()['currentCostOrder']['costClass']),
    'limit one next cost is narrow scan' => static fn (TestRunner $t) => $t->same('runnable-json-table-narrow-visible-scan', $limit113()['nextCostOrder']['costClass']),
    'limit one current row order has one row' => static fn (TestRunner $t) => $t->same(1, count($limit113()['currentCostOrder']['rowOrder'])),
    'limit one next row order has one row' => static fn (TestRunner $t) => $t->same(1, count($limit113()['nextCostOrder']['rowOrder'])),
    'unrunnable next cost class is unrunnable' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable113()['nextCostOrder']['costClass']),
    'unrunnable next effective cost remains sentinel' => static fn (TestRunner $t) => $t->same(1000000, $unrunnable113()['nextCostOrder']['effectiveEstimatedCost']),
    'unrunnable next row order is empty' => static fn (TestRunner $t) => $t->same([], $unrunnable113()['nextCostOrder']['rowOrder']),
    'unrunnable next reports plan unrunnable' => static fn (TestRunner $t) => $t->true(in_array('next-source-plan-becomes-unrunnable', $unrunnable113()['costOrderReplanReasons'], true)),
    'bad order direction is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceConstraintCostOrder('json_each', $current113, $next113, 'option_value', $constraints113, 'scan_root', [['column' => 'key', 'direction' => 'SIDEWAYS']])),
];

foreach ($tests as $name => $case) {
    $tests['json table constraint cost order current source next113 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
