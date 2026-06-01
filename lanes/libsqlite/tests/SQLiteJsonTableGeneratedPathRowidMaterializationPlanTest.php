<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current180 = [
    'setting_id' => 180,
    'key_name' => 'app_plugin_generated_path_rowid_materialization',
    'key_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"load_policy":"yes"}}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
    'source_generation' => 'current-180-a',
];
$next180 = [
    'setting_id' => 180,
    'key_name' => 'app_plugin_generated_path_rowid_materialization',
    'key_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"load_policy":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-180-b',
];
$coveredConstraints180 = [
    ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ['column' => '_rowid_', 'operator' => '=', 'value' => '6'],
];

$plan180 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
): array => SQLiteJsonTablePlan::generatedPathRowidMaterializationPlan(
    'json_tree',
    $current ?? $current180,
    $next ?? $next180,
    'key_value',
    'generated_path',
    $constraints ?? $coveredConstraints180,
    'scan_root',
    $orderBy ?? [['column' => 'path'], ['column' => 'rowid']],
);

$stable180 = static fn (): array => $plan180($current180, $current180);
$range180 = static fn (): array => $plan180(
    array_replace($current180, ['generated_path' => '$.rules']),
    array_replace($current180, ['generated_path' => '$.rules']),
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [4, 9]],
    ],
);
$conflict180 = static fn (): array => $plan180(
    $current180,
    $current180,
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
        ['column' => 'oid', 'operator' => '=', 'value' => 7],
    ],
);
$unusable180 = static fn (): array => $plan180(
    $current180,
    $current180,
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6, 'usable' => false],
    ],
);

