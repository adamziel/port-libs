<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentRows = [
    [
        'option_id' => 10,
        'option_name' => 'wp_plugin_alpha_rules',
        'payload' => '{"rules":[{"slug":"seo","enabled":true},{"slug":"cache","enabled":false}]}',
        'scan_root' => '$.rules',
        'wanted_type' => 'object',
    ],
    [
        'option_id' => 20,
        'option_name' => 'wp_plugin_beta_rules',
        'payload' => '{"rules":[],"meta":{"version":1}}',
        'scan_root' => '$.rules',
        'wanted_type' => 'object',
    ],
    [
        'option_id' => 30,
        'option_name' => 'wp_plugin_gamma_rules',
        'payload' => '{"rules":[{"slug":"forms","enabled":true}]}',
        'scan_root' => '$.rules',
        'wanted_type' => 'object',
    ],
];

$nextRows = [
    [
        'option_id' => 30,
        'option_name' => 'wp_plugin_gamma_rules',
        'payload' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode('{"rules":[{"slug":"forms","enabled":true}]}'))),
        'scan_root' => '$.rules',
        'wanted_type' => 'object',
    ],
    [
        'option_id' => 10,
        'option_name' => 'wp_plugin_alpha_rules',
        'payload' => '{"rules":[{"slug":"seo","enabled":true},{"slug":"cache","enabled":true},{"slug":"media","enabled":true}]}',
        'scan_root' => '$.rules',
        'wanted_type' => 'object',
    ],
    [
        'option_id' => 40,
        'option_name' => 'wp_plugin_delta_rules',
        'payload' => '{"rules":[{"slug":"shop","enabled":true}]}',
        'scan_root' => '$.rules',
        'wanted_type' => 'object',
    ],
    [
        'option_id' => 20,
        'option_name' => 'wp_plugin_beta_rules',
        'payload' => '{"rules":[{"slug":"beta","enabled":true}],"meta":{"version":2}}',
        'scan_root' => '$.missing',
        'wanted_type' => 'object',
    ],
];

$constraintSources = [
    ['column' => 'json', 'sourceColumn' => 'payload'],
    ['column' => 'root', 'sourceColumn' => 'scan_root'],
    ['column' => 'type', 'sourceColumn' => 'wanted_type'],
];

$plan = static fn (): array => SQLiteJsonTablePlan::lateralConstraintHidden(
    $currentRows,
    $nextRows,
    'option_id',
    'json_each',
    $constraintSources,
    [['column' => 'id']],
    'left',
);

$stablePlan = static fn (): array => SQLiteJsonTablePlan::lateralConstraintHidden(
    $currentRows,
    [$currentRows[2], $currentRows[0], $currentRows[1]],
    'option_id',
    'json_each',
    $constraintSources,
    [['column' => 'id']],
    'left',
);

$treePlan = static fn (): array => SQLiteJsonTablePlan::lateralConstraintHidden(
    $currentRows,
    $nextRows,
    'option_id',
    'json_tree',
    [
        ['column' => 'json', 'sourceColumn' => 'payload'],
        ['column' => 'root', 'sourceColumn' => 'scan_root'],
        ['column' => 'id', 'operator' => 'BETWEEN', 'value' => [1, 4]],
    ],
    [['column' => 'rowid']],
    'inner',
);

$nullRootPlan = static fn (): array => SQLiteJsonTablePlan::lateralConstraintHidden(
    [$currentRows[0]],
    [array_replace($currentRows[0], ['scan_root' => null])],
    'option_id',
    'json_each',
    $constraintSources,
    [],
    'left',
);

