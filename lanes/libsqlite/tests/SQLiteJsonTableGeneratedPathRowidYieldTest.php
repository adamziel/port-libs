<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current169 = [
    'option_id' => 169,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next169',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
];
$next169 = [
    'option_id' => 169,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next169',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
];
$constraints169 = [
    ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ['column' => '_rowid_', 'operator' => '=', 'value' => 6],
];
$plan169 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostYieldPlan(
    'json_tree',
    $current ?? $current169,
    $next ?? $next169,
    'option_value',
    'generated_path',
    $constraints ?? $constraints169,
    'scan_root',
    $orderBy ?? [['column' => 'path'], ['column' => 'rowid']],
);

$stable169 = static fn (): array => $plan169($current169, $current169);
$range169 = static fn (): array => $plan169(
    array_replace($current169, ['generated_path' => '$.rules']),
    array_replace($next169, ['generated_path' => '$.rules']),
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules'],
        ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [5, 7]],
    ],
);
$partial169 = static fn (): array => $plan169(
    array_replace($current169, ['generated_path' => '$.rules']),
    array_replace($next169, ['generated_path' => '$.rules']),
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules'],
        ['column' => 'oid', 'operator' => 'IN', 'value' => [5, 7, 42]],
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ],
);
$scan169 = static fn (): array => $plan169(
    array_replace($current169, ['generated_path' => '$.rules']),
    array_replace($next169, ['generated_path' => '$.rules']),
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => '>', 'value' => 3],
    ],
);
$miss169 = static fn (): array => $plan169($current169, $next169, [
    ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ['column' => 'rowid', 'operator' => '=', 'value' => 99],
]);
$unusable169 = static fn (): array => $plan169($current169, $current169, [
    ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ['column' => 'rowid', 'operator' => '=', 'value' => 6, 'usable' => false],
]);

