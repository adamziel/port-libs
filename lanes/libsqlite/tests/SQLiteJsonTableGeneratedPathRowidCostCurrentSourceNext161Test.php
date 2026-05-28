<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current161 = [
    'option_id' => 161,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next161',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules',
];
$next161 = [
    'option_id' => 161,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next161',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules[2]',
];

$plan161 = static fn (?array $current = null, ?array $next = null, ?array $constraints = null): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext161(
    'json_tree',
    $current ?? $current161,
    $next ?? $next161,
    'option_value',
    'generated_path',
    $constraints ?? [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'IN', 'value' => [5, 6, 42]],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'id']],
);
$stable161 = static fn (): array => $plan161($current161, $current161);
$point161 = static fn (): array => $plan161(
    array_replace($current161, ['generated_path' => '$.rules[1]']),
    array_replace($next161, ['generated_path' => '$.rules[1]']),
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ],
);
$wide161 = static fn (): array => $plan161(
    $current161,
    $next161,
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [1, 100]],
    ],
);
$unusable161 = static fn (): array => $plan161(
    $current161,
    $next161,
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%', 'usable' => false],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6]],
    ],
);
$nullNext161 = static fn (): array => $plan161($current161, array_replace($next161, ['option_value' => null]));

$tests = [
    'records next161 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next161', $plan161()['dependencies'], true)),
    'preserves next159 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-seek-cost-current-source-next159', $plan161()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-path-rowid-cost-current-source-next161-until-cursor-reset', $plan161()['currentReaderPolicy']),
    'prepares changed next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-path-rowid-cost-current-source-next161-plan', $plan161()['nextReaderPolicy']),
    'stable next reader policy reuses current' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-cost-current-source-next161-plan', $stable161()['nextReaderPolicy']),
    'stable has no next161 reasons' => static fn (TestRunner $t) => $t->same([], $stable161()['next161ReplanReasons']),
    'current source profile remains exposed' => static fn (TestRunner $t) => $t->same('pin-current-source-json-table-cursor', $plan161()['currentGeneratedPathRowidCurrentSource']['reuseDecision']),
    'next source profile remains exposed' => static fn (TestRunner $t) => $t->same('prepare-fresh-json-table-cursor', $plan161()['nextGeneratedPathRowidCurrentSource']['reuseDecision']),
    'current admission source key is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan161()['currentGeneratedPathRowidCurrentSourceAdmission']['sourcePinKey'])),
    'current admission reuses source' => static fn (TestRunner $t) => $t->same(true, $plan161()['currentGeneratedPathRowidCurrentSourceAdmission']['currentSourceReusable']),
    'next admission does not reuse empty source' => static fn (TestRunner $t) => $t->same(false, $plan161()['nextGeneratedPathRowidCurrentSourceAdmission']['currentSourceReusable']),
    'current admission seekable' => static fn (TestRunner $t) => $t->same(true, $plan161()['currentGeneratedPathRowidCurrentSourceAdmission']['rowidSeekable']),
    'current admission seek operator' => static fn (TestRunner $t) => $t->same('IN', $plan161()['currentGeneratedPathRowidCurrentSourceAdmission']['rowidSeekOperator']),
    'current admission seek rowids' => static fn (TestRunner $t) => $t->same([5, 6, 42], $plan161()['currentGeneratedPathRowidCurrentSourceAdmission']['seekRowids']),
    'current admission matched seek rowids' => static fn (TestRunner $t) => $t->same([5, 6], $plan161()['currentGeneratedPathRowidCurrentSourceAdmission']['matchedSeekRowids']),
    'current admission missing seek rowids' => static fn (TestRunner $t) => $t->same([42], $plan161()['currentGeneratedPathRowidCurrentSourceAdmission']['missingSeekRowids']),
    'current admission estimated rows' => static fn (TestRunner $t) => $t->same(2, $plan161()['currentGeneratedPathRowidCurrentSourceAdmission']['estimatedRows']),
    'current admission estimated cost' => static fn (TestRunner $t) => $t->same(2, $plan161()['currentGeneratedPathRowidCurrentSourceAdmission']['estimatedCost']),
    'current admission cost class partial' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-admission-partial', $plan161()['currentGeneratedPathRowidCurrentSourceAdmission']['costClass']),
    'current admission planner source pinned' => static fn (TestRunner $t) => $t->same('current-source-generated-path-rowid-pinned', $plan161()['currentGeneratedPathRowidCurrentSourceAdmission']['plannerSource']),
    'next admission planner source reprepare' => static fn (TestRunner $t) => $t->same('next-source-generated-path-rowid-reprepare', $plan161()['nextGeneratedPathRowidCurrentSourceAdmission']['plannerSource']),
    'current admission idx string' => static fn (TestRunner $t) => $t->same('omit:path:LIKE|omit:id:IN', $plan161()['currentGeneratedPathRowidCurrentSourceAdmission']['idxStr']),
    'next admission idx string keeps same constraint tape' => static fn (TestRunner $t) => $t->same('residual:path:LIKE|residual:id:IN', $plan161()['nextGeneratedPathRowidCurrentSourceAdmission']['idxStr']),
    'current omits path and id' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan161()['currentGeneratedPathRowidCurrentSourceAdmission']['omitColumns']),
    'current has no residual columns for covered path and rowid' => static fn (TestRunner $t) => $t->same([], $plan161()['currentGeneratedPathRowidCurrentSourceAdmission']['residualColumns']),
    'next has no omit columns when reprepare needed' => static fn (TestRunner $t) => $t->same([], $plan161()['nextGeneratedPathRowidCurrentSourceAdmission']['omitColumns']),
    'next residual columns include normalized id' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan161()['nextGeneratedPathRowidCurrentSourceAdmission']['residualColumns']),
    'constraint usage count' => static fn (TestRunner $t) => $t->same(2, count($plan161()['currentGeneratedPathRowidCurrentSourceAdmission']['constraintUsage'])),
    'constraint usage normalizes rowid alias' => static fn (TestRunner $t) => $t->same('id', $plan161()['currentGeneratedPathRowidCurrentSourceAdmission']['constraintUsage'][1]['column']),
    'constraint usage marks generated path rowid kind' => static fn (TestRunner $t) => $t->same('generated-path-rowid', $plan161()['currentGeneratedPathRowidCurrentSourceAdmission']['constraintUsage'][1]['kind']),
    'constraint usage path also generated path rowid kind' => static fn (TestRunner $t) => $t->same('generated-path-rowid', $plan161()['currentGeneratedPathRowidCurrentSourceAdmission']['constraintUsage'][0]['kind']),
    'argv binding count' => static fn (TestRunner $t) => $t->same(2, count($plan161()['currentGeneratedPathRowidCurrentSourceAdmission']['argvBindings'])),
    'argv binding records rowid in value' => static fn (TestRunner $t) => $t->same([5, 6, 42], $plan161()['currentGeneratedPathRowidCurrentSourceAdmission']['argvBindings'][1]['value']),
    'argv binding omits rowid' => static fn (TestRunner $t) => $t->same(true, $plan161()['currentGeneratedPathRowidCurrentSourceAdmission']['argvBindings'][1]['omit']),
    'argv binding omits path' => static fn (TestRunner $t) => $t->same(true, $plan161()['currentGeneratedPathRowidCurrentSourceAdmission']['argvBindings'][0]['omit']),
    'transition count records admission state' => static fn (TestRunner $t) => $t->same(13, count($plan161()['generatedPathRowidCurrentSourceAdmissionTransitions'])),
    'transition source pin changes' => static fn (TestRunner $t) => $t->same(true, $plan161()['generatedPathRowidCurrentSourceAdmissionTransitions'][0]['changed']),
    'transition reusable changes' => static fn (TestRunner $t) => $t->same(true, $plan161()['generatedPathRowidCurrentSourceAdmissionTransitions'][1]['changed']),
    'transition seekable stable' => static fn (TestRunner $t) => $t->same(false, $plan161()['generatedPathRowidCurrentSourceAdmissionTransitions'][2]['changed']),
    'transition matched seek rows change' => static fn (TestRunner $t) => $t->same(true, $plan161()['generatedPathRowidCurrentSourceAdmissionTransitions'][4]['changed']),
    'transition omit columns change' => static fn (TestRunner $t) => $t->same(true, $plan161()['generatedPathRowidCurrentSourceAdmissionTransitions'][7]['changed']),
    'transition cost class changes' => static fn (TestRunner $t) => $t->same(true, $plan161()['generatedPathRowidCurrentSourceAdmissionTransitions'][10]['changed']),
    'reasons include admission source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-admission-source-changed', $plan161()['next161ReplanReasons'], true)),
    'reasons include admission rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-admission-rowset-changed', $plan161()['next161ReplanReasons'], true)),
    'reasons include admission usage' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-admission-usage-changed', $plan161()['next161ReplanReasons'], true)),
    'reasons include admission cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-admission-cost-changed', $plan161()['next161ReplanReasons'], true)),
    'reasons preserve next159 rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-seek-rowset-changed', $plan161()['next161ReplanReasons'], true)),
    'point admission class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-admission-point', $point161()['currentGeneratedPathRowidCurrentSourceAdmission']['costClass']),
    'point admission estimated cost' => static fn (TestRunner $t) => $t->same(1, $point161()['currentGeneratedPathRowidCurrentSourceAdmission']['estimatedCost']),
    'point admission omits both constraints' => static fn (TestRunner $t) => $t->same(['path', 'id'], $point161()['currentGeneratedPathRowidCurrentSourceAdmission']['omitColumns']),
    'wide admission residual class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-admission-residual', $wide161()['currentGeneratedPathRowidCurrentSourceAdmission']['costClass']),
    'wide admission rowid remains residual' => static fn (TestRunner $t) => $t->same(['id'], $wide161()['currentGeneratedPathRowidCurrentSourceAdmission']['residualColumns']),
    'unusable path is not bound' => static fn (TestRunner $t) => $t->same(null, $unusable161()['currentGeneratedPathRowidCurrentSourceAdmission']['constraintUsage'][0]['argvIndex']),
    'unusable path prevents current-source omission' => static fn (TestRunner $t) => $t->same([], $unusable161()['currentGeneratedPathRowidCurrentSourceAdmission']['omitColumns']),
    'null next admission unrunnable class' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $nullNext161()['nextGeneratedPathRowidCurrentSourceAdmission']['costClass']),
    'null next admission sentinel cost' => static fn (TestRunner $t) => $t->same(1000000, $nullNext161()['nextGeneratedPathRowidCurrentSourceAdmission']['estimatedCost']),
    'bad generated path source rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan161(array_replace($current161, ['generated_path' => 161]), $next161)),
    'missing json source rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext161('json_tree', [], $next161, 'option_value', 'generated_path')),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next161 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