$tests = [
    'normalizes function' => static fn (TestRunner $t) => $t->same('json_each', $plan()['function']),
    'records lateral hidden constraint dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-lateral-hidden-constraint', $plan()['dependencies'], true)),
    'records hidden source dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-hidden-constraint-source-current-source-next102', $plan()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-lateral-hidden-current-source-until-host-key-advances', $plan()['currentReaderPolicy']),
    'prepares next source tape' => static fn (TestRunner $t) => $t->same('prepare-next-lateral-hidden-current-source-tape', $plan()['nextReaderPolicy']),
    'requires replan' => static fn (TestRunner $t) => $t->same(true, $plan()['replanRequired']),
    'is left join' => static fn (TestRunner $t) => $t->same(true, $plan()['leftJoin']),
    'records current host order' => static fn (TestRunner $t) => $t->same([10, 20, 30], $plan()['hostOrderTransition']['current']),
    'records next host order' => static fn (TestRunner $t) => $t->same([30, 10, 40, 20], $plan()['hostOrderTransition']['next']),
    'host order changed' => static fn (TestRunner $t) => $t->same(true, $plan()['hostOrderTransition']['changed']),
    'transition count follows keyed union' => static fn (TestRunner $t) => $t->same(4, count($plan()['transitions'])),
    'alpha transition first by current key' => static fn (TestRunner $t) => $t->same(10, $plan()['transitions'][0]['hostKey']),
    'alpha current rows two' => static fn (TestRunner $t) => $t->same(2, $plan()['transitions'][0]['currentRows']),
    'alpha next rows three' => static fn (TestRunner $t) => $t->same(3, $plan()['transitions'][0]['nextRows']),
    'alpha row count changed' => static fn (TestRunner $t) => $t->same(true, $plan()['transitions'][0]['rowCountChanged']),
    'alpha reason plan changed' => static fn (TestRunner $t) => $t->same('lateral-hidden-current-source-plan-changed', $plan()['transitions'][0]['reason']),
    'alpha pair reason includes value change' => static fn (TestRunner $t) => $t->true(in_array('hidden-constraint-source-value-changed', $plan()['transitions'][0]['pairReplanReasons'], true)),
    'alpha pair reason includes row count change' => static fn (TestRunner $t) => $t->true(in_array('hidden-constraint-source-row-count-changed', $plan()['transitions'][0]['pairReplanReasons'], true)),
    'alpha constraint transition column json' => static fn (TestRunner $t) => $t->same('json', $plan()['transitions'][0]['constraintValueTransitions'][0]['column']),
    'alpha constraint transition source column payload' => static fn (TestRunner $t) => $t->same('payload', $plan()['transitions'][0]['constraintValueTransitions'][0]['sourceColumn']),
    'alpha json transition changed' => static fn (TestRunner $t) => $t->same(true, $plan()['transitions'][0]['constraintValueTransitions'][0]['changed']),
    'alpha root transition stable' => static fn (TestRunner $t) => $t->same(false, $plan()['transitions'][0]['constraintValueTransitions'][1]['changed']),
    'alpha visible type transition stable' => static fn (TestRunner $t) => $t->same(false, $plan()['transitions'][0]['constraintValueTransitions'][2]['changed']),
    'alpha current first value contains seo' => static fn (TestRunner $t) => $t->same('{"slug":"seo","enabled":true}', $plan()['current'][0]['rows'][0]['value']),
    'alpha next last value contains media' => static fn (TestRunner $t) => $t->same('{"slug":"media","enabled":true}', $plan()['next'][0]['rows'][2]['value']),
    'alpha constraint source hidden json' => static fn (TestRunner $t) => $t->same(true, $plan()['current'][0]['constraintSources'][0]['hidden']),
    'alpha constraint source visible type' => static fn (TestRunner $t) => $t->same(false, $plan()['current'][0]['constraintSources'][2]['hidden']),
    'alpha filter argument count includes json root and visible type' => static fn (TestRunner $t) => $t->same(3, count($plan()['current'][0]['filterArguments'])),
    'beta current null extends' => static fn (TestRunner $t) => $t->same(true, $plan()['transitions'][1]['currentNullExtended']),
    'beta next null extends after missing root' => static fn (TestRunner $t) => $t->same(true, $plan()['transitions'][1]['nextNullExtended']),
    'beta root value changes' => static fn (TestRunner $t) => $t->same(true, $plan()['transitions'][1]['constraintValueTransitions'][1]['changed']),
    'beta reason is plan changed' => static fn (TestRunner $t) => $t->same('lateral-hidden-current-source-plan-changed', $plan()['transitions'][1]['reason']),
    'gamma current rows one' => static fn (TestRunner $t) => $t->same(1, $plan()['transitions'][2]['currentRows']),
    'gamma next rows one' => static fn (TestRunner $t) => $t->same(1, $plan()['transitions'][2]['nextRows']),
    'gamma detects json kind change' => static fn (TestRunner $t) => $t->true(in_array('hidden-constraint-source-json-kind-changed', $plan()['transitions'][2]['pairReplanReasons'], true)),
    'gamma next source kind is jsonb' => static fn (TestRunner $t) => $t->same('jsonb', $plan()['next'][2]['jsonInputKind']),
    'gamma ordinal changed' => static fn (TestRunner $t) => $t->same(true, $plan()['transitions'][2]['ordinalChanged']),
    'delta added host reason' => static fn (TestRunner $t) => $t->same('next-lateral-hidden-current-source-host-row-added', $plan()['transitions'][3]['reason']),
    'delta current is null' => static fn (TestRunner $t) => $t->same(null, $plan()['transitions'][3]['current']),
    'delta next rows one' => static fn (TestRunner $t) => $t->same(1, $plan()['transitions'][3]['nextRows']),
    'reasons include added host' => static fn (TestRunner $t) => $t->true(in_array('next-lateral-hidden-current-source-host-row-added', $plan()['replanReasons'], true)),
    'reasons include hidden value changed' => static fn (TestRunner $t) => $t->true(in_array('hidden-constraint-source-value-changed', $plan()['replanReasons'], true)),
    'stable reorder does not require replan' => static fn (TestRunner $t) => $t->same(false, $stablePlan()['replanRequired']),
    'stable reorder reuses source tape' => static fn (TestRunner $t) => $t->same('reuse-current-lateral-hidden-current-source-tape', $stablePlan()['nextReaderPolicy']),
    'stable reorder records host order changed only' => static fn (TestRunner $t) => $t->same(true, $stablePlan()['hostOrderTransition']['changed']),
    'stable reorder alpha reason stable' => static fn (TestRunner $t) => $t->same('stable-lateral-hidden-current-source', $stablePlan()['transitions'][0]['reason']),
    'stable reorder gamma ordinal changed' => static fn (TestRunner $t) => $t->same(true, $stablePlan()['transitions'][2]['ordinalChanged']),
    'tree normalizes function' => static fn (TestRunner $t) => $t->same('json_tree', $treePlan()['function']),
    'tree inner join disables leftJoin' => static fn (TestRunner $t) => $t->same(false, $treePlan()['leftJoin']),
    'tree alpha current includes root and children' => static fn (TestRunner $t) => $t->same(4, $treePlan()['transitions'][0]['currentRows']),
    'tree beta does not null extend' => static fn (TestRunner $t) => $t->same(false, $treePlan()['transitions'][1]['currentNullExtended']),
    'tree id constraint is hidden' => static fn (TestRunner $t) => $t->same(true, $treePlan()['current'][0]['constraintSources'][2]['hidden']),
    'tree order by rowid consumed' => static fn (TestRunner $t) => $t->same(true, $treePlan()['current'][0]['orderByConsumed']),
    'null root next is unrunnable' => static fn (TestRunner $t) => $t->same(false, $nullRootPlan()['next'][0]['runnable']),
    'null root next reports SQL NULL root' => static fn (TestRunner $t) => $t->same('SQL NULL root path', $nullRootPlan()['next'][0]['jsonError']),
    'null root has no next rows' => static fn (TestRunner $t) => $t->same(0, $nullRootPlan()['transitions'][0]['nextRows']),
    'missing host key is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralConstraintHidden([['payload' => '{}', 'scan_root' => '$']], $nextRows, 'option_id', 'json_each', $constraintSources)),
    'missing source column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralConstraintHidden([['option_id' => 1, 'scan_root' => '$']], $nextRows, 'option_id', 'json_each', $constraintSources)),
    'duplicate current key is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralConstraintHidden([$currentRows[0], $currentRows[0]], $nextRows, 'option_id', 'json_each', $constraintSources)),
    'duplicate next key is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralConstraintHidden($currentRows, [$nextRows[0], $nextRows[0]], 'option_id', 'json_each', $constraintSources)),
    'empty host key is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralConstraintHidden($currentRows, $nextRows, '', 'json_each', $constraintSources)),
    'empty constraint sources are rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralConstraintHidden($currentRows, $nextRows, 'option_id', 'json_each', [])),
    'bad function is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralConstraintHidden($currentRows, $nextRows, 'option_id', 'json_bad', $constraintSources)),
    'bad join type is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralConstraintHidden($currentRows, $nextRows, 'option_id', 'json_each', $constraintSources, [], 'outer')),
];

foreach ($tests as $name => $case) {
    $tests['json table lateral constraint hidden ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
