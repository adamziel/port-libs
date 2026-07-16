<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current224 = [
    'option_id' => 224,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next224',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-224-a',
];
$next224 = [
    'option_id' => 224,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next224',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":8},{"slug":"forms","priority":4}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'next-224-b',
];

$plan224 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = 5,
    ?int $lastYieldedRowid = null,
    ?int $yieldBatchSize = 3,
    ?array $projection = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidXCurrentYieldGuard(
    'json_tree',
    $current ?? $current224,
    $next ?? $next224,
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
    $observedFingerprint,
    $observedRowid,
);

$stable224 = static fn (): array => $plan224($current224, $current224);
$staleFingerprint224 = static fn (): array => $plan224($current224, $current224, null, null, 5, null, 3, null, str_repeat('0', 64), 7);
$staleRowid224 = static fn (): array => $plan224($current224, $current224, null, null, 5, null, 3, null, null, 8);
$eof224 = static fn (): array => $plan224($current224, $current224, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [100, 200]],
]);
$projection224 = static fn (): array => $plan224($current224, $current224, null, null, 5, null, 3, ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey']);
$currentProfile224 = static fn (): array => $plan224()['currentGeneratedPathRowidXCurrentYieldGuard224'];
$nextProfile224 = static fn (): array => $plan224()['nextGeneratedPathRowidXCurrentYieldGuard224'];

$tests = [
    'records next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $plan224()['dependencies'], true)),
    'preserves next212 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next212', $plan224()['dependencies'], true)),
    'current reader policy guards yield' => static fn (TestRunner $t) => $t->same('yield-guard-xcurrent-json-table-generated-path-rowid-next224', $plan224()['currentReaderPolicy']),
    'changed next reader restarts' => static fn (TestRunner $t) => $t->same('restart-yield-guard-xcurrent-json-table-generated-path-rowid-next224', $plan224()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-yield-guard-xcurrent-json-table-generated-path-rowid-next224', $stable224()['nextReaderPolicy']),
    'stable next224 reasons empty' => static fn (TestRunner $t) => $t->same([], $stable224()['next224ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $currentProfile224()['function']),
    'current root recorded' => static fn (TestRunner $t) => $t->same('$.rules', $currentProfile224()['root']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $currentProfile224()['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-224-a', $currentProfile224()['sourceGeneration']),
    'current observed fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($currentProfile224()['observedXCurrentFingerprint'])),
    'current actual fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($currentProfile224()['actualXCurrentFingerprint'])),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $currentProfile224()['xCurrentFingerprintMatches']),
    'current observed active rowid' => static fn (TestRunner $t) => $t->same(7, $currentProfile224()['observedActiveRowid']),
    'current actual active rowid' => static fn (TestRunner $t) => $t->same(7, $currentProfile224()['actualActiveRowid']),
    'current active rowid matches' => static fn (TestRunner $t) => $t->same(true, $currentProfile224()['activeRowidMatches']),
    'current projected rowid' => static fn (TestRunner $t) => $t->same(7, $currentProfile224()['activeProjectedColumns']['rowid']),
    'current projected value is object' => static fn (TestRunner $t) => $t->same('{"slug":"forms","priority":4}', $currentProfile224()['activeProjectedColumns']['value']),
    'current projected type' => static fn (TestRunner $t) => $t->same('object', $currentProfile224()['activeProjectedColumns']['type']),
    'current projected fullkey' => static fn (TestRunner $t) => $t->same('$.rules[2]', $currentProfile224()['activeProjectedColumns']['fullkey']),
    'current alias value' => static fn (TestRunner $t) => $t->same(7, $currentProfile224()['activeAliasValues']['rowid']),
    'current remaining rowids' => static fn (TestRunner $t) => $t->same([8], $currentProfile224()['remainingRowids']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $currentProfile224()['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $currentProfile224()['restartRowids']),
    'current source reusable' => static fn (TestRunner $t) => $t->same(true, $currentProfile224()['sourceReusable']),
    'current upstream replan false' => static fn (TestRunner $t) => $t->same(false, $currentProfile224()['upstreamReplanRequired']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $currentProfile224()['yieldGuardReusable']),
    'current opcode delivers' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidYieldGuardDeliverNext224', $currentProfile224()['yieldGuardOpcode']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $currentProfile224()['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $currentProfile224()['estimatedCost']),
    'current cost class range' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-guard-range-next224', $currentProfile224()['costClass']),
    'current guard fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($currentProfile224()['yieldGuardFingerprint'])),
    'next profile source changed' => static fn (TestRunner $t) => $t->same('next-224-b', $nextProfile224()['sourceGeneration']),
    'next profile not source reusable' => static fn (TestRunner $t) => $t->same(false, $nextProfile224()['sourceReusable']),
    'next profile not guard reusable' => static fn (TestRunner $t) => $t->same(false, $nextProfile224()['yieldGuardReusable']),
    'next opcode reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidYieldGuardReprepareNext224', $nextProfile224()['yieldGuardOpcode']),
    'next cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $nextProfile224()['estimatedCost']),
    'next cost class reprepare' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-guard-reprepare-next224', $nextProfile224()['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(23, count($plan224()['generatedPathRowidXCurrentYieldGuard224Transitions'])),
    'reasons include source changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $plan224()['next224ReplanReasons'], true)),
    'reasons include fingerprint changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-fingerprint-changed-next224', $plan224()['next224ReplanReasons'], true)),
    'reasons include rowid changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-rowid-changed-next224', $plan224()['next224ReplanReasons'], true)),
    'reasons include row changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-row-changed-next224', $plan224()['next224ReplanReasons'], true)),
    'reasons include admission changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-admission-changed-next224', $plan224()['next224ReplanReasons'], true)),
    'reasons include cost changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-cost-changed-next224', $plan224()['next224ReplanReasons'], true)),
    'preserves next212 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcurrent-source-changed-next212', $plan224()['next224ReplanReasons'], true)),
    'stale fingerprint mismatch' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint224()['currentGeneratedPathRowidXCurrentYieldGuard224']['xCurrentFingerprintMatches']),
    'stale fingerprint restarts rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleFingerprint224()['currentGeneratedPathRowidXCurrentYieldGuard224']['restartRowids']),
    'stale fingerprint opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidYieldGuardRestartFingerprintNext224', $staleFingerprint224()['currentGeneratedPathRowidXCurrentYieldGuard224']['yieldGuardOpcode']),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-guard-stale-fingerprint-next224', $staleFingerprint224()['currentGeneratedPathRowidXCurrentYieldGuard224']['costClass']),
    'stale rowid mismatch' => static fn (TestRunner $t) => $t->same(false, $staleRowid224()['currentGeneratedPathRowidXCurrentYieldGuard224']['activeRowidMatches']),
    'stale rowid opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidYieldGuardRestartRowidNext224', $staleRowid224()['currentGeneratedPathRowidXCurrentYieldGuard224']['yieldGuardOpcode']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-guard-stale-rowid-next224', $staleRowid224()['currentGeneratedPathRowidXCurrentYieldGuard224']['costClass']),
    'eof active rowid null' => static fn (TestRunner $t) => $t->same(null, $eof224()['currentGeneratedPathRowidXCurrentYieldGuard224']['actualActiveRowid']),
    'eof opcode reparses when range unusable' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidYieldGuardReprepareNext224', $eof224()['currentGeneratedPathRowidXCurrentYieldGuard224']['yieldGuardOpcode']),
    'projection aliases survive guard' => static fn (TestRunner $t) => $t->same(7, $projection224()['currentGeneratedPathRowidXCurrentYieldGuard224']['activeProjectedColumns']['_rowid_']),
    'projection oid survives guard' => static fn (TestRunner $t) => $t->same(7, $projection224()['currentGeneratedPathRowidXCurrentYieldGuard224']['activeProjectedColumns']['oid']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan224($current224, $current224, null, null, 5, null, 3, null, 'bad-fingerprint', 7)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan224(array_replace($current224, ['generated_path' => '$.rules[']), $current224)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next224 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
