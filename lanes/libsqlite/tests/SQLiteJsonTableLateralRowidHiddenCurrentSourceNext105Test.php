<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current105 = [
    [
        'option_id' => 10,
        'option_name' => 'wp_plugin_alpha_rules',
        'option_value' => '{"rules":[{"slug":"seo","enabled":true},{"slug":"cache","enabled":false}],"meta":{"version":1}}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 20,
        'option_name' => 'wp_plugin_beta_rules',
        'option_value' => '{"rules":[],"meta":{"version":1}}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 30,
        'option_name' => 'wp_plugin_gamma_rules',
        'option_value' => '{"rules":[{"slug":"forms","enabled":true}],"meta":{"version":1}}',
        'scan_root' => '$.rules',
    ],
];

$next105 = [
    [
        'option_id' => 30,
        'option_name' => 'wp_plugin_gamma_rules',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode('{"rules":[{"slug":"forms","enabled":true}],"meta":{"version":1}}'))),
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 10,
        'option_name' => 'wp_plugin_alpha_rules',
        'option_value' => '{"rules":[{"slug":"seo","enabled":true},{"slug":"cache","enabled":true},{"slug":"media","enabled":true}],"meta":{"version":2}}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 40,
        'option_name' => 'wp_plugin_delta_rules',
        'option_value' => '{"rules":[{"slug":"shop","enabled":true}],"meta":{"version":1}}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 20,
        'option_name' => 'wp_plugin_beta_rules',
        'option_value' => '{"rules":[{"slug":"beta","enabled":true}],"meta":{"version":2}}',
        'scan_root' => '$.meta',
    ],
];

$constraints105 = [
    ['column' => 'root', 'operator' => '=', 'value' => '$.rules'],
    ['column' => '_rowid_', 'operator' => '>=', 'value' => 1],
    ['column' => 'type', 'operator' => '=', 'value' => 'object'],
];

$plan105 = static fn (): array => SQLiteJsonTablePlan::lateralRowidHiddenCurrentSource(
    $current105,
    $next105,
    'option_id',
    'option_value',
    'json_each',
    $constraints105,
    'scan_root',
    [['column' => 'id']],
    'left',
);

$stableReorder105 = static fn (): array => SQLiteJsonTablePlan::lateralRowidHiddenCurrentSource(
    $current105,
    [$current105[2], $current105[0], $current105[1]],
    'option_id',
    'option_value',
    'json_each',
    $constraints105,
    'scan_root',
    [['column' => 'id']],
    'left',
);

$oidPlan105 = static fn (): array => SQLiteJsonTablePlan::lateralRowidHiddenCurrentSource(
    $current105,
    $next105,
    'option_id',
    'option_value',
    'json_tree',
    [
        ['column' => 'root', 'operator' => '=', 'value' => '$.rules'],
        ['column' => 'oid', 'operator' => 'BETWEEN', 'value' => [1, 4]],
    ],
    'scan_root',
    [['column' => 'id']],
    'inner',
);

