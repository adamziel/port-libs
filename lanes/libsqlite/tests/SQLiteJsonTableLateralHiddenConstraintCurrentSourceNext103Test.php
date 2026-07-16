<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current103 = [
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

$next103 = [
    [
        'option_id' => 30,
        'option_name' => 'wp_plugin_gamma_rules',
        'option_value' => '{"rules":[{"slug":"forms","enabled":true}],"meta":{"version":1}}',
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

$jsonbNext103 = $next103;
$jsonbNext103[0]['option_value'] = new SQLiteBlobValue(SQLiteJsonB::encode(json_decode('{"rules":[{"slug":"forms","enabled":true}],"meta":{"version":1}}')));

$plan103 = static fn (): array => SQLiteJsonTablePlan::lateralHiddenConstraintCurrentSource(
    $current103,
    $next103,
    'option_id',
    'option_value',
    'json_each',
    [
        ['column' => 'root', 'operator' => '=', 'value' => '$.rules'],
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ],
    'scan_root',
    [['column' => 'id']],
    'left',
);

$stableReorder103 = static fn (): array => SQLiteJsonTablePlan::lateralHiddenConstraintCurrentSource(
    $current103,
    [$current103[2], $current103[0], $current103[1]],
    'option_id',
    'option_value',
    'json_each',
    [
        ['column' => 'root', 'operator' => '=', 'value' => '$.rules'],
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ],
    'scan_root',
    [['column' => 'id']],
    'left',
);

$jsonbPlan103 = static fn (): array => SQLiteJsonTablePlan::lateralHiddenConstraintCurrentSource(
    $current103,
    $jsonbNext103,
    'option_id',
    'option_value',
    'json_each',
    [['column' => 'root', 'operator' => '=', 'value' => '$.rules']],
    'scan_root',
    [],
    'inner',
);

$removed103 = static fn (): array => SQLiteJsonTablePlan::lateralHiddenConstraintCurrentSource(
    $current103,
    [$next103[1]],
    'option_id',
    'option_value',
    'json_each',
    [['column' => 'root', 'operator' => '=', 'value' => '$.rules']],
    'scan_root',
    [],
    'left',
);

$tests = [
    'normalizes json table function' => static fn (TestRunner $t) => $t->same('json_each', $plan103()['function']),
    'records host key column' => static fn (TestRunner $t) => $t->same('option_id', $plan103()['hostKeyColumn']),
    'records next103 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-lateral-hidden-constraint-current-source-next103', $plan103()['dependencies'], true)),
    'preserves lateral hidden dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-lateral-hidden-planner', $plan103()['dependencies'], true)),
    'preserves next88 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-hidden-constraint-planner-current-source-next88', $plan103()['dependencies'], true)),
    'marks left join mode' => static fn (TestRunner $t) => $t->same(true, $plan103()['leftJoin']),
    'pins current keyed reader policy' => static fn (TestRunner $t) => $t->same('pin-current-lateral-hidden-keyed-json-source-until-host-key-advances', $plan103()['currentReaderPolicy']),
    'prepares next keyed source tape when keyed rowsets change' => static fn (TestRunner $t) => $t->same('prepare-next-lateral-hidden-keyed-json-source-tape', $plan103()['nextReaderPolicy']),
    'requires replan for changed keyed source' => static fn (TestRunner $t) => $t->same(true, $plan103()['replanRequired']),
    'reports source json changes' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $plan103()['replanReasons'], true)),
    'reports source root changes' => static fn (TestRunner $t) => $t->true(in_array('source-root-changed', $plan103()['replanReasons'], true)),
    'reports hidden rowset changes' => static fn (TestRunner $t) => $t->true(in_array('hidden-residual-rowset-changed', $plan103()['replanReasons'], true)),
    'does not replan solely for host order changes' => static fn (TestRunner $t) => $t->same(false, $stableReorder103()['replanRequired']),
    'stable reorder reuses keyed source tape' => static fn (TestRunner $t) => $t->same('reuse-current-lateral-hidden-keyed-json-source-tape', $stableReorder103()['nextReaderPolicy']),
    'stable reorder still records order transition' => static fn (TestRunner $t) => $t->same(true, $stableReorder103()['hostOrderTransition']['changed']),
    'stable reorder current order is original' => static fn (TestRunner $t) => $t->same([10, 20, 30], $stableReorder103()['hostOrderTransition']['current']),
    'stable reorder next order is changed' => static fn (TestRunner $t) => $t->same([30, 10, 20], $stableReorder103()['hostOrderTransition']['next']),
    'stable reorder keeps keyed transition stable for alpha' => static fn (TestRunner $t) => $t->same('stable-lateral-hidden-json-plan', $stableReorder103()['transitions'][0]['reason']),
    'stable reorder marks alpha ordinal changed' => static fn (TestRunner $t) => $t->same(true, $stableReorder103()['transitions'][0]['ordinalChanged']),
    'stable reorder keeps alpha row count stable' => static fn (TestRunner $t) => $t->same(false, $stableReorder103()['transitions'][0]['rowCountChanged']),
    'host order transition current includes keys' => static fn (TestRunner $t) => $t->same([10, 20, 30], $plan103()['hostOrderTransition']['current']),
    'host order transition next includes keys' => static fn (TestRunner $t) => $t->same([30, 10, 40, 20], $plan103()['hostOrderTransition']['next']),
    'host order transition changed for moved and added hosts' => static fn (TestRunner $t) => $t->same(true, $plan103()['hostOrderTransition']['changed']),
    'transition count follows keyed union' => static fn (TestRunner $t) => $t->same(4, count($plan103()['transitions'])),
    'transition union starts with alpha current key' => static fn (TestRunner $t) => $t->same(10, $plan103()['transitions'][0]['hostKey']),
    'alpha current ordinal is original zero' => static fn (TestRunner $t) => $t->same(0, $plan103()['transitions'][0]['currentOrdinal']),
    'alpha next ordinal follows reordered next' => static fn (TestRunner $t) => $t->same(1, $plan103()['transitions'][0]['nextOrdinal']),
    'alpha ordinal changed without becoming transition reason' => static fn (TestRunner $t) => $t->same(true, $plan103()['transitions'][0]['ordinalChanged']),
    'alpha transition reason is source plan changed' => static fn (TestRunner $t) => $t->same('lateral-hidden-source-plan-changed', $plan103()['transitions'][0]['reason']),
    'alpha current rows see two objects' => static fn (TestRunner $t) => $t->same(2, $plan103()['transitions'][0]['currentRows']),
    'alpha next rows see three objects' => static fn (TestRunner $t) => $t->same(3, $plan103()['transitions'][0]['nextRows']),
    'alpha row count changed' => static fn (TestRunner $t) => $t->same(true, $plan103()['transitions'][0]['rowCountChanged']),
    'alpha hidden residual columns keep duplicate root' => static fn (TestRunner $t) => $t->same(['root'], $plan103()['current'][0]['hiddenResidualColumns']),
    'alpha next hidden residual columns keep duplicate root' => static fn (TestRunner $t) => $t->same(['root'], $plan103()['next'][0]['hiddenResidualColumns']),
    'alpha visible type remains pushed into index string' => static fn (TestRunner $t) => $t->same('hidden:json:=|hidden:root:=|visible:type:=', $plan103()['current'][0]['idxStr']),
    'beta current null extends' => static fn (TestRunner $t) => $t->same(true, $plan103()['transitions'][1]['currentNullExtended']),
    'beta next remains null extended after root residual mismatch' => static fn (TestRunner $t) => $t->same(true, $plan103()['transitions'][1]['nextNullExtended']),
    'beta source root change is preserved by key' => static fn (TestRunner $t) => $t->same('$.meta', $plan103()['transitions'][1]['next']['rootValue']),
    'beta transition carries source root reason' => static fn (TestRunner $t) => $t->true(in_array('source-root-changed', $plan103()['transitions'][1]['pairReplanReasons'], true)),
    'gamma remains keyed even when moved to next first' => static fn (TestRunner $t) => $t->same(30, $plan103()['transitions'][2]['hostKey']),
    'gamma current ordinal remains two' => static fn (TestRunner $t) => $t->same(2, $plan103()['transitions'][2]['currentOrdinal']),
    'gamma next ordinal is zero after reorder' => static fn (TestRunner $t) => $t->same(0, $plan103()['transitions'][2]['nextOrdinal']),
    'gamma keyed rowset remains stable' => static fn (TestRunner $t) => $t->same('stable-lateral-hidden-json-plan', $plan103()['transitions'][2]['reason']),
    'gamma moved ordinal does not mark changed' => static fn (TestRunner $t) => $t->same(false, $plan103()['transitions'][2]['changed']),
    'delta added host is reported' => static fn (TestRunner $t) => $t->same('next-lateral-hidden-host-row-added', $plan103()['transitions'][3]['reason']),
    'delta added host has null current plan' => static fn (TestRunner $t) => $t->same(null, $plan103()['transitions'][3]['current']),
    'delta added host keeps next ordinal' => static fn (TestRunner $t) => $t->same(2, $plan103()['transitions'][3]['nextOrdinal']),
    'delta next row count is one' => static fn (TestRunner $t) => $t->same(1, $plan103()['transitions'][3]['nextRows']),
    'jsonb next reports kind change' => static fn (TestRunner $t) => $t->true(in_array('source-json-kind-changed', $jsonbPlan103()['replanReasons'], true)),
    'jsonb keyed gamma input kind is jsonb' => static fn (TestRunner $t) => $t->same('jsonb', $jsonbPlan103()['transitions'][2]['next']['jsonInputKind']),
    'jsonb keyed gamma remains valid' => static fn (TestRunner $t) => $t->same(true, $jsonbPlan103()['transitions'][2]['next']['jsonValid']),
    'inner join mode does not null extend empty beta' => static fn (TestRunner $t) => $t->same(false, $jsonbPlan103()['transitions'][1]['currentNullExtended']),
    'removed beta host transition is reported by key' => static fn (TestRunner $t) => $t->same('current-lateral-hidden-host-row-removed', $removed103()['transitions'][1]['reason']),
    'removed beta host keeps null next' => static fn (TestRunner $t) => $t->same(null, $removed103()['transitions'][1]['next']),
    'missing current key column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralHiddenConstraintCurrentSource([['option_value' => '{}']], $next103, 'option_id', 'option_value', 'json_each')),
    'missing next key column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralHiddenConstraintCurrentSource($current103, [['option_value' => '{}']], 'option_id', 'option_value', 'json_each')),
    'duplicate current key is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralHiddenConstraintCurrentSource([$current103[0], $current103[0]], $next103, 'option_id', 'option_value', 'json_each')),
    'duplicate next key is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralHiddenConstraintCurrentSource($current103, [$next103[0], $next103[0]], 'option_id', 'option_value', 'json_each')),
    'empty key column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralHiddenConstraintCurrentSource($current103, $next103, '', 'option_value', 'json_each')),
    'empty json column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralHiddenConstraintCurrentSource($current103, $next103, 'option_id', '', 'json_each')),
    'bad function is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralHiddenConstraintCurrentSource($current103, $next103, 'option_id', 'option_value', 'json_bad')),
    'bad join type is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralHiddenConstraintCurrentSource($current103, $next103, 'option_id', 'option_value', 'json_each', [], null, [], 'outer')),
];

foreach ($tests as $name => $case) {
    $tests['json table lateral hidden constraint current source next103 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
