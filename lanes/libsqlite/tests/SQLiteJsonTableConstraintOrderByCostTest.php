<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current124 = [
    'option_id' => 124,
    'option_name' => 'wp_plugin_rule_priorities',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]}',
    'scan_root' => '$.rules',
];
$next124 = [
    'option_id' => 124,
    'option_name' => 'wp_plugin_rule_priorities',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4},{"slug":"shop","priority":5}]}',
    'scan_root' => '$.rules',
];
$priorityConstraints124 = [
    ['column' => 'key', 'operator' => '=', 'value' => 'priority'],
    ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
];

$partial124 = static fn (): array => SQLiteJsonTablePlan::currentSourceConstraintOrderByCost(
    'json_tree',
    $current124,
    $next124,
    'option_value',
    $priorityConstraints124,
    'scan_root',
    [['column' => 'key'], ['column' => 'atom', 'direction' => 'DESC']],
);

$complete124 = static fn (): array => SQLiteJsonTablePlan::currentSourceConstraintOrderByCost(
    'json_tree',
    $current124,
    $current124,
    'option_value',
    $priorityConstraints124,
    'scan_root',
    [['column' => 'key'], ['column' => 'rowid']],
);

$fullSort124 = static fn (): array => SQLiteJsonTablePlan::currentSourceConstraintOrderByCost(
    'json_tree',
    $current124,
    $current124,
    'option_value',
    $priorityConstraints124,
    'scan_root',
    [['column' => 'atom', 'direction' => 'DESC'], ['column' => 'key']],
);

$singleRow124 = static fn (): array => SQLiteJsonTablePlan::currentSourceConstraintOrderByCost(
    'json_tree',
    $current124,
    $current124,
    'option_value',
    [['column' => 'id', 'operator' => 'BETWEEN', 'value' => [3, 3]]],
    'scan_root',
    [['column' => 'id', 'direction' => 'DESC'], ['column' => 'atom']],
);

$unrunnable124 = static fn (): array => SQLiteJsonTablePlan::currentSourceConstraintOrderByCost(
    'json_tree',
    $current124,
    array_replace($current124, ['option_value' => null]),
    'option_value',
    $priorityConstraints124,
    'scan_root',
    [['column' => 'key'], ['column' => 'atom', 'direction' => 'DESC']],
);

