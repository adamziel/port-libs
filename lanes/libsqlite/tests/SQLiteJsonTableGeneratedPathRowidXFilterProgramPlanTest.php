<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current177 = [
    'option_id' => 177,
    'option_name' => 'wp_plugin_generated_path_rowid_xfilter_program',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
];
$next177 = [
    'option_id' => 177,
    'option_name' => 'wp_plugin_generated_path_rowid_xfilter_program',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
];
$coveredConstraints177 = [
    ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ['column' => '_rowid_', 'operator' => '=', 'value' => '6'],
];
$conflictingConstraints177 = [
    ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ['column' => '_rowid_', 'operator' => '=', 'value' => 7],
];

$plan177 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
): array => SQLiteJsonTablePlan::generatedPathRowidXFilterProgramPlan(
    'json_tree',
    $current ?? $current177,
    $next ?? $next177,
    'option_value',
    'generated_path',
    $constraints ?? $coveredConstraints177,
    'scan_root',
    [['column' => 'path'], ['column' => 'rowid']],
);
$stable177 = static fn (): array => $plan177($current177, $current177, $coveredConstraints177);
$range177 = static fn (): array => $plan177(
    array_replace($current177, ['generated_path' => '$.rules']),
    array_replace($current177, ['generated_path' => '$.rules']),
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [4, 9]],
    ],
);
$conflict177 = static fn (): array => $plan177($current177, $current177, $conflictingConstraints177);
$unusable177 = static fn (): array => $plan177(
    $current177,
    $current177,
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6, 'usable' => false],
    ],
);

