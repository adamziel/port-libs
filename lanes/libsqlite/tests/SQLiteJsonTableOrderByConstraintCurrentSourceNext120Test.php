<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current120 = [
    'option_id' => 401,
    'option_name' => 'wp_plugin_rule_order',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
];
$next120 = [
    'option_id' => 401,
    'option_name' => 'wp_plugin_rule_order',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4},{"slug":"shop","priority":5}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
];

$constantKey120 = static fn (): array => SQLiteJsonTablePlan::currentSourceOrderByConstraintNext120(
    'json_tree',
    $current120,
    $next120,
    'option_value',
    [
        ['column' => 'key', 'operator' => '=', 'value' => 'priority'],
        ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
    ],
    'scan_root',
    [['column' => 'key', 'direction' => 'DESC'], ['column' => 'id', 'direction' => 'ASC']],
);

$singleInType120 = static fn (): array => SQLiteJsonTablePlan::currentSourceOrderByConstraintNext120(
    'json_each',
    $current120,
    $next120,
    'option_value',
    [
        ['column' => 'type', 'operator' => 'IN', 'value' => ['object']],
        ['column' => 'limit', 'operator' => '=', 'value' => 3],
    ],
    'scan_root',
    [['column' => 'type', 'direction' => 'ASC'], ['column' => 'id', 'direction' => 'ASC']],
);

$betweenId120 = static fn (): array => SQLiteJsonTablePlan::currentSourceOrderByConstraintNext120(
    'json_tree',
    $current120,
    $next120,
    'option_value',
    [
        ['column' => 'id', 'operator' => 'BETWEEN', 'value' => [5, 5]],
    ],
    'scan_root',
    [['column' => 'rowid', 'direction' => 'DESC']],
);

$notConsumed120 = static fn (): array => SQLiteJsonTablePlan::currentSourceOrderByConstraintNext120(
    'json_tree',
    $current120,
    $next120,
    'option_value',
    [
        ['column' => 'key', 'operator' => 'IN', 'value' => ['slug', 'priority']],
        ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
    ],
    'scan_root',
    [['column' => 'key', 'direction' => 'DESC']],
);