$tests = [
    'normalizes function name' => static fn (TestRunner $t) => $t->same('json_tree', $partial124()['function']),
    'records next124 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-constraint-orderby-cost-current-source-next124', $partial124()['dependencies'], true)),
    'preserves next120 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-orderby-constraint-current-source-next120', $partial124()['dependencies'], true)),
    'preserves next113 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-current-source-constraint-cost-order', $partial124()['dependencies'], true)),
    'pins current partial order reader' => static fn (TestRunner $t) => $t->same('pin-current-json-table-partial-order-cost-source-until-cursor-reset', $partial124()['currentReaderPolicy']),
    'prepares next partial order plan' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-partial-order-cost-source-plan', $partial124()['nextReaderPolicy']),
    'stable complete order reuses current plan' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-partial-order-cost-source-plan', $complete124()['nextReaderPolicy']),
    'partial current consumes key prefix only' => static fn (TestRunner $t) => $t->same(['key'], $partial124()['currentPartialOrderCost']['consumedPrefixColumns']),
    'partial next consumes key prefix only' => static fn (TestRunner $t) => $t->same(['key'], $partial124()['nextPartialOrderCost']['consumedPrefixColumns']),
    'partial suffix is atom' => static fn (TestRunner $t) => $t->same(['atom'], $partial124()['currentPartialOrderCost']['suffixColumns']),
    'partial prefix count is one' => static fn (TestRunner $t) => $t->same(1, $partial124()['currentPartialOrderCost']['prefixConsumedCount']),
    'partial suffix width is one' => static fn (TestRunner $t) => $t->same(1, $partial124()['currentPartialOrderCost']['suffixSortWidth']),
    'partial block sort required current' => static fn (TestRunner $t) => $t->same(true, $partial124()['currentPartialOrderCost']['blockSortRequired']),
    'partial block sort required next' => static fn (TestRunner $t) => $t->same(true, $partial124()['nextPartialOrderCost']['blockSortRequired']),
    'partial current base sort penalty keeps full width' => static fn (TestRunner $t) => $t->same(12, $partial124()['currentPartialOrderCost']['baseSortPenalty']),
    'partial next base sort penalty keeps full width' => static fn (TestRunner $t) => $t->same(16, $partial124()['nextPartialOrderCost']['baseSortPenalty']),
    'partial current block penalty uses suffix width' => static fn (TestRunner $t) => $t->same(6, $partial124()['currentPartialOrderCost']['blockSortPenalty']),
    'partial next block penalty uses suffix width' => static fn (TestRunner $t) => $t->same(8, $partial124()['nextPartialOrderCost']['blockSortPenalty']),
    'partial current sort savings recorded' => static fn (TestRunner $t) => $t->same(6, $partial124()['currentPartialOrderCost']['sortSavings']),
    'partial next sort savings recorded' => static fn (TestRunner $t) => $t->same(8, $partial124()['nextPartialOrderCost']['sortSavings']),
    'partial current effective cost narrows sort' => static fn (TestRunner $t) => $t->same(8, $partial124()['currentPartialOrderCost']['effectiveEstimatedCost']),
    'partial next effective cost narrows sort' => static fn (TestRunner $t) => $t->same(10, $partial124()['nextPartialOrderCost']['effectiveEstimatedCost']),
    'partial current base effective remains next113 value' => static fn (TestRunner $t) => $t->same(14, $partial124()['currentPartialOrderCost']['baseEffectiveEstimatedCost']),
    'partial next base effective remains next113 value' => static fn (TestRunner $t) => $t->same(18, $partial124()['nextPartialOrderCost']['baseEffectiveEstimatedCost']),
    'partial cost class is block sort current' => static fn (TestRunner $t) => $t->same('json-table-partial-order-block-sort', $partial124()['currentPartialOrderCost']['costClass']),
    'partial cost class is block sort next' => static fn (TestRunner $t) => $t->same('json-table-partial-order-block-sort', $partial124()['nextPartialOrderCost']['costClass']),
    'partial current row order sorts suffix descending' => static fn (TestRunner $t) => $t->same([6, 9, 3], $partial124()['currentPartialOrderCost']['rowOrder']),
    'partial next row order includes changed source row' => static fn (TestRunner $t) => $t->same([6, 12, 9, 3], $partial124()['nextPartialOrderCost']['rowOrder']),
    'partial current first suffix key is highest atom' => static fn (TestRunner $t) => $t->same([7], $partial124()['currentPartialOrderCost']['firstSuffixKey']),
    'partial next first suffix key is highest changed atom' => static fn (TestRunner $t) => $t->same([6], $partial124()['nextPartialOrderCost']['firstSuffixKey']),
    'partial current last suffix key is lowest atom' => static fn (TestRunner $t) => $t->same([2], $partial124()['currentPartialOrderCost']['lastSuffixKey']),
    'partial next last suffix key is lowest changed atom' => static fn (TestRunner $t) => $t->same([3], $partial124()['nextPartialOrderCost']['lastSuffixKey']),
    'partial transition count' => static fn (TestRunner $t) => $t->same(7, count($partial124()['partialOrderCostTransitions'])),
    'partial prefix transition stable' => static fn (TestRunner $t) => $t->same(false, $partial124()['partialOrderCostTransitions'][0]['changed']),
    'partial suffix transition stable' => static fn (TestRunner $t) => $t->same(false, $partial124()['partialOrderCostTransitions'][1]['changed']),
    'partial sorter transition stable' => static fn (TestRunner $t) => $t->same(false, $partial124()['partialOrderCostTransitions'][2]['changed']),
    'partial penalty transition changes' => static fn (TestRunner $t) => $t->same(true, $partial124()['partialOrderCostTransitions'][3]['changed']),
    'partial cost transition changes' => static fn (TestRunner $t) => $t->same(true, $partial124()['partialOrderCostTransitions'][4]['changed']),
    'partial class transition stable' => static fn (TestRunner $t) => $t->same(false, $partial124()['partialOrderCostTransitions'][5]['changed']),
    'partial row order transition changes' => static fn (TestRunner $t) => $t->same(true, $partial124()['partialOrderCostTransitions'][6]['changed']),
    'partial reasons include cost changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-partial-order-cost-changed', $partial124()['next124ReplanReasons'], true)),
    'partial reasons include output changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-partial-order-output-changed', $partial124()['next124ReplanReasons'], true)),
    'partial reasons preserve source json changed' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $partial124()['next124ReplanReasons'], true)),
    'complete consumes key and id' => static fn (TestRunner $t) => $t->same(['key', 'id'], $complete124()['currentPartialOrderCost']['consumedPrefixColumns']),
    'complete has empty suffix' => static fn (TestRunner $t) => $t->same([], $complete124()['currentPartialOrderCost']['suffixColumns']),
    'complete needs no block sort' => static fn (TestRunner $t) => $t->same(false, $complete124()['currentPartialOrderCost']['blockSortRequired']),
    'complete class is consumed' => static fn (TestRunner $t) => $t->same('json-table-complete-order-consumed', $complete124()['currentPartialOrderCost']['costClass']),
    'complete rowid alias normalized' => static fn (TestRunner $t) => $t->same('id', $complete124()['currentPartialOrderCost']['orderBy'][1]['column']),
    'complete stable has no next124 reasons' => static fn (TestRunner $t) => $t->same([], $complete124()['next124ReplanReasons']),
    'full sort has no consumed prefix' => static fn (TestRunner $t) => $t->same([], $fullSort124()['currentPartialOrderCost']['consumedPrefixColumns']),
    'full sort suffix keeps both terms' => static fn (TestRunner $t) => $t->same(['atom', 'key'], $fullSort124()['currentPartialOrderCost']['suffixColumns']),
    'full sort has no savings' => static fn (TestRunner $t) => $t->same(0, $fullSort124()['currentPartialOrderCost']['sortSavings']),
    'full sort class is full order sort' => static fn (TestRunner $t) => $t->same('json-table-full-order-sort', $fullSort124()['currentPartialOrderCost']['costClass']),
    'single row consumes id prefix' => static fn (TestRunner $t) => $t->same(['id'], $singleRow124()['currentPartialOrderCost']['consumedPrefixColumns']),
    'single row has suffix atom' => static fn (TestRunner $t) => $t->same(['atom'], $singleRow124()['currentPartialOrderCost']['suffixColumns']),
    'single row needs no block sort' => static fn (TestRunner $t) => $t->same(false, $singleRow124()['currentPartialOrderCost']['blockSortRequired']),
    'single row class is narrow scan' => static fn (TestRunner $t) => $t->same('json-table-partial-order-narrow-scan', $singleRow124()['currentPartialOrderCost']['costClass']),
    'single row preserves between constraint value' => static fn (TestRunner $t) => $t->same([3, 3], $singleRow124()['currentPartialOrderCost']['consumedPrefix'][0]['constraintValue']),
    'unrunnable next class is sentinel' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable124()['nextPartialOrderCost']['costClass']),
    'unrunnable next effective cost is sentinel' => static fn (TestRunner $t) => $t->same(1000000, $unrunnable124()['nextPartialOrderCost']['effectiveEstimatedCost']),
    'bad order direction is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceConstraintOrderByCost('json_tree', $current124, $next124, 'option_value', $priorityConstraints124, 'scan_root', [['column' => 'key', 'direction' => 'SIDEWAYS']])),
];

foreach ($tests as $name => $case) {
    $tests['json table constraint orderby cost current source next124 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