$tests = [
    'normalizes function' => static fn (TestRunner $t) => $t->same('json_each', $plan105()['function']),
    'records next105 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-lateral-rowid-hidden-current-source-next105', $plan105()['dependencies'], true)),
    'preserves next103 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-lateral-hidden-constraint-current-source-next103', $plan105()['dependencies'], true)),
    'preserves next99 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-rowid-hidden-constraint-current-source-next99', $plan105()['dependencies'], true)),
    'preserves next81 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-lateral-rowid-comparison', $plan105()['dependencies'], true)),
    'pins current hidden rowid reader policy' => static fn (TestRunner $t) => $t->same('pin-current-lateral-hidden-rowid-source-until-host-key-advances', $plan105()['currentReaderPolicy']),
    'prepares next hidden rowid source tape' => static fn (TestRunner $t) => $t->same('prepare-next-lateral-hidden-rowid-source-tape', $plan105()['nextReaderPolicy']),
    'requires replan for changed rowid tape' => static fn (TestRunner $t) => $t->same(true, $plan105()['replanRequired']),
    'reports rowid tape changed' => static fn (TestRunner $t) => $t->true(in_array('lateral-hidden-rowid-tape-changed', $plan105()['replanReasons'], true)),
    'reports added rowid source row' => static fn (TestRunner $t) => $t->true(in_array('next-hidden-rowid-source-row-added', $plan105()['replanReasons'], true)),
    'reports payload changed source row' => static fn (TestRunner $t) => $t->true(in_array('hidden-rowid-source-payload-changed', $plan105()['replanReasons'], true)),
    'keeps source json change reason' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $plan105()['replanReasons'], true)),
    'keeps source root change reason' => static fn (TestRunner $t) => $t->true(in_array('source-root-changed', $plan105()['replanReasons'], true)),
    'records host order transition current keys' => static fn (TestRunner $t) => $t->same([10, 20, 30], $plan105()['hostOrderTransition']['current']),
    'records host order transition next keys' => static fn (TestRunner $t) => $t->same([30, 10, 40, 20], $plan105()['hostOrderTransition']['next']),
    'rowid transition count follows keyed union' => static fn (TestRunner $t) => $t->same(4, count($plan105()['rowidTransitions'])),
    'alpha rowid transition is first by current key' => static fn (TestRunner $t) => $t->same(10, $plan105()['rowidTransitions'][0]['hostKey']),
    'alpha current rowids are stable source ids' => static fn (TestRunner $t) => $t->same([1, 2], $plan105()['rowidTransitions'][0]['currentRowids']),
    'alpha next rowids include inserted media row' => static fn (TestRunner $t) => $t->same([1, 2, 3], $plan105()['rowidTransitions'][0]['nextRowids']),
    'alpha rowid transition changed' => static fn (TestRunner $t) => $t->same(true, $plan105()['rowidTransitions'][0]['rowidChanged']),
    'alpha rowid transition reason is rowid tape changed' => static fn (TestRunner $t) => $t->same('lateral-hidden-rowid-tape-changed', $plan105()['rowidTransitions'][0]['reason']),
    'alpha first row transition is stable' => static fn (TestRunner $t) => $t->same('stable-hidden-rowid-source-row', $plan105()['rowidTransitions'][0]['rowTransitions'][0]['reason']),
    'alpha second row transition sees payload change' => static fn (TestRunner $t) => $t->same('hidden-rowid-source-payload-changed', $plan105()['rowidTransitions'][0]['rowTransitions'][1]['reason']),
    'alpha third row transition is added' => static fn (TestRunner $t) => $t->same('next-hidden-rowid-source-row-added', $plan105()['rowidTransitions'][0]['rowTransitions'][2]['reason']),
    'alpha current first rowid is one' => static fn (TestRunner $t) => $t->same(1, $plan105()['currentRowidByHost']['int:10']['firstRowid']),
    'alpha next last rowid is three' => static fn (TestRunner $t) => $t->same(3, $plan105()['nextRowidByHost']['int:10']['lastRowid']),
    'alpha current source kind is text' => static fn (TestRunner $t) => $t->same('text', $plan105()['currentRowidByHost']['int:10']['sourceKind']),
    'gamma next source kind is jsonb' => static fn (TestRunner $t) => $t->same('jsonb', $plan105()['nextRowidByHost']['int:30']['sourceKind']),
    'beta current summary null extends' => static fn (TestRunner $t) => $t->same(true, $plan105()['currentRowidByHost']['int:20']['nullExtended']),
    'beta current rowids are empty' => static fn (TestRunner $t) => $t->same([], $plan105()['rowidTransitions'][1]['currentRowids']),
    'beta next rowids are empty after root residual mismatch' => static fn (TestRunner $t) => $t->same([], $plan105()['rowidTransitions'][1]['nextRowids']),
    'beta rowid transition keeps hidden source change reason' => static fn (TestRunner $t) => $t->same('lateral-hidden-source-plan-changed', $plan105()['rowidTransitions'][1]['reason']),
    'gamma rowid transition keeps hidden source kind reason' => static fn (TestRunner $t) => $t->same('lateral-hidden-source-plan-changed', $plan105()['rowidTransitions'][2]['reason']),
    'gamma ordinal changed is tracked' => static fn (TestRunner $t) => $t->same(true, $plan105()['rowidTransitions'][2]['ordinalChanged']),
    'gamma rowid unchanged' => static fn (TestRunner $t) => $t->same(false, $plan105()['rowidTransitions'][2]['rowidChanged']),
    'delta added host rowid transition is reported' => static fn (TestRunner $t) => $t->same('next-lateral-hidden-rowid-host-row-added', $plan105()['rowidTransitions'][3]['reason']),
    'delta current summary is null' => static fn (TestRunner $t) => $t->same(null, $plan105()['rowidTransitions'][3]['currentSummary']),
    'delta next rowids start at one' => static fn (TestRunner $t) => $t->same([1], $plan105()['rowidTransitions'][3]['nextRowids']),
    'rowid alias constraint keeps original underscore alias' => static fn (TestRunner $t) => $t->same('_rowid_', $plan105()['rowidAliasConstraints'][0]['originalColumn']),
    'rowid alias constraint normalizes to id' => static fn (TestRunner $t) => $t->same('id', $plan105()['rowidAliasConstraints'][0]['normalizedColumn']),
    'rowid alias constraint keeps operator' => static fn (TestRunner $t) => $t->same('>=', $plan105()['rowidAliasConstraints'][0]['operator']),
    'rowid alias transition is stable' => static fn (TestRunner $t) => $t->same(false, $plan105()['rowidAliasTransition']['changed']),
    'stable reorder does not require replan' => static fn (TestRunner $t) => $t->same(false, $stableReorder105()['replanRequired']),
    'stable reorder reuses hidden rowid tape' => static fn (TestRunner $t) => $t->same('reuse-current-lateral-hidden-rowid-source-tape', $stableReorder105()['nextReaderPolicy']),
    'stable reorder records changed host order' => static fn (TestRunner $t) => $t->same(true, $stableReorder105()['hostOrderTransition']['changed']),
    'stable reorder alpha rowids stay same' => static fn (TestRunner $t) => $t->same([1, 2], $stableReorder105()['rowidTransitions'][0]['currentRowids']),
    'stable reorder alpha next rowids stay same' => static fn (TestRunner $t) => $t->same([1, 2], $stableReorder105()['rowidTransitions'][0]['nextRowids']),
    'stable reorder alpha rowid transition is stable' => static fn (TestRunner $t) => $t->same('stable-lateral-hidden-rowid-source', $stableReorder105()['rowidTransitions'][0]['reason']),
    'stable reorder beta null extension remains stable' => static fn (TestRunner $t) => $t->same('stable-lateral-hidden-rowid-source', $stableReorder105()['rowidTransitions'][1]['reason']),
    'stable reorder gamma ordinal changed without rowid change' => static fn (TestRunner $t) => $t->same(true, $stableReorder105()['rowidTransitions'][2]['ordinalChanged']),
    'oid plan normalizes function to json tree' => static fn (TestRunner $t) => $t->same('json_tree', $oidPlan105()['function']),
    'oid plan records oid alias provenance' => static fn (TestRunner $t) => $t->same('oid', $oidPlan105()['rowidAliasConstraints'][0]['originalColumn']),
    'oid plan alpha tree rowids include root and children' => static fn (TestRunner $t) => $t->same([1, 2, 3, 4], $oidPlan105()['rowidTransitions'][0]['currentRowids']),
    'oid plan alpha next tree rowids include inserted object' => static fn (TestRunner $t) => $t->same([1, 2, 3, 4], $oidPlan105()['rowidTransitions'][0]['nextRowids']),
    'oid plan gamma jsonb source remains stable rowids' => static fn (TestRunner $t) => $t->same([1, 2, 3], $oidPlan105()['rowidTransitions'][2]['nextRowids']),
    'inner oid plan does not null extend beta current' => static fn (TestRunner $t) => $t->same(false, $oidPlan105()['current'][1]['nullExtended']),
    'inner oid plan beta rowids are empty' => static fn (TestRunner $t) => $t->same([], $oidPlan105()['rowidTransitions'][1]['currentRowids']),
    'missing current key is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralRowidHiddenCurrentSource([['option_value' => '{}']], $next105, 'option_id', 'option_value', 'json_each')),
    'missing next key is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralRowidHiddenCurrentSource($current105, [['option_value' => '{}']], 'option_id', 'option_value', 'json_each')),
    'duplicate current key is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralRowidHiddenCurrentSource([$current105[0], $current105[0]], $next105, 'option_id', 'option_value', 'json_each')),
    'duplicate next key is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralRowidHiddenCurrentSource($current105, [$next105[0], $next105[0]], 'option_id', 'option_value', 'json_each')),
    'empty key column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralRowidHiddenCurrentSource($current105, $next105, '', 'option_value', 'json_each')),
    'empty json column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralRowidHiddenCurrentSource($current105, $next105, 'option_id', '', 'json_each')),
    'bad function is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralRowidHiddenCurrentSource($current105, $next105, 'option_id', 'option_value', 'json_bad')),
    'bad join type is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralRowidHiddenCurrentSource($current105, $next105, 'option_id', 'option_value', 'json_each', [], null, [], 'outer')),
];

foreach ($tests as $name => $case) {
    $tests['json table lateral rowid hidden current source next105 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
