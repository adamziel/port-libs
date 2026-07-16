<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentRows = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_alpha_settings',
        'option_value' => '{"rules":[{"name":"seo","enabled":true},{"name":"cache","enabled":false}],"meta":{"enabled":true}}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_beta_settings',
        'option_value' => '{"rules":[],"meta":{"enabled":false}}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_gamma_settings',
        'option_value' => '{"rules":[{"name":"forms","enabled":true}],"meta":{"enabled":true}}',
        'scan_root' => '$.rules',
    ],
];

$nextRows = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_alpha_settings',
        'option_value' => '{"rules":[{"name":"seo","enabled":true},{"name":"cache","enabled":true},{"name":"media","enabled":true}],"meta":{"enabled":true}}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_beta_settings',
        'option_value' => '{"rules":[{"name":"beta","enabled":true}],"meta":{"enabled":true}}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_gamma_settings',
        'option_value' => '{"rules":[{"name":"forms","enabled":true}],"meta":{"enabled":true}}',
        'scan_root' => '$.meta',
    ],
    [
        'option_id' => 4,
        'option_name' => 'plugin_delta_settings',
        'option_value' => '{"rules":[{"name":"delta","enabled":true}]}',
        'scan_root' => '$.rules',
    ],
];

$nextJsonbRows = $nextRows;
$nextJsonbRows[2]['option_value'] = new SQLiteBlobValue(SQLiteJsonB::encode(json_decode('{"rules":[{"name":"forms","enabled":true}],"meta":{"enabled":true}}')));

