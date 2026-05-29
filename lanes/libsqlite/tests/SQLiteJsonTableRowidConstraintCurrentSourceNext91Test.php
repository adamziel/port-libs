<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current91 = [
    'option_id' => 31,
    'option_name' => 'wp_plugin_nav_rules',
    'option_value' => '{"rules":[{"slug":"seo","enabled":true},{"slug":"cache","enabled":false},{"slug":"forms","enabled":true}],"meta":{"version":1}}',
    'json_root' => '$.rules',
];
$next91 = [
    'option_id' => 31,
    'option_name' => 'wp_plugin_nav_rules',
    'option_value' => '{"rules":[{"slug":"seo","enabled":true},{"slug":"forms","enabled":true},{"slug":"security","enabled":true}],"meta":{"version":2}}',
    'json_root' => '$.rules',
];

$plan91 = static fn (string $alias, int $rowid, array $next = null, array $orderBy = []): array => SQLiteJsonTablePlan::currentSourceConstraintPlanner(
    'json_each',
    $current91,
    $next ?? $next91,
    'option_value',
    [
        ['column' => $alias, 'operator' => '=', 'value' => $rowid],
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ],
    'json_root',
    $orderBy,
);

$treePlan91 = static fn (string $alias, int $rowid, array $next = null): array => SQLiteJsonTablePlan::currentSourceConstraintPlanner(
    'json_tree',
    $current91,
    $next ?? $next91,
    'option_value',
    [
        ['column' => $alias, 'operator' => '=', 'value' => $rowid],
        ['column' => 'atom', 'operator' => 'IS NOT NULL', 'value' => null],
    ],
    'json_root',
    [['column' => 'id']],
);

$directRows91 = static fn (string $alias, int $rowid): array => SQLiteJsonTablePlan::filteredRows(
    'json_each',
    [
        ['column' => 'json', 'operator' => '=', 'value' => $current91['option_value']],
        ['column' => 'root', 'operator' => '=', 'value' => '$.rules'],
        ['column' => $alias, 'operator' => '=', 'value' => $rowid],
    ],
);

