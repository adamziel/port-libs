<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current131 = [
    'option_id' => 131,
    'option_name' => 'wp_plugin_rule_path_order',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
];
$next131 = [
    'option_id' => 131,
    'option_name' => 'wp_plugin_rule_path_order',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4},{"slug":"shop","priority":5}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
];

$pathPriority131 = static fn (): array => SQLiteJsonTablePlan::currentSourcePathOrderByCost(
    'json_tree',
    $current131,
    $next131,
    'option_value',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'key', 'operator' => '=', 'value' => 'priority'],
        ['column' => 'atom', 'operator' => '>=', 'value' => 3],
    ],
    'scan_root',
    [['column' => 'key'], ['column' => 'atom', 'direction' => 'DESC']],
);

$pathPoint131 = static fn (): array => SQLiteJsonTablePlan::currentSourcePathOrderByCost(
    'json_tree',
    $current131,
    $current131,
    'option_value',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'key', 'operator' => '=', 'value' => 'slug'],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'id']],
);

$pathInsert131 = static fn (): array => SQLiteJsonTablePlan::currentSourcePathOrderByCost(
    'json_tree',
    $current131,
    $next131,
    'option_value',
    [
        ['column' => 'path', 'operator' => 'IN', 'value' => ['$.rules[0]', '$.rules[3]']],
        ['column' => 'key', 'operator' => 'IN', 'value' => ['slug', 'priority']],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'id']],
);

$unrunnable131 = static fn (): array => SQLiteJsonTablePlan::currentSourcePathOrderByCost(
    'json_tree',
    $current131,
    array_replace($next131, ['option_value' => null]),
    'option_value',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ],
    'scan_root',
    [['column' => 'path']],
);

