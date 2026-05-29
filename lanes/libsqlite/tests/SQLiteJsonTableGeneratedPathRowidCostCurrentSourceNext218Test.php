<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current218 = [
    'option_id' => 218,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next218',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-218-a',
];
$next218 = [
    'option_id' => 218,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next218',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-218-b',
];

$plan218 = static fn (
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
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext218(
    'json_tree',
    $current ?? $current218,
    $next ?? $next218,
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

$stable218 = static fn (): array => $plan218($current218, $current218);
$point218 = static fn (): array => $plan218($current218, $current218, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [8, 8]],
]);
$empty218 = static fn (): array => $plan218($current218, $current218, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [20, 30]],
]);
$missingColumn218 = static fn (): array => $plan218($current218, $current218, null, null, 5, null, 3, ['rowid', 'value'], ['rowid', 'type']);
$staleGeneration218 = static fn (): array => $plan218($current218, $current218, null, null, 5, null, 3, null, null, 'stale-218');
$staleFingerprint218 = static fn (): array => $plan218($current218, $current218, null, null, 5, null, 3, null, null, null, str_repeat('0', 64));
$aliasOnly218 = static fn (): array => $plan218($current218, $current218, null, null, 5, null, 3, ['rowid'], ['_rowid_', 'oid']);

