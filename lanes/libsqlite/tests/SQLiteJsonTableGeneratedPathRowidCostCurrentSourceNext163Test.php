<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current163 = [
    'option_id' => 163,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next163',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
];
$next163 = [
    'option_id' => 163,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next163',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
];
$constraints163 = [
    ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ['column' => '_rowid_', 'operator' => '=', 'value' => 6],
];
$order163 = [['column' => 'path'], ['column' => 'rowid']];

$plan163 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostBestIndex(
    'json_tree',
    $current ?? $current163,
    $next ?? $next163,
    'option_value',
    'generated_path',
    $constraints ?? $constraints163,
    'scan_root',
    $orderBy ?? $order163,
);

$stable163 = static fn (): array => $plan163($current163, $current163);
$range163 = static fn (): array => $plan163(
    array_replace($current163, ['generated_path' => '$.rules']),
    array_replace($next163, ['generated_path' => '$.rules']),
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [4, 9]],
        ['column' => 'type', 'operator' => 'IN', 'value' => ['object', 'integer']],
    ],
);
$unusable163 = static fn (): array => $plan163(
    $current163,
    $current163,
    array_replace($constraints163, [1 => ['column' => 'oid', 'operator' => '=', 'value' => 6, 'usable' => false]]),
);
$jsonb163 = static fn (): array => $plan163(
    $current163,
    array_replace($current163, ['option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($current163['option_value'])))]),
);
$unrunnable163 = static fn (): array => $plan163($current163, array_replace($next163, ['option_value' => null]));

$tests = [
    'records next163 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next163', $plan163()['dependencies'], true)),
    'preserves next160 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next160', $plan163()['dependencies'], true)),
    'pins current best index policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-path-rowid-best-index-until-xfilter-reset', $plan163()['currentReaderPolicy']),
    'prepares changed next best index policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-path-rowid-best-index-plan', $plan163()['nextReaderPolicy']),
    'stable next best index policy reuses current' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-best-index-plan', $stable163()['nextReaderPolicy']),
    'stable next163 reasons empty' => static fn (TestRunner $t) => $t->same([], $stable163()['next163ReplanReasons']),
    'current idx num includes path rowid order runnable' => static fn (TestRunner $t) => $t->same(15, $plan163()['currentGeneratedPathRowidBestIndex']['idxNum']),
    'next idx num keeps runnable source bit for empty rowset' => static fn (TestRunner $t) => $t->same(15, $plan163()['nextGeneratedPathRowidBestIndex']['idxNum']),
    'idx string carries rowid alias' => static fn (TestRunner $t) => $t->same('path|rowid:_rowid_|order|covering|json-table-generated-path-rowid-point', $plan163()['currentGeneratedPathRowidBestIndex']['idxStr']),
    'next idx string records empty cost' => static fn (TestRunner $t) => $t->same('path|rowid:_rowid_|order|covering|json-table-generated-path-rowid-empty', $plan163()['nextGeneratedPathRowidBestIndex']['idxStr']),
    'rowid alias preserved from constraint spelling' => static fn (TestRunner $t) => $t->same('_rowid_', $plan163()['currentGeneratedPathRowidBestIndex']['rowidAlias']),
    'path constraint omitted' => static fn (TestRunner $t) => $t->same(true, $plan163()['currentGeneratedPathRowidBestIndex']['pathConstraintOmitted']),
    'rowid constraint omitted' => static fn (TestRunner $t) => $t->same(true, $plan163()['currentGeneratedPathRowidBestIndex']['rowidConstraintOmitted']),
    'usable columns normalized' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan163()['currentGeneratedPathRowidBestIndex']['usableConstraintColumns']),
    'argv binding columns normalized' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan163()['currentGeneratedPathRowidBestIndex']['argvBindingColumns']),
    'residual columns empty for covering point' => static fn (TestRunner $t) => $t->same([], $plan163()['currentGeneratedPathRowidBestIndex']['residualColumns']),
    'order by consumed for path rowid order' => static fn (TestRunner $t) => $t->same(true, $plan163()['currentGeneratedPathRowidBestIndex']['orderByConsumed']),
    'estimated rows is point rowset' => static fn (TestRunner $t) => $t->same(1, $plan163()['currentGeneratedPathRowidBestIndex']['estimatedRows']),
    'estimated cost is point cost' => static fn (TestRunner $t) => $t->same(1, $plan163()['currentGeneratedPathRowidBestIndex']['estimatedCost']),
    'cursor admission pins current source' => static fn (TestRunner $t) => $t->same('admit-current-source-generated-path-rowid-cursor', $plan163()['currentGeneratedPathRowidBestIndex']['cursorAdmission']),
    'next empty cursor prepares fresh' => static fn (TestRunner $t) => $t->same('prepare-json-table-cursor', $plan163()['nextGeneratedPathRowidBestIndex']['cursorAdmission']),
    'current source pinned true' => static fn (TestRunner $t) => $t->same(true, $plan163()['currentGeneratedPathRowidBestIndex']['currentSourcePinned']),
    'next source pinned false for empty rowset' => static fn (TestRunner $t) => $t->same(false, $plan163()['nextGeneratedPathRowidBestIndex']['currentSourcePinned']),
    'covering constraint set true' => static fn (TestRunner $t) => $t->same(true, $plan163()['currentGeneratedPathRowidBestIndex']['coveringConstraintSet']),
    'best index fingerprint is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan163()['currentGeneratedPathRowidBestIndex']['planFingerprint'])),
    'best index fingerprint changes with source' => static fn (TestRunner $t) => $t->true($plan163()['currentGeneratedPathRowidBestIndex']['planFingerprint'] !== $plan163()['nextGeneratedPathRowidBestIndex']['planFingerprint']),
    'transition count records best index fields' => static fn (TestRunner $t) => $t->same(8, count($plan163()['generatedPathRowidBestIndexTransitions'])),
    'idx num transition stable' => static fn (TestRunner $t) => $t->same(false, $plan163()['generatedPathRowidBestIndexTransitions'][0]['changed']),
    'idx string transition changes' => static fn (TestRunner $t) => $t->same(true, $plan163()['generatedPathRowidBestIndexTransitions'][1]['changed']),
    'estimated row transition changes' => static fn (TestRunner $t) => $t->same(true, $plan163()['generatedPathRowidBestIndexTransitions'][2]['changed']),
    'estimated cost transition stable for point to empty' => static fn (TestRunner $t) => $t->same(false, $plan163()['generatedPathRowidBestIndexTransitions'][3]['changed']),
    'cursor admission transition changes' => static fn (TestRunner $t) => $t->same(true, $plan163()['generatedPathRowidBestIndexTransitions'][4]['changed']),
    'pin transition changes' => static fn (TestRunner $t) => $t->same(true, $plan163()['generatedPathRowidBestIndexTransitions'][5]['changed']),
    'covering transition stable' => static fn (TestRunner $t) => $t->same(false, $plan163()['generatedPathRowidBestIndexTransitions'][6]['changed']),
    'fingerprint transition changes' => static fn (TestRunner $t) => $t->same(true, $plan163()['generatedPathRowidBestIndexTransitions'][7]['changed']),
    'next163 reasons include shape change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-best-index-shape-changed', $plan163()['next163ReplanReasons'], true)),
    'next163 reasons include admission change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-best-index-admission-changed', $plan163()['next163ReplanReasons'], true)),
    'next163 reasons include fingerprint change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-best-index-fingerprint-changed', $plan163()['next163ReplanReasons'], true)),
    'next163 preserves next160 source rowset reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-source-rowset-changed', $plan163()['next163ReplanReasons'], true)),
    'range idx num keeps runnable bit only' => static fn (TestRunner $t) => $t->same(8, $range163()['currentGeneratedPathRowidBestIndex']['idxNum']),
    'range keeps rowid alias null for nonpoint rowid' => static fn (TestRunner $t) => $t->same(null, $range163()['currentGeneratedPathRowidBestIndex']['rowidAlias']),
    'range order by not consumed' => static fn (TestRunner $t) => $t->same(false, $range163()['currentGeneratedPathRowidBestIndex']['orderByConsumed']),
    'range estimates four rows' => static fn (TestRunner $t) => $t->same(4, $range163()['currentGeneratedPathRowidBestIndex']['estimatedRows']),
    'range estimates bounded cost' => static fn (TestRunner $t) => $t->same(4, $range163()['currentGeneratedPathRowidBestIndex']['estimatedCost']),
    'range residual columns include path id and type' => static fn (TestRunner $t) => $t->same(['path', 'id', 'type'], $range163()['currentGeneratedPathRowidBestIndex']['residualColumns']),
    'range covering false' => static fn (TestRunner $t) => $t->same(false, $range163()['currentGeneratedPathRowidBestIndex']['coveringConstraintSet']),
    'unusable rowid idx num keeps path only' => static fn (TestRunner $t) => $t->same(9, $unusable163()['currentGeneratedPathRowidBestIndex']['idxNum']),
    'unusable rowid alias null' => static fn (TestRunner $t) => $t->same(null, $unusable163()['currentGeneratedPathRowidBestIndex']['rowidAlias']),
    'unusable rowid argv only path' => static fn (TestRunner $t) => $t->same(['path'], $unusable163()['currentGeneratedPathRowidBestIndex']['argvBindingColumns']),
    'jsonb next keeps runnable best index' => static fn (TestRunner $t) => $t->same(15, $jsonb163()['nextGeneratedPathRowidBestIndex']['idxNum']),
    'jsonb next remains pinned' => static fn (TestRunner $t) => $t->same(true, $jsonb163()['nextGeneratedPathRowidBestIndex']['currentSourcePinned']),
    'unrunnable next estimates no rows' => static fn (TestRunner $t) => $t->same(0, $unrunnable163()['nextGeneratedPathRowidBestIndex']['estimatedRows']),
    'unrunnable next estimates sentinel cost' => static fn (TestRunner $t) => $t->same(1000000, $unrunnable163()['nextGeneratedPathRowidBestIndex']['estimatedCost']),
    'unrunnable next prepares cursor' => static fn (TestRunner $t) => $t->same('prepare-json-table-cursor', $unrunnable163()['nextGeneratedPathRowidBestIndex']['cursorAdmission']),
    'bad json source column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostBestIndex('json_tree', $current163, $next163, '', 'generated_path', $constraints163)),
    'bad generated path column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostBestIndex('json_tree', $current163, $next163, 'option_value', '', $constraints163)),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next163 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