$tests = [
    'records next180 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next180', $plan180()['dependencies'], true)),
    'preserves next177 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next177', $plan180()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-path-rowid-materialization-next180-until-rowset-drain', $plan180()['currentReaderPolicy']),
    'prepares changed next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-path-rowid-materialization-next180', $plan180()['nextReaderPolicy']),
    'stable reuses next reader policy' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-materialization-next180', $stable180()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable180()['next180ReplanReasons']),
    'current source generation records explicit source' => static fn (TestRunner $t) => $t->same('source_generation:current-180-a', $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['sourceGeneration']),
    'next source generation records explicit source' => static fn (TestRunner $t) => $t->same('source_generation:next-180-b', $plan180()['nextGeneratedPathRowidCurrentSourceMaterialization180']['sourceGeneration']),
    'current source id recorded' => static fn (TestRunner $t) => $t->same(180, $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['sourceSettingId']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules[1]', $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['generatedPath']),
    'current xfilter opcode carried' => static fn (TestRunner $t) => $t->same('xfilter-current-source-covered-seek-next177', $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['xFilterOpcode']),
    'current materializes covered rowset' => static fn (TestRunner $t) => $t->same('materialize-current-source-covered-rowset-next180', $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['materializationOpcode']),
    'current reset false' => static fn (TestRunner $t) => $t->same(false, $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['resetRequired']),
    'current source pinned true' => static fn (TestRunner $t) => $t->same(true, $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['currentSourcePinned']),
    'current residual columns empty' => static fn (TestRunner $t) => $t->same([], $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['residualConstraintColumns']),
    'current seek tape has one step' => static fn (TestRunner $t) => $t->same(1, count($plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['seekTape'])),
    'current seek tape step one' => static fn (TestRunner $t) => $t->same(1, $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['seekTape'][0]['step']),
    'current seek tape rowid six' => static fn (TestRunner $t) => $t->same(6, $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['seekTape'][0]['rowid']),
    'current seek tape path' => static fn (TestRunner $t) => $t->same('$.rules[1]', $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['seekTape'][0]['path']),
    'current seek tape source generation' => static fn (TestRunner $t) => $t->same('source_generation:current-180-a', $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['seekTape'][0]['sourceGeneration']),
    'current seek tape covered' => static fn (TestRunner $t) => $t->same(true, $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['seekTape'][0]['covered']),
    'program fingerprint is carried' => static fn (TestRunner $t) => $t->same($plan180()['currentGeneratedPathRowidXFilterProgram177']['programFingerprint'], $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['programFingerprint']),
    'program fingerprint is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['programFingerprint'])),
    'current materialized one row' => static fn (TestRunner $t) => $t->same(1, count($plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['materializedRows'])),
    'current materialized rowid' => static fn (TestRunner $t) => $t->same(6, $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['materializedRows'][0]['rowid']),
    'current materialized id alias' => static fn (TestRunner $t) => $t->same(6, $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['materializedRows'][0]['id']),
    'current materialized path' => static fn (TestRunner $t) => $t->same('$.rules[1]', $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['materializedRows'][0]['path']),
    'current materialized source id' => static fn (TestRunner $t) => $t->same(180, $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['materializedRows'][0]['source_setting_id']),
    'current materialized generated path' => static fn (TestRunner $t) => $t->same('$.rules[1]', $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['materializedRows'][0]['generated_path']),
    'current materialized source generation' => static fn (TestRunner $t) => $t->same('source_generation:current-180-a', $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['materializedRows'][0]['source_generation']),
    'current materialized pinned flag' => static fn (TestRunner $t) => $t->same(true, $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['materializedRows'][0]['current_source_pinned']),
    'current materialized rowids' => static fn (TestRunner $t) => $t->same([6], $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['materializedRowids']),
    'current materialized paths' => static fn (TestRunner $t) => $t->same(['$.rules[1]'], $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['materializedPaths']),
    'rowid aliases preserved' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_', 'oid', 'id'], $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['rowidAliasColumns']),
    'omitted columns carried' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['omittedConstraintColumns']),
    'estimated rows one' => static fn (TestRunner $t) => $t->same(1, $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['estimatedRows']),
    'estimated cost one' => static fn (TestRunner $t) => $t->same(1, $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['estimatedCost']),
    'cost class point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-materialization-point-next180', $plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['costClass']),
    'materialization fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['materializationFingerprint'])),
    'next stale materialization reset' => static fn (TestRunner $t) => $t->same('materialize-reset-stale-current-source-next180', $plan180()['nextGeneratedPathRowidCurrentSourceMaterialization180']['materializationOpcode']),
    'next materialized rows empty' => static fn (TestRunner $t) => $t->same([], $plan180()['nextGeneratedPathRowidCurrentSourceMaterialization180']['materializedRows']),
    'next materialized rowids empty' => static fn (TestRunner $t) => $t->same([], $plan180()['nextGeneratedPathRowidCurrentSourceMaterialization180']['materializedRowids']),
    'next reset required true' => static fn (TestRunner $t) => $t->same(true, $plan180()['nextGeneratedPathRowidCurrentSourceMaterialization180']['resetRequired']),
    'next cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $plan180()['nextGeneratedPathRowidCurrentSourceMaterialization180']['estimatedCost']),
    'next cost class reset' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-materialization-reset-next180', $plan180()['nextGeneratedPathRowidCurrentSourceMaterialization180']['costClass']),
    'transition count records materialization fields' => static fn (TestRunner $t) => $t->same(16, count($plan180()['generatedPathRowidCurrentSourceMaterialization180Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $plan180()['generatedPathRowidCurrentSourceMaterialization180Transitions'][0]['changed']),
    'transition opcode changes' => static fn (TestRunner $t) => $t->same(true, $plan180()['generatedPathRowidCurrentSourceMaterialization180Transitions'][5]['changed']),
    'transition seek tape changes' => static fn (TestRunner $t) => $t->same(true, $plan180()['generatedPathRowidCurrentSourceMaterialization180Transitions'][8]['changed']),
    'transition materialized rowids change' => static fn (TestRunner $t) => $t->same(true, $plan180()['generatedPathRowidCurrentSourceMaterialization180Transitions'][9]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $plan180()['generatedPathRowidCurrentSourceMaterialization180Transitions'][13]['changed']),
    'reasons include source changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-materialization-next180-source-changed', $plan180()['next180ReplanReasons'], true)),
    'reasons include program changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-materialization-next180-program-changed', $plan180()['next180ReplanReasons'], true)),
    'reasons include rowset changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-materialization-next180-rowset-changed', $plan180()['next180ReplanReasons'], true)),
    'reasons include cost changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-materialization-next180-cost-changed', $plan180()['next180ReplanReasons'], true)),
    'reasons preserve next177 source changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xfilter-next177-source-changed', $plan180()['next180ReplanReasons'], true)),
    'range materializes covered rowset' => static fn (TestRunner $t) => $t->same('materialize-current-source-residual-rowset-next180', $range180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['materializationOpcode']),
    'range residual columns path id' => static fn (TestRunner $t) => $t->same(['path', 'id'], $range180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['residualConstraintColumns']),
    'range seek tape kept for residual pass' => static fn (TestRunner $t) => $t->same([4, 7, 5, 6, 8, 9], array_column($range180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['seekTape'], 'rowid')),
    'range cost class empty until residual is reapplied' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-materialization-empty-next180', $range180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['costClass']),
    'conflict materialization empty' => static fn (TestRunner $t) => $t->same('materialize-empty-rowid-alias-contradiction-next180', $conflict180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['materializationOpcode']),
    'conflict rowids empty' => static fn (TestRunner $t) => $t->same([], $conflict180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['materializedRowids']),
    'conflict cost class empty' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-materialization-contradiction-empty-next180', $conflict180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['costClass']),
    'unusable rowid still materializes path point' => static fn (TestRunner $t) => $t->same([6], $unusable180()['currentGeneratedPathRowidCurrentSourceMaterialization180']['materializedRowids']),
    'bad function rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::generatedPathRowidMaterializationPlan('json_bad', $current180, $next180, 'key_value', 'generated_path')),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid materialization plan ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
