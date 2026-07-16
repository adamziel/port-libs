<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentHosts81 = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_alpha_settings',
        'option_value' => '{"rules":["seo","cache"]}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_beta_settings',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['rules' => ['forms']])),
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_empty_settings',
        'option_value' => '{"rules":[]}',
        'scan_root' => '$.rules',
    ],
];

$nextHosts81 = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_alpha_settings',
        'option_value' => '{"rules":["seo","shop","cache"]}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_beta_settings',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['rules' => ['media']])),
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 4,
        'option_name' => 'plugin_gamma_settings',
        'option_value' => '{"rules":["gallery"]}',
        'scan_root' => '$.rules',
    ],
];

$constraints81 = [
    ['column' => 'atom', 'operator' => 'IS NOT NULL', 'value' => null],
];

$plan81 = static fn (): array => SQLiteJsonTablePlan::lateralRowidComparison(
    $currentHosts81,
    $nextHosts81,
    'option_value',
    'json_each',
    $constraints81,
    'scan_root',
    ['key', 'atom', 'fullkey', 'path'],
    'left',
    'rule_',
);

$stable81 = static fn (): array => SQLiteJsonTablePlan::lateralRowidComparison(
    [$currentHosts81[0]],
    [$currentHosts81[0]],
    'option_value',
    'json_each',
    $constraints81,
    'scan_root',
    ['key', 'atom', 'fullkey'],
    'inner',
    'rule_',
);

$inner81 = static fn (): array => SQLiteJsonTablePlan::lateralRowidComparison(
    $currentHosts81,
    $nextHosts81,
    'option_value',
    'json_each',
    $constraints81,
    'scan_root',
    ['key', 'atom'],
);

