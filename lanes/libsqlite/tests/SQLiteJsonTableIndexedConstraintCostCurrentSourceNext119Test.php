<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current119 = [
    'option_id' => 17,
    'option_name' => 'wp_plugin_rule_index',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]}',
    'scan_root' => '$.rules',
];
$next119 = [
    'option_id' => 17,
    'option_name' => 'wp_plugin_rule_index',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4},{"slug":"shop","priority":5}]}',
    'scan_root' => '$.rules',
];
$priorityConstraints119 = [
    ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
    ['column' => 'atom', 'operator' => 'BETWEEN', 'value' => [3, 7]],
    ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.rules[%].priority'],
];

$fullkey119 = static fn (): array => SQLiteJsonTablePlan::currentSourceIndexedConstraintCostNext119(
    'json_tree',
    $current119,
    $next119,
    'option_value',
    $priorityConstraints119,
    'scan_root',
    [['column' => 'id']],
);

$stable119 = static fn (): array => SQLiteJsonTablePlan::currentSourceIndexedConstraintCostNext119(
    'json_tree',
    $current119,
    $current119,
    'option_value',
    $priorityConstraints119,
    'scan_root',
    [['column' => 'id']],
);

$rowid119 = static fn (): array => SQLiteJsonTablePlan::currentSourceIndexedConstraintCostNext119(
    'json_tree',
    $current119,
    $next119,
    'option_value',
    [['column' => 'id', 'operator' => '=', 'value' => 4], ['column' => 'type', 'operator' => '=', 'value' => 'integer']],
    'scan_root',
);

$scan119 = static fn (): array => SQLiteJsonTablePlan::currentSourceIndexedConstraintCostNext119(
    'json_each',
    $current119,
    $current119,
    'option_value',
    [['column' => 'type', 'operator' => '=', 'value' => 'object']],
    'scan_root',
    [['column' => 'key', 'direction' => 'DESC']],
);

$unrunnable119 = static fn (): array => SQLiteJsonTablePlan::currentSourceIndexedConstraintCostNext119(
    'json_tree',
    $current119,
    array_replace($current119, ['option_value' => null]),
    'option_value',
    $priorityConstraints119,
    'scan_root',
    [['column' => 'id']],
);

