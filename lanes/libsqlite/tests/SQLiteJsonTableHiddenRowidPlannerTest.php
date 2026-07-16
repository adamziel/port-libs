<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentSource94 = [
    'option_id' => 101,
    'option_name' => 'widget_plugin_settings',
    'option_value' => '{"plugins":[{"slug":"seo","enabled":true},{"slug":"cache","enabled":false}],"meta":{"scope":"site"}}',
    'scan_root' => '$.plugins',
];

$nextSource94 = [
    'option_id' => 101,
    'option_name' => 'widget_plugin_settings',
    'option_value' => '{"plugins":[{"slug":"seo","enabled":true},{"slug":"cache","enabled":true},{"slug":"forms","enabled":true}],"meta":{"scope":"network"}}',
    'scan_root' => '$.plugins',
];

$rootShiftSource94 = array_merge($nextSource94, ['scan_root' => '$.meta']);
$jsonbSource94 = [
    'option_id' => 102,
    'option_name' => 'jsonb_plugin_settings',
    'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
        'plugins' => [
            ['slug' => 'seo', 'enabled' => true],
            ['slug' => 'media', 'enabled' => true],
        ],
    ])),
    'scan_root' => '$.plugins',
];

$plan94 = static fn (array $current = null, array $next = null, array $constraints = null, string $function = 'json_tree'): array => SQLiteJsonTablePlan::currentSourceHiddenRowidPlanner(
    $function,
    $current ?? $currentSource94,
    $next ?? $nextSource94,
    'option_value',
    $constraints ?? [['column' => 'rowid', 'operator' => '=', 'value' => 6]],
    'scan_root',
    [['column' => 'rowid', 'direction' => 'ASC']],
);

$stable94 = static fn (): array => $plan94($currentSource94, $currentSource94, [['column' => '_rowid_', 'operator' => '=', 'value' => 2]]);
$oid94 = static fn (): array => $plan94($currentSource94, $nextSource94, [['column' => 'oid', 'operator' => '=', 'value' => 6]]);
$miss94 = static fn (): array => $plan94($currentSource94, $nextSource94, [['column' => 'rowid', 'operator' => '=', 'value' => 99]]);
$jsonEach94 = static fn (): array => $plan94($currentSource94, $nextSource94, [['column' => 'rowid', 'operator' => '=', 'value' => 2]], 'json_each');
$jsonb94 = static fn (): array => $plan94($jsonbSource94, $jsonbSource94, [['column' => 'rowid', 'operator' => '=', 'value' => 2]]);
$rootShift94 = static fn (): array => $plan94($currentSource94, $rootShiftSource94, [['column' => 'rowid', 'operator' => '=', 'value' => 2]]);

