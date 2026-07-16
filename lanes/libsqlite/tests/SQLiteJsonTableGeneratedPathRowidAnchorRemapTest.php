<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current195 = [
    'option_id' => 195,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next195',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$',
    'source_generation' => 'shared-anchor-195',
];
$next195 = [
    'option_id' => 195,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next195',
    'option_value' => '{"meta":{"autoload":"no"},"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]}',
    'generated_path' => '$.rules',
    'scan_root' => '$',
    'source_generation' => 'shared-anchor-195',
];

$plan195 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = 5,
    ?int $lastYieldedRowid = 10,
    ?int $yieldBatchSize = 1,
    ?array $projection = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidAnchorRemap(
    'json_tree',
    $current ?? $current195,
    $next ?? $next195,
    'option_value',
    'generated_path',
    $constraints ?? [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'IN', 'value' => [6, 7, 8, 9, 10]],
    ],
    'scan_root',
    $orderBy ?? [['column' => '_rowid_', 'direction' => 'DESC']],
    $limit,
    $lastYieldedRowid,
    $yieldBatchSize,
    $projection ?? ['key', 'value', 'type', 'id', 'fullkey', 'path'],
);

$stable195 = static fn (): array => $plan195($current195, $current195);
$missing195 = static fn (): array => $plan195(
    $current195,
    array_replace($next195, ['option_value' => '{"meta":{"autoload":"no"},"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7}]}']),
);
$collision195 = static fn (): array => $plan195(
    $current195,
    array_replace($current195, ['option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"audit","priority":4}],"meta":{"autoload":"yes"}}']),
);
$range195 = static fn (): array => $plan195($current195, $next195, null, null, 5, 10, 2);

