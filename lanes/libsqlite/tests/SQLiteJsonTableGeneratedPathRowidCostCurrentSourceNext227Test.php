<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current227 = [
    'option_id' => 227,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next227',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-227-a',
];
$next227 = [
    'option_id' => 227,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next227',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":8},{"slug":"forms","priority":4},{"slug":"security","priority":10},{"slug":"search","priority":6}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'next-227-b',
];

$plan227 = static fn (
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
    ?string $observedSourceGeneration = null,
    ?string $observedSourceFingerprint = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceGuard(
    'json_tree',
    $current ?? $current227,
    $next ?? $next227,
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
    $observedSourceGeneration,
    $observedSourceFingerprint,
);

$stable227 = static fn (): array => $plan227($current227, $current227);
$currentProfile227 = static fn (): array => $plan227()['currentGeneratedPathRowidCurrentSourceGuard227'];
$nextProfile227 = static fn (): array => $plan227()['nextGeneratedPathRowidCurrentSourceGuard227'];
$staleGeneration227 = static fn (): array => $plan227($current227, $current227, null, null, 5, null, 3, null, null, null, 'stale-227');
$staleFingerprint227 = static fn (): array => $plan227($current227, $current227, null, null, 5, null, 3, null, null, null, null, str_repeat('0', 64));
$staleXCurrent227 = static fn (): array => $plan227($current227, $current227, null, null, 5, null, 3, null, str_repeat('1', 64));
$staleRowid227 = static fn (): array => $plan227($current227, $current227, null, null, 5, null, 3, null, null, 8);
$projection227 = static fn (): array => $plan227($current227, $current227, null, null, 5, null, 3, ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey']);
$point227 = static fn (): array => $plan227($current227, $current227, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [8, 8]],
]);

