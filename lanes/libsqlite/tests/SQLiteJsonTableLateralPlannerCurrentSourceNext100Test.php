<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current100 = [
    [
        'option_id' => 10,
        'option_name' => 'plugin_alpha_settings',
        'option_value' => '{"rules":["seo","cache"],"meta":{"enabled":true}}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 20,
        'option_name' => 'plugin_beta_settings',
        'option_value' => '{"rules":["forms"],"meta":{"enabled":false}}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 30,
        'option_name' => 'plugin_empty_settings',
        'option_value' => '{"rules":[],"meta":{"enabled":false}}',
        'scan_root' => '$.rules',
    ],
];

$next100 = [
    [
        'option_id' => 20,
        'option_name' => 'plugin_beta_settings',
        'option_value' => '{"rules":["forms","payments"],"meta":{"enabled":true}}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 10,
        'option_name' => 'plugin_alpha_settings',
        'option_value' => '{"rules":["seo","cache"],"meta":{"enabled":true}}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 40,
        'option_name' => 'plugin_gamma_settings',
        'option_value' => '{"rules":["media"],"meta":{"enabled":true}}',
        'scan_root' => '$.rules',
    ],
];

$jsonbCurrent100 = [
    [
        'option_id' => 50,
        'option_name' => 'plugin_jsonb_settings',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['rules' => ['seo', 'media']])),
        'scan_root' => '$.rules',
    ],
];

$jsonbNext100 = [
    [
        'option_id' => 50,
        'option_name' => 'plugin_jsonb_settings',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['rules' => ['seo', 'media', 'forms']])),
        'scan_root' => '$.rules',
    ],
];

$plan100 = static fn (array $current = null, array $next = null, array $constraints = null, string $function = 'json_each'): array => SQLiteJsonTablePlan::lateralCurrentSourcePlanner(
    $current ?? $current100,
    $next ?? $next100,
    'option_id',
    'option_value',
    $function,
    $constraints ?? [['column' => 'type', 'operator' => '=', 'value' => 'text']],
    'scan_root',
    [['column' => 'key', 'direction' => 'ASC']],
);

$stable100 = static fn (): array => $plan100($current100, $current100);
$jsonb100 = static fn (): array => $plan100($jsonbCurrent100, $jsonbNext100);
$treeCurrent100 = array_map(static fn (array $row): array => array_merge($row, ['scan_root' => '$.meta']), $current100);
$treeNext100 = array_map(static fn (array $row): array => array_merge($row, ['scan_root' => '$.meta']), $next100);
$tree100 = static fn (): array => $plan100($treeCurrent100, $treeNext100, [['column' => 'key', 'operator' => '=', 'value' => 'enabled']], 'json_tree');
$rootShift100 = static fn (): array => $plan100($current100, [
    [
        'option_id' => 10,
        'option_name' => 'plugin_alpha_settings',
        'option_value' => $current100[0]['option_value'],
        'scan_root' => '$.meta',
    ],
]);

