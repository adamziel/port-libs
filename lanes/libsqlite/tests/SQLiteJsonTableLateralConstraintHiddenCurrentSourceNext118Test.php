<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current118 = [
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

$next118 = [
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

$constraintSources118 = [
    ['column' => 'json', 'sourceColumn' => 'payload'],
    ['column' => 'root', 'sourceColumn' => 'scan_root'],
    ['column' => 'type', 'sourceColumn' => 'wanted_type'],
];

$plan118 = static fn (): array => SQLiteJsonTablePlan::lateralConstraintHiddenCurrentSourceNext118(
    $current118,
    $next118,
    'option_id',
    'json_each',
    $constraintSources118,
    [['column' => 'id']],
    'left',
);

$stable118 = static fn (): array => SQLiteJsonTablePlan::lateralConstraintHiddenCurrentSourceNext118(
    $current118,
    [$current118[2], $current118[0], $current118[1]],
    'option_id',
    'json_each',
    $constraintSources118,
    [['column' => 'id']],
    'left',
);

$tree118 = static fn (): array => SQLiteJsonTablePlan::lateralConstraintHiddenCurrentSourceNext118(
    $current118,
    $next118,
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

$nullRoot118 = static fn (): array => SQLiteJsonTablePlan::lateralConstraintHiddenCurrentSourceNext118(
    [$current118[0]],
    [array_replace($current118[0], ['scan_root' => null])],
    'option_id',
    'json_each',
    $constraintSources118,
    [],
    'left',
);

$tests = [
    'normalizes function' => static fn (TestRunner $t) => $t->same('json_each', $plan118()['function']),
    'records next118 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-lateral-hidden-current-source-next118', $plan118()['dependencies'], true)),
    'records hidden source dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-hidden-constraint-source-current-source-next102', $plan118()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-lateral-hidden-current-source-until-host-key-advances', $plan118()['currentReaderPolicy']),
    'prepares next source tape' => static fn (TestRunner $t) => $t->same('prepare-next-lateral-hidden-current-source-tape', $plan118()['nextReaderPolicy']),
    'requires replan' => static fn (TestRunner $t) => $t->same(true, $plan118()['replanRequired']),
    'is left join' => static fn (TestRunner $t) => $t->same(true, $plan118()['leftJoin']),
    'records current host order' => static fn (TestRunner $t) => $t->same([10, 20, 30], $plan118()['hostOrderTransition']['current']),
    'records next host order' => static fn (TestRunner $t) => $t->same([30, 10, 40, 20], $plan118()['hostOrderTransition']['next']),
    'host order changed' => static fn (TestRunner $t) => $t->same(true, $plan118()['hostOrderTransition']['changed']),
    'transition count follows keyed union' => static fn (TestRunner $t) => $t->same(4, count($plan118()['transitions'])),
    'alpha transition first by current key' => static fn (TestRunner $t) => $t->same(10, $plan118()['transitions'][0]['hostKey']),
    'alpha current rows two' => static fn (TestRunner $t) => $t->same(2, $plan118()['transitions'][0]['currentRows']),
    'alpha next rows three' => static fn (TestRunner $t) => $t->same(3, $plan118()['transitions'][0]['nextRows']),
    'alpha row count changed' => static fn (TestRunner $t) => $t->same(true, $plan118()['transitions'][0]['rowCountChanged']),
    'alpha reason plan changed' => static fn (TestRunner $t) => $t->same('lateral-hidden-current-source-plan-changed', $plan118()['transitions'][0]['reason']),
    'alpha pair reason includes value change' => static fn (TestRunner $t) => $t->true(in_array('hidden-constraint-source-value-changed', $plan118()['transitions'][0]['pairReplanReasons'], true)),
    'alpha pair reason includes row count change' => static fn (TestRunner $t) => $t->true(in_array('hidden-constraint-source-row-count-changed', $plan118()['transitions'][0]['pairReplanReasons'], true)),
    'alpha constraint transition column json' => static fn (TestRunner $t) => $t->same('json', $plan118()['transitions'][0]['constraintValueTransitions'][0]['column']),
    'alpha constraint transition source column payload' => static fn (TestRunner $t) => $t->same('payload', $plan118()['transitions'][0]['constraintValueTransitions'][0]['sourceColumn']),
    'alpha json transition changed' => static fn (TestRunner $t) => $t->same(true, $plan118()['transitions'][0]['constraintValueTransitions'][0]['changed']),
    'alpha root transition stable' => static fn (TestRunner $t) => $t->same(false, $plan118()['transitions'][0]['constraintValueTransitions'][1]['changed']),
    'alpha visible type transition stable' => static fn (TestRunner $t) => $t->same(false, $plan118()['transitions'][0]['constraintValueTransitions'][2]['changed']),
    'alpha current first value contains seo' => static fn (TestRunner $t) => $t->same('{"slug":"seo","enabled":true}', $plan118()['current'][0]['rows'][0]['value']),
    'alpha next last value contains media' => static fn (TestRunner $t) => $t->same('{"slug":"media","enabled":true}', $plan118()['next'][0]['rows'][2]['value']),
    'alpha constraint source hidden json' => static fn (TestRunner $t) => $t->same(true, $plan118()['current'][0]['constraintSources'][0]['hidden']),
    'alpha constraint source visible type' => static fn (TestRunner $t) => $t->same(false, $plan118()['current'][0]['constraintSources'][2]['hidden']),
    'alpha filter argument count includes json root and visible type' => static fn (TestRunner $t) => $t->same(3, count($plan118()['current'][0]['filterArguments'])),
    'beta current null extends' => static fn (TestRunner $t) => $t->same(true, $plan118()['transitions'][1]['currentNullExtended']),
    'beta next null extends after missing root' => static fn (TestRunner $t) => $t->same(true, $plan118()['transitions'][1]['nextNullExtended']),
    'beta root value changes' => static fn (TestRunner $t) => $t->same(true, $plan118()['transitions'][1]['constraintValueTransitions'][1]['changed']),
    'beta reason is plan changed' => static fn (TestRunner $t) => $t->same('lateral-hidden-current-source-plan-changed', $plan118()['transitions'][1]['reason']),
    'gamma current rows one' => static fn (TestRunner $t) => $t->same(1, $plan118()['transitions'][2]['currentRows']),
    'gamma next rows one' => static fn (TestRunner $t) => $t->same(1, $plan118()['transitions'][2]['nextRows']),
    'gamma detects json kind change' => static fn (TestRunner $t) => $t->true(in_array('hidden-constraint-source-json-kind-changed', $plan118()['transitions'][2]['pairReplanReasons'], true)),
    'gamma next source kind is jsonb' => static fn (TestRunner $t) => $t->same('jsonb', $plan118()['next'][2]['jsonInputKind']),
    'gamma ordinal changed' => static fn (TestRunner $t) => $t->same(true, $plan118()['transitions'][2]['ordinalChanged']),
    'delta added host reason' => static fn (TestRunner $t) => $t->same('next-lateral-hidden-current-source-host-row-added', $plan118()['transitions'][3]['reason']),
    'delta current is null' => static fn (TestRunner $t) => $t->same(null, $plan118()['transitions'][3]['current']),
    'delta next rows one' => static fn (TestRunner $t) => $t->same(1, $plan118()['transitions'][3]['nextRows']),
    'reasons include added host' => static fn (TestRunner $t) => $t->true(in_array('next-lateral-hidden-current-source-host-row-added', $plan118()['replanReasons'], true)),
    'reasons include hidden value changed' => static fn (TestRunner $t) => $t->true(in_array('hidden-constraint-source-value-changed', $plan118()['replanReasons'], true)),
    'stable reorder does not require replan' => static fn (TestRunner $t) => $t->same(false, $stable118()['replanRequired']),
    'stable reorder reuses source tape' => static fn (TestRunner $t) => $t->same('reuse-current-lateral-hidden-current-source-tape', $stable118()['nextReaderPolicy']),
    'stable reorder records host order changed only' => static fn (TestRunner $t) => $t->same(true, $stable118()['hostOrderTransition']['changed']),
    'stable reorder alpha reason stable' => static fn (TestRunner $t) => $t->same('stable-lateral-hidden-current-source', $stable118()['transitions'][0]['reason']),
    'stable reorder gamma ordinal changed' => static fn (TestRunner $t) => $t->same(true, $stable118()['transitions'][2]['ordinalChanged']),
    'tree normalizes function' => static fn (TestRunner $t) => $t->same('json_tree', $tree118()['function']),
    'tree inner join disables leftJoin' => static fn (TestRunner $t) => $t->same(false, $tree118()['leftJoin']),
    'tree alpha current includes root and children' => static fn (TestRunner $t) => $t->same(4, $tree118()['transitions'][0]['currentRows']),
    'tree beta does not null extend' => static fn (TestRunner $t) => $t->same(false, $tree118()['transitions'][1]['currentNullExtended']),
    'tree id constraint is hidden' => static fn (TestRunner $t) => $t->same(true, $tree118()['current'][0]['constraintSources'][2]['hidden']),
    'tree order by rowid consumed' => static fn (TestRunner $t) => $t->same(true, $tree118()['current'][0]['orderByConsumed']),
    'null root next is unrunnable' => static fn (TestRunner $t) => $t->same(false, $nullRoot118()['next'][0]['runnable']),
    'null root next reports SQL NULL root' => static fn (TestRunner $t) => $t->same('SQL NULL root path', $nullRoot118()['next'][0]['jsonError']),
    'null root has no next rows' => static fn (TestRunner $t) => $t->same(0, $nullRoot118()['transitions'][0]['nextRows']),
    'missing host key is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralConstraintHiddenCurrentSourceNext118([['payload' => '{}', 'scan_root' => '$']], $next118, 'option_id', 'json_each', $constraintSources118)),
    'missing source column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralConstraintHiddenCurrentSourceNext118([['option_id' => 1, 'scan_root' => '$']], $next118, 'option_id', 'json_each', $constraintSources118)),
    'duplicate current key is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralConstraintHiddenCurrentSourceNext118([$current118[0], $current118[0]], $next118, 'option_id', 'json_each', $constraintSources118)),
    'duplicate next key is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralConstraintHiddenCurrentSourceNext118($current118, [$next118[0], $next118[0]], 'option_id', 'json_each', $constraintSources118)),
    'empty host key is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralConstraintHiddenCurrentSourceNext118($current118, $next118, '', 'json_each', $constraintSources118)),
    'empty constraint sources are rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralConstraintHiddenCurrentSourceNext118($current118, $next118, 'option_id', 'json_each', [])),
    'bad function is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralConstraintHiddenCurrentSourceNext118($current118, $next118, 'option_id', 'json_bad', $constraintSources118)),
    'bad join type is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralConstraintHiddenCurrentSourceNext118($current118, $next118, 'option_id', 'json_each', $constraintSources118, [], 'outer')),
];

foreach ($tests as $name => $case) {
    $tests['json table lateral constraint hidden current source next118 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
