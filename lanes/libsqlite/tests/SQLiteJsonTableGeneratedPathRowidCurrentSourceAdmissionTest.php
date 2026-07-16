<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentAdmissionSource = [
    'option_id' => 161,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_admission',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules',
];
$nextAdmissionSource = [
    'option_id' => 161,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_admission',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules[2]',
];

$plan = static fn (?array $currentSource = null, ?array $nextSource = null, ?array $constraints = null): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceAdmissionPlan(
    'json_tree',
    $currentSource ?? $currentAdmissionSource,
    $nextSource ?? $nextAdmissionSource,
    'option_value',
    'generated_path',
    $constraints ?? [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'IN', 'value' => [5, 6, 42]],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'id']],
);
$stable = static fn (): array => $plan($currentAdmissionSource, $currentAdmissionSource);
$point = static fn (): array => $plan(
    array_replace($currentAdmissionSource, ['generated_path' => '$.rules[1]']),
    array_replace($nextAdmissionSource, ['generated_path' => '$.rules[1]']),
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ],
);
$wide = static fn (): array => $plan(
    $currentAdmissionSource,
    $nextAdmissionSource,
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [1, 100]],
    ],
);
$unusable = static fn (): array => $plan(
    $currentAdmissionSource,
    $nextAdmissionSource,
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%', 'usable' => false],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6]],
    ],
);
$null = static fn (): array => $plan($currentAdmissionSource, array_replace($nextAdmissionSource, ['option_value' => null]));