$tests = [
    'records next218 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next218', $plan218()['dependencies'], true)),
    'preserves next212 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next212', $plan218()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('yield-current-source-rowid-json-table-generated-path-rowid-next218', $plan218()['currentReaderPolicy']),
    'next reader policy reparses on source change' => static fn (TestRunner $t) => $t->same('reprepare-yield-next-json-table-generated-path-rowid-next218', $plan218()['nextReaderPolicy']),
    'stable reader policy reuses yield' => static fn (TestRunner $t) => $t->same('reuse-yield-current-source-rowid-json-table-generated-path-rowid-next218', $stable218()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable218()['next218ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $plan218()['currentGeneratedPathRowidCurrentSourceYield218']['function']),
    'current root recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan218()['currentGeneratedPathRowidCurrentSourceYield218']['root']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan218()['currentGeneratedPathRowidCurrentSourceYield218']['generatedPath']),
    'source generation recorded' => static fn (TestRunner $t) => $t->same('current-218-a', $plan218()['currentGeneratedPathRowidCurrentSourceYield218']['sourceGeneration']),
    'observed generation defaults current' => static fn (TestRunner $t) => $t->same('current-218-a', $plan218()['currentGeneratedPathRowidCurrentSourceYield218']['observedSourceGeneration']),
    'source generation matches' => static fn (TestRunner $t) => $t->same(true, $plan218()['currentGeneratedPathRowidCurrentSourceYield218']['sourceGenerationMatches']),
    'source fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan218()['currentGeneratedPathRowidCurrentSourceYield218']['sourceFingerprint'])),
    'observed fingerprint defaults current' => static fn (TestRunner $t) => $t->same($plan218()['currentGeneratedPathRowidCurrentSourceYield218']['sourceFingerprint'], $plan218()['currentGeneratedPathRowidCurrentSourceYield218']['observedSourceFingerprint']),
    'source fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $plan218()['currentGeneratedPathRowidCurrentSourceYield218']['sourceFingerprintMatches']),
    'active rowid yielded' => static fn (TestRunner $t) => $t->same(7, $plan218()['currentGeneratedPathRowidCurrentSourceYield218']['activeRowid']),
    'remaining rowids preserved' => static fn (TestRunner $t) => $t->same([8], $plan218()['currentGeneratedPathRowidCurrentSourceYield218']['remainingRowids']),
    'requested columns normalized aliases' => static fn (TestRunner $t) => $t->same(['id', 'value', 'type', 'fullkey'], $plan218()['currentGeneratedPathRowidCurrentSourceYield218']['requestedColumns']),
    'missing columns empty' => static fn (TestRunner $t) => $t->same([], $plan218()['currentGeneratedPathRowidCurrentSourceYield218']['missingColumns']),
    'requested id value' => static fn (TestRunner $t) => $t->same(7, $plan218()['currentGeneratedPathRowidCurrentSourceYield218']['requestedValues']['id']),
    'requested value payload' => static fn (TestRunner $t) => $t->same('{"slug":"forms","priority":4}', $plan218()['currentGeneratedPathRowidCurrentSourceYield218']['requestedValues']['value']),
    'requested type payload' => static fn (TestRunner $t) => $t->same('object', $plan218()['currentGeneratedPathRowidCurrentSourceYield218']['requestedValues']['type']),
    'requested fullkey payload' => static fn (TestRunner $t) => $t->same('$.rules[2]', $plan218()['currentGeneratedPathRowidCurrentSourceYield218']['requestedValues']['fullkey']),
    'row fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen((string) $plan218()['currentGeneratedPathRowidCurrentSourceYield218']['rowFingerprint'])),
    'inherits xcurrent opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXCurrentNext212', $plan218()['currentGeneratedPathRowidCurrentSourceYield218']['xCurrentOpcode']),
    'inherits xcurrent reusable' => static fn (TestRunner $t) => $t->same(true, $plan218()['currentGeneratedPathRowidCurrentSourceYield218']['xCurrentReusable']),
    'active row materialized' => static fn (TestRunner $t) => $t->same(true, $plan218()['currentGeneratedPathRowidCurrentSourceYield218']['activeRowMaterialized']),
    'yield reusable' => static fn (TestRunner $t) => $t->same(true, $plan218()['currentGeneratedPathRowidCurrentSourceYield218']['yieldReusable']),
    'yield opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidCurrentSourceYieldNext218', $plan218()['currentGeneratedPathRowidCurrentSourceYield218']['yieldOpcode']),
    'estimated rows one' => static fn (TestRunner $t) => $t->same(1, $plan218()['currentGeneratedPathRowidCurrentSourceYield218']['estimatedRows']),
    'estimated cost one' => static fn (TestRunner $t) => $t->same(1, $plan218()['currentGeneratedPathRowidCurrentSourceYield218']['estimatedCost']),
    'cost class point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-yield-point-next218', $plan218()['currentGeneratedPathRowidCurrentSourceYield218']['costClass']),
    'yield fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan218()['currentGeneratedPathRowidCurrentSourceYield218']['yieldFingerprint'])),
    'next source generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $plan218()['nextGeneratedPathRowidCurrentSourceYield218']['generatedPath']),
    'next source yield reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidCurrentSourceYieldReprepareNext218', $plan218()['nextGeneratedPathRowidCurrentSourceYield218']['yieldOpcode']),
    'next source requested values empty' => static fn (TestRunner $t) => $t->same([], $plan218()['nextGeneratedPathRowidCurrentSourceYield218']['requestedValues']),
    'next source cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $plan218()['nextGeneratedPathRowidCurrentSourceYield218']['estimatedCost']),
    'next source cost class reparses' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-yield-reprepare-next218', $plan218()['nextGeneratedPathRowidCurrentSourceYield218']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(24, count($plan218()['generatedPathRowidCurrentSourceYield218Transitions'])),
    'transition source changes' => static fn (TestRunner $t) => $t->same(true, $plan218()['generatedPathRowidCurrentSourceYield218Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $plan218()['generatedPathRowidCurrentSourceYield218Transitions'][9]['changed']),
    'transition row changes' => static fn (TestRunner $t) => $t->same(true, $plan218()['generatedPathRowidCurrentSourceYield218Transitions'][13]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $plan218()['generatedPathRowidCurrentSourceYield218Transitions'][18]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $plan218()['generatedPathRowidCurrentSourceYield218Transitions'][20]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-yield-source-changed-next218', $plan218()['next218ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-yield-rowset-changed-next218', $plan218()['next218ReplanReasons'], true)),
    'reasons include row' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-yield-row-changed-next218', $plan218()['next218ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-yield-admission-changed-next218', $plan218()['next218ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-yield-cost-changed-next218', $plan218()['next218ReplanReasons'], true)),
    'preserves next212 reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcurrent-source-changed-next212', $plan218()['next218ReplanReasons'], true)),
    'point active rowid' => static fn (TestRunner $t) => $t->same(8, $point218()['currentGeneratedPathRowidCurrentSourceYield218']['activeRowid']),
    'point remaining rowids empty' => static fn (TestRunner $t) => $t->same([], $point218()['currentGeneratedPathRowidCurrentSourceYield218']['remainingRowids']),
    'point yield reusable' => static fn (TestRunner $t) => $t->same(true, $point218()['currentGeneratedPathRowidCurrentSourceYield218']['yieldReusable']),
    'empty range reseeks' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidCurrentSourceYieldReseekNext218', $empty218()['currentGeneratedPathRowidCurrentSourceYield218']['yieldOpcode']),
    'empty range active rowid null' => static fn (TestRunner $t) => $t->same(null, $empty218()['currentGeneratedPathRowidCurrentSourceYield218']['activeRowid']),
    'missing column materializes' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidCurrentSourceYieldMaterializeNext218', $missingColumn218()['currentGeneratedPathRowidCurrentSourceYield218']['yieldOpcode']),
    'missing column records type' => static fn (TestRunner $t) => $t->same(['type'], $missingColumn218()['currentGeneratedPathRowidCurrentSourceYield218']['missingColumns']),
    'missing column cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-yield-materialize-next218', $missingColumn218()['currentGeneratedPathRowidCurrentSourceYield218']['costClass']),
    'stale generation mismatches' => static fn (TestRunner $t) => $t->same(false, $staleGeneration218()['currentGeneratedPathRowidCurrentSourceYield218']['sourceGenerationMatches']),
    'stale generation reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidCurrentSourceYieldReprepareNext218', $staleGeneration218()['currentGeneratedPathRowidCurrentSourceYield218']['yieldOpcode']),
    'stale fingerprint mismatches' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint218()['currentGeneratedPathRowidCurrentSourceYield218']['sourceFingerprintMatches']),
    'stale fingerprint reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidCurrentSourceYieldReprepareNext218', $staleFingerprint218()['currentGeneratedPathRowidCurrentSourceYield218']['yieldOpcode']),
    'alias-only requested columns normalize to id' => static fn (TestRunner $t) => $t->same(['id'], $aliasOnly218()['currentGeneratedPathRowidCurrentSourceYield218']['requestedColumns']),
    'alias-only id value survives' => static fn (TestRunner $t) => $t->same(7, $aliasOnly218()['currentGeneratedPathRowidCurrentSourceYield218']['requestedValues']['id']),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan218(array_replace($current218, ['generated_path' => '$.rules[']), $current218)),
    'bad function rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext218('json_bad', $current218, $current218, 'option_value', 'generated_path')),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next218 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