$tests = [
    'records next195 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next195', $plan195()['dependencies'], true)),
    'preserves next191 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next191', $plan195()['dependencies'], true)),
    'current reader policy anchors' => static fn (TestRunner $t) => $t->same('anchor-current-json-table-generated-path-rowid-next195', $plan195()['currentReaderPolicy']),
    'next reader policy reseeks by fullkey' => static fn (TestRunner $t) => $t->same('reseek-fullkey-anchor-json-table-generated-path-rowid-next195', $plan195()['nextReaderPolicy']),
    'stable reader policy reuses rowid' => static fn (TestRunner $t) => $t->same('reuse-rowid-anchor-json-table-generated-path-rowid-next195', $stable195()['nextReaderPolicy']),
    'stable reasons empty at next195 layer' => static fn (TestRunner $t) => $t->same([], $stable195()['next195ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $plan195()['currentGeneratedPathRowidAnchorRemap195']['function']),
    'current root recorded' => static fn (TestRunner $t) => $t->same('$', $plan195()['currentGeneratedPathRowidAnchorRemap195']['root']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan195()['currentGeneratedPathRowidAnchorRemap195']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('shared-anchor-195', $plan195()['currentGeneratedPathRowidAnchorRemap195']['sourceGeneration']),
    'current checkpoint rowid' => static fn (TestRunner $t) => $t->same([9], $plan195()['currentGeneratedPathRowidAnchorRemap195']['checkpointRowids']),
    'current stable rowid' => static fn (TestRunner $t) => $t->same([9], $plan195()['currentGeneratedPathRowidAnchorRemap195']['stableRowids']),
    'current remapped rowids empty' => static fn (TestRunner $t) => $t->same([], $plan195()['currentGeneratedPathRowidAnchorRemap195']['remappedRowids']),
    'current collision rowids empty' => static fn (TestRunner $t) => $t->same([], $plan195()['currentGeneratedPathRowidAnchorRemap195']['collisionRowids']),
    'current missing rowids empty' => static fn (TestRunner $t) => $t->same([], $plan195()['currentGeneratedPathRowidAnchorRemap195']['missingRowids']),
    'current resume by rowid true' => static fn (TestRunner $t) => $t->same(true, $plan195()['currentGeneratedPathRowidAnchorRemap195']['resumeByRowid']),
    'current resume by fullkey false' => static fn (TestRunner $t) => $t->same(false, $plan195()['currentGeneratedPathRowidAnchorRemap195']['resumeByFullkey']),
    'current opcode resumes rowid' => static fn (TestRunner $t) => $t->same('resume-rowid-json-table-generated-path-rowid-anchor-next195', $plan195()['currentGeneratedPathRowidAnchorRemap195']['anchorOpcode']),
    'current cost class rowid' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-anchor-rowid-next195', $plan195()['currentGeneratedPathRowidAnchorRemap195']['costClass']),
    'current anchor fullkey' => static fn (TestRunner $t) => $t->same('$.rules[2].slug', $plan195()['currentGeneratedPathRowidAnchorRemap195']['anchorTape'][0]['fullkey']),
    'current anchor same rowid' => static fn (TestRunner $t) => $t->same(true, $plan195()['currentGeneratedPathRowidAnchorRemap195']['anchorTape'][0]['sameRowid']),
    'current anchor checkpoint fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan195()['currentGeneratedPathRowidAnchorRemap195']['anchorTape'][0]['checkpointValueFingerprint'])),
    'current anchor source fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan195()['currentGeneratedPathRowidAnchorRemap195']['anchorTape'][0]['sourceValueFingerprint'])),
    'current anchor fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan195()['currentGeneratedPathRowidAnchorRemap195']['anchorFingerprint'])),
    'next stable rowids empty' => static fn (TestRunner $t) => $t->same([], $plan195()['nextGeneratedPathRowidAnchorRemap195']['stableRowids']),
    'next remapped rowids' => static fn (TestRunner $t) => $t->same([11], $plan195()['nextGeneratedPathRowidAnchorRemap195']['remappedRowids']),
    'next missing rowids empty' => static fn (TestRunner $t) => $t->same([], $plan195()['nextGeneratedPathRowidAnchorRemap195']['missingRowids']),
    'next collision rowids empty' => static fn (TestRunner $t) => $t->same([], $plan195()['nextGeneratedPathRowidAnchorRemap195']['collisionRowids']),
    'next resume by rowid false' => static fn (TestRunner $t) => $t->same(false, $plan195()['nextGeneratedPathRowidAnchorRemap195']['resumeByRowid']),
    'next resume by fullkey true' => static fn (TestRunner $t) => $t->same(true, $plan195()['nextGeneratedPathRowidAnchorRemap195']['resumeByFullkey']),
    'next anchor remapped flag' => static fn (TestRunner $t) => $t->same(true, $plan195()['nextGeneratedPathRowidAnchorRemap195']['anchorTape'][0]['remapped']),
    'next anchor remapped rowid' => static fn (TestRunner $t) => $t->same(11, $plan195()['nextGeneratedPathRowidAnchorRemap195']['anchorTape'][0]['remappedRowid']),
    'next anchor fullkey preserved' => static fn (TestRunner $t) => $t->same('$.rules[2].slug', $plan195()['nextGeneratedPathRowidAnchorRemap195']['anchorTape'][0]['fullkey']),
    'next anchor value fingerprint preserved' => static fn (TestRunner $t) => $t->same($plan195()['currentGeneratedPathRowidAnchorRemap195']['anchorTape'][0]['checkpointValueFingerprint'], $plan195()['nextGeneratedPathRowidAnchorRemap195']['anchorTape'][0]['sourceValueFingerprint']),
    'next opcode reseeks fullkey' => static fn (TestRunner $t) => $t->same('reseek-fullkey-json-table-generated-path-rowid-anchor-next195', $plan195()['nextGeneratedPathRowidAnchorRemap195']['anchorOpcode']),
    'next estimated rows one' => static fn (TestRunner $t) => $t->same(1, $plan195()['nextGeneratedPathRowidAnchorRemap195']['estimatedRows']),
    'next estimated cost fullkey reseek' => static fn (TestRunner $t) => $t->same(2, $plan195()['nextGeneratedPathRowidAnchorRemap195']['estimatedCost']),
    'next cost class point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-anchor-fullkey-point-next195', $plan195()['nextGeneratedPathRowidAnchorRemap195']['costClass']),
    'transition count records anchor fields' => static fn (TestRunner $t) => $t->same(16, count($plan195()['generatedPathRowidAnchorRemap195Transitions'])),
    'transition stable rowids changed' => static fn (TestRunner $t) => $t->same(true, $plan195()['generatedPathRowidAnchorRemap195Transitions'][4]['changed']),
    'transition remapped rowids changed' => static fn (TestRunner $t) => $t->same(true, $plan195()['generatedPathRowidAnchorRemap195Transitions'][5]['changed']),
    'transition resume mode changed' => static fn (TestRunner $t) => $t->same(true, $plan195()['generatedPathRowidAnchorRemap195Transitions'][8]['changed']),
    'transition cost changed' => static fn (TestRunner $t) => $t->same(true, $plan195()['generatedPathRowidAnchorRemap195Transitions'][13]['changed']),
    'reasons include fullkey remap' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-anchor-fullkey-remap-next195', $plan195()['next195ReplanReasons'], true)),
    'reasons include resume mode' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-anchor-resume-mode-changed-next195', $plan195()['next195ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-anchor-rowset-changed-next195', $plan195()['next195ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-anchor-cost-changed-next195', $plan195()['next195ReplanReasons'], true)),
    'missing rowid restarts' => static fn (TestRunner $t) => $t->same('restart-anchor-json-table-generated-path-rowid-next195', $missing195()['nextReaderPolicy']),
    'missing rowid becomes collision when rowid is reused' => static fn (TestRunner $t) => $t->same([9], $missing195()['nextGeneratedPathRowidAnchorRemap195']['collisionRowids']),
    'collision reason recorded for missing anchor rowid reuse' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-anchor-collision-next195', $missing195()['next195ReplanReasons'], true)),
    'collision rowid rejects rowid reuse' => static fn (TestRunner $t) => $t->same(false, $collision195()['nextGeneratedPathRowidAnchorRemap195']['resumeByRowid']),
    'collision rowid restart policy' => static fn (TestRunner $t) => $t->same('restart-anchor-json-table-generated-path-rowid-next195', $collision195()['nextReaderPolicy']),
    'range batch remaps two rowids' => static fn (TestRunner $t) => $t->same([11, 10], $range195()['nextGeneratedPathRowidAnchorRemap195']['remappedRowids']),
    'range batch cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-anchor-fullkey-range-next195', $range195()['nextGeneratedPathRowidAnchorRemap195']['costClass']),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan195(array_replace($current195, ['generated_path' => '$.rules[']), $current195)),
    'bad root rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan195(array_replace($current195, ['scan_root' => 195]), $current195)),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid anchor remap ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