$tests = [
    'records next177 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next177', $plan177()['dependencies'], true)),
    'preserves next174 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next174', $plan177()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-path-rowid-xfilter-next177-until-source-reset', $plan177()['currentReaderPolicy']),
    'prepares changed next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-path-rowid-xfilter-next177-program', $plan177()['nextReaderPolicy']),
    'stable reuses next reader policy' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-xfilter-next177-program', $stable177()['nextReaderPolicy']),
    'stable next177 reasons empty' => static fn (TestRunner $t) => $t->same([], $stable177()['next177ReplanReasons']),
    'current function normalized' => static fn (TestRunner $t) => $t->same('json_tree', $plan177()['currentGeneratedPathRowidXFilterProgram177']['function']),
    'current source id recorded' => static fn (TestRunner $t) => $t->same(177, $plan177()['currentGeneratedPathRowidXFilterProgram177']['sourceOptionId']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules[1]', $plan177()['currentGeneratedPathRowidXFilterProgram177']['generatedPath']),
    'current source pinned' => static fn (TestRunner $t) => $t->same(true, $plan177()['currentGeneratedPathRowidXFilterProgram177']['currentSourcePinned']),
    'current aliases not conflicting' => static fn (TestRunner $t) => $t->same(false, $plan177()['currentGeneratedPathRowidXFilterProgram177']['conflictingRowidAliases']),
    'current covered opcode' => static fn (TestRunner $t) => $t->same('xfilter-current-source-covered-seek-next177', $plan177()['currentGeneratedPathRowidXFilterProgram177']['xFilterOpcode']),
    'current covered reset false' => static fn (TestRunner $t) => $t->same(false, $plan177()['currentGeneratedPathRowidXFilterProgram177']['resetRequired']),
    'current argv columns include path and rowid' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan177()['currentGeneratedPathRowidXFilterProgram177']['argvColumns']),
    'current argv values include generated path and rowid' => static fn (TestRunner $t) => $t->same(['$.rules[1]', 6], $plan177()['currentGeneratedPathRowidXFilterProgram177']['argvValues']),
    'argv program path opcode' => static fn (TestRunner $t) => $t->same('bind-generated-path', $plan177()['currentGeneratedPathRowidXFilterProgram177']['argvProgram'][0]['opcode']),
    'argv program rowid opcode' => static fn (TestRunner $t) => $t->same('bind-rowid', $plan177()['currentGeneratedPathRowidXFilterProgram177']['argvProgram'][1]['opcode']),
    'argv program rowid value' => static fn (TestRunner $t) => $t->same(6, $plan177()['currentGeneratedPathRowidXFilterProgram177']['argvProgram'][1]['value']),
    'argv program constraints omitted' => static fn (TestRunner $t) => $t->same(true, $plan177()['currentGeneratedPathRowidXFilterProgram177']['argvProgram'][0]['omit']),
    'omitted columns path and id' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan177()['currentGeneratedPathRowidXFilterProgram177']['omittedConstraintColumns']),
    'residual columns empty' => static fn (TestRunner $t) => $t->same([], $plan177()['currentGeneratedPathRowidXFilterProgram177']['residualConstraintColumns']),
    'yield rowids point' => static fn (TestRunner $t) => $t->same([6], $plan177()['currentGeneratedPathRowidXFilterProgram177']['yieldRowids']),
    'yield paths point' => static fn (TestRunner $t) => $t->same(['$.rules[1]'], $plan177()['currentGeneratedPathRowidXFilterProgram177']['yieldPaths']),
    'yield count one' => static fn (TestRunner $t) => $t->same(1, $plan177()['currentGeneratedPathRowidXFilterProgram177']['yieldCount']),
    'source pin key is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan177()['currentGeneratedPathRowidXFilterProgram177']['sourcePinKey'])),
    'estimated rows one' => static fn (TestRunner $t) => $t->same(1, $plan177()['currentGeneratedPathRowidXFilterProgram177']['estimatedRows']),
    'estimated cost one' => static fn (TestRunner $t) => $t->same(1, $plan177()['currentGeneratedPathRowidXFilterProgram177']['estimatedCost']),
    'cost class point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xfilter-point-next177', $plan177()['currentGeneratedPathRowidXFilterProgram177']['costClass']),
    'program fingerprint is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan177()['currentGeneratedPathRowidXFilterProgram177']['programFingerprint'])),
    'next changed source empties stale rowset opcode' => static fn (TestRunner $t) => $t->same('xfilter-empty-current-source-rowset-next177', $plan177()['nextGeneratedPathRowidXFilterProgram177']['xFilterOpcode']),
    'next changed source reset required' => static fn (TestRunner $t) => $t->same(true, $plan177()['nextGeneratedPathRowidXFilterProgram177']['resetRequired']),
    'next changed source sentinel cost' => static fn (TestRunner $t) => $t->same(1000000, $plan177()['nextGeneratedPathRowidXFilterProgram177']['estimatedCost']),
    'next changed source empty cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xfilter-empty-next177', $plan177()['nextGeneratedPathRowidXFilterProgram177']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(18, count($plan177()['generatedPathRowidXFilterProgram177Transitions'])),
    'transition generated path changes' => static fn (TestRunner $t) => $t->same(true, $plan177()['generatedPathRowidXFilterProgram177Transitions'][1]['changed']),
    'transition opcode changes' => static fn (TestRunner $t) => $t->same(true, $plan177()['generatedPathRowidXFilterProgram177Transitions'][4]['changed']),
    'transition argv values change with generated path' => static fn (TestRunner $t) => $t->same(true, $plan177()['generatedPathRowidXFilterProgram177Transitions'][7]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $plan177()['generatedPathRowidXFilterProgram177Transitions'][11]['changed']),
    'transition source pin changes' => static fn (TestRunner $t) => $t->same(true, $plan177()['generatedPathRowidXFilterProgram177Transitions'][13]['changed']),
    'reasons include source changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xfilter-next177-source-changed', $plan177()['next177ReplanReasons'], true)),
    'reasons include admission changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xfilter-next177-admission-changed', $plan177()['next177ReplanReasons'], true)),
    'reasons include rowset changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xfilter-next177-rowset-changed', $plan177()['next177ReplanReasons'], true)),
    'reasons include cost changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xfilter-next177-cost-changed', $plan177()['next177ReplanReasons'], true)),
    'range opcode residual scan' => static fn (TestRunner $t) => $t->same('xfilter-current-source-residual-scan-next177', $range177()['currentGeneratedPathRowidXFilterProgram177']['xFilterOpcode']),
    'range residual columns path and id' => static fn (TestRunner $t) => $t->same(['path', 'id'], $range177()['currentGeneratedPathRowidXFilterProgram177']['residualConstraintColumns']),
    'range yields inherited rowids' => static fn (TestRunner $t) => $t->same([4, 7, 5, 6, 8, 9], $range177()['currentGeneratedPathRowidXFilterProgram177']['yieldRowids']),
    'range estimates six rows' => static fn (TestRunner $t) => $t->same(6, $range177()['currentGeneratedPathRowidXFilterProgram177']['estimatedRows']),
    'range cost class residual' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xfilter-residual-next177', $range177()['currentGeneratedPathRowidXFilterProgram177']['costClass']),
    'conflict opcode empty' => static fn (TestRunner $t) => $t->same('xfilter-empty-rowid-alias-contradiction-next177', $conflict177()['currentGeneratedPathRowidXFilterProgram177']['xFilterOpcode']),
    'conflict reset required' => static fn (TestRunner $t) => $t->same(true, $conflict177()['currentGeneratedPathRowidXFilterProgram177']['resetRequired']),
    'conflict rowids empty' => static fn (TestRunner $t) => $t->same([], $conflict177()['currentGeneratedPathRowidXFilterProgram177']['yieldRowids']),
    'conflict estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $conflict177()['currentGeneratedPathRowidXFilterProgram177']['estimatedRows']),
    'conflict cost class empty' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xfilter-contradiction-empty-next177', $conflict177()['currentGeneratedPathRowidXFilterProgram177']['costClass']),
    'unusable rowid keeps path-only covered seek' => static fn (TestRunner $t) => $t->same('xfilter-current-source-covered-seek-next177', $unusable177()['currentGeneratedPathRowidXFilterProgram177']['xFilterOpcode']),
    'bad function rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::generatedPathRowidXFilterProgramPlan('json_blob', $current177, $next177, 'option_value', 'generated_path')),
    'bad json source column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::generatedPathRowidXFilterProgramPlan('json_tree', $current177, $next177, '', 'generated_path')),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid xfilter program plan ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