$tests = [
    'function is normalized' => static fn (TestRunner $t) => $t->same('json_each', $plan81()['function']),
    'dependency marker names current next81' => static fn (TestRunner $t) => $t->same('sqlite-json-table-lateral-rowid-comparison', $plan81()['dependencies'][0]),
    'current reader policy is rowid specific' => static fn (TestRunner $t) => $t->same('keep-current-lateral-json-rowid-until-host-row-advances', $plan81()['currentReaderPolicy']),
    'next policy materializes changed rowid tape' => static fn (TestRunner $t) => $t->same('materialize-next-lateral-json-rowid-tape', $plan81()['nextReaderPolicy']),
    'stable next policy reuses rowid tape' => static fn (TestRunner $t) => $t->same('reuse-current-lateral-json-rowid-tape', $stable81()['nextReaderPolicy']),
    'current left join row count includes null extension' => static fn (TestRunner $t) => $t->same(4, count($plan81()['current'])),
    'next left join row count includes new gamma row' => static fn (TestRunner $t) => $t->same(5, count($plan81()['next'])),
    'inner join omits empty current host' => static fn (TestRunner $t) => $t->same(3, count($inner81()['current'])),
    'current host order is preserved' => static fn (TestRunner $t) => $t->same([1, 1, 2, 3], array_column($plan81()['current'], 'option_id')),
    'next host order is preserved' => static fn (TestRunner $t) => $t->same([1, 1, 1, 2, 4], array_column($plan81()['next'], 'option_id')),
    'current host indexes are tracked' => static fn (TestRunner $t) => $t->same([0, 0, 1, 2], array_column($plan81()['current'], '__host_index')),
    'next host indexes are tracked' => static fn (TestRunner $t) => $t->same([0, 0, 0, 1, 2], array_column($plan81()['next'], '__host_index')),
    'first current atom is seo' => static fn (TestRunner $t) => $t->same('seo', $plan81()['current'][0]['rule_atom']),
    'second current atom is cache' => static fn (TestRunner $t) => $t->same('cache', $plan81()['current'][1]['rule_atom']),
    'current jsonb atom is forms' => static fn (TestRunner $t) => $t->same('forms', $plan81()['current'][2]['rule_atom']),
    'next inserted middle atom is shop' => static fn (TestRunner $t) => $t->same('shop', $plan81()['next'][1]['rule_atom']),
    'next jsonb atom changes to media' => static fn (TestRunner $t) => $t->same('media', $plan81()['next'][3]['rule_atom']),
    'next added gamma atom is gallery' => static fn (TestRunner $t) => $t->same('gallery', $plan81()['next'][4]['rule_atom']),
    'current rowid alias starts at one' => static fn (TestRunner $t) => $t->same(1, $plan81()['current'][0]['rule_rowid']),
    'current underscore rowid mirrors rowid' => static fn (TestRunner $t) => $t->same(1, $plan81()['current'][0]['rule__rowid_']),
    'current oid mirrors rowid' => static fn (TestRunner $t) => $t->same(1, $plan81()['current'][0]['rule_oid']),
    'current second rowid alias increments' => static fn (TestRunner $t) => $t->same(2, $plan81()['current'][1]['rule_rowid']),
    'jsonb rowid alias starts from its own root' => static fn (TestRunner $t) => $t->same(1, $plan81()['current'][2]['rule_rowid']),
    'next third alpha rowid is three' => static fn (TestRunner $t) => $t->same(3, $plan81()['next'][2]['rule_rowid']),
    'next gamma rowid starts at one' => static fn (TestRunner $t) => $t->same(1, $plan81()['next'][4]['rule_rowid']),
    'fullkey is projected for current rows' => static fn (TestRunner $t) => $t->same('$.rules[0]', $plan81()['current'][0]['rule_fullkey']),
    'path is projected for current rows' => static fn (TestRunner $t) => $t->same('$.rules', $plan81()['current'][0]['rule_path']),
    'key is projected for current rows' => static fn (TestRunner $t) => $t->same(0, $plan81()['current'][0]['rule_key']),
    'left join null extends empty atom' => static fn (TestRunner $t) => $t->same(null, $plan81()['current'][3]['rule_atom']),
    'left join null extends empty rowid' => static fn (TestRunner $t) => $t->same(null, $plan81()['current'][3]['rule_rowid']),
    'left join null extends empty underscore rowid' => static fn (TestRunner $t) => $t->same(null, $plan81()['current'][3]['rule__rowid_']),
    'left join null extends empty oid' => static fn (TestRunner $t) => $t->same(null, $plan81()['current'][3]['rule_oid']),
    'transition count spans next growth' => static fn (TestRunner $t) => $t->same(5, count($plan81()['transitions'])),
    'first transition is stable' => static fn (TestRunner $t) => $t->same('stable-lateral-json-rowid', $plan81()['transitions'][0]['reason']),
    'second transition rowid is stable despite changed atom' => static fn (TestRunner $t) => $t->same(false, $plan81()['transitions'][1]['rowidChanged']),
    'second transition falls back to stable rowid' => static fn (TestRunner $t) => $t->same('stable-lateral-json-rowid', $plan81()['transitions'][1]['reason']),
    'third transition crosses host boundary' => static fn (TestRunner $t) => $t->same('lateral-host-row-boundary-changed', $plan81()['transitions'][2]['reason']),
    'third transition marks host changed' => static fn (TestRunner $t) => $t->same(true, $plan81()['transitions'][2]['hostChanged']),
    'fourth transition changes from null rowid' => static fn (TestRunner $t) => $t->same(true, $plan81()['transitions'][3]['rowidChanged']),
    'fourth transition reports host boundary changed' => static fn (TestRunner $t) => $t->same('lateral-host-row-boundary-changed', $plan81()['transitions'][3]['reason']),
    'fifth transition reports added row' => static fn (TestRunner $t) => $t->same('next-lateral-json-row-added', $plan81()['transitions'][4]['reason']),
    'transition current rowid is exposed' => static fn (TestRunner $t) => $t->same(1, $plan81()['transitions'][0]['currentRowid']),
    'transition next rowid is exposed' => static fn (TestRunner $t) => $t->same(1, $plan81()['transitions'][0]['nextRowid']),
    'added transition current rowid is null' => static fn (TestRunner $t) => $t->same(null, $plan81()['transitions'][4]['currentRowid']),
    'added transition next rowid is one' => static fn (TestRunner $t) => $t->same(1, $plan81()['transitions'][4]['nextRowid']),
    'stable rowid transition count is two' => static fn (TestRunner $t) => $t->same(2, count($stable81()['transitions'])),
    'stable rowid transition is unchanged' => static fn (TestRunner $t) => $t->same(false, $stable81()['transitions'][0]['changed']),
    'default prefix projects json rowid' => static fn (TestRunner $t) => $t->same(1, $inner81()['current'][0]['json_rowid']),
    'default prefix projects json atom' => static fn (TestRunner $t) => $t->same('seo', $inner81()['current'][0]['json_atom']),
    'missing json host column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralRowidComparison([['option_name' => 'missing']], [], 'option_value', 'json_each')),
    'missing root host column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralRowidComparison([['option_value' => '{}']], [], 'option_value', 'json_each', [], 'scan_root')),
    'empty json column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralRowidComparison([], [], '', 'json_each')),
    'empty root column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralRowidComparison([], [], 'option_value', 'json_each', [], '')),
    'bad function is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralRowidComparison([], [], 'option_value', 'json_bad')),
    'bad join type is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralRowidComparison([], [], 'option_value', 'json_each', [], null, ['atom'], 'outer')),
    'empty projection is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralRowidComparison([], [], 'option_value', 'json_each', [], null, [])),
    'empty prefix is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralRowidComparison([], [], 'option_value', 'json_each', [], null, ['atom'], 'inner', '')),
];

foreach ($tests as $name => $case) {
    $tests['json table lateral rowid current next81 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