$tests = [
    'records next169 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next169', $plan169()['dependencies'], true)),
    'preserves next165 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next165', $plan169()['dependencies'], true)),
    'pins current yield policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-path-rowid-yield-next169-until-xfilter-reset', $plan169()['currentReaderPolicy']),
    'prepares changed next yield policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-path-rowid-yield-next169-plan', $plan169()['nextReaderPolicy']),
    'stable next yield policy reuses current' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-yield-next169-plan', $stable169()['nextReaderPolicy']),
    'stable next169 reasons empty' => static fn (TestRunner $t) => $t->same([], $stable169()['next169ReplanReasons']),
    'point admission admits current source yield' => static fn (TestRunner $t) => $t->same('admit-current-source-generated-path-rowid-yield', $plan169()['currentGeneratedPathRowidYield169']['admission']),
    'next point prepares fresh yield' => static fn (TestRunner $t) => $t->same('prepare-json-table-generated-path-rowid-yield', $plan169()['nextGeneratedPathRowidYield169']['admission']),
    'point yield mode' => static fn (TestRunner $t) => $t->same('point-or-range-yield', $plan169()['currentGeneratedPathRowidYield169']['yieldMode']),
    'point rowid alias retained' => static fn (TestRunner $t) => $t->same('_rowid_', $plan169()['currentGeneratedPathRowidYield169']['rowidAlias']),
    'point seek operator retained' => static fn (TestRunner $t) => $t->same('=', $plan169()['currentGeneratedPathRowidYield169']['seekOperator']),
    'point seekable true' => static fn (TestRunner $t) => $t->same(true, $plan169()['currentGeneratedPathRowidYield169']['seekable']),
    'point ordered rowids' => static fn (TestRunner $t) => $t->same([6], $plan169()['currentGeneratedPathRowidYield169']['orderedRowids']),
    'point missing rowids empty' => static fn (TestRunner $t) => $t->same([], $plan169()['currentGeneratedPathRowidYield169']['missingRowids']),
    'point yield tape count' => static fn (TestRunner $t) => $t->same(1, count($plan169()['currentGeneratedPathRowidYield169']['yieldTape'])),
    'point yield tape ordinal' => static fn (TestRunner $t) => $t->same(0, $plan169()['currentGeneratedPathRowidYield169']['yieldTape'][0]['ordinal']),
    'point yield tape rowid' => static fn (TestRunner $t) => $t->same(6, $plan169()['currentGeneratedPathRowidYield169']['yieldTape'][0]['rowid']),
    'point yield tape generated path' => static fn (TestRunner $t) => $t->same('$.rules[1]', $plan169()['currentGeneratedPathRowidYield169']['yieldTape'][0]['generatedPath']),
    'point yield tape status' => static fn (TestRunner $t) => $t->same('yield-current-source-row', $plan169()['currentGeneratedPathRowidYield169']['yieldTape'][0]['status']),
    'point argv program path rowid' => static fn (TestRunner $t) => $t->same(['argv1:path', 'argv2:rowid'], $plan169()['currentGeneratedPathRowidYield169']['argvProgram']),
    'point omit columns path id' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan169()['currentGeneratedPathRowidYield169']['omitColumns']),
    'point residual columns empty' => static fn (TestRunner $t) => $t->same([], $plan169()['currentGeneratedPathRowidYield169']['residualColumns']),
    'point estimated rows one' => static fn (TestRunner $t) => $t->same(1, $plan169()['currentGeneratedPathRowidYield169']['estimatedRows']),
    'point estimated cost one' => static fn (TestRunner $t) => $t->same(1, $plan169()['currentGeneratedPathRowidYield169']['estimatedCost']),
    'point cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-next169-point', $plan169()['currentGeneratedPathRowidYield169']['costClass']),
    'point fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan169()['currentGeneratedPathRowidYield169']['programFingerprint'])),
    'next empty rowids' => static fn (TestRunner $t) => $t->same([], $plan169()['nextGeneratedPathRowidYield169']['orderedRowids']),
    'next empty class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-next169-empty', $plan169()['nextGeneratedPathRowidYield169']['costClass']),
    'transition count records yield fields' => static fn (TestRunner $t) => $t->same(13, count($plan169()['generatedPathRowidYield169Transitions'])),
    'admission transition changes' => static fn (TestRunner $t) => $t->same(true, $plan169()['generatedPathRowidYield169Transitions'][0]['changed']),
    'yield mode transition changes' => static fn (TestRunner $t) => $t->same(true, $plan169()['generatedPathRowidYield169Transitions'][1]['changed']),
    'ordered rowids transition changes' => static fn (TestRunner $t) => $t->same(true, $plan169()['generatedPathRowidYield169Transitions'][3]['changed']),
    'yield tape transition changes' => static fn (TestRunner $t) => $t->same(true, $plan169()['generatedPathRowidYield169Transitions'][5]['changed']),
    'argv program transition stable' => static fn (TestRunner $t) => $t->same(false, $plan169()['generatedPathRowidYield169Transitions'][6]['changed']),
    'cost class transition changes' => static fn (TestRunner $t) => $t->same(true, $plan169()['generatedPathRowidYield169Transitions'][11]['changed']),
    'fingerprint transition changes' => static fn (TestRunner $t) => $t->same(true, $plan169()['generatedPathRowidYield169Transitions'][12]['changed']),
    'reasons include admission change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-next169-admission-changed', $plan169()['next169ReplanReasons'], true)),
    'reasons include rowset change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-next169-rowset-changed', $plan169()['next169ReplanReasons'], true)),
    'reasons include cost change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-next169-cost-changed', $plan169()['next169ReplanReasons'], true)),
    'reasons preserve next165 rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-next165-rowset-changed', $plan169()['next169ReplanReasons'], true)),
    'range ordered rowids' => static fn (TestRunner $t) => $t->same([7], $range169()['currentGeneratedPathRowidYield169']['orderedRowids']),
    'range yield tape count' => static fn (TestRunner $t) => $t->same(1, count($range169()['currentGeneratedPathRowidYield169']['yieldTape'])),
    'range class records partial source intersection' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-next169-partial', $range169()['currentGeneratedPathRowidYield169']['costClass']),
    'range estimated cost capped to matched row count' => static fn (TestRunner $t) => $t->same(1, $range169()['currentGeneratedPathRowidYield169']['estimatedCost']),
    'partial mode records missing' => static fn (TestRunner $t) => $t->same('partial-yield-with-missing-rowids', $partial169()['currentGeneratedPathRowidYield169']['yieldMode']),
    'partial missing rowids' => static fn (TestRunner $t) => $t->same([5, 42], $partial169()['currentGeneratedPathRowidYield169']['missingRowids']),
    'partial residual type retained' => static fn (TestRunner $t) => $t->same(['type'], $partial169()['currentGeneratedPathRowidYield169']['residualColumns']),
    'partial class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-next169-partial', $partial169()['currentGeneratedPathRowidYield169']['costClass']),
    'scan admission prepares yield' => static fn (TestRunner $t) => $t->same('prepare-json-table-generated-path-rowid-yield', $scan169()['currentGeneratedPathRowidYield169']['admission']),
    'scan mode residual' => static fn (TestRunner $t) => $t->same('residual-scan-yield', $scan169()['currentGeneratedPathRowidYield169']['yieldMode']),
    'scan class empty because residual scan has no seek tape' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-next169-empty', $scan169()['currentGeneratedPathRowidYield169']['costClass']),
    'miss ordered rowids empty' => static fn (TestRunner $t) => $t->same([], $miss169()['currentGeneratedPathRowidYield169']['orderedRowids']),
    'miss missing rowids contains requested rowid' => static fn (TestRunner $t) => $t->same([99], $miss169()['currentGeneratedPathRowidYield169']['missingRowids']),
    'miss class empty' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-next169-empty', $miss169()['currentGeneratedPathRowidYield169']['costClass']),
    'unusable rowid argv only path' => static fn (TestRunner $t) => $t->same(['argv1:path'], $unusable169()['currentGeneratedPathRowidYield169']['argvProgram']),
    'unusable rowid is not seekable' => static fn (TestRunner $t) => $t->same(false, $unusable169()['currentGeneratedPathRowidYield169']['seekable']),
    'bad generated path column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostYieldPlan('json_tree', $current169, $next169, 'option_value', '', [])),
    'bad json source column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostYieldPlan('json_tree', $current169, $next169, '', 'generated_path', [])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next169 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