$plan = static fn (): array => SQLiteJsonTablePlan::lateralHiddenPlanner(
    $currentRows,
    $nextRows,
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

$stablePlan = static fn (): array => SQLiteJsonTablePlan::lateralHiddenPlanner(
    $currentRows,
    $currentRows,
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

$jsonbPlan = static fn (): array => SQLiteJsonTablePlan::lateralHiddenPlanner(
    $currentRows,
    $nextJsonbRows,
    'option_value',
    'json_each',
    [
        ['column' => 'root', 'operator' => '=', 'value' => '$.rules'],
    ],
    'scan_root',
    [],
    'inner',
);

$tests = [
    'normalizes function' => static fn (TestRunner $t) => $t->same('json_each', $plan()['function']),
    'records lateral hidden dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-lateral-hidden-planner', $plan()['dependencies'], true)),
    'preserves next88 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-hidden-constraint-planner-current-source-next88', $plan()['dependencies'], true)),
    'marks left join mode' => static fn (TestRunner $t) => $t->same(true, $plan()['leftJoin']),
    'pins current lateral hidden reader policy' => static fn (TestRunner $t) => $t->same('pin-current-lateral-hidden-json-source-until-host-row-advances', $plan()['currentReaderPolicy']),
    'prepares next lateral hidden source tape' => static fn (TestRunner $t) => $t->same('prepare-next-lateral-hidden-json-source-tape', $plan()['nextReaderPolicy']),
    'requires replan when any host source changes' => static fn (TestRunner $t) => $t->same(true, $plan()['replanRequired']),
    'reports source json changes' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $plan()['replanReasons'], true)),
    'reports source root changes' => static fn (TestRunner $t) => $t->true(in_array('source-root-changed', $plan()['replanReasons'], true)),
    'reports argument tape changes' => static fn (TestRunner $t) => $t->true(in_array('source-argument-tape-changed', $plan()['replanReasons'], true)),
    'reports rowset changes from hidden residuals' => static fn (TestRunner $t) => $t->true(in_array('hidden-residual-rowset-changed', $plan()['replanReasons'], true)),
    'keeps hidden residual presence local to host transition' => static fn (TestRunner $t) => $t->true(in_array('hidden-residual-constraint-present', $plan()['transitions'][0]['pairReplanReasons'], true)),
    'current host plans count current hosts' => static fn (TestRunner $t) => $t->same(3, count($plan()['current'])),
    'next host plans count added host' => static fn (TestRunner $t) => $t->same(4, count($plan()['next'])),
    'transition count covers max host count' => static fn (TestRunner $t) => $t->same(4, count($plan()['transitions'])),
    'first current host index is stable' => static fn (TestRunner $t) => $t->same(0, $plan()['current'][0]['hostIndex']),
    'first current host row is preserved' => static fn (TestRunner $t) => $t->same('plugin_alpha_settings', $plan()['current'][0]['hostRow']['option_name']),
    'first next host row is preserved' => static fn (TestRunner $t) => $t->same('plugin_alpha_settings', $plan()['next'][0]['hostRow']['option_name']),
    'first current row count sees two rules' => static fn (TestRunner $t) => $t->same(2, $plan()['current'][0]['rowCount']),
    'first next row count sees three rules' => static fn (TestRunner $t) => $t->same(3, $plan()['next'][0]['rowCount']),
    'first transition records current rows' => static fn (TestRunner $t) => $t->same(2, $plan()['transitions'][0]['currentRows']),
    'first transition records next rows' => static fn (TestRunner $t) => $t->same(3, $plan()['transitions'][0]['nextRows']),
    'first transition row count changed' => static fn (TestRunner $t) => $t->same(true, $plan()['transitions'][0]['rowCountChanged']),
    'first transition reason is source plan changed' => static fn (TestRunner $t) => $t->same('lateral-hidden-source-plan-changed', $plan()['transitions'][0]['reason']),
    'second current host null extends' => static fn (TestRunner $t) => $t->same(true, $plan()['current'][1]['nullExtended']),
    'second next host no longer null extends' => static fn (TestRunner $t) => $t->same(false, $plan()['next'][1]['nullExtended']),
    'second transition reports null extension current' => static fn (TestRunner $t) => $t->same(true, $plan()['transitions'][1]['currentNullExtended']),
    'second transition reports null extension next' => static fn (TestRunner $t) => $t->same(false, $plan()['transitions'][1]['nextNullExtended']),
    'third root change moves next rowset to meta object' => static fn (TestRunner $t) => $t->same('$.meta', $plan()['next'][2]['rootValue']),
    'third current root remains rules' => static fn (TestRunner $t) => $t->same('$.rules', $plan()['current'][2]['rootValue']),
    'third row count changes after root residual filters meta' => static fn (TestRunner $t) => $t->same(true, $plan()['transitions'][2]['rowCountChanged']),
    'third transition carries source root reason' => static fn (TestRunner $t) => $t->true(in_array('source-root-changed', $plan()['transitions'][2]['pairReplanReasons'], true)),
    'added host transition has null current plan' => static fn (TestRunner $t) => $t->same(null, $plan()['transitions'][3]['current']),
    'added host transition carries next host index' => static fn (TestRunner $t) => $t->same(3, $plan()['transitions'][3]['next']['hostIndex']),
    'added host transition reason is added' => static fn (TestRunner $t) => $t->same('next-lateral-hidden-host-row-added', $plan()['transitions'][3]['reason']),
    'hidden residual columns keep duplicate root' => static fn (TestRunner $t) => $t->same(['root'], $plan()['current'][0]['hiddenResidualColumns']),
    'next hidden residual columns keep duplicate root' => static fn (TestRunner $t) => $t->same(['root'], $plan()['next'][0]['hiddenResidualColumns']),
    'hidden residual change flag is false for same duplicate shape' => static fn (TestRunner $t) => $t->same(false, $plan()['transitions'][0]['hiddenResidualChanged']),
    'visible type keeps expected index string' => static fn (TestRunner $t) => $t->same('hidden:json:=|hidden:root:=|visible:type:=', $plan()['current'][0]['idxStr']),
    'order by id is consumed in current plans' => static fn (TestRunner $t) => $t->same(true, $plan()['current'][0]['orderByConsumed']),
    'stable plan has no replan' => static fn (TestRunner $t) => $t->same(false, $stablePlan()['replanRequired']),
    'stable plan reuses source tape' => static fn (TestRunner $t) => $t->same('reuse-current-lateral-hidden-json-source-tape', $stablePlan()['nextReaderPolicy']),
    'stable transition reason remains stable' => static fn (TestRunner $t) => $t->same('stable-lateral-hidden-json-plan', $stablePlan()['transitions'][0]['reason']),
    'stable transition changed flag is false' => static fn (TestRunner $t) => $t->same(false, $stablePlan()['transitions'][0]['changed']),
    'stable second host stays null extended' => static fn (TestRunner $t) => $t->same(true, $stablePlan()['transitions'][1]['currentNullExtended']),
    'stable second host next is also null extended' => static fn (TestRunner $t) => $t->same(true, $stablePlan()['transitions'][1]['nextNullExtended']),
    'jsonb next reports kind change' => static fn (TestRunner $t) => $t->true(in_array('source-json-kind-changed', $jsonbPlan()['replanReasons'], true)),
    'jsonb next host input kind is jsonb' => static fn (TestRunner $t) => $t->same('jsonb', $jsonbPlan()['next'][2]['jsonInputKind']),
    'jsonb next remains valid' => static fn (TestRunner $t) => $t->same(true, $jsonbPlan()['next'][2]['jsonValid']),
    'inner join mode does not null extend empty host' => static fn (TestRunner $t) => $t->same(false, $jsonbPlan()['current'][1]['nullExtended']),
    'removed host transition is reported' => static function (TestRunner $t) use ($currentRows): void {
        $plan = SQLiteJsonTablePlan::lateralHiddenPlanner(
            $currentRows,
            array_slice($currentRows, 0, 2),
            'option_value',
            'json_each',
            [['column' => 'root', 'operator' => '=', 'value' => '$.rules']],
            'scan_root',
            [],
            'left',
        );
        $t->same('current-lateral-hidden-host-row-removed', $plan['transitions'][2]['reason']);
    },
    'removed host transition has null next' => static function (TestRunner $t) use ($currentRows): void {
        $plan = SQLiteJsonTablePlan::lateralHiddenPlanner(
            $currentRows,
            array_slice($currentRows, 0, 2),
            'option_value',
            'json_each',
            [['column' => 'root', 'operator' => '=', 'value' => '$.rules']],
            'scan_root',
            [],
            'left',
        );
        $t->same(null, $plan['transitions'][2]['next']);
    },
    'missing current json column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralHiddenPlanner([['scan_root' => '$']], $nextRows, 'option_value', 'json_each')),
    'missing next json column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralHiddenPlanner($currentRows, [['scan_root' => '$']], 'option_value', 'json_each')),
    'missing current root column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralHiddenPlanner([['option_value' => '{}']], $nextRows, 'option_value', 'json_each', [], 'scan_root')),
    'missing next root column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralHiddenPlanner($currentRows, [['option_value' => '{}']], 'option_value', 'json_each', [], 'scan_root')),
    'empty json column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralHiddenPlanner($currentRows, $nextRows, '', 'json_each')),
    'empty root column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralHiddenPlanner($currentRows, $nextRows, 'option_value', 'json_each', [], '')),
    'bad function is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralHiddenPlanner($currentRows, $nextRows, 'option_value', 'json_bad')),
    'bad join type is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralHiddenPlanner($currentRows, $nextRows, 'option_value', 'json_each', [], null, [], 'outer')),
];

foreach ($tests as $name => $case) {
    $tests['json table lateral hidden planner ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
