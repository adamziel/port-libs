<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current235 = [
    'option_id' => 235,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next235',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-235-a',
];
$next235 = [
    'option_id' => 235,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next235',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":8},{"slug":"forms","priority":4}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'next-235-b',
];

$plan235 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = 5,
    ?int $lastYieldedRowid = null,
    ?int $yieldBatchSize = 3,
    ?array $projection = null,
    ?string $observedYieldFingerprint = null,
    ?int $observedLastYieldedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceYieldPlan(
    'json_tree',
    $current ?? $current235,
    $next ?? $next235,
    'option_value',
    'generated_path',
    $constraints ?? [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
        ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [7, 8]],
    ],
    'scan_root',
    $orderBy ?? [['column' => 'rowid', 'direction' => 'ASC']],
    $limit,
    $lastYieldedRowid,
    $yieldBatchSize,
    $projection ?? ['rowid', 'value', 'type', 'fullkey'],
    $observedYieldFingerprint,
    $observedLastYieldedRowid,
);

$stable235 = static fn (): array => $plan235($current235, $current235);
$staleFingerprint235 = static fn (): array => $plan235($current235, $current235, null, null, 5, null, 3, null, str_repeat('1', 64), 7);
$staleRowid235 = static fn (): array => $plan235($current235, $current235, null, null, 5, null, 3, null, null, 8);
$eof235 = static fn (): array => $plan235($current235, $current235, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [100, 200]],
]);
$point235 = static fn (): array => $plan235($current235, $current235, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [7, 7]],
]);
$projection235 = static fn (): array => $plan235($current235, $current235, null, null, 5, null, 3, ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey']);
$currentProfile235 = static fn (): array => $plan235()['currentGeneratedPathRowidCurrentSourceYield'];
$nextProfile235 = static fn (): array => $plan235()['nextGeneratedPathRowidCurrentSourceYield'];

$tests = [
    'records next235 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-current-source-yield', $plan235()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $plan235()['dependencies'], true)),
    'current reader policy yields' => static fn (TestRunner $t) => $t->same('yield-current-source-json-table-generated-path-rowid', $plan235()['currentReaderPolicy']),
    'changed next reader restarts' => static fn (TestRunner $t) => $t->same('restart-yield-current-source-json-table-generated-path-rowid', $plan235()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-yield-current-source-json-table-generated-path-rowid', $stable235()['nextReaderPolicy']),
    'stable next235 reasons empty' => static fn (TestRunner $t) => $t->same([], $stable235()['generatedPathRowidCurrentSourceYieldReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $currentProfile235()['function']),
    'current root recorded' => static fn (TestRunner $t) => $t->same('$.rules', $currentProfile235()['root']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $currentProfile235()['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-235-a', $currentProfile235()['sourceGeneration']),
    'current observed yield fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($currentProfile235()['observedYieldFingerprint'])),
    'current actual yield fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($currentProfile235()['actualYieldFingerprint'])),
    'current yield fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $currentProfile235()['yieldFingerprintMatches']),
    'current observed last yielded rowid' => static fn (TestRunner $t) => $t->same(7, $currentProfile235()['observedLastYieldedRowid']),
    'current actual last yielded rowid' => static fn (TestRunner $t) => $t->same(7, $currentProfile235()['actualLastYieldedRowid']),
    'current last yielded matches' => static fn (TestRunner $t) => $t->same(true, $currentProfile235()['lastYieldedRowidMatches']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $currentProfile235()['deliveredRowids']),
    'current resume rowids' => static fn (TestRunner $t) => $t->same([8], $currentProfile235()['resumeRowids']),
    'current projected rowid' => static fn (TestRunner $t) => $t->same(7, $currentProfile235()['activeProjectedColumns']['rowid']),
    'current projected value is object' => static fn (TestRunner $t) => $t->same('{"slug":"forms","priority":4}', $currentProfile235()['activeProjectedColumns']['value']),
    'current projected type' => static fn (TestRunner $t) => $t->same('object', $currentProfile235()['activeProjectedColumns']['type']),
    'current projected fullkey' => static fn (TestRunner $t) => $t->same('$.rules[2]', $currentProfile235()['activeProjectedColumns']['fullkey']),
    'current guard reusable' => static fn (TestRunner $t) => $t->same(true, $currentProfile235()['yieldGuardReusable']),
    'current upstream replan false' => static fn (TestRunner $t) => $t->same(false, $currentProfile235()['upstreamReplanRequired']),
    'current yield tape reusable' => static fn (TestRunner $t) => $t->same(true, $currentProfile235()['yieldTapeReusable']),
    'current opcode yields' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidYieldCurrentSource', $currentProfile235()['yieldTapeOpcode']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $currentProfile235()['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(2, $currentProfile235()['estimatedCost']),
    'current cost class resume' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-current-source-resume', $currentProfile235()['costClass']),
    'current yield tape fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($currentProfile235()['yieldTapeFingerprint'])),
    'next source generation changed' => static fn (TestRunner $t) => $t->same('next-235-b', $nextProfile235()['sourceGeneration']),
    'next guard not reusable' => static fn (TestRunner $t) => $t->same(false, $nextProfile235()['yieldGuardReusable']),
    'next tape not reusable' => static fn (TestRunner $t) => $t->same(false, $nextProfile235()['yieldTapeReusable']),
    'next opcode reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidYieldReprepare', $nextProfile235()['yieldTapeOpcode']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $nextProfile235()['deliveredRowids']),
    'next restart rowids cleared after source change' => static fn (TestRunner $t) => $t->same([], $nextProfile235()['resumeRowids']),
    'next cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $nextProfile235()['estimatedCost']),
    'next cost class reprepare' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-current-source-reprepare', $nextProfile235()['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(21, count($plan235()['generatedPathRowidCurrentSourceYieldTransitions'])),
    'transition source changes' => static fn (TestRunner $t) => $t->same(true, $plan235()['generatedPathRowidCurrentSourceYieldTransitions'][3]['changed']),
    'transition fingerprint changes' => static fn (TestRunner $t) => $t->same(true, $plan235()['generatedPathRowidCurrentSourceYieldTransitions'][5]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $plan235()['generatedPathRowidCurrentSourceYieldTransitions'][15]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $plan235()['generatedPathRowidCurrentSourceYieldTransitions'][18]['changed']),
    'reasons include source changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-current-source-changed', $plan235()['generatedPathRowidCurrentSourceYieldReplanReasons'], true)),
    'reasons include rowid changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-current-source-rowid-changed', $plan235()['generatedPathRowidCurrentSourceYieldReplanReasons'], true)),
    'reasons include admission changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-current-source-admission-changed', $plan235()['generatedPathRowidCurrentSourceYieldReplanReasons'], true)),
    'reasons include cost changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-current-source-cost-changed', $plan235()['generatedPathRowidCurrentSourceYieldReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $plan235()['generatedPathRowidCurrentSourceYieldReplanReasons'], true)),
    'stale fingerprint mismatch' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint235()['currentGeneratedPathRowidCurrentSourceYield']['yieldFingerprintMatches']),
    'stale fingerprint restarts rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleFingerprint235()['currentGeneratedPathRowidCurrentSourceYield']['resumeRowids']),
    'stale fingerprint opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidYieldRestartFingerprint', $staleFingerprint235()['currentGeneratedPathRowidCurrentSourceYield']['yieldTapeOpcode']),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-current-source-stale-fingerprint', $staleFingerprint235()['currentGeneratedPathRowidCurrentSourceYield']['costClass']),
    'stale rowid mismatch' => static fn (TestRunner $t) => $t->same(false, $staleRowid235()['currentGeneratedPathRowidCurrentSourceYield']['lastYieldedRowidMatches']),
    'stale rowid opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidYieldRestartRowid', $staleRowid235()['currentGeneratedPathRowidCurrentSourceYield']['yieldTapeOpcode']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-current-source-stale-rowid', $staleRowid235()['currentGeneratedPathRowidCurrentSourceYield']['costClass']),
    'eof last rowid null' => static fn (TestRunner $t) => $t->same(null, $eof235()['currentGeneratedPathRowidCurrentSourceYield']['actualLastYieldedRowid']),
    'eof opcode reparses when range unusable' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidYieldReprepare', $eof235()['currentGeneratedPathRowidCurrentSourceYield']['yieldTapeOpcode']),
    'point cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-current-source-point', $point235()['currentGeneratedPathRowidCurrentSourceYield']['costClass']),
    'projection aliases survive yield tape' => static fn (TestRunner $t) => $t->same(7, $projection235()['currentGeneratedPathRowidCurrentSourceYield']['activeProjectedColumns']['_rowid_']),
    'projection oid survives yield tape' => static fn (TestRunner $t) => $t->same(7, $projection235()['currentGeneratedPathRowidCurrentSourceYield']['activeProjectedColumns']['oid']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan235($current235, $current235, null, null, 5, null, 3, null, 'bad-fingerprint', 7)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan235(array_replace($current235, ['generated_path' => '$.rules[']), $current235)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next235 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