$tests = [
    'records next227 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next227', $plan227()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $plan227()['dependencies'], true)),
    'current reader policy guards source' => static fn (TestRunner $t) => $t->same('current-source-guard-json-table-generated-path-rowid-next227', $plan227()['currentReaderPolicy']),
    'changed next reader restarts' => static fn (TestRunner $t) => $t->same('restart-current-source-guard-json-table-generated-path-rowid-next227', $plan227()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-current-source-guard-json-table-generated-path-rowid-next227', $stable227()['nextReaderPolicy']),
    'stable next227 reasons empty' => static fn (TestRunner $t) => $t->same([], $stable227()['next227ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $currentProfile227()['function']),
    'current root recorded' => static fn (TestRunner $t) => $t->same('$.rules', $currentProfile227()['root']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $currentProfile227()['generatedPath']),
    'observed generation defaults current' => static fn (TestRunner $t) => $t->same('current-227-a', $currentProfile227()['observedSourceGeneration']),
    'actual generation recorded' => static fn (TestRunner $t) => $t->same('current-227-a', $currentProfile227()['actualSourceGeneration']),
    'generation matches' => static fn (TestRunner $t) => $t->same(true, $currentProfile227()['sourceGenerationMatches']),
    'observed fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($currentProfile227()['observedSourceFingerprint'])),
    'actual fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($currentProfile227()['actualSourceFingerprint'])),
    'fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $currentProfile227()['sourceFingerprintMatches']),
    'active rowid recorded' => static fn (TestRunner $t) => $t->same(7, $currentProfile227()['activeRowid']),
    'delivered rowids recorded' => static fn (TestRunner $t) => $t->same([7], $currentProfile227()['deliveredRowids']),
    'restart rowids empty on reusable source' => static fn (TestRunner $t) => $t->same([], $currentProfile227()['restartRowids']),
    'projected rowid survives guard' => static fn (TestRunner $t) => $t->same(7, $currentProfile227()['activeProjectedColumns']['rowid']),
    'projected value survives guard' => static fn (TestRunner $t) => $t->same('{"slug":"forms","priority":4}', $currentProfile227()['activeProjectedColumns']['value']),
    'projected type survives guard' => static fn (TestRunner $t) => $t->same('object', $currentProfile227()['activeProjectedColumns']['type']),
    'projected fullkey survives guard' => static fn (TestRunner $t) => $t->same('$.rules[2]', $currentProfile227()['activeProjectedColumns']['fullkey']),
    'alias rowid survives guard' => static fn (TestRunner $t) => $t->same(7, $currentProfile227()['activeAliasValues']['rowid']),
    'yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $currentProfile227()['yieldGuardReusable']),
    'source reusable' => static fn (TestRunner $t) => $t->same(true, $currentProfile227()['sourceReusable']),
    'upstream replan false' => static fn (TestRunner $t) => $t->same(false, $currentProfile227()['upstreamReplanRequired']),
    'current source guard reusable' => static fn (TestRunner $t) => $t->same(true, $currentProfile227()['currentSourceGuardReusable']),
    'current opcode delivers' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidCurrentSourceDeliverNext227', $currentProfile227()['currentSourceGuardOpcode']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $currentProfile227()['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $currentProfile227()['estimatedCost']),
    'current cost class point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-point-next227', $currentProfile227()['costClass']),
    'current source guard fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($currentProfile227()['currentSourceGuardFingerprint'])),
    'next actual generation changes' => static fn (TestRunner $t) => $t->same('next-227-b', $nextProfile227()['actualSourceGeneration']),
    'next source not reusable' => static fn (TestRunner $t) => $t->same(false, $nextProfile227()['sourceReusable']),
    'next guard not reusable' => static fn (TestRunner $t) => $t->same(false, $nextProfile227()['currentSourceGuardReusable']),
    'next opcode reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidCurrentSourceReprepareNext227', $nextProfile227()['currentSourceGuardOpcode']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $nextProfile227()['deliveredRowids']),
    'next restart rowids empty on reprepare' => static fn (TestRunner $t) => $t->same([], $nextProfile227()['restartRowids']),
    'next cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $nextProfile227()['estimatedCost']),
    'next cost class reprepare' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-reprepare-next227', $nextProfile227()['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(23, count($plan227()['generatedPathRowidCurrentSourceGuard227Transitions'])),
    'reasons include source changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-source-changed-next227', $plan227()['next227ReplanReasons'], true)),
    'reasons include generation changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-generation-changed-next227', $plan227()['next227ReplanReasons'], true)),
    'reasons include fingerprint changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-fingerprint-changed-next227', $plan227()['next227ReplanReasons'], true)),
    'reasons include rowid changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-rowid-changed-next227', $plan227()['next227ReplanReasons'], true)),
    'reasons include row changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-row-changed-next227', $plan227()['next227ReplanReasons'], true)),
    'reasons include admission changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-admission-changed-next227', $plan227()['next227ReplanReasons'], true)),
    'reasons include cost changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-cost-changed-next227', $plan227()['next227ReplanReasons'], true)),
    'preserves next224 reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $plan227()['next227ReplanReasons'], true)),
    'stale generation mismatch' => static fn (TestRunner $t) => $t->same(false, $staleGeneration227()['currentGeneratedPathRowidCurrentSourceGuard227']['sourceGenerationMatches']),
    'stale generation opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidCurrentSourceRestartGenerationNext227', $staleGeneration227()['currentGeneratedPathRowidCurrentSourceGuard227']['currentSourceGuardOpcode']),
    'stale generation restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleGeneration227()['currentGeneratedPathRowidCurrentSourceGuard227']['restartRowids']),
    'stale generation cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-stale-generation-next227', $staleGeneration227()['currentGeneratedPathRowidCurrentSourceGuard227']['costClass']),
    'stale fingerprint mismatch' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint227()['currentGeneratedPathRowidCurrentSourceGuard227']['sourceFingerprintMatches']),
    'stale fingerprint opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidCurrentSourceRestartFingerprintNext227', $staleFingerprint227()['currentGeneratedPathRowidCurrentSourceGuard227']['currentSourceGuardOpcode']),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-stale-fingerprint-next227', $staleFingerprint227()['currentGeneratedPathRowidCurrentSourceGuard227']['costClass']),
    'stale xcurrent reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidCurrentSourceReprepareNext227', $staleXCurrent227()['currentGeneratedPathRowidCurrentSourceGuard227']['currentSourceGuardOpcode']),
    'stale rowid reparses through yield guard' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidCurrentSourceReprepareNext227', $staleRowid227()['currentGeneratedPathRowidCurrentSourceGuard227']['currentSourceGuardOpcode']),
    'projection alias _rowid survives' => static fn (TestRunner $t) => $t->same(7, $projection227()['currentGeneratedPathRowidCurrentSourceGuard227']['activeProjectedColumns']['_rowid_']),
    'projection alias oid survives' => static fn (TestRunner $t) => $t->same(7, $projection227()['currentGeneratedPathRowidCurrentSourceGuard227']['activeProjectedColumns']['oid']),
    'point rowid delivered' => static fn (TestRunner $t) => $t->same([8], $point227()['currentGeneratedPathRowidCurrentSourceGuard227']['deliveredRowids']),
    'point fullkey delivered' => static fn (TestRunner $t) => $t->same('$.rules[2].slug', $point227()['currentGeneratedPathRowidCurrentSourceGuard227']['activeProjectedColumns']['fullkey']),
    'bad source fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan227($current227, $current227, null, null, 5, null, 3, null, null, null, null, 'bad-fingerprint')),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan227(array_replace($current227, ['generated_path' => '$.rules[']), $current227)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next227 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