$tests = [
    'records dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source', $plan()['dependencies'], true)),
    'preserves next159 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-seek-cost-current-source-next159', $plan()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-path-rowid-cost-current-source-until-cursor-reset', $plan()['currentReaderPolicy']),
    'prepares changed next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-path-rowid-cost-current-source-plan', $plan()['nextReaderPolicy']),
    'stable next reader policy reuses current' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-cost-current-source-plan', $stable()['nextReaderPolicy']),
    'stable has no reasons' => static fn (TestRunner $t) => $t->same([], $stable()['generatedPathRowidCurrentSourceAdmissionReplanReasons']),
    'current source profile remains exposed' => static fn (TestRunner $t) => $t->same('pin-current-source-json-table-cursor', $plan()['currentGeneratedPathRowidCurrentSource']['reuseDecision']),
    'next source profile remains exposed' => static fn (TestRunner $t) => $t->same('prepare-fresh-json-table-cursor', $plan()['nextGeneratedPathRowidCurrentSource']['reuseDecision']),
    'current admission source key is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan()['currentGeneratedPathRowidCurrentSourceAdmission']['sourcePinKey'])),
    'current admission reuses source' => static fn (TestRunner $t) => $t->same(true, $plan()['currentGeneratedPathRowidCurrentSourceAdmission']['currentSourceReusable']),
    'next admission does not reuse empty source' => static fn (TestRunner $t) => $t->same(false, $plan()['nextGeneratedPathRowidCurrentSourceAdmission']['currentSourceReusable']),
    'current admission seekable' => static fn (TestRunner $t) => $t->same(true, $plan()['currentGeneratedPathRowidCurrentSourceAdmission']['rowidSeekable']),
    'current admission seek operator' => static fn (TestRunner $t) => $t->same('IN', $plan()['currentGeneratedPathRowidCurrentSourceAdmission']['rowidSeekOperator']),
    'current admission seek rowids' => static fn (TestRunner $t) => $t->same([5, 6, 42], $plan()['currentGeneratedPathRowidCurrentSourceAdmission']['seekRowids']),
    'current admission matched seek rowids' => static fn (TestRunner $t) => $t->same([5, 6], $plan()['currentGeneratedPathRowidCurrentSourceAdmission']['matchedSeekRowids']),
    'current admission missing seek rowids' => static fn (TestRunner $t) => $t->same([42], $plan()['currentGeneratedPathRowidCurrentSourceAdmission']['missingSeekRowids']),
    'current admission estimated rows' => static fn (TestRunner $t) => $t->same(2, $plan()['currentGeneratedPathRowidCurrentSourceAdmission']['estimatedRows']),
    'current admission estimated cost' => static fn (TestRunner $t) => $t->same(2, $plan()['currentGeneratedPathRowidCurrentSourceAdmission']['estimatedCost']),
    'current admission cost class partial' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-admission-partial', $plan()['currentGeneratedPathRowidCurrentSourceAdmission']['costClass']),
    'current admission planner source pinned' => static fn (TestRunner $t) => $t->same('current-source-generated-path-rowid-pinned', $plan()['currentGeneratedPathRowidCurrentSourceAdmission']['plannerSource']),
    'next admission planner source reprepare' => static fn (TestRunner $t) => $t->same('next-source-generated-path-rowid-reprepare', $plan()['nextGeneratedPathRowidCurrentSourceAdmission']['plannerSource']),
    'current admission idx string' => static fn (TestRunner $t) => $t->same('omit:path:LIKE|omit:id:IN', $plan()['currentGeneratedPathRowidCurrentSourceAdmission']['idxStr']),
    'next admission idx string keeps same constraint tape' => static fn (TestRunner $t) => $t->same('residual:path:LIKE|residual:id:IN', $plan()['nextGeneratedPathRowidCurrentSourceAdmission']['idxStr']),
    'current omits path and id' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan()['currentGeneratedPathRowidCurrentSourceAdmission']['omitColumns']),
    'current has no residual columns for covered path and rowid' => static fn (TestRunner $t) => $t->same([], $plan()['currentGeneratedPathRowidCurrentSourceAdmission']['residualColumns']),
    'next has no omit columns when reprepare needed' => static fn (TestRunner $t) => $t->same([], $plan()['nextGeneratedPathRowidCurrentSourceAdmission']['omitColumns']),
    'next residual columns include normalized id' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan()['nextGeneratedPathRowidCurrentSourceAdmission']['residualColumns']),
    'constraint usage count' => static fn (TestRunner $t) => $t->same(2, count($plan()['currentGeneratedPathRowidCurrentSourceAdmission']['constraintUsage'])),
    'constraint usage normalizes rowid alias' => static fn (TestRunner $t) => $t->same('id', $plan()['currentGeneratedPathRowidCurrentSourceAdmission']['constraintUsage'][1]['column']),
    'constraint usage marks generated path rowid kind' => static fn (TestRunner $t) => $t->same('generated-path-rowid', $plan()['currentGeneratedPathRowidCurrentSourceAdmission']['constraintUsage'][1]['kind']),
    'constraint usage path also generated path rowid kind' => static fn (TestRunner $t) => $t->same('generated-path-rowid', $plan()['currentGeneratedPathRowidCurrentSourceAdmission']['constraintUsage'][0]['kind']),
    'argv binding count' => static fn (TestRunner $t) => $t->same(2, count($plan()['currentGeneratedPathRowidCurrentSourceAdmission']['argvBindings'])),
    'argv binding records rowid in value' => static fn (TestRunner $t) => $t->same([5, 6, 42], $plan()['currentGeneratedPathRowidCurrentSourceAdmission']['argvBindings'][1]['value']),
    'argv binding omits rowid' => static fn (TestRunner $t) => $t->same(true, $plan()['currentGeneratedPathRowidCurrentSourceAdmission']['argvBindings'][1]['omit']),
    'argv binding omits path' => static fn (TestRunner $t) => $t->same(true, $plan()['currentGeneratedPathRowidCurrentSourceAdmission']['argvBindings'][0]['omit']),
    'transition count records admission state' => static fn (TestRunner $t) => $t->same(13, count($plan()['generatedPathRowidCurrentSourceAdmissionTransitions'])),
    'transition source pin changes' => static fn (TestRunner $t) => $t->same(true, $plan()['generatedPathRowidCurrentSourceAdmissionTransitions'][0]['changed']),
    'transition reusable changes' => static fn (TestRunner $t) => $t->same(true, $plan()['generatedPathRowidCurrentSourceAdmissionTransitions'][1]['changed']),
    'transition seekable stable' => static fn (TestRunner $t) => $t->same(false, $plan()['generatedPathRowidCurrentSourceAdmissionTransitions'][2]['changed']),
    'transition matched seek rows change' => static fn (TestRunner $t) => $t->same(true, $plan()['generatedPathRowidCurrentSourceAdmissionTransitions'][4]['changed']),
    'transition omit columns change' => static fn (TestRunner $t) => $t->same(true, $plan()['generatedPathRowidCurrentSourceAdmissionTransitions'][7]['changed']),
    'transition cost class changes' => static fn (TestRunner $t) => $t->same(true, $plan()['generatedPathRowidCurrentSourceAdmissionTransitions'][10]['changed']),
    'reasons include admission source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-admission-source-changed', $plan()['generatedPathRowidCurrentSourceAdmissionReplanReasons'], true)),
    'reasons include admission rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-admission-rowset-changed', $plan()['generatedPathRowidCurrentSourceAdmissionReplanReasons'], true)),
    'reasons include admission usage' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-admission-usage-changed', $plan()['generatedPathRowidCurrentSourceAdmissionReplanReasons'], true)),
    'reasons include admission cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-admission-cost-changed', $plan()['generatedPathRowidCurrentSourceAdmissionReplanReasons'], true)),
    'reasons preserve next159 rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-seek-rowset-changed', $plan()['generatedPathRowidCurrentSourceAdmissionReplanReasons'], true)),
    'point admission class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-admission-point', $point()['currentGeneratedPathRowidCurrentSourceAdmission']['costClass']),
    'point admission estimated cost' => static fn (TestRunner $t) => $t->same(1, $point()['currentGeneratedPathRowidCurrentSourceAdmission']['estimatedCost']),
    'point admission omits both constraints' => static fn (TestRunner $t) => $t->same(['path', 'id'], $point()['currentGeneratedPathRowidCurrentSourceAdmission']['omitColumns']),
    'wide admission residual class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-admission-residual', $wide()['currentGeneratedPathRowidCurrentSourceAdmission']['costClass']),
    'wide admission rowid remains residual' => static fn (TestRunner $t) => $t->same(['id'], $wide()['currentGeneratedPathRowidCurrentSourceAdmission']['residualColumns']),
    'unusable path is not bound' => static fn (TestRunner $t) => $t->same(null, $unusable()['currentGeneratedPathRowidCurrentSourceAdmission']['constraintUsage'][0]['argvIndex']),
    'unusable path prevents current-source omission' => static fn (TestRunner $t) => $t->same([], $unusable()['currentGeneratedPathRowidCurrentSourceAdmission']['omitColumns']),
    'null next admission unrunnable class' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $null()['nextGeneratedPathRowidCurrentSourceAdmission']['costClass']),
    'null next admission sentinel cost' => static fn (TestRunner $t) => $t->same(1000000, $null()['nextGeneratedPathRowidCurrentSourceAdmission']['estimatedCost']),
    'bad generated path source rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan(array_replace($currentAdmissionSource, ['generated_path' => 161]), $nextAdmissionSource)),
    'missing json source rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceAdmissionPlan('json_tree', [], $nextAdmissionSource, 'option_value', 'generated_path')),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