$tests = [
    'normalizes function name' => static fn (TestRunner $t) => $t->same('json_tree', $fullkey119()['function']),
    'records next119 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-indexed-constraint-cost-current-source-next119', $fullkey119()['dependencies'], true)),
    'preserves next113 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-current-source-constraint-cost-order', $fullkey119()['dependencies'], true)),
    'pins current indexed constraint reader' => static fn (TestRunner $t) => $t->same('pin-current-json-table-indexed-constraint-cost-until-cursor-reset', $fullkey119()['currentReaderPolicy']),
    'prepares next indexed constraint plan' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-indexed-constraint-cost-plan', $fullkey119()['nextReaderPolicy']),
    'stable indexed constraint reuses plan' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-indexed-constraint-cost-plan', $stable119()['nextReaderPolicy']),
    'stable indexed constraint has no next119 reasons' => static fn (TestRunner $t) => $t->same([], $stable119()['next119ReplanReasons']),
    'fullkey profile has one indexed constraint' => static fn (TestRunner $t) => $t->same(1, count($fullkey119()['currentIndexedConstraintCost']['indexedConstraints'])),
    'fullkey selected column is fullkey' => static fn (TestRunner $t) => $t->same('fullkey', $fullkey119()['currentIndexedConstraintCost']['selected']['column']),
    'fullkey selected operator is LIKE' => static fn (TestRunner $t) => $t->same('LIKE', $fullkey119()['currentIndexedConstraintCost']['selected']['operator']),
    'fullkey selected value is pattern' => static fn (TestRunner $t) => $t->same('$.rules[%].priority', $fullkey119()['currentIndexedConstraintCost']['selected']['value']),
    'fullkey selected rank beats key scans' => static fn (TestRunner $t) => $t->same(2, $fullkey119()['currentIndexedConstraintCost']['selected']['rank']),
    'fullkey selected selectivity records prefix benefit' => static fn (TestRunner $t) => $t->same(5, $fullkey119()['currentIndexedConstraintCost']['selected']['selectivity']),
    'fullkey signature is stable' => static fn (TestRunner $t) => $t->same('4:fullkey:LIKE:"$.rules[%].priority"', $fullkey119()['currentIndexedConstraintCost']['selectedSignature']),
    'fullkey next signature matches current' => static fn (TestRunner $t) => $t->same($fullkey119()['currentIndexedConstraintCost']['selectedSignature'], $fullkey119()['nextIndexedConstraintCost']['selectedSignature']),
    'fullkey scan strategy is indexed' => static fn (TestRunner $t) => $t->same('indexed-json-table-constraint', $fullkey119()['currentIndexedConstraintCost']['scanStrategy']),
    'fullkey next scan strategy is indexed' => static fn (TestRunner $t) => $t->same('indexed-json-table-constraint', $fullkey119()['nextIndexedConstraintCost']['scanStrategy']),
    'fullkey indexed rows are narrowed' => static fn (TestRunner $t) => $t->same(1, $fullkey119()['currentIndexedConstraintCost']['indexedEstimatedRows']),
    'fullkey next indexed rows are narrowed' => static fn (TestRunner $t) => $t->same(1, $fullkey119()['nextIndexedConstraintCost']['indexedEstimatedRows']),
    'fullkey indexed cost is one' => static fn (TestRunner $t) => $t->same(1, $fullkey119()['currentIndexedConstraintCost']['indexedEstimatedCost']),
    'fullkey next indexed cost is one' => static fn (TestRunner $t) => $t->same(1, $fullkey119()['nextIndexedConstraintCost']['indexedEstimatedCost']),
    'fullkey effective cost includes no streamed sorter' => static fn (TestRunner $t) => $t->same(1, $fullkey119()['currentIndexedConstraintCost']['effectiveEstimatedCost']),
    'fullkey next effective cost includes no streamed sorter' => static fn (TestRunner $t) => $t->same(1, $fullkey119()['nextIndexedConstraintCost']['effectiveEstimatedCost']),
    'fullkey cost class is indexed narrow scan' => static fn (TestRunner $t) => $t->same('json-table-indexed-narrow-scan', $fullkey119()['currentIndexedConstraintCost']['costClass']),
    'fullkey current row count sees matching priorities' => static fn (TestRunner $t) => $t->same(2, $fullkey119()['currentIndexedConstraintCost']['rowCount']),
    'fullkey next row count sees inserted matching priority' => static fn (TestRunner $t) => $t->same(4, $fullkey119()['nextIndexedConstraintCost']['rowCount']),
    'fullkey transition count records indexed fields' => static fn (TestRunner $t) => $t->same(6, count($fullkey119()['indexedConstraintTransitions'])),
    'selected signature transition is stable' => static fn (TestRunner $t) => $t->same(false, $fullkey119()['indexedConstraintTransitions'][0]['changed']),
    'scan strategy transition is stable' => static fn (TestRunner $t) => $t->same(false, $fullkey119()['indexedConstraintTransitions'][1]['changed']),
    'indexed cost transition is stable' => static fn (TestRunner $t) => $t->same(false, $fullkey119()['indexedConstraintTransitions'][2]['changed']),
    'effective cost transition is stable' => static fn (TestRunner $t) => $t->same(false, $fullkey119()['indexedConstraintTransitions'][3]['changed']),
    'cost class transition is stable' => static fn (TestRunner $t) => $t->same(false, $fullkey119()['indexedConstraintTransitions'][4]['changed']),
    'row count transition changes' => static fn (TestRunner $t) => $t->same(true, $fullkey119()['indexedConstraintTransitions'][5]['changed']),
    'next119 reasons include row count change' => static fn (TestRunner $t) => $t->true(in_array('json-table-indexed-row-count-changed', $fullkey119()['next119ReplanReasons'], true)),
    'next119 reasons preserve source json change' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $fullkey119()['next119ReplanReasons'], true)),
    'rowid selected column is id' => static fn (TestRunner $t) => $t->same('id', $rowid119()['currentIndexedConstraintCost']['selected']['column']),
    'rowid equality selectivity is highest' => static fn (TestRunner $t) => $t->same(16, $rowid119()['currentIndexedConstraintCost']['selected']['selectivity']),
    'rowid cost class is point lookup' => static fn (TestRunner $t) => $t->same('json-table-rowid-point-lookup', $rowid119()['currentIndexedConstraintCost']['costClass']),
    'rowid selected signature records id' => static fn (TestRunner $t) => $t->same('2:id:=:4', $rowid119()['currentIndexedConstraintCost']['selectedSignature']),
    'rowid indexed cost remains one' => static fn (TestRunner $t) => $t->same(1, $rowid119()['currentIndexedConstraintCost']['indexedEstimatedCost']),
    'rowid indexed rows remain one' => static fn (TestRunner $t) => $t->same(1, $rowid119()['currentIndexedConstraintCost']['indexedEstimatedRows']),
    'non indexed visible constraint has no selected constraint' => static fn (TestRunner $t) => $t->same(null, $scan119()['currentIndexedConstraintCost']['selected']),
    'non indexed visible constraint uses full scan strategy' => static fn (TestRunner $t) => $t->same('full-json-table-scan', $scan119()['currentIndexedConstraintCost']['scanStrategy']),
    'non indexed full scan inherits sort penalty' => static fn (TestRunner $t) => $t->same(6, $scan119()['currentIndexedConstraintCost']['sortPenalty']),
    'non indexed full scan inherits effective cost' => static fn (TestRunner $t) => $t->same($scan119()['currentCostOrder']['effectiveEstimatedCost'], $scan119()['currentIndexedConstraintCost']['effectiveEstimatedCost']),
    'non indexed full scan class remains scan' => static fn (TestRunner $t) => $t->same('json-table-full-scan', $scan119()['currentIndexedConstraintCost']['costClass']),
    'unrunnable next strategy is unrunnable' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable119()['nextIndexedConstraintCost']['scanStrategy']),
    'unrunnable next indexed cost is sentinel' => static fn (TestRunner $t) => $t->same(1000000, $unrunnable119()['nextIndexedConstraintCost']['indexedEstimatedCost']),
    'unrunnable next effective cost is sentinel' => static fn (TestRunner $t) => $t->same(1000000, $unrunnable119()['nextIndexedConstraintCost']['effectiveEstimatedCost']),
    'unrunnable next cost class is unrunnable' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable119()['nextIndexedConstraintCost']['costClass']),
    'unrunnable reasons include source plan unrunnable' => static fn (TestRunner $t) => $t->true(in_array('next-source-plan-becomes-unrunnable', $unrunnable119()['next119ReplanReasons'], true)),
    'bad order direction still rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceIndexedConstraintCostNext119('json_each', $current119, $next119, 'option_value', $priorityConstraints119, 'scan_root', [['column' => 'key', 'direction' => 'BAD']])),
];

foreach ($tests as $name => $case) {
    $tests['json table indexed constraint cost current source next119 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
