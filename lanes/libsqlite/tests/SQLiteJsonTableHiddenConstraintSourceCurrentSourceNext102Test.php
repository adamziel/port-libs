<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current102 = [
    'option_id' => 701,
    'option_name' => 'wp_plugin_source_constraints',
    'option_value' => '{"rules":[{"slug":"seo","enabled":true},{"slug":"cache","enabled":false},{"slug":"forms","enabled":true}],"meta":{"scope":"site"}}',
    'scan_root' => '$.rules',
    'target_id' => 6,
    'target_type' => 'false',
];
$next102 = [
    'option_id' => 701,
    'option_name' => 'wp_plugin_source_constraints',
    'option_value' => '{"rules":[{"slug":"seo","enabled":true},{"slug":"cache","enabled":true},{"slug":"forms","enabled":true},{"slug":"shop","enabled":true}],"meta":{"scope":"site"}}',
    'scan_root' => '$.rules',
    'target_id' => 6,
    'target_type' => 'true',
];
$constraintSources102 = [
    ['column' => 'json', 'sourceColumn' => 'option_value'],
    ['column' => 'root', 'sourceColumn' => 'scan_root'],
    ['column' => 'rowid', 'sourceColumn' => 'target_id'],
    ['column' => 'type', 'sourceColumn' => 'target_type'],
];

$plan102 = static fn (array $current = null, array $next = null, array $sources = null): array => SQLiteJsonTablePlan::hiddenConstraintSourceCurrentSource(
    'json_tree',
    $current ?? $current102,
    $next ?? $next102,
    $sources ?? $constraintSources102,
    [['column' => 'id']],
);
$stable102 = static fn (): array => $plan102($current102, $current102);
$rootNull102 = static fn (): array => $plan102($current102, array_replace($next102, ['scan_root' => null]));
$jsonb102 = static fn (): array => $plan102($current102, array_replace($next102, [
    'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($next102['option_value']))),
]));
$literal102 = static fn (): array => $plan102($current102, $next102, [
    ['column' => 'json', 'sourceColumn' => 'option_value'],
    ['column' => 'root', 'value' => '$.rules'],
    ['column' => '_rowid_', 'sourceColumn' => 'target_id'],
]);