$tests = [
    'records next131 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-path-orderby-cost-current-source-next131', $pathPriority131()['dependencies'], true)),
    'preserves path pushdown dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-path-constraint-pushdown-current-source-next123', $pathPriority131()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-path-orderby-cost-source-until-cursor-reset', $pathPriority131()['currentReaderPolicy']),
    'prepares next reader policy on inserted path rows' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-path-orderby-cost-source-plan', $pathPriority131()['nextReaderPolicy']),
    'stable point reader policy reuses plan' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-path-orderby-cost-source-plan', $pathPoint131()['nextReaderPolicy']),
    'stable point does not require replan' => static fn (TestRunner $t) => $t->same(false, $pathPoint131()['replanRequired']),
    'priority path selected by LIKE' => static fn (TestRunner $t) => $t->same('2:path:LIKE:"$.rules%"', $pathPriority131()['currentPathOrderByCost']['selectedPathSignature']),
    'priority scan strategy is path pushdown' => static fn (TestRunner $t) => $t->same('path-constraint-pushdown', $pathPriority131()['currentPathOrderByCost']['pathScanStrategy']),
    'priority current path row count two after atom residual' => static fn (TestRunner $t) => $t->same(2, $pathPriority131()['currentPathOrderByCost']['pathRowCount']),
    'priority next path row count four' => static fn (TestRunner $t) => $t->same(4, $pathPriority131()['nextPathOrderByCost']['pathRowCount']),
    'priority current consumes key prefix' => static fn (TestRunner $t) => $t->same(['key'], $pathPriority131()['currentPathOrderByCost']['consumedPrefixColumns']),
    'priority current suffix is atom' => static fn (TestRunner $t) => $t->same(['atom'], $pathPriority131()['currentPathOrderByCost']['suffixColumns']),
    'priority next consumes key prefix' => static fn (TestRunner $t) => $t->same(['key'], $pathPriority131()['nextPathOrderByCost']['consumedPrefixColumns']),
    'priority next suffix is atom' => static fn (TestRunner $t) => $t->same(['atom'], $pathPriority131()['nextPathOrderByCost']['suffixColumns']),
    'priority current requires suffix sorter' => static fn (TestRunner $t) => $t->same(true, $pathPriority131()['currentPathOrderByCost']['requiresSorter']),
    'priority next requires suffix sorter' => static fn (TestRunner $t) => $t->same(true, $pathPriority131()['nextPathOrderByCost']['requiresSorter']),
    'priority current cost class is block sort' => static fn (TestRunner $t) => $t->same('json-table-path-order-block-sort', $pathPriority131()['currentPathOrderByCost']['costClass']),
    'priority next cost class is block sort' => static fn (TestRunner $t) => $t->same('json-table-path-order-block-sort', $pathPriority131()['nextPathOrderByCost']['costClass']),
    'priority current sort penalty is suffix only' => static fn (TestRunner $t) => $t->same(2, $pathPriority131()['currentPathOrderByCost']['sortPenalty']),
    'priority next sort penalty grows with inserted row' => static fn (TestRunner $t) => $t->same(8, $pathPriority131()['nextPathOrderByCost']['sortPenalty']),
    'priority current effective cost includes selected path and suffix sort' => static fn (TestRunner $t) => $t->same(3, $pathPriority131()['currentPathOrderByCost']['effectiveEstimatedCost']),
    'priority next effective cost includes selected path and suffix sort' => static fn (TestRunner $t) => $t->same(9, $pathPriority131()['nextPathOrderByCost']['effectiveEstimatedCost']),
    'priority current tape follows priority desc' => static fn (TestRunner $t) => $t->same([
        ['path' => '$.rules[1]', 'rowid' => 6],
        ['path' => '$.rules[2]', 'rowid' => 9],
    ], $pathPriority131()['currentPathOrderByCost']['orderedPathTape']),
    'priority next tape includes inserted shop' => static fn (TestRunner $t) => $t->same([
        ['path' => '$.rules[1]', 'rowid' => 6],
        ['path' => '$.rules[3]', 'rowid' => 12],
        ['path' => '$.rules[2]', 'rowid' => 9],
        ['path' => '$.rules[0]', 'rowid' => 3],
    ], $pathPriority131()['nextPathOrderByCost']['orderedPathTape']),
    'priority first current ordered path is cache' => static fn (TestRunner $t) => $t->same(['path' => '$.rules[1]', 'rowid' => 6], $pathPriority131()['currentPathOrderByCost']['firstOrderedPath']),
    'priority last next ordered path is seo' => static fn (TestRunner $t) => $t->same(['path' => '$.rules[0]', 'rowid' => 3], $pathPriority131()['nextPathOrderByCost']['lastOrderedPath']),
    'priority path row count transition changes' => static fn (TestRunner $t) => $t->same(true, $pathPriority131()['pathConstraintTransitions'][4]['changed']),
    'priority output transition changes' => static fn (TestRunner $t) => $t->same(true, $pathPriority131()['pathOrderByCostTransitions'][7]['changed']),
    'priority reasons include path row count' => static fn (TestRunner $t) => $t->true(in_array('json-table-path-row-count-changed', $pathPriority131()['next131ReplanReasons'], true)),
    'priority reasons include path order output' => static fn (TestRunner $t) => $t->true(in_array('json-table-path-orderby-output-changed', $pathPriority131()['next131ReplanReasons'], true)),
    'priority reasons include source json change' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $pathPriority131()['next131ReplanReasons'], true)),
    'point path streams order' => static fn (TestRunner $t) => $t->same('json-table-path-order-stream', $pathPoint131()['currentPathOrderByCost']['costClass']),
    'point path does not require sorter' => static fn (TestRunner $t) => $t->same(false, $pathPoint131()['currentPathOrderByCost']['requiresSorter']),
    'point path suffix empty' => static fn (TestRunner $t) => $t->same([], $pathPoint131()['currentPathOrderByCost']['suffixColumns']),
    'point path current row is cache slug' => static fn (TestRunner $t) => $t->same('cache', $pathPoint131()['currentRows'][0]['atom']),
    'point path current tape has one slug row' => static fn (TestRunner $t) => $t->same([['path' => '$.rules[1]', 'rowid' => 5]], $pathPoint131()['currentPathOrderByCost']['orderedPathTape']),
    'point selected signature stable' => static fn (TestRunner $t) => $t->same(false, $pathPoint131()['pathOrderByCostTransitions'][0]['changed']),
    'point output stable' => static fn (TestRunner $t) => $t->same(false, $pathPoint131()['pathOrderByCostTransitions'][7]['changed']),
    'insert path current row count only first rule' => static fn (TestRunner $t) => $t->same(2, $pathInsert131()['currentPathOrderByCost']['pathRowCount']),
    'insert path next row count includes inserted fourth rule' => static fn (TestRunner $t) => $t->same(4, $pathInsert131()['nextPathOrderByCost']['pathRowCount']),
    'insert path next last ordered path is fourth rule slug' => static fn (TestRunner $t) => $t->same(['path' => '$.rules[3]', 'rowid' => 12], $pathInsert131()['nextPathOrderByCost']['lastOrderedPath']),
    'insert path output changes' => static fn (TestRunner $t) => $t->same(true, $pathInsert131()['pathOrderByCostTransitions'][7]['changed']),
    'insert path cost changes' => static fn (TestRunner $t) => $t->same(true, $pathInsert131()['pathOrderByCostTransitions'][5]['changed']),
    'insert path reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-path-orderby-cost-changed', $pathInsert131()['next131ReplanReasons'], true)),
    'insert path multi-value IN consumes no order prefix' => static fn (TestRunner $t) => $t->same([], $pathInsert131()['currentPathOrderByCost']['consumedPrefixColumns']),
    'insert path requires path id sorter' => static fn (TestRunner $t) => $t->same(true, $pathInsert131()['currentPathOrderByCost']['requiresSorter']),
    'insert path cost class block sorts' => static fn (TestRunner $t) => $t->same('json-table-path-order-block-sort', $pathInsert131()['currentPathOrderByCost']['costClass']),
    'unrunnable next cost class' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable131()['nextPathOrderByCost']['costClass']),
    'unrunnable next effective cost' => static fn (TestRunner $t) => $t->same(1000000, $unrunnable131()['nextPathOrderByCost']['effectiveEstimatedCost']),
    'unrunnable next ordered tape empty' => static fn (TestRunner $t) => $t->same([], $unrunnable131()['nextPathOrderByCost']['orderedPathTape']),
    'unrunnable reasons include source plan' => static fn (TestRunner $t) => $t->true(in_array('next-source-plan-becomes-unrunnable', $unrunnable131()['next131ReplanReasons'], true)),
    'invalid json column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourcePathOrderByCost('json_tree', $current131, $next131, '', [])),
    'invalid root column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourcePathOrderByCost('json_tree', $current131, $next131, 'option_value', [], '')),
    'missing source json is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourcePathOrderByCost('json_tree', ['scan_root' => '$'], $next131, 'option_value', [])),
    'malformed root is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourcePathOrderByCost('json_tree', $current131, array_replace($next131, ['scan_root' => '$[']), 'option_value', [], 'scan_root')),
    'bad function is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourcePathOrderByCost('json_bad', $current131, $next131, 'option_value', [])),
];

foreach ($tests as $name => $case) {
    $tests['json table path orderby cost current source next131 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