$tests = [
    'function is normalized' => static fn (TestRunner $t) => $t->same('json_each', $plan100()['function']),
    'dependency includes current source planner' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-constraint-planner-current-source-next86', $plan100()['dependencies'], true)),
    'dependency includes next100 planner' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-lateral-planner-current-source-next100', $plan100()['dependencies'], true)),
    'current reader policy pins by host key' => static fn (TestRunner $t) => $t->same('pin-current-lateral-json-source-by-host-key-until-cursor-reset', $plan100()['currentReaderPolicy']),
    'changed next reader prepares keyed source tape' => static fn (TestRunner $t) => $t->same('prepare-next-lateral-json-source-by-host-key', $plan100()['nextReaderPolicy']),
    'stable next reader reuses keyed source tape' => static fn (TestRunner $t) => $t->same('reuse-current-lateral-json-source-by-host-key', $stable100()['nextReaderPolicy']),
    'current host keys preserve current order' => static fn (TestRunner $t) => $t->same(['10', '20', '30'], array_column($plan100()['current'], 'hostKey')),
    'next host keys are keyed to current union order' => static fn (TestRunner $t) => $t->same(['10', '20', '40'], array_column($plan100()['next'], 'hostKey')),
    'transition keys are stable union order' => static fn (TestRunner $t) => $t->same(['10', '20', '30', '40'], array_column($plan100()['transitions'], 'hostKey')),
    'alpha transition is keyed despite reorder' => static fn (TestRunner $t) => $t->same('10', $plan100()['transitions'][0]['hostKey']),
    'alpha current index is zero' => static fn (TestRunner $t) => $t->same(0, $plan100()['transitions'][0]['currentHostIndex']),
    'alpha next index is one after reorder' => static fn (TestRunner $t) => $t->same(1, $plan100()['transitions'][0]['nextHostIndex']),
    'alpha is marked reordered' => static fn (TestRunner $t) => $t->true($plan100()['transitions'][0]['hostReordered']),
    'alpha reason is reorder only' => static fn (TestRunner $t) => $t->same('lateral-current-source-host-row-reordered', $plan100()['transitions'][0]['reason']),
    'alpha row count stays two' => static fn (TestRunner $t) => $t->same(2, $plan100()['transitions'][0]['currentRows']),
    'alpha next row count stays two' => static fn (TestRunner $t) => $t->same(2, $plan100()['transitions'][0]['nextRows']),
    'alpha current atoms remain seo cache' => static fn (TestRunner $t) => $t->same(['seo', 'cache'], array_column($plan100()['transitions'][0]['current']['rows'], 'atom')),
    'alpha next atoms remain seo cache' => static fn (TestRunner $t) => $t->same(['seo', 'cache'], array_column($plan100()['transitions'][0]['next']['rows'], 'atom')),
    'beta transition uses same host key' => static fn (TestRunner $t) => $t->same('20', $plan100()['transitions'][1]['hostKey']),
    'beta current index is one' => static fn (TestRunner $t) => $t->same(1, $plan100()['transitions'][1]['currentHostIndex']),
    'beta next index is zero' => static fn (TestRunner $t) => $t->same(0, $plan100()['transitions'][1]['nextHostIndex']),
    'beta row count changes from one' => static fn (TestRunner $t) => $t->same(1, $plan100()['transitions'][1]['currentRows']),
    'beta row count changes to two' => static fn (TestRunner $t) => $t->same(2, $plan100()['transitions'][1]['nextRows']),
    'beta row count changed flag is true' => static fn (TestRunner $t) => $t->true($plan100()['transitions'][1]['rowCountChanged']),
    'beta reason is source plan changed' => static fn (TestRunner $t) => $t->same('lateral-current-source-plan-changed', $plan100()['transitions'][1]['reason']),
    'beta pair records source json changed' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $plan100()['transitions'][1]['pairReplanReasons'], true)),
    'beta pair records argument tape changed' => static fn (TestRunner $t) => $t->true(in_array('source-argument-tape-changed', $plan100()['transitions'][1]['pairReplanReasons'], true)),
    'beta next atoms include payments' => static fn (TestRunner $t) => $t->same(['forms', 'payments'], array_column($plan100()['transitions'][1]['next']['rows'], 'atom')),
    'removed empty host reason is removed' => static fn (TestRunner $t) => $t->same('current-lateral-current-source-host-row-removed', $plan100()['transitions'][2]['reason']),
    'removed empty host has no next plan' => static fn (TestRunner $t) => $t->same(null, $plan100()['transitions'][2]['next']),
    'removed empty host current row count is zero' => static fn (TestRunner $t) => $t->same(0, $plan100()['transitions'][2]['currentRows']),
    'added gamma reason is added' => static fn (TestRunner $t) => $t->same('next-lateral-current-source-host-row-added', $plan100()['transitions'][3]['reason']),
    'added gamma has no current plan' => static fn (TestRunner $t) => $t->same(null, $plan100()['transitions'][3]['current']),
    'added gamma next row count is one' => static fn (TestRunner $t) => $t->same(1, $plan100()['transitions'][3]['nextRows']),
    'added gamma atom is media' => static fn (TestRunner $t) => $t->same(['media'], array_column($plan100()['transitions'][3]['next']['rows'], 'atom')),
    'plan requires replan for changed keyed set' => static fn (TestRunner $t) => $t->true($plan100()['replanRequired']),
    'replan reasons include reorder' => static fn (TestRunner $t) => $t->true(in_array('lateral-current-source-host-row-reordered', $plan100()['replanReasons'], true)),
    'replan reasons include changed source' => static fn (TestRunner $t) => $t->true(in_array('lateral-current-source-plan-changed', $plan100()['replanReasons'], true)),
    'replan reasons include removed host' => static fn (TestRunner $t) => $t->true(in_array('current-lateral-current-source-host-row-removed', $plan100()['replanReasons'], true)),
    'replan reasons include added host' => static fn (TestRunner $t) => $t->true(in_array('next-lateral-current-source-host-row-added', $plan100()['replanReasons'], true)),
    'stable plan has three transitions' => static fn (TestRunner $t) => $t->same(3, count($stable100()['transitions'])),
    'stable plan does not require replan' => static fn (TestRunner $t) => $t->same(false, $stable100()['replanRequired']),
    'stable transition reason is stable' => static fn (TestRunner $t) => $t->same('stable-lateral-current-source-json-plan', $stable100()['transitions'][0]['reason']),
    'stable transition changed flag is false' => static fn (TestRunner $t) => $t->same(false, $stable100()['transitions'][0]['changed']),
    'stable plan has no reasons' => static fn (TestRunner $t) => $t->same([], $stable100()['replanReasons']),
    'jsonb source kind is recorded' => static fn (TestRunner $t) => $t->same('jsonb', $jsonb100()['current'][0]['jsonInputKind']),
    'jsonb row count changes' => static fn (TestRunner $t) => $t->same([2, 3], [$jsonb100()['transitions'][0]['currentRows'], $jsonb100()['transitions'][0]['nextRows']]),
    'jsonb next atoms include forms' => static fn (TestRunner $t) => $t->same(['seo', 'media', 'forms'], array_column($jsonb100()['next'][0]['rows'], 'atom')),
    'json tree function is normalized' => static fn (TestRunner $t) => $t->same('json_tree', $tree100()['function']),
    'json tree keyed beta selects enabled row' => static fn (TestRunner $t) => $t->same(['enabled'], array_column($tree100()['transitions'][1]['current']['rows'], 'key')),
    'json tree beta current enabled atom false' => static fn (TestRunner $t) => $t->same([0], array_column($tree100()['transitions'][1]['current']['rows'], 'atom')),
    'json tree beta next enabled atom true' => static fn (TestRunner $t) => $t->same([1], array_column($tree100()['transitions'][1]['next']['rows'], 'atom')),
    'root shift records root changed' => static fn (TestRunner $t) => $t->true(in_array('source-root-changed', $rootShift100()['transitions'][0]['pairReplanReasons'], true)),
    'root shift current rows use rules' => static fn (TestRunner $t) => $t->same(['seo', 'cache'], array_column($rootShift100()['transitions'][0]['current']['rows'], 'atom')),
    'root shift next rows are empty under type text' => static fn (TestRunner $t) => $t->same([], $rootShift100()['transitions'][0]['next']['rows']),
    'argument transition records current json value' => static fn (TestRunner $t) => $t->same($current100[1]['option_value'], $plan100()['transitions'][1]['argumentTransitions'][0]['current']),
    'argument transition records next json value' => static fn (TestRunner $t) => $t->same($next100[0]['option_value'], $plan100()['transitions'][1]['argumentTransitions'][0]['next']),
    'argument transition marks changed' => static fn (TestRunner $t) => $t->true($plan100()['transitions'][1]['argumentTransitions'][0]['changed']),
    'missing key is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralCurrentSourcePlanner([['option_value' => '{}']], [], 'option_id', 'option_value', 'json_each')),
    'null key is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralCurrentSourcePlanner([['option_id' => null, 'option_value' => '{}']], [], 'option_id', 'option_value', 'json_each')),
    'duplicate current key is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralCurrentSourcePlanner([['option_id' => 1, 'option_value' => '{}'], ['option_id' => 1, 'option_value' => '{}']], [], 'option_id', 'option_value', 'json_each')),
    'missing json column is rejected through current source planner' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralCurrentSourcePlanner([['option_id' => 1]], [['option_id' => 1, 'option_value' => '{}']], 'option_id', 'option_value', 'json_each')),
    'bad function is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralCurrentSourcePlanner([], [], 'option_id', 'option_value', 'json_bad')),
];

foreach ($tests as $name => $case) {
    $tests['json table lateral planner current source next100 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
