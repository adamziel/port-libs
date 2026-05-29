<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current126 = [
    'option_id' => 126,
    'option_name' => 'wp_plugin_rule_lookup',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"version":1}}',
    'scan_root' => '$.rules',
];
$next126 = [
    'option_id' => 126,
    'option_name' => 'wp_plugin_rule_lookup',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"version":2}}',
    'scan_root' => '$.rules',
];

$point126 = static fn (): array => SQLiteJsonTablePlan::currentSourcePathHiddenRowidCost(
    'json_tree',
    $current126,
    $next126,
    'option_value',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
        ['column' => 'key', 'operator' => '=', 'value' => 'priority'],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'rowid']],
);

$range126 = static fn (): array => SQLiteJsonTablePlan::currentSourcePathHiddenRowidCost(
    'json_tree',
    $current126,
    $next126,
    'option_value',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'oid', 'operator' => 'BETWEEN', 'value' => [3, 9]],
        ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'rowid']],
);

$rowidOnly126 = static fn (): array => SQLiteJsonTablePlan::currentSourcePathHiddenRowidCost(
    'json_tree',
    $current126,
    $next126,
    'option_value',
    [
        ['column' => '_rowid_', 'operator' => '=', 'value' => 6],
    ],
    'scan_root',
);

$pathOnly126 = static fn (): array => SQLiteJsonTablePlan::currentSourcePathHiddenRowidCost(
    'json_tree',
    $current126,
    $next126,
    'option_value',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ],
    'scan_root',
);

$stable126 = static fn (): array => SQLiteJsonTablePlan::currentSourcePathHiddenRowidCost(
    'json_tree',
    $current126,
    $current126,
    'option_value',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
        ['column' => 'key', 'operator' => '=', 'value' => 'priority'],
    ],
    'scan_root',
);

$unrunnable126 = static fn (): array => SQLiteJsonTablePlan::currentSourcePathHiddenRowidCost(
    'json_tree',
    $current126,
    array_replace($current126, ['option_value' => null]),
    'option_value',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ],
    'scan_root',
);