$tests = [
    'records next120 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-orderby-constraint-current-source-next120', $constantKey120()['dependencies'], true)),
    'preserves cost order dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-current-source-constraint-cost-order', $constantKey120()['dependencies'], true)),
    'preserves current source dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-current-source-constraint-planner', $constantKey120()['dependencies'], true)),
    'constant key current consumes order' => static fn (TestRunner $t) => $t->same(true, $constantKey120()['current']['orderByConsumed']),
    'constant key next consumes order' => static fn (TestRunner $t) => $t->same(true, $constantKey120()['next']['orderByConsumed']),
    'constant key current profile consumes order' => static fn (TestRunner $t) => $t->same(true, $constantKey120()['currentCostOrder']['orderByConsumed']),
    'constant key next profile consumes order' => static fn (TestRunner $t) => $t->same(true, $constantKey120()['nextCostOrder']['orderByConsumed']),
    'constant key current needs no sorter' => static fn (TestRunner $t) => $t->same(false, $constantKey120()['currentCostOrder']['requiresSorter']),
    'constant key next needs no sorter' => static fn (TestRunner $t) => $t->same(false, $constantKey120()['nextCostOrder']['requiresSorter']),
    'constant key sort penalty is zero current' => static fn (TestRunner $t) => $t->same(0, $constantKey120()['currentCostOrder']['sortPenalty']),
    'constant key sort penalty is zero next' => static fn (TestRunner $t) => $t->same(0, $constantKey120()['nextCostOrder']['sortPenalty']),
    'constant key cost class streams current' => static fn (TestRunner $t) => $t->same('runnable-json-table-streaming-order', $constantKey120()['currentCostOrder']['costClass']),
    'constant key cost class streams next' => static fn (TestRunner $t) => $t->same('runnable-json-table-streaming-order', $constantKey120()['nextCostOrder']['costClass']),
    'constant key first coverage is key desc' => static fn (TestRunner $t) => $t->same('key', $constantKey120()['currentOrderConstraintCoverage'][0]['column']),
    'constant key first coverage direction preserved' => static fn (TestRunner $t) => $t->same('DESC', $constantKey120()['currentOrderConstraintCoverage'][0]['direction']),
    'constant key first coverage consumed' => static fn (TestRunner $t) => $t->same(true, $constantKey120()['currentOrderConstraintCoverage'][0]['consumed']),
    'constant key coverage reason is constraint' => static fn (TestRunner $t) => $t->same('constant-visible-constraint', $constantKey120()['currentOrderConstraintCoverage'][0]['reason']),
    'constant key coverage operator recorded' => static fn (TestRunner $t) => $t->same('=', $constantKey120()['currentOrderConstraintCoverage'][0]['constraintOperator']),
    'constant key coverage value recorded' => static fn (TestRunner $t) => $t->same('priority', $constantKey120()['currentOrderConstraintCoverage'][0]['constraintValue']),
    'constant key second coverage is id' => static fn (TestRunner $t) => $t->same('id', $constantKey120()['currentOrderConstraintCoverage'][1]['column']),
    'constant key second coverage reason is natural id' => static fn (TestRunner $t) => $t->same('natural-json-rowid-order', $constantKey120()['currentOrderConstraintCoverage'][1]['reason']),
    'constant key row order remains natural current' => static fn (TestRunner $t) => $t->same([3, 6, 9], $constantKey120()['currentCostOrder']['rowOrder']),
    'constant key row order remains natural next' => static fn (TestRunner $t) => $t->same([3, 6, 9, 12], $constantKey120()['nextCostOrder']['rowOrder']),
    'constant key order by transition stable' => static fn (TestRunner $t) => $t->same(false, $constantKey120()['costOrderTransitions'][1]['changed']),
    'constant key emits no sorter replan reason' => static fn (TestRunner $t) => $t->same(false, in_array('json-table-sorter-requirement-changed', $constantKey120()['next120ReplanReasons'], true)),
    'constant key still tracks source json change' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $constantKey120()['next120ReplanReasons'], true)),
    'single in type current consumes order' => static fn (TestRunner $t) => $t->same(true, $singleInType120()['current']['orderByConsumed']),
    'single in type next consumes order' => static fn (TestRunner $t) => $t->same(true, $singleInType120()['next']['orderByConsumed']),
    'single in type coverage reason is constraint' => static fn (TestRunner $t) => $t->same('constant-visible-constraint', $singleInType120()['currentOrderConstraintCoverage'][0]['reason']),
    'single in type coverage operator is in' => static fn (TestRunner $t) => $t->same('IN', $singleInType120()['currentOrderConstraintCoverage'][0]['constraintOperator']),
    'single in type coverage value is singleton list' => static fn (TestRunner $t) => $t->same(['object'], $singleInType120()['currentOrderConstraintCoverage'][0]['constraintValue']),
    'single in type keeps limit in idx num' => static fn (TestRunner $t) => $t->same(15, $singleInType120()['current']['idxNum']),
    'single in type current rows limited to three' => static fn (TestRunner $t) => $t->same(3, count($singleInType120()['currentRows'])),
    'single in type next rows limited to three' => static fn (TestRunner $t) => $t->same(3, count($singleInType120()['nextRows'])),
    'single in type no sorter current' => static fn (TestRunner $t) => $t->same(false, $singleInType120()['currentCostOrder']['requiresSorter']),
    'single in type no sorter next' => static fn (TestRunner $t) => $t->same(false, $singleInType120()['nextCostOrder']['requiresSorter']),
    'between id desc current consumes because id constant' => static fn (TestRunner $t) => $t->same(true, $betweenId120()['current']['orderByConsumed']),
    'between id desc next consumes because id constant' => static fn (TestRunner $t) => $t->same(true, $betweenId120()['next']['orderByConsumed']),
    'between id coverage normalizes rowid' => static fn (TestRunner $t) => $t->same('id', $betweenId120()['currentOrderConstraintCoverage'][0]['column']),
    'between id coverage preserves desc' => static fn (TestRunner $t) => $t->same('DESC', $betweenId120()['currentOrderConstraintCoverage'][0]['direction']),
    'between id coverage reason is constraint' => static fn (TestRunner $t) => $t->same('constant-visible-constraint', $betweenId120()['currentOrderConstraintCoverage'][0]['reason']),
    'between id coverage operator recorded' => static fn (TestRunner $t) => $t->same('BETWEEN', $betweenId120()['currentOrderConstraintCoverage'][0]['constraintOperator']),
    'between id current row order single id' => static fn (TestRunner $t) => $t->same([5], $betweenId120()['currentCostOrder']['rowOrder']),
    'between id next row order single id' => static fn (TestRunner $t) => $t->same([5], $betweenId120()['nextCostOrder']['rowOrder']),
    'between id cost class streams current' => static fn (TestRunner $t) => $t->same('runnable-json-table-streaming-order', $betweenId120()['currentCostOrder']['costClass']),
    'between id cost class streams next' => static fn (TestRunner $t) => $t->same('runnable-json-table-streaming-order', $betweenId120()['nextCostOrder']['costClass']),
    'multi in key current does not consume order' => static fn (TestRunner $t) => $t->same(false, $notConsumed120()['current']['orderByConsumed']),
    'multi in key next does not consume order' => static fn (TestRunner $t) => $t->same(false, $notConsumed120()['next']['orderByConsumed']),
    'multi in key requires sorter current' => static fn (TestRunner $t) => $t->same(true, $notConsumed120()['currentCostOrder']['requiresSorter']),
    'multi in key requires sorter next' => static fn (TestRunner $t) => $t->same(true, $notConsumed120()['nextCostOrder']['requiresSorter']),
    'multi in key coverage not consumed' => static fn (TestRunner $t) => $t->same(false, $notConsumed120()['currentOrderConstraintCoverage'][0]['consumed']),
    'multi in key coverage reason is not consumed' => static fn (TestRunner $t) => $t->same('not-consumed', $notConsumed120()['currentOrderConstraintCoverage'][0]['reason']),
    'multi in key coverage has no operator' => static fn (TestRunner $t) => $t->same(null, $notConsumed120()['currentOrderConstraintCoverage'][0]['constraintOperator']),
    'multi in key row order sorted descending current' => static fn (TestRunner $t) => $t->same([3, 6, 9], $notConsumed120()['currentCostOrder']['rowOrder']),
    'multi in key row order sorted descending next' => static fn (TestRunner $t) => $t->same([3, 6, 9, 12], $notConsumed120()['nextCostOrder']['rowOrder']),
    'current xbestindex consumes is null parent order' => static fn (TestRunner $t) => $t->same(true, SQLiteJsonTablePlan::xBestIndexPlan('json_tree', [['column' => 'json', 'operator' => '=', 'value' => $current120['option_value']], ['column' => 'parent', 'operator' => 'IS NULL', 'value' => null]], [['column' => 'parent', 'direction' => 'DESC']])['orderByConsumed']),
    'current xbestindex consumes is not distinct constant atom order' => static fn (TestRunner $t) => $t->same(true, SQLiteJsonTablePlan::xBestIndexPlan('json_tree', [['column' => 'json', 'operator' => '=', 'value' => $current120['option_value']], ['column' => 'atom', 'operator' => 'IS NOT DISTINCT FROM', 'value' => 4]], [['column' => 'atom', 'direction' => 'DESC']])['orderByConsumed']),
    'current xbestindex rejects unsupported order direction' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::xBestIndexPlan('json_tree', [['column' => 'json', 'operator' => '=', 'value' => $current120['option_value']]], [['column' => 'key', 'direction' => 'SIDEWAYS']])),
];

foreach ($tests as $name => $case) {
    $tests['json table order by constraint current source next120 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
