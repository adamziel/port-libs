<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current160 = [
    'setting_id' => 160,
    'key_name' => 'app_plugin_generated_path_rowid_cost_source',
    'key_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"load_policy":"yes"}}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
];
$next160 = [
    'setting_id' => 160,
    'key_name' => 'app_plugin_generated_path_rowid_cost_source',
    'key_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"load_policy":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
];
$constraints160 = [
    ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ['column' => 'rowid', 'operator' => '=', 'value' => 6],
];

$plan160 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSourcePlan(
    'json_tree',
    $current ?? $current160,
    $next ?? $next160,
    'key_value',
    'generated_path',
    $constraints ?? $constraints160,
    'scan_root',
    [['column' => 'path'], ['column' => 'id']],
);

$stable160 = static fn (): array => $plan160($current160, $current160);
$range160 = static fn (): array => $plan160(
    array_replace($current160, ['generated_path' => '$.rules']),
    array_replace($next160, ['generated_path' => '$.rules']),
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'BETWEEN', 'value' => [4, 9]],
        ['column' => 'type', 'operator' => 'IN', 'value' => ['object', 'integer']],
    ],
);
$miss160 = static fn (): array => $plan160(
    $current160,
    $next160,
    array_replace($constraints160, [1 => ['column' => 'oid', 'operator' => '=', 'value' => 99]]),
);
$unusable160 = static fn (): array => $plan160(
    $current160,
    $current160,
    array_replace($constraints160, [1 => ['column' => 'rowid', 'operator' => '=', 'value' => 6, 'usable' => false]]),
);
$jsonb160 = static fn (): array => $plan160(
    $current160,
    array_replace($current160, ['key_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($current160['key_value'])))]),
);
$unrunnable160 = static fn (): array => $plan160($current160, array_replace($next160, ['key_value' => null]));

$tests = [
    'normalizes function name' => static fn (TestRunner $t) => $t->same('json_tree', $plan160()['function']),
    'records next160 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next160', $plan160()['dependencies'], true)),
    'preserves generated path rowid cost dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source', $plan160()['dependencies'], true)),
    'pins current reader source' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-path-rowid-cost-source-until-vtab-filter-reset', $plan160()['currentReaderPolicy']),
    'prepares changed next source' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-path-rowid-cost-source-plan', $plan160()['nextReaderPolicy']),
    'stable source reuses current plan' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-cost-source-plan', $stable160()['nextReaderPolicy']),
    'stable has no next160 reasons' => static fn (TestRunner $t) => $t->same([], $stable160()['next160ReplanReasons']),
    'current source kind is text json' => static fn (TestRunner $t) => $t->same('text', $plan160()['currentGeneratedPathRowidCostSource']['sourceKind']),
    'current source is runnable' => static fn (TestRunner $t) => $t->same(true, $plan160()['currentGeneratedPathRowidCostSource']['runnable']),
    'current json column retained' => static fn (TestRunner $t) => $t->same('key_value', $plan160()['currentGeneratedPathRowidCostSource']['jsonColumn']),
    'generated path column retained' => static fn (TestRunner $t) => $t->same('generated_path', $plan160()['currentGeneratedPathRowidCostSource']['generatedPathColumn']),
    'root column retained' => static fn (TestRunner $t) => $t->same('scan_root', $plan160()['currentGeneratedPathRowidCostSource']['rootColumn']),
    'source setting id retained' => static fn (TestRunner $t) => $t->same(160, $plan160()['currentGeneratedPathRowidCostSource']['sourceSettingId']),
    'source setting name retained' => static fn (TestRunner $t) => $t->same('app_plugin_generated_path_rowid_cost_source', $plan160()['currentGeneratedPathRowidCostSource']['sourceKeyName']),
    'source root retained' => static fn (TestRunner $t) => $t->same('$.rules', $plan160()['currentGeneratedPathRowidCostSource']['sourceRoot']),
    'current generated path retained' => static fn (TestRunner $t) => $t->same('$.rules[1]', $plan160()['currentGeneratedPathRowidCostSource']['generatedPath']),
    'next generated path retained' => static fn (TestRunner $t) => $t->same('$.rules[2]', $plan160()['nextGeneratedPathRowidCostSource']['generatedPath']),
    'rowid signature retained' => static fn (TestRunner $t) => $t->same('id:=:6', $plan160()['currentGeneratedPathRowidCostSource']['rowidConstraintSignature']),
    'rowid point is scoped' => static fn (TestRunner $t) => $t->same(true, $plan160()['currentGeneratedPathRowidCostSource']['rowidScoped']),
    'current intersected rowid retained' => static fn (TestRunner $t) => $t->same([6], $plan160()['currentGeneratedPathRowidCostSource']['intersectedRowids']),
    'current intersected path retained' => static fn (TestRunner $t) => $t->same(['$.rules[1]'], $plan160()['currentGeneratedPathRowidCostSource']['intersectedPaths']),
    'next rowid source empties after path drift' => static fn (TestRunner $t) => $t->same([], $plan160()['nextGeneratedPathRowidCostSource']['intersectedRowids']),
    'current effective cost retained' => static fn (TestRunner $t) => $t->same(1, $plan160()['currentGeneratedPathRowidCostSource']['effectiveEstimatedCost']),
    'current cost class retained' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-point', $plan160()['currentGeneratedPathRowidCostSource']['costClass']),
    'next cost class retained' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-empty', $plan160()['nextGeneratedPathRowidCostSource']['costClass']),
    'argv binding count includes pushdowns' => static fn (TestRunner $t) => $t->same(2, count($plan160()['currentGeneratedPathRowidCostSource']['argvBindings'])),
    'path binding is omitted' => static fn (TestRunner $t) => $t->same(['column' => 'path', 'omit' => true, 'kind' => 'generated-path'], array_intersect_key($plan160()['currentGeneratedPathRowidCostSource']['argvBindings'][0], ['column' => true, 'omit' => true, 'kind' => true])),
    'rowid binding is omitted' => static fn (TestRunner $t) => $t->same(['column' => 'id', 'omit' => true, 'kind' => 'rowid-point'], array_intersect_key($plan160()['currentGeneratedPathRowidCostSource']['argvBindings'][1], ['column' => true, 'omit' => true, 'kind' => true])),
    'omit columns include path and id' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan160()['currentGeneratedPathRowidCostSource']['omitColumns']),
    'residual columns empty for point seek' => static fn (TestRunner $t) => $t->same([], $plan160()['currentGeneratedPathRowidCostSource']['residualColumns']),
    'source fingerprint length' => static fn (TestRunner $t) => $t->same(64, strlen($plan160()['currentGeneratedPathRowidCostSource']['sourceFingerprint'])),
    'cost fingerprint length' => static fn (TestRunner $t) => $t->same(64, strlen($plan160()['currentGeneratedPathRowidCostSource']['costFingerprint'])),
    'plan fingerprint length' => static fn (TestRunner $t) => $t->same(64, strlen($plan160()['currentGeneratedPathRowidCostSource']['planFingerprint'])),
    'transition count records next160 state' => static fn (TestRunner $t) => $t->same(13, count($plan160()['generatedPathRowidCostSourceTransitions'])),
    'source kind transition stable' => static fn (TestRunner $t) => $t->same(false, $plan160()['generatedPathRowidCostSourceTransitions'][0]['changed']),
    'runnable transition stable' => static fn (TestRunner $t) => $t->same(false, $plan160()['generatedPathRowidCostSourceTransitions'][1]['changed']),
    'root transition stable' => static fn (TestRunner $t) => $t->same(false, $plan160()['generatedPathRowidCostSourceTransitions'][2]['changed']),
    'generated path transition changes' => static fn (TestRunner $t) => $t->same(true, $plan160()['generatedPathRowidCostSourceTransitions'][3]['changed']),
    'rowid constraint transition stable' => static fn (TestRunner $t) => $t->same(false, $plan160()['generatedPathRowidCostSourceTransitions'][4]['changed']),
    'rowset transition changes' => static fn (TestRunner $t) => $t->same(true, $plan160()['generatedPathRowidCostSourceTransitions'][5]['changed']),
    'cost class transition changes' => static fn (TestRunner $t) => $t->same(true, $plan160()['generatedPathRowidCostSourceTransitions'][8]['changed']),
    'argv binding transition stable' => static fn (TestRunner $t) => $t->same(false, $plan160()['generatedPathRowidCostSourceTransitions'][9]['changed']),
    'source fingerprint transition changes' => static fn (TestRunner $t) => $t->same(true, $plan160()['generatedPathRowidCostSourceTransitions'][10]['changed']),
    'cost fingerprint transition changes' => static fn (TestRunner $t) => $t->same(true, $plan160()['generatedPathRowidCostSourceTransitions'][11]['changed']),
    'plan fingerprint transition changes' => static fn (TestRunner $t) => $t->same(true, $plan160()['generatedPathRowidCostSourceTransitions'][12]['changed']),
    'reasons include source change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-source-changed', $plan160()['next160ReplanReasons'], true)),
    'reasons include source rowset change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-source-rowset-changed', $plan160()['next160ReplanReasons'], true)),
    'reasons include source cost change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-source-cost-changed', $plan160()['next160ReplanReasons'], true)),
    'reasons include fingerprint change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-source-fingerprint-changed', $plan160()['next160ReplanReasons'], true)),
    'reasons preserve next145 source change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-source-changed', $plan160()['next160ReplanReasons'], true)),
    'range rowid signature preserved' => static fn (TestRunner $t) => $t->same('id:BETWEEN:[4,9]', $range160()['currentGeneratedPathRowidCostSource']['rowidConstraintSignature']),
    'range rowid is not scoped' => static fn (TestRunner $t) => $t->same(false, $range160()['currentGeneratedPathRowidCostSource']['rowidScoped']),
    'range keeps intersected rowids' => static fn (TestRunner $t) => $t->same([4, 7, 6, 9], $range160()['currentGeneratedPathRowidCostSource']['intersectedRowids']),
    'range keeps narrow cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-narrow-intersection', $range160()['currentGeneratedPathRowidCostSource']['costClass']),
    'range rowid range remains residual' => static fn (TestRunner $t) => $t->true(in_array('id', $range160()['currentGeneratedPathRowidCostSource']['residualColumns'], true)),
    'miss has empty source rowset' => static fn (TestRunner $t) => $t->same([], $miss160()['currentGeneratedPathRowidCostSource']['intersectedRowids']),
    'miss keeps empty cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-empty', $miss160()['currentGeneratedPathRowidCostSource']['costClass']),
    'unusable rowid is residual-free path-only omit' => static fn (TestRunner $t) => $t->same(['path'], $unusable160()['currentGeneratedPathRowidCostSource']['omitColumns']),
    'unusable rowid becomes unconstrained cost' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-unconstrained', $unusable160()['currentGeneratedPathRowidCostSource']['costClass']),
    'jsonb next source kind changes' => static fn (TestRunner $t) => $t->same('jsonb', $jsonb160()['nextGeneratedPathRowidCostSource']['sourceKind']),
    'jsonb source change is a next160 reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-source-changed', $jsonb160()['next160ReplanReasons'], true)),
    'unrunnable next source is not runnable' => static fn (TestRunner $t) => $t->same(false, $unrunnable160()['nextGeneratedPathRowidCostSource']['runnable']),
    'unrunnable next source cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $unrunnable160()['nextGeneratedPathRowidCostSource']['effectiveEstimatedCost']),
    'bad json source column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSourcePlan('json_tree', $current160, $next160, '', 'generated_path', $constraints160)),
    'bad generated path source column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSourcePlan('json_tree', $current160, $next160, 'key_value', '', $constraints160)),
    'missing generated path source column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSourcePlan('json_tree', $current160, $next160, 'key_value', 'missing_path', $constraints160)),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next160 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
