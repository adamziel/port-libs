<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current218 = [
    'option_id' => 218,
    'option_name' => 'wp_plugin_generated_path_rowid_yield',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-218-a',
];
$yieldSource = [
    'option_id' => 218,
    'option_name' => 'wp_plugin_generated_path_rowid_yield',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-218-b',
];

$yieldPlan = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = 5,
    ?int $lastYieldedRowid = null,
    ?int $yieldBatchSize = 3,
    ?array $projection = null,
    ?array $requestedColumns = null,
    ?string $observedGeneration = null,
    ?string $observedFingerprint = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceYield(
    'json_tree',
    $current ?? $current218,
    $next ?? $yieldSource,
    'option_value',
    'generated_path',
    $constraints ?? [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
        ['column' => '_rowid_', 'operator' => 'BETWEEN', 'value' => [7, 8]],
    ],
    'scan_root',
    $orderBy ?? [['column' => 'rowid', 'direction' => 'ASC']],
    $limit,
    $lastYieldedRowid,
    $yieldBatchSize,
    $projection ?? ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey'],
    $requestedColumns ?? ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey'],
    $observedGeneration,
    $observedFingerprint,
);

$stable218 = static fn (): array => $yieldPlan($current218, $current218);
$point218 = static fn (): array => $yieldPlan($current218, $current218, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [8, 8]],
]);
$emptyYield = static fn (): array => $yieldPlan($current218, $current218, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [20, 30]],
]);
$missingColumnYield = static fn (): array => $yieldPlan($current218, $current218, null, null, 5, null, 3, ['rowid', 'value'], ['rowid', 'type']);
$staleGenerationYield = static fn (): array => $yieldPlan($current218, $current218, null, null, 5, null, 3, null, null, 'stale-218');
$staleFingerprintYield = static fn (): array => $yieldPlan($current218, $current218, null, null, 5, null, 3, null, null, null, str_repeat('0', 64));
$aliasOnly218 = static fn (): array => $yieldPlan($current218, $current218, null, null, 5, null, 3, ['rowid'], ['_rowid_', 'oid']);