$tests = [
    'function is normalized' => static fn (TestRunner $t) => $t->same('json_tree', $plan102()['function']),
    'dependency marker is recorded' => static fn (TestRunner $t) => $t->same(['sqlite-json-table-hidden-constraint-source-current-source-next102'], $plan102()['dependencies']),
    'current reader pins constraint source' => static fn (TestRunner $t) => $t->same('pin-current-json-table-hidden-constraint-source-until-cursor-reset', $plan102()['currentReaderPolicy']),
    'next reader prepares changed source' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-hidden-constraint-source', $plan102()['nextReaderPolicy']),
    'stable reader reuses source' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-hidden-constraint-source', $stable102()['nextReaderPolicy']),
    'changed source requires replan' => static fn (TestRunner $t) => $t->same(true, $plan102()['replanRequired']),
    'stable source needs no replan' => static fn (TestRunner $t) => $t->same(false, $stable102()['replanRequired']),
    'stable reasons are empty' => static fn (TestRunner $t) => $t->same([], $stable102()['replanReasons']),
    'json value transition is first' => static fn (TestRunner $t) => $t->same('json', $plan102()['constraintValueTransitions'][0]['column']),
    'json value transition uses option value source' => static fn (TestRunner $t) => $t->same('option_value', $plan102()['constraintValueTransitions'][0]['sourceColumn']),
    'json value transition changed' => static fn (TestRunner $t) => $t->same(true, $plan102()['constraintValueTransitions'][0]['changed']),
    'root transition is stable' => static fn (TestRunner $t) => $t->same(false, $plan102()['constraintValueTransitions'][1]['changed']),
    'rowid transition is stable' => static fn (TestRunner $t) => $t->same(false, $plan102()['constraintValueTransitions'][2]['changed']),
    'visible type transition changed' => static fn (TestRunner $t) => $t->same(true, $plan102()['constraintValueTransitions'][3]['changed']),
    'visible type transition is not hidden' => static fn (TestRunner $t) => $t->same(false, $plan102()['constraintValueTransitions'][3]['hidden']),
    'hidden value change reason is present' => static fn (TestRunner $t) => $t->true(in_array('hidden-constraint-source-value-changed', $plan102()['replanReasons'], true)),
    'visible value change reason is present' => static fn (TestRunner $t) => $t->true(in_array('visible-constraint-source-value-changed', $plan102()['replanReasons'], true)),
    'argument tape change reason is present' => static fn (TestRunner $t) => $t->true(in_array('hidden-constraint-source-argument-tape-changed', $plan102()['replanReasons'], true)),
    'row count change reason is absent for single selected row' => static fn (TestRunner $t) => $t->same(false, in_array('hidden-constraint-source-row-count-changed', $plan102()['replanReasons'], true)),
    'current plan is runnable' => static fn (TestRunner $t) => $t->same(true, $plan102()['current']['runnable']),
    'next plan is runnable' => static fn (TestRunner $t) => $t->same(true, $plan102()['next']['runnable']),
    'current input kind is text' => static fn (TestRunner $t) => $t->same('text', $plan102()['current']['jsonInputKind']),
    'next input kind is text' => static fn (TestRunner $t) => $t->same('text', $plan102()['next']['jsonInputKind']),
    'current idx string includes hidden json root rowid and visible type' => static fn (TestRunner $t) => $t->same('hidden:json:=|hidden:root:=|visible:id:=|visible:type:=', $plan102()['current']['idxStr']),
    'current idx number records json root visible constraints' => static fn (TestRunner $t) => $t->same(7, $plan102()['current']['idxNum']),
    'current filter argument starts with json value' => static fn (TestRunner $t) => $t->same($current102['option_value'], $plan102()['current']['filterArguments'][0]),
    'next filter argument keeps target id' => static fn (TestRunner $t) => $t->same(6, $plan102()['next']['filterArguments'][2]),
    'current row is selected by rowid and type' => static fn (TestRunner $t) => $t->same(1, count($plan102()['currentRows'])),
    'next row is selected by rowid and type' => static fn (TestRunner $t) => $t->same(1, count($plan102()['nextRows'])),
    'current row keeps false atom' => static fn (TestRunner $t) => $t->same(0, $plan102()['currentRows'][0]['atom']),
    'next row keeps true atom' => static fn (TestRunner $t) => $t->same(1, $plan102()['nextRows'][0]['atom']),
    'current fullkey is enabled leaf' => static fn (TestRunner $t) => $t->same('$.rules[1].enabled', $plan102()['currentRows'][0]['fullkey']),
    'next fullkey is enabled leaf' => static fn (TestRunner $t) => $t->same('$.rules[1].enabled', $plan102()['nextRows'][0]['fullkey']),
    'current metadata records rowid as hidden' => static fn (TestRunner $t) => $t->same(true, $plan102()['current']['constraintSources'][2]['hidden']),
    'current metadata normalizes rowid to id' => static fn (TestRunner $t) => $t->same('id', $plan102()['current']['constraintSources'][2]['column']),
    'current metadata records visible type source' => static fn (TestRunner $t) => $t->same('target_type', $plan102()['current']['constraintSources'][3]['sourceColumn']),
    'literal root records literal metadata' => static fn (TestRunner $t) => $t->same(true, $literal102()['current']['constraintSources'][1]['literal']),
    'literal root has no source column' => static fn (TestRunner $t) => $t->same(null, $literal102()['current']['constraintSources'][1]['sourceColumn']),
    'literal underscore rowid normalizes to id' => static fn (TestRunner $t) => $t->same('id', $literal102()['current']['constraintSources'][2]['column']),
    'literal underscore rowid selects current enabled leaf' => static fn (TestRunner $t) => $t->same(0, $literal102()['currentRows'][0]['atom']),
    'jsonb next reports kind changed' => static fn (TestRunner $t) => $t->true(in_array('hidden-constraint-source-json-kind-changed', $jsonb102()['replanReasons'], true)),
    'jsonb next remains runnable' => static fn (TestRunner $t) => $t->same(true, $jsonb102()['next']['runnable']),
    'jsonb next input kind is jsonb' => static fn (TestRunner $t) => $t->same('jsonb', $jsonb102()['next']['jsonInputKind']),
    'root null next becomes unrunnable' => static fn (TestRunner $t) => $t->same(false, $rootNull102()['next']['runnable']),
    'root null next rows are empty' => static fn (TestRunner $t) => $t->same([], $rootNull102()['nextRows']),
    'root null reports unrunnable' => static fn (TestRunner $t) => $t->true(in_array('next-hidden-constraint-source-becomes-unrunnable', $rootNull102()['replanReasons'], true)),
    'root null reports sql null root error' => static fn (TestRunner $t) => $t->same('SQL NULL root path', $rootNull102()['next']['jsonError']),
    'missing source column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan102([], $next102)),
    'missing constraint source value is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan102($current102, $next102, [['column' => 'json']])),
    'empty constraint sources are rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::hiddenConstraintSourceCurrentSource('json_tree', $current102, $next102, [])),
    'empty source column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan102($current102, $next102, [['column' => 'json', 'sourceColumn' => '']])),
    'bad function is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::hiddenConstraintSourceCurrentSource('json_bad', $current102, $next102, $constraintSources102)),
];

foreach ($tests as $name => $case) {
    $tests['json table hidden constraint source current source next102 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
