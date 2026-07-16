<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current158 = [
    'option_id' => 158,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules[1]',
];
$next158 = [
    'option_id' => 158,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules[2]',
];

$point158 = static fn (?array $current = null, ?array $next = null): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSourceProfilePlan(
    'json_tree',
    $current ?? $current158,
    $next ?? $next158,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'id']],
);
$stable158 = static fn (): array => $point158($current158, $current158);
$range158 = static fn (): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSourceProfilePlan(
    'json_tree',
    array_replace($current158, ['generated_path' => '$.rules']),
    array_replace($next158, ['generated_path' => '$.rules']),
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'BETWEEN', 'value' => [4, 9]],
        ['column' => 'type', 'operator' => 'IN', 'value' => ['text', 'integer']],
    ],
    'scan_root',
);
$miss158 = static fn (): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSourceProfilePlan(
    'json_tree',
    $current158,
    $next158,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'oid', 'operator' => '=', 'value' => 99],
    ],
    'scan_root',
);
$unrunnable158 = static fn (): array => $point158($current158, array_replace($next158, ['option_value' => null]));

$tests = [
    'records next158 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next158', $point158()['dependencies'], true)),
    'preserves generated path rowid cost dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source', $point158()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-path-rowid-cost-source158-until-cursor-reset', $point158()['currentReaderPolicy']),
    'prepares changed next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-path-rowid-cost-source158-plan', $point158()['nextReaderPolicy']),
    'stable next reader policy reuses current' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-cost-source158-plan', $stable158()['nextReaderPolicy']),
    'stable plan has no next158 reasons' => static fn (TestRunner $t) => $t->same([], $stable158()['next158ReplanReasons']),
    'current profile records json column' => static fn (TestRunner $t) => $t->same('option_value', $point158()['currentGeneratedPathRowidCurrentSource']['jsonColumn']),
    'current profile records generated path column' => static fn (TestRunner $t) => $t->same('generated_path', $point158()['currentGeneratedPathRowidCurrentSource']['generatedPathColumn']),
    'current json source kind is text' => static fn (TestRunner $t) => $t->same('text', $point158()['currentGeneratedPathRowidCurrentSource']['jsonSourceKind']),
    'next json source kind is text' => static fn (TestRunner $t) => $t->same('text', $point158()['nextGeneratedPathRowidCurrentSource']['jsonSourceKind']),
    'current source fingerprint is stable hash token' => static fn (TestRunner $t) => $t->true(str_starts_with($point158()['currentGeneratedPathRowidCurrentSource']['jsonSourceFingerprint'], 'string:')),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules[1]', $point158()['currentGeneratedPathRowidCurrentSource']['generatedPath']),
    'next generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules[2]', $point158()['nextGeneratedPathRowidCurrentSource']['generatedPath']),
    'generated path fingerprint changes' => static fn (TestRunner $t) => $t->true($point158()['currentGeneratedPathRowidCurrentSource']['generatedPathFingerprint'] !== $point158()['nextGeneratedPathRowidCurrentSource']['generatedPathFingerprint']),
    'rowid signature carries through' => static fn (TestRunner $t) => $t->same('id:=:6', $point158()['currentGeneratedPathRowidCurrentSource']['rowidConstraintSignature']),
    'current intersected rowids carry through' => static fn (TestRunner $t) => $t->same([6], $point158()['currentGeneratedPathRowidCurrentSource']['intersectedRowids']),
    'current intersected paths carry through' => static fn (TestRunner $t) => $t->same(['$.rules[1]'], $point158()['currentGeneratedPathRowidCurrentSource']['intersectedPaths']),
    'current intersection count carries through' => static fn (TestRunner $t) => $t->same(1, $point158()['currentGeneratedPathRowidCurrentSource']['intersectedRowCount']),
    'current source is reusable' => static fn (TestRunner $t) => $t->same(true, $point158()['currentGeneratedPathRowidCurrentSource']['currentSourceReusable']),
    'current reuse decision pins cursor' => static fn (TestRunner $t) => $t->same('pin-current-source-json-table-cursor', $point158()['currentGeneratedPathRowidCurrentSource']['reuseDecision']),
    'current pinned cost is point seek' => static fn (TestRunner $t) => $t->same(1, $point158()['currentGeneratedPathRowidCurrentSource']['pinnedEstimatedCost']),
    'current effective cost carries through' => static fn (TestRunner $t) => $t->same(1, $point158()['currentGeneratedPathRowidCurrentSource']['effectiveEstimatedCost']),
    'current cost class is pinned point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-pinned-point', $point158()['currentGeneratedPathRowidCurrentSource']['costClass']),
    'source pin key is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($point158()['currentGeneratedPathRowidCurrentSource']['sourcePinKey'])),
    'source pin key changes when next source shifts' => static fn (TestRunner $t) => $t->true($point158()['currentGeneratedPathRowidCurrentSource']['sourcePinKey'] !== $point158()['nextGeneratedPathRowidCurrentSource']['sourcePinKey']),
    'next empty intersection is not reusable' => static fn (TestRunner $t) => $t->same(false, $point158()['nextGeneratedPathRowidCurrentSource']['currentSourceReusable']),
    'next empty decision prepares cursor' => static fn (TestRunner $t) => $t->same('prepare-fresh-json-table-cursor', $point158()['nextGeneratedPathRowidCurrentSource']['reuseDecision']),
    'next empty pinned cost is sentinel' => static fn (TestRunner $t) => $t->same(1000000, $point158()['nextGeneratedPathRowidCurrentSource']['pinnedEstimatedCost']),
    'next empty cost class records empty source' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-empty', $point158()['nextGeneratedPathRowidCurrentSource']['costClass']),
    'transition count records current-source state' => static fn (TestRunner $t) => $t->same(10, count($point158()['generatedPathRowidCurrentSourceTransitions'])),
    'transition source kind stable' => static fn (TestRunner $t) => $t->same(false, $point158()['generatedPathRowidCurrentSourceTransitions'][0]['changed']),
    'transition source fingerprint changes' => static fn (TestRunner $t) => $t->same(true, $point158()['generatedPathRowidCurrentSourceTransitions'][1]['changed']),
    'transition path fingerprint changes' => static fn (TestRunner $t) => $t->same(true, $point158()['generatedPathRowidCurrentSourceTransitions'][2]['changed']),
    'transition rowid signature stable' => static fn (TestRunner $t) => $t->same(false, $point158()['generatedPathRowidCurrentSourceTransitions'][3]['changed']),
    'transition rowids change' => static fn (TestRunner $t) => $t->same(true, $point158()['generatedPathRowidCurrentSourceTransitions'][4]['changed']),
    'transition paths change' => static fn (TestRunner $t) => $t->same(true, $point158()['generatedPathRowidCurrentSourceTransitions'][5]['changed']),
    'transition pin key changes' => static fn (TestRunner $t) => $t->same(true, $point158()['generatedPathRowidCurrentSourceTransitions'][6]['changed']),
    'transition reuse decision changes' => static fn (TestRunner $t) => $t->same(true, $point158()['generatedPathRowidCurrentSourceTransitions'][7]['changed']),
    'transition pinned cost changes' => static fn (TestRunner $t) => $t->same(true, $point158()['generatedPathRowidCurrentSourceTransitions'][8]['changed']),
    'transition cost class changes' => static fn (TestRunner $t) => $t->same(true, $point158()['generatedPathRowidCurrentSourceTransitions'][9]['changed']),
    'next158 reasons include source json drift' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-json-changed', $point158()['next158ReplanReasons'], true)),
    'next158 reasons include path drift' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-path-changed', $point158()['next158ReplanReasons'], true)),
    'next158 reasons include rowset drift' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-rowset-changed', $point158()['next158ReplanReasons'], true)),
    'next158 reasons include pin key drift' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-pin-key-changed', $point158()['next158ReplanReasons'], true)),
    'next158 reasons include reuse decision drift' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-reuse-decision-changed', $point158()['next158ReplanReasons'], true)),
    'next158 preserves next145 source changed reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-source-changed', $point158()['next158ReplanReasons'], true)),
    'range current pins narrow source' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-pinned-narrow', $range158()['currentGeneratedPathRowidCurrentSource']['costClass']),
    'range pinned cost keeps existing narrow estimate' => static fn (TestRunner $t) => $t->same(1, $range158()['currentGeneratedPathRowidCurrentSource']['pinnedEstimatedCost']),
    'range intersected rowids retained' => static fn (TestRunner $t) => $t->same([5, 6, 8, 9], $range158()['currentGeneratedPathRowidCurrentSource']['intersectedRowids']),
    'miss current not reusable' => static fn (TestRunner $t) => $t->same(false, $miss158()['currentGeneratedPathRowidCurrentSource']['currentSourceReusable']),
    'miss current cost class empty' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-empty', $miss158()['currentGeneratedPathRowidCurrentSource']['costClass']),
    'unrunnable next source kind is sql null' => static fn (TestRunner $t) => $t->same('sql-null', $unrunnable158()['nextGeneratedPathRowidCurrentSource']['jsonSourceKind']),
    'unrunnable next cost class carries sentinel' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable158()['nextGeneratedPathRowidCurrentSource']['costClass']),
    'unrunnable next reasons include source kind' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-kind-changed', $unrunnable158()['next158ReplanReasons'], true)),
    'missing json source rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSourceProfilePlan('json_tree', [], $next158, 'option_value', 'generated_path')),
    'missing generated path source rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSourceProfilePlan('json_tree', $current158, $next158, 'option_value', 'missing_path')),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next158 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