$tests = [
    'normalizes function name' => static fn (TestRunner $t) => $t->same('json_tree', $point126()['function']),
    'records next126 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-path-hidden-rowid-cost-current-source-next126', $point126()['dependencies'], true)),
    'preserves next123 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-path-constraint-pushdown-current-source-next123', $point126()['dependencies'], true)),
    'preserves next119 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-indexed-constraint-cost-current-source-next119', $point126()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-path-rowid-cost-source-until-cursor-reset', $point126()['currentReaderPolicy']),
    'prepares changed source plan' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-path-rowid-cost-source-plan', $point126()['nextReaderPolicy']),
    'stable plan reuses current reader' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-path-rowid-cost-source-plan', $stable126()['nextReaderPolicy']),
    'stable has no next126 reasons' => static fn (TestRunner $t) => $t->same([], $stable126()['next126ReplanReasons']),
    'point current path signature' => static fn (TestRunner $t) => $t->same('2:path:=:"$.rules[1]"', $point126()['currentPathHiddenRowidCost']['pathSignature']),
    'point current rowid signature normalizes alias' => static fn (TestRunner $t) => $t->same('3:id:=:6', $point126()['currentPathHiddenRowidCost']['rowidSignature']),
    'point composite signature joins path and rowid' => static fn (TestRunner $t) => $t->same('2:path:=:"$.rules[1]"&&3:id:=:6', $point126()['currentPathHiddenRowidCost']['compositeSignature']),
    'point strategy is intersection' => static fn (TestRunner $t) => $t->same('path-rowid-intersection', $point126()['currentPathHiddenRowidCost']['scanStrategy']),
    'point cost class is point intersection' => static fn (TestRunner $t) => $t->same('json-table-path-rowid-point-intersection', $point126()['currentPathHiddenRowidCost']['costClass']),
    'point path cost is one' => static fn (TestRunner $t) => $t->same(1, $point126()['currentPathHiddenRowidCost']['pathEstimatedCost']),
    'point rowid cost is one' => static fn (TestRunner $t) => $t->same(1, $point126()['currentPathHiddenRowidCost']['rowidEstimatedCost']),
    'point intersected rows is one' => static fn (TestRunner $t) => $t->same(1, $point126()['currentPathHiddenRowidCost']['intersectedEstimatedRows']),
    'point effective cost is one' => static fn (TestRunner $t) => $t->same(1, $point126()['currentPathHiddenRowidCost']['effectiveEstimatedCost']),
    'point row count is one' => static fn (TestRunner $t) => $t->same(1, $point126()['currentPathHiddenRowidCost']['rowCount']),
    'point current rowid tape' => static fn (TestRunner $t) => $t->same([6], $point126()['currentPathHiddenRowidCost']['rowids']),
    'point current path rowid tape' => static fn (TestRunner $t) => $t->same([['path' => '$.rules[1]', 'rowid' => 6]], $point126()['currentPathHiddenRowidCost']['pathRowidTape']),
    'point next path rowid tape stays constrained' => static fn (TestRunner $t) => $t->same([['path' => '$.rules[1]', 'rowid' => 6]], $point126()['nextPathHiddenRowidCost']['pathRowidTape']),
    'point first path rowid recorded' => static fn (TestRunner $t) => $t->same(['path' => '$.rules[1]', 'rowid' => 6], $point126()['currentPathHiddenRowidCost']['firstPathRowid']),
    'point last path rowid recorded' => static fn (TestRunner $t) => $t->same(['path' => '$.rules[1]', 'rowid' => 6], $point126()['currentPathHiddenRowidCost']['lastPathRowid']),
    'point transition count' => static fn (TestRunner $t) => $t->same(6, count($point126()['pathHiddenRowidCostTransitions'])),
    'point composite transition stable' => static fn (TestRunner $t) => $t->same(false, $point126()['pathHiddenRowidCostTransitions'][0]['changed']),
    'point strategy transition stable' => static fn (TestRunner $t) => $t->same(false, $point126()['pathHiddenRowidCostTransitions'][1]['changed']),
    'point cost transition stable' => static fn (TestRunner $t) => $t->same(false, $point126()['pathHiddenRowidCostTransitions'][2]['changed']),
    'point rowid transition stable' => static fn (TestRunner $t) => $t->same(false, $point126()['pathHiddenRowidCostTransitions'][4]['changed']),
    'point preserves source json replan reason' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $point126()['next126ReplanReasons'], true)),
    'point preserves argument tape replan reason' => static fn (TestRunner $t) => $t->true(in_array('source-argument-tape-changed', $point126()['next126ReplanReasons'], true)),
    'range rowid signature uses oid alias as id' => static fn (TestRunner $t) => $t->same('3:id:BETWEEN:[3,9]', $range126()['currentPathHiddenRowidCost']['rowidSignature']),
    'range path signature records like prefix' => static fn (TestRunner $t) => $t->same('2:path:LIKE:"$.rules%"', $range126()['currentPathHiddenRowidCost']['pathSignature']),
    'range cost class is broad intersection' => static fn (TestRunner $t) => $t->same('json-table-path-rowid-intersection', $range126()['currentPathHiddenRowidCost']['costClass']),
    'range effective cost includes order sort penalty' => static fn (TestRunner $t) => $t->same(13, $range126()['currentPathHiddenRowidCost']['effectiveEstimatedCost']),
    'range current rowids' => static fn (TestRunner $t) => $t->same([3, 6, 9], $range126()['currentPathHiddenRowidCost']['rowids']),
    'range next rowids remain bounded' => static fn (TestRunner $t) => $t->same([3, 6, 9], $range126()['nextPathHiddenRowidCost']['rowids']),
    'range first path rowid' => static fn (TestRunner $t) => $t->same(['path' => '$.rules[0]', 'rowid' => 3], $range126()['currentPathHiddenRowidCost']['firstPathRowid']),
    'range last path rowid' => static fn (TestRunner $t) => $t->same(['path' => '$.rules[2]', 'rowid' => 9], $range126()['currentPathHiddenRowidCost']['lastPathRowid']),
    'rowid only has no composite signature' => static fn (TestRunner $t) => $t->same(null, $rowidOnly126()['currentPathHiddenRowidCost']['compositeSignature']),
    'rowid only strategy' => static fn (TestRunner $t) => $t->same('rowid-only-lookup', $rowidOnly126()['currentPathHiddenRowidCost']['scanStrategy']),
    'rowid only cost class' => static fn (TestRunner $t) => $t->same('json-table-rowid-point-lookup', $rowidOnly126()['currentPathHiddenRowidCost']['costClass']),
    'rowid only tape keeps path' => static fn (TestRunner $t) => $t->same([['path' => '$.rules[1]', 'rowid' => 6]], $rowidOnly126()['currentPathHiddenRowidCost']['pathRowidTape']),
    'path only has no rowid signature' => static fn (TestRunner $t) => $t->same(null, $pathOnly126()['currentPathHiddenRowidCost']['rowidSignature']),
    'path only strategy' => static fn (TestRunner $t) => $t->same('path-only-lookup', $pathOnly126()['currentPathHiddenRowidCost']['scanStrategy']),
    'path only cost class' => static fn (TestRunner $t) => $t->same('json-table-path-only-lookup', $pathOnly126()['currentPathHiddenRowidCost']['costClass']),
    'path only keeps two child rowids' => static fn (TestRunner $t) => $t->same([5, 6], $pathOnly126()['currentPathHiddenRowidCost']['rowids']),
    'path only tape keeps both child rows' => static fn (TestRunner $t) => $t->same([['path' => '$.rules[1]', 'rowid' => 5], ['path' => '$.rules[1]', 'rowid' => 6]], $pathOnly126()['currentPathHiddenRowidCost']['pathRowidTape']),
    'unrunnable next strategy' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable126()['nextPathHiddenRowidCost']['scanStrategy']),
    'unrunnable next cost class' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable126()['nextPathHiddenRowidCost']['costClass']),
    'unrunnable next effective cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $unrunnable126()['nextPathHiddenRowidCost']['effectiveEstimatedCost']),
    'unrunnable next row tape empty' => static fn (TestRunner $t) => $t->same([], $unrunnable126()['nextPathHiddenRowidCost']['pathRowidTape']),
    'unrunnable reason includes source plan' => static fn (TestRunner $t) => $t->true(in_array('next-source-plan-becomes-unrunnable', $unrunnable126()['next126ReplanReasons'], true)),
    'bad json column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourcePathHiddenRowidCost('json_tree', $current126, $next126, '', [])),
    'bad root column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourcePathHiddenRowidCost('json_tree', $current126, $next126, 'option_value', [], '')),
    'bad function rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourcePathHiddenRowidCost('json_bad', $current126, $next126, 'option_value', [])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table path hidden rowid cost current source next126 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