$tests = [
    'function is normalized' => static fn (TestRunner $t) => $t->same('json_tree', $plan94()['function']),
    'dependency marker is present' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-json-table-hidden-rowid-planner', $plan94()['dependencies'], true)),
    'current reader policy pins hidden rowid source' => static fn (TestRunner $t) => $t->same('pin-current-json-table-hidden-rowid-source-until-cursor-reset', $plan94()['currentReaderPolicy']),
    'changed source prepares next hidden rowid source' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-hidden-rowid-source', $plan94()['nextReaderPolicy']),
    'stable source reuses hidden rowid source' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-hidden-rowid-source', $stable94()['nextReaderPolicy']),
    'rowid residual is recorded for current' => static fn (TestRunner $t) => $t->same('rowid', $plan94()['currentRowidResiduals'][0]['column']),
    'rowid residual is recorded for next' => static fn (TestRunner $t) => $t->same('rowid', $plan94()['nextRowidResiduals'][0]['column']),
    'rowid residual operator is equality' => static fn (TestRunner $t) => $t->same('=', $plan94()['currentRowidResiduals'][0]['operator']),
    'underscore rowid alias is recorded' => static fn (TestRunner $t) => $t->same('_rowid_', $stable94()['currentRowidResiduals'][0]['column']),
    'oid alias is recorded' => static fn (TestRunner $t) => $t->same('oid', $oid94()['currentRowidResiduals'][0]['column']),
    'current rowid summary exposes matched rowid' => static fn (TestRunner $t) => $t->same([6], $plan94()['currentRowidSummary']['rowids']),
    'next rowid summary exposes matched rowid' => static fn (TestRunner $t) => $t->same([6], $plan94()['nextRowidSummary']['rowids']),
    'current first rowid is six' => static fn (TestRunner $t) => $t->same(6, $plan94()['currentRowidSummary']['firstRowid']),
    'current last rowid is six' => static fn (TestRunner $t) => $t->same(6, $plan94()['currentRowidSummary']['lastRowid']),
    'current row count is one' => static fn (TestRunner $t) => $t->same(1, $plan94()['currentRowidSummary']['rowCount']),
    'next row count is one' => static fn (TestRunner $t) => $t->same(1, $plan94()['nextRowidSummary']['rowCount']),
    'current source kind is text' => static fn (TestRunner $t) => $t->same('text', $plan94()['currentRowidSummary']['sourceKind']),
    'next source kind is text' => static fn (TestRunner $t) => $t->same('text', $plan94()['nextRowidSummary']['sourceKind']),
    'current root is host root' => static fn (TestRunner $t) => $t->same('$.plugins', $plan94()['currentRowidSummary']['root']),
    'next root is host root' => static fn (TestRunner $t) => $t->same('$.plugins', $plan94()['nextRowidSummary']['root']),
    'current rows contain selected enabled flag' => static fn (TestRunner $t) => $t->same(0, $plan94()['currentRows'][0]['atom']),
    'next rows contain selected enabled flag' => static fn (TestRunner $t) => $t->same(1, $plan94()['nextRows'][0]['atom']),
    'current fullkey is stable plugin enabled leaf' => static fn (TestRunner $t) => $t->same('$.plugins[1].enabled', $plan94()['currentRows'][0]['fullkey']),
    'next fullkey is stable plugin enabled leaf' => static fn (TestRunner $t) => $t->same('$.plugins[1].enabled', $plan94()['nextRows'][0]['fullkey']),
    'rowid transition keeps same rowid set' => static fn (TestRunner $t) => $t->same(false, $plan94()['rowidTransition']['changed']),
    'rowid transition current set is exposed' => static fn (TestRunner $t) => $t->same([6], $plan94()['rowidTransition']['current']),
    'rowid transition next set is exposed' => static fn (TestRunner $t) => $t->same([6], $plan94()['rowidTransition']['next']),
    'payload change is detected for same rowid' => static fn (TestRunner $t) => $t->same('hidden-rowid-source-payload-changed', $plan94()['rowTransitions'][0]['reason']),
    'payload transition marks changed' => static fn (TestRunner $t) => $t->same(true, $plan94()['rowTransitions'][0]['changed']),
    'payload transition current rowid is exposed' => static fn (TestRunner $t) => $t->same(6, $plan94()['rowTransitions'][0]['currentRowid']),
    'payload transition next rowid is exposed' => static fn (TestRunner $t) => $t->same(6, $plan94()['rowTransitions'][0]['nextRowid']),
    'payload transition current atom is falsey integer' => static fn (TestRunner $t) => $t->same(0, $plan94()['rowTransitions'][0]['current']['atom']),
    'payload transition next atom is truthy integer' => static fn (TestRunner $t) => $t->same(1, $plan94()['rowTransitions'][0]['next']['atom']),
    'stable transition reason is stable' => static fn (TestRunner $t) => $t->same('stable-hidden-rowid-source-row', $stable94()['rowTransitions'][0]['reason']),
    'stable transition is unchanged' => static fn (TestRunner $t) => $t->same(false, $stable94()['rowTransitions'][0]['changed']),
    'stable plan still records rowid residual presence' => static fn (TestRunner $t) => $t->same(['hidden-rowid-residual-constraint-present'], $stable94()['hiddenRowidReplanReasons']),
    'changed plan records source json change' => static fn (TestRunner $t) => $t->same(true, in_array('source-json-changed', $plan94()['hiddenRowidReplanReasons'], true)),
    'changed plan records payload change' => static fn (TestRunner $t) => $t->same(true, in_array('hidden-rowid-source-payload-changed', $plan94()['hiddenRowidReplanReasons'], true)),
    'miss current summary has no rowids' => static fn (TestRunner $t) => $t->same([], $miss94()['currentRowidSummary']['rowids']),
    'miss next summary has no rowids' => static fn (TestRunner $t) => $t->same([], $miss94()['nextRowidSummary']['rowids']),
    'miss rowid transition is unchanged empty' => static fn (TestRunner $t) => $t->same(false, $miss94()['rowidTransition']['changed']),
    'miss row transition list is empty' => static fn (TestRunner $t) => $t->same([], $miss94()['rowTransitions']),
    'json_each rowid maps to array object' => static fn (TestRunner $t) => $t->same(1, $jsonEach94()['currentRows'][0]['key']),
    'json_each rowid summary is one row' => static fn (TestRunner $t) => $t->same([2], $jsonEach94()['currentRowidSummary']['rowids']),
    'jsonb source kind is jsonb' => static fn (TestRunner $t) => $t->same('jsonb', $jsonb94()['currentRowidSummary']['sourceKind']),
    'jsonb rowid selects first slug leaf' => static fn (TestRunner $t) => $t->same('slug', $jsonb94()['currentRows'][0]['key']),
    'root shift records root change' => static fn (TestRunner $t) => $t->same(true, in_array('source-root-changed', $rootShift94()['hiddenRowidReplanReasons'], true)),
    'root shift changes rowid set' => static fn (TestRunner $t) => $t->same(true, $rootShift94()['rowidTransition']['changed']),
    'root shift removes current row' => static fn (TestRunner $t) => $t->same('current-hidden-rowid-source-row-removed', $rootShift94()['rowTransitions'][0]['reason']),
    'root shift next summary is empty for rowid two' => static fn (TestRunner $t) => $t->same([], $rootShift94()['nextRowidSummary']['rowids']),
    'empty json column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceHiddenRowidPlanner('json_tree', $currentSource94, $nextSource94, '', [])),
    'empty root column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceHiddenRowidPlanner('json_tree', $currentSource94, $nextSource94, 'option_value', [], '')),
    'missing current json column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceHiddenRowidPlanner('json_tree', ['scan_root' => '$'], $nextSource94, 'option_value')),
    'missing next root column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceHiddenRowidPlanner('json_tree', $currentSource94, ['option_value' => '{}'], 'option_value', [], 'scan_root')),
    'bad function is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceHiddenRowidPlanner('json_bad', $currentSource94, $nextSource94, 'option_value')),
];

foreach ($tests as $name => $case) {
    $tests['json table hidden rowid source current ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