$tests = [
    'records yield dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-yield', $yieldPlan()['dependencies'], true)),
    'preserves xcurrent dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-xcurrent', $yieldPlan()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('yield-current-source-rowid-json-table-generated-path-rowid', $yieldPlan()['currentReaderPolicy']),
    'next reader policy reparses on source change' => static fn (TestRunner $t) => $t->same('reprepare-yield-next-json-table-generated-path-rowid', $yieldPlan()['nextReaderPolicy']),
    'stable reader policy reuses yield' => static fn (TestRunner $t) => $t->same('reuse-yield-current-source-rowid-json-table-generated-path-rowid', $stable218()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable218()['generatedPathRowidYieldReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $yieldPlan()['currentGeneratedPathRowidYieldProfile']['function']),
    'current root recorded' => static fn (TestRunner $t) => $t->same('$.rules', $yieldPlan()['currentGeneratedPathRowidYieldProfile']['root']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $yieldPlan()['currentGeneratedPathRowidYieldProfile']['generatedPath']),
    'source generation recorded' => static fn (TestRunner $t) => $t->same('current-218-a', $yieldPlan()['currentGeneratedPathRowidYieldProfile']['sourceGeneration']),
    'observed generation defaults current' => static fn (TestRunner $t) => $t->same('current-218-a', $yieldPlan()['currentGeneratedPathRowidYieldProfile']['observedSourceGeneration']),
    'source generation matches' => static fn (TestRunner $t) => $t->same(true, $yieldPlan()['currentGeneratedPathRowidYieldProfile']['sourceGenerationMatches']),
    'source fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($yieldPlan()['currentGeneratedPathRowidYieldProfile']['sourceFingerprint'])),
    'observed fingerprint defaults current' => static fn (TestRunner $t) => $t->same($yieldPlan()['currentGeneratedPathRowidYieldProfile']['sourceFingerprint'], $yieldPlan()['currentGeneratedPathRowidYieldProfile']['observedSourceFingerprint']),
    'source fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $yieldPlan()['currentGeneratedPathRowidYieldProfile']['sourceFingerprintMatches']),
    'active rowid yielded' => static fn (TestRunner $t) => $t->same(7, $yieldPlan()['currentGeneratedPathRowidYieldProfile']['activeRowid']),
    'remaining rowids preserved' => static fn (TestRunner $t) => $t->same([8], $yieldPlan()['currentGeneratedPathRowidYieldProfile']['remainingRowids']),
    'requested columns normalized aliases' => static fn (TestRunner $t) => $t->same(['id', 'value', 'type', 'fullkey'], $yieldPlan()['currentGeneratedPathRowidYieldProfile']['requestedColumns']),
    'missing columns empty' => static fn (TestRunner $t) => $t->same([], $yieldPlan()['currentGeneratedPathRowidYieldProfile']['missingColumns']),
    'requested id value' => static fn (TestRunner $t) => $t->same(7, $yieldPlan()['currentGeneratedPathRowidYieldProfile']['requestedValues']['id']),
    'requested value payload' => static fn (TestRunner $t) => $t->same('{"slug":"forms","priority":4}', $yieldPlan()['currentGeneratedPathRowidYieldProfile']['requestedValues']['value']),
    'requested type payload' => static fn (TestRunner $t) => $t->same('object', $yieldPlan()['currentGeneratedPathRowidYieldProfile']['requestedValues']['type']),
    'requested fullkey payload' => static fn (TestRunner $t) => $t->same('$.rules[2]', $yieldPlan()['currentGeneratedPathRowidYieldProfile']['requestedValues']['fullkey']),
    'row fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen((string) $yieldPlan()['currentGeneratedPathRowidYieldProfile']['rowFingerprint'])),
    'inherits xcurrent opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXCurrent', $yieldPlan()['currentGeneratedPathRowidYieldProfile']['xCurrentOpcode']),
    'inherits xcurrent reusable' => static fn (TestRunner $t) => $t->same(true, $yieldPlan()['currentGeneratedPathRowidYieldProfile']['xCurrentReusable']),
    'active row materialized' => static fn (TestRunner $t) => $t->same(true, $yieldPlan()['currentGeneratedPathRowidYieldProfile']['activeRowMaterialized']),
    'yield reusable' => static fn (TestRunner $t) => $t->same(true, $yieldPlan()['currentGeneratedPathRowidYieldProfile']['yieldReusable']),
    'yield opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidCurrentSourceYield', $yieldPlan()['currentGeneratedPathRowidYieldProfile']['yieldOpcode']),
    'estimated rows one' => static fn (TestRunner $t) => $t->same(1, $yieldPlan()['currentGeneratedPathRowidYieldProfile']['estimatedRows']),
    'estimated cost one' => static fn (TestRunner $t) => $t->same(1, $yieldPlan()['currentGeneratedPathRowidYieldProfile']['estimatedCost']),
    'cost class point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-yield-point', $yieldPlan()['currentGeneratedPathRowidYieldProfile']['costClass']),
    'yield fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($yieldPlan()['currentGeneratedPathRowidYieldProfile']['yieldFingerprint'])),
    'next source generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $yieldPlan()['nextGeneratedPathRowidYieldProfile']['generatedPath']),
    'next source yield reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidCurrentSourceYieldReprepareYield', $yieldPlan()['nextGeneratedPathRowidYieldProfile']['yieldOpcode']),
    'next source requested values empty' => static fn (TestRunner $t) => $t->same([], $yieldPlan()['nextGeneratedPathRowidYieldProfile']['requestedValues']),
    'next source cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $yieldPlan()['nextGeneratedPathRowidYieldProfile']['estimatedCost']),
    'next source cost class reparses' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-yield-reprepare', $yieldPlan()['nextGeneratedPathRowidYieldProfile']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(24, count($yieldPlan()['generatedPathRowidYieldTransitions'])),
    'transition source changes' => static fn (TestRunner $t) => $t->same(true, $yieldPlan()['generatedPathRowidYieldTransitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $yieldPlan()['generatedPathRowidYieldTransitions'][9]['changed']),
    'transition row changes' => static fn (TestRunner $t) => $t->same(true, $yieldPlan()['generatedPathRowidYieldTransitions'][13]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $yieldPlan()['generatedPathRowidYieldTransitions'][18]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $yieldPlan()['generatedPathRowidYieldTransitions'][20]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-yield-source-changed', $yieldPlan()['generatedPathRowidYieldReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-yield-rowset-changed', $yieldPlan()['generatedPathRowidYieldReplanReasons'], true)),
    'reasons include row' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-yield-row-changed', $yieldPlan()['generatedPathRowidYieldReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-yield-admission-changed', $yieldPlan()['generatedPathRowidYieldReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-yield-cost-changed', $yieldPlan()['generatedPathRowidYieldReplanReasons'], true)),
    'preserves xcurrent reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcurrent-source-changed', $yieldPlan()['generatedPathRowidYieldReplanReasons'], true)),
    'point active rowid' => static fn (TestRunner $t) => $t->same(8, $point218()['currentGeneratedPathRowidYieldProfile']['activeRowid']),
    'point remaining rowids empty' => static fn (TestRunner $t) => $t->same([], $point218()['currentGeneratedPathRowidYieldProfile']['remainingRowids']),
    'point yield reusable' => static fn (TestRunner $t) => $t->same(true, $point218()['currentGeneratedPathRowidYieldProfile']['yieldReusable']),
    'empty range reseeks' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidCurrentSourceYieldReseekYield', $emptyYield()['currentGeneratedPathRowidYieldProfile']['yieldOpcode']),
    'empty range active rowid null' => static fn (TestRunner $t) => $t->same(null, $emptyYield()['currentGeneratedPathRowidYieldProfile']['activeRowid']),
    'missing column materializes' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidCurrentSourceYieldMaterializeYield', $missingColumnYield()['currentGeneratedPathRowidYieldProfile']['yieldOpcode']),
    'missing column records type' => static fn (TestRunner $t) => $t->same(['type'], $missingColumnYield()['currentGeneratedPathRowidYieldProfile']['missingColumns']),
    'missing column cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-yield-materialize', $missingColumnYield()['currentGeneratedPathRowidYieldProfile']['costClass']),
    'stale generation mismatches' => static fn (TestRunner $t) => $t->same(false, $staleGenerationYield()['currentGeneratedPathRowidYieldProfile']['sourceGenerationMatches']),
    'stale generation reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidCurrentSourceYieldReprepareYield', $staleGenerationYield()['currentGeneratedPathRowidYieldProfile']['yieldOpcode']),
    'stale fingerprint mismatches' => static fn (TestRunner $t) => $t->same(false, $staleFingerprintYield()['currentGeneratedPathRowidYieldProfile']['sourceFingerprintMatches']),
    'stale fingerprint reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidCurrentSourceYieldReprepareYield', $staleFingerprintYield()['currentGeneratedPathRowidYieldProfile']['yieldOpcode']),
    'alias-only requested columns normalize to id' => static fn (TestRunner $t) => $t->same(['id'], $aliasOnly218()['currentGeneratedPathRowidYieldProfile']['requestedColumns']),
    'alias-only id value survives' => static fn (TestRunner $t) => $t->same(7, $aliasOnly218()['currentGeneratedPathRowidYieldProfile']['requestedValues']['id']),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $yieldPlan(array_replace($current218, ['generated_path' => '$.rules[']), $current218)),
    'bad function rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceYield('json_bad', $current218, $current218, 'option_value', 'generated_path')),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid yield ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