$tests = [
    'rowid alias normalizes into visible id in idx string' => static fn (TestRunner $t) => $t->same('hidden:json:=|hidden:root:=|visible:id:=|visible:type:=', $plan91('rowid', 2)['current']['idxStr']),
    'underscore rowid alias normalizes into visible id in idx string' => static fn (TestRunner $t) => $t->same('hidden:json:=|hidden:root:=|visible:id:=|visible:type:=', $plan91('_rowid_', 2)['current']['idxStr']),
    'oid alias normalizes into visible id in idx string' => static fn (TestRunner $t) => $t->same('hidden:json:=|hidden:root:=|visible:id:=|visible:type:=', $plan91('oid', 2)['current']['idxStr']),
    'rowid alias usage column is id' => static fn (TestRunner $t) => $t->same('id', $plan91('rowid', 2)['current']['constraintUsage'][2]['column']),
    'underscore rowid usage column is id' => static fn (TestRunner $t) => $t->same('id', $plan91('_rowid_', 2)['current']['constraintUsage'][2]['column']),
    'oid usage column is id' => static fn (TestRunner $t) => $t->same('id', $plan91('oid', 2)['current']['constraintUsage'][2]['column']),
    'rowid alias is pushed as visible constraint' => static fn (TestRunner $t) => $t->same('visible', $plan91('rowid', 2)['current']['constraintUsage'][2]['kind']),
    'underscore rowid alias is pushed as visible constraint' => static fn (TestRunner $t) => $t->same('visible', $plan91('_rowid_', 2)['current']['constraintUsage'][2]['kind']),
    'oid alias is pushed as visible constraint' => static fn (TestRunner $t) => $t->same('visible', $plan91('oid', 2)['current']['constraintUsage'][2]['kind']),
    'rowid alias filter argument keeps requested rowid' => static fn (TestRunner $t) => $t->same(2, $plan91('rowid', 2)['current']['filterArguments'][2]),
    'underscore rowid alias filter argument keeps requested rowid' => static fn (TestRunner $t) => $t->same(2, $plan91('_rowid_', 2)['current']['filterArguments'][2]),
    'oid alias filter argument keeps requested rowid' => static fn (TestRunner $t) => $t->same(2, $plan91('oid', 2)['current']['filterArguments'][2]),
    'rowid alias leaves no rowid residual' => static fn (TestRunner $t) => $t->same([], array_column($plan91('rowid', 2)['current']['constraintUsage'], 'kind') === ['hidden', 'hidden', 'visible', 'visible'] ? [] : ['unexpected']),
    'rowid alias reduces estimated rows like id equality' => static fn (TestRunner $t) => $t->same(1, $plan91('rowid', 2)['current']['estimatedRows']),
    'underscore rowid alias reduces estimated rows like id equality' => static fn (TestRunner $t) => $t->same(1, $plan91('_rowid_', 2)['current']['estimatedRows']),
    'oid alias reduces estimated rows like id equality' => static fn (TestRunner $t) => $t->same(1, $plan91('oid', 2)['current']['estimatedRows']),
    'rowid alias current row selects cache rule' => static fn (TestRunner $t) => $t->same(1, $plan91('rowid', 2)['currentRows'][0]['key']),
    'rowid alias current row id is two' => static fn (TestRunner $t) => $t->same(2, $plan91('rowid', 2)['currentRows'][0]['id']),
    'rowid alias next row selects forms after source change' => static fn (TestRunner $t) => $t->same(1, $plan91('rowid', 2)['nextRows'][0]['key']),
    'rowid alias next source retains changed json' => static fn (TestRunner $t) => $t->same($next91['option_value'], $plan91('rowid', 2)['nextRows'][0]['json']),
    'rowid alias source change requires replan' => static fn (TestRunner $t) => $t->same(true, $plan91('rowid', 2)['replanRequired']),
    'rowid alias source json change is reported' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $plan91('rowid', 2)['replanReasons'], true)),
    'rowid alias argument tape change is reported' => static fn (TestRunner $t) => $t->true(in_array('source-argument-tape-changed', $plan91('rowid', 2)['replanReasons'], true)),
    'rowid alias stable source can reuse plan' => static fn (TestRunner $t) => $t->same(false, $plan91('rowid', 2, $current91)['replanRequired']),
    'rowid alias stable source policy reuses plan' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-source-plan', $plan91('rowid', 2, $current91)['nextReaderPolicy']),
    'rowid alias current reader remains pinned' => static fn (TestRunner $t) => $t->same('pin-current-json-table-source-until-cursor-reset', $plan91('rowid', 2)['currentReaderPolicy']),
    'rowid alias next reader prepares changed source' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-source-plan', $plan91('rowid', 2)['nextReaderPolicy']),
    'rowid alias exposes current next usage pair' => static fn (TestRunner $t) => $t->same('id', $plan91('rowid', 2)['current']['currentNext'][2]['current']['column']),
    'rowid alias visible type remains after id' => static fn (TestRunner $t) => $t->same('type', $plan91('rowid', 2)['current']['currentNext'][3]['current']['column']),
    'rowid alias ordered plan consumes id order' => static fn (TestRunner $t) => $t->same(true, $plan91('rowid', 2, null, [['column' => 'id']])['current']['orderByConsumed']),
    'rowid alias direct filtered rows select requested id' => static fn (TestRunner $t) => $t->same(2, $directRows91('rowid', 2)[0]['id']),
    'underscore rowid direct filtered rows select requested id' => static fn (TestRunner $t) => $t->same(3, $directRows91('_rowid_', 3)[0]['id']),
    'oid direct filtered rows select requested id' => static fn (TestRunner $t) => $t->same(1, $directRows91('oid', 1)[0]['id']),
    'rowid direct missing id returns empty rows' => static fn (TestRunner $t) => $t->same([], $directRows91('rowid', 30)),
    'rowid direct row keeps expected key' => static fn (TestRunner $t) => $t->same(1, $directRows91('rowid', 2)[0]['key']),
    'rowid direct row keeps expected type' => static fn (TestRunner $t) => $t->same('object', $directRows91('rowid', 2)[0]['type']),
    'tree rowid alias normalizes in current tree plan' => static fn (TestRunner $t) => $t->same('id', $treePlan91('rowid', 6)['current']['constraintUsage'][2]['column']),
    'tree underscore rowid alias normalizes in current tree plan' => static fn (TestRunner $t) => $t->same('id', $treePlan91('_rowid_', 6)['current']['constraintUsage'][2]['column']),
    'tree oid alias normalizes in current tree plan' => static fn (TestRunner $t) => $t->same('id', $treePlan91('oid', 6)['current']['constraintUsage'][2]['column']),
    'tree rowid alias selects enabled leaf' => static fn (TestRunner $t) => $t->same('enabled', $treePlan91('rowid', 3)['currentRows'][0]['key']),
    'tree rowid alias selects scalar atom' => static fn (TestRunner $t) => $t->same(1, $treePlan91('rowid', 3)['currentRows'][0]['atom']),
    'tree rowid alias next source keeps same id boundary' => static fn (TestRunner $t) => $t->same(3, $treePlan91('rowid', 3)['nextRows'][0]['id']),
    'tree rowid alias next source sees changed fullkey tape' => static fn (TestRunner $t) => $t->same('$.rules[0].enabled', $treePlan91('rowid', 3)['nextRows'][0]['fullkey']),
    'tree rowid alias stable source has equal row counts' => static fn (TestRunner $t) => $t->same(count($treePlan91('rowid', 3, $current91)['currentRows']), count($treePlan91('rowid', 3, $current91)['nextRows'])),
    'tree rowid alias rowid estimate is narrow' => static fn (TestRunner $t) => $t->same(1, $treePlan91('rowid', 3)['current']['estimatedRows']),
    'rowid alias dependency remains current source planner' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-current-source-constraint-planner', $plan91('rowid', 2)['dependencies'], true)),
];

foreach ($tests as $name => $case) {
    $tests['json table rowid constraint current source next91 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
