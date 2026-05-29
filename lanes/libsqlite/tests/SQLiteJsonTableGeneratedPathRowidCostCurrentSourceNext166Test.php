<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current166 = [
    'option_id' => 166,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next166',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
];
$next166 = [
    'option_id' => 166,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next166',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
];
$constraints166 = [
    ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ['column' => '_rowid_', 'operator' => '=', 'value' => 6],
];
$order166 = [['column' => 'path'], ['column' => 'rowid']];

$plan166 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidYieldPlan(
    'json_tree',
    $current ?? $current166,
    $next ?? $next166,
    'option_value',
    'generated_path',
    $constraints ?? $constraints166,
    'scan_root',
    $orderBy ?? $order166,
);

$stable166 = static fn (): array => $plan166($current166, $current166);
$range166 = static fn (): array => $plan166(
    array_replace($current166, ['generated_path' => '$.rules']),
    array_replace($next166, ['generated_path' => '$.rules']),
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [4, 9]],
        ['column' => 'type', 'operator' => 'IN', 'value' => ['object', 'integer']],
    ],
);
$unusable166 = static fn (): array => $plan166(
    $current166,
    $current166,
    array_replace($constraints166, [1 => ['column' => 'oid', 'operator' => '=', 'value' => 6, 'usable' => false]]),
);
$jsonb166 = static fn (): array => $plan166(
    $current166,
    array_replace($current166, ['option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($current166['option_value'])))]),
);
$unrunnable166 = static fn (): array => $plan166($current166, array_replace($next166, ['option_value' => null]));

$tests = [
    'records next166 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next166', $plan166()['dependencies'], true)),
    'preserves next163 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next163', $plan166()['dependencies'], true)),
    'pins current yield policy' => static fn (TestRunner $t) => $t->same('yield-current-json-table-generated-path-rowid-cost-source-next166-until-xfilter-reset', $plan166()['currentReaderPolicy']),
    'prepares changed next yield policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-path-rowid-yield-next166-plan', $plan166()['nextReaderPolicy']),
    'stable next yield policy reuses current' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-yield-next166-plan', $stable166()['nextReaderPolicy']),
    'stable next166 reasons empty' => static fn (TestRunner $t) => $t->same([], $stable166()['next166ReplanReasons']),
    'current source option id recorded' => static fn (TestRunner $t) => $t->same(166, $plan166()['currentGeneratedPathRowidYield']['sourceOptionId']),
    'current source option name recorded' => static fn (TestRunner $t) => $t->same('wp_plugin_generated_path_rowid_cost_current_source_next166', $plan166()['currentGeneratedPathRowidYield']['sourceOptionName']),
    'current source root recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan166()['currentGeneratedPathRowidYield']['sourceRoot']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules[1]', $plan166()['currentGeneratedPathRowidYield']['generatedPath']),
    'current rowid alias preserves spelling' => static fn (TestRunner $t) => $t->same('_rowid_', $plan166()['currentGeneratedPathRowidYield']['rowidAlias']),
    'current argv signature records generated path binding' => static fn (TestRunner $t) => $t->same('1:path:=:generated-path:omit', $plan166()['currentGeneratedPathRowidYield']['argvSignature'][0]),
    'current argv signature records rowid binding' => static fn (TestRunner $t) => $t->same('2:id:=:rowid-point:omit', $plan166()['currentGeneratedPathRowidYield']['argvSignature'][1]),
    'current omitted constraints are path and id' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan166()['currentGeneratedPathRowidYield']['omittedConstraintColumns']),
    'current residual constraints are empty' => static fn (TestRunner $t) => $t->same([], $plan166()['currentGeneratedPathRowidYield']['residualConstraintColumns']),
    'current yield rowids pin point' => static fn (TestRunner $t) => $t->same([6], $plan166()['currentGeneratedPathRowidYield']['yieldRowids']),
    'current yield paths pin point' => static fn (TestRunner $t) => $t->same(['$.rules[1]'], $plan166()['currentGeneratedPathRowidYield']['yieldPaths']),
    'current yield row count is one' => static fn (TestRunner $t) => $t->same(1, $plan166()['currentGeneratedPathRowidYield']['yieldRowCount']),
    'current first yield rowid' => static fn (TestRunner $t) => $t->same(6, $plan166()['currentGeneratedPathRowidYield']['firstYieldRowid']),
    'current last yield rowid' => static fn (TestRunner $t) => $t->same(6, $plan166()['currentGeneratedPathRowidYield']['lastYieldRowid']),
    'current rowset fingerprint is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan166()['currentGeneratedPathRowidYield']['rowsetFingerprint'])),
    'current source is pinned' => static fn (TestRunner $t) => $t->same(true, $plan166()['currentGeneratedPathRowidYield']['currentSourcePinned']),
    'current constraint set is covering' => static fn (TestRunner $t) => $t->same(true, $plan166()['currentGeneratedPathRowidYield']['coveringConstraintSet']),
    'current order by consumed' => static fn (TestRunner $t) => $t->same(true, $plan166()['currentGeneratedPathRowidYield']['orderByConsumed']),
    'current estimated rows is point' => static fn (TestRunner $t) => $t->same(1, $plan166()['currentGeneratedPathRowidYield']['estimatedRows']),
    'current estimated cost is point' => static fn (TestRunner $t) => $t->same(1, $plan166()['currentGeneratedPathRowidYield']['estimatedCost']),
    'current yield decision is covering' => static fn (TestRunner $t) => $t->same('yield-current-source-generated-path-rowid-covering', $plan166()['currentGeneratedPathRowidYield']['yieldDecision']),
    'current cost class is covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-covering-point', $plan166()['currentGeneratedPathRowidYield']['costClass']),
    'current plan fingerprint is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan166()['currentGeneratedPathRowidYield']['planFingerprint'])),
    'next source has changed generated path' => static fn (TestRunner $t) => $t->same('$.rules[2]', $plan166()['nextGeneratedPathRowidYield']['generatedPath']),
    'next source empty rowids after path shift' => static fn (TestRunner $t) => $t->same([], $plan166()['nextGeneratedPathRowidYield']['yieldRowids']),
    'next source prepares fresh yield' => static fn (TestRunner $t) => $t->same('prepare-fresh-json-table-yield', $plan166()['nextGeneratedPathRowidYield']['yieldDecision']),
    'next source cost class is empty' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-empty', $plan166()['nextGeneratedPathRowidYield']['costClass']),
    'yield transition count records tracked fields' => static fn (TestRunner $t) => $t->same(15, count($plan166()['generatedPathRowidYieldTransitions'])),
    'generated path transition changes' => static fn (TestRunner $t) => $t->same(true, $plan166()['generatedPathRowidYieldTransitions'][2]['changed']),
    'argv transition stable' => static fn (TestRunner $t) => $t->same(false, $plan166()['generatedPathRowidYieldTransitions'][4]['changed']),
    'rowid transition changes' => static fn (TestRunner $t) => $t->same(true, $plan166()['generatedPathRowidYieldTransitions'][5]['changed']),
    'rowset fingerprint transition changes' => static fn (TestRunner $t) => $t->same(true, $plan166()['generatedPathRowidYieldTransitions'][7]['changed']),
    'pin transition changes' => static fn (TestRunner $t) => $t->same(true, $plan166()['generatedPathRowidYieldTransitions'][8]['changed']),
    'covering transition stable' => static fn (TestRunner $t) => $t->same(false, $plan166()['generatedPathRowidYieldTransitions'][9]['changed']),
    'yield decision transition changes' => static fn (TestRunner $t) => $t->same(true, $plan166()['generatedPathRowidYieldTransitions'][12]['changed']),
    'cost class transition changes' => static fn (TestRunner $t) => $t->same(true, $plan166()['generatedPathRowidYieldTransitions'][13]['changed']),
    'plan fingerprint transition changes' => static fn (TestRunner $t) => $t->same(true, $plan166()['generatedPathRowidYieldTransitions'][14]['changed']),
    'next166 reasons include source change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-source-changed', $plan166()['next166ReplanReasons'], true)),
    'next166 reasons include rowset change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-rowset-changed', $plan166()['next166ReplanReasons'], true)),
    'next166 reasons include admission change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-admission-changed', $plan166()['next166ReplanReasons'], true)),
    'next166 reasons include cost change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-cost-changed', $plan166()['next166ReplanReasons'], true)),
    'next166 preserves best index admission reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-best-index-admission-changed', $plan166()['next166ReplanReasons'], true)),
    'range yields four current rowids in json tree order' => static fn (TestRunner $t) => $t->same([4, 7, 6, 9], $range166()['currentGeneratedPathRowidYield']['yieldRowids']),
    'range yield prepares fresh when path like remains residual' => static fn (TestRunner $t) => $t->same('prepare-fresh-json-table-yield', $range166()['currentGeneratedPathRowidYield']['yieldDecision']),
    'range cost class is empty because best index cannot omit like path' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-empty', $range166()['currentGeneratedPathRowidYield']['costClass']),
    'range residual columns retained' => static fn (TestRunner $t) => $t->same(['path', 'id', 'type'], $range166()['currentGeneratedPathRowidYield']['residualConstraintColumns']),
    'range covering false' => static fn (TestRunner $t) => $t->same(false, $range166()['currentGeneratedPathRowidYield']['coveringConstraintSet']),
    'unusable rowid only omits path' => static fn (TestRunner $t) => $t->same(['path'], $unusable166()['currentGeneratedPathRowidYield']['omittedConstraintColumns']),
    'unusable rowid has residual none because unusable ignored' => static fn (TestRunner $t) => $t->same([], $unusable166()['currentGeneratedPathRowidYield']['residualConstraintColumns']),
    'unusable rowid alias is null' => static fn (TestRunner $t) => $t->same(null, $unusable166()['currentGeneratedPathRowidYield']['rowidAlias']),
    'jsonb next remains yieldable' => static fn (TestRunner $t) => $t->same('yield-current-source-generated-path-rowid-covering', $jsonb166()['nextGeneratedPathRowidYield']['yieldDecision']),
    'jsonb next remains covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-covering-point', $jsonb166()['nextGeneratedPathRowidYield']['costClass']),
    'unrunnable next cost class sentinel' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable166()['nextGeneratedPathRowidYield']['costClass']),
    'unrunnable next prepares fresh yield' => static fn (TestRunner $t) => $t->same('prepare-fresh-json-table-yield', $unrunnable166()['nextGeneratedPathRowidYield']['yieldDecision']),
    'bad json source column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidYieldPlan('json_tree', $current166, $next166, '', 'generated_path', $constraints166)),
    'bad generated path column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidYieldPlan('json_tree', $current166, $next166, 'option_value', '', $constraints166)),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next166 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
