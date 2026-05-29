<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current190 = [
    'option_id' => 190,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next190',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-190-a',
];
$next190 = [
    'option_id' => 190,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next190',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-190-b',
];

$plan190 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = null,
    ?int $lastYieldedRowid = null,
    ?array $projection = null,
    ?int $yieldedRowid = 6,
    ?string $observedSourceGeneration = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidXColumnYieldPlan(
    'json_tree',
    $current ?? $current190,
    $next ?? $next190,
    'option_value',
    'generated_path',
    $constraints ?? [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'IN', 'value' => [5, 6, 42]],
    ],
    'scan_root',
    $orderBy ?? [['column' => '_rowid_', 'direction' => 'DESC']],
    $limit,
    $lastYieldedRowid,
    $projection ?? ['id', 'fullkey', 'atom', 'value', 'type'],
    $yieldedRowid,
    $observedSourceGeneration,
);

$yield190 = static fn (): array => $plan190();
$stable190 = static fn (): array => $plan190($current190, $current190);
$first190 = static fn (): array => $plan190($current190, $current190, null, null, null, null, null, null);
$final190 = static fn (): array => $plan190($current190, $current190, null, null, null, null, null, 5);
$missing190 = static fn (): array => $plan190($current190, $current190, null, null, null, null, null, 99);
$staleObserved190 = static fn (): array => $plan190($current190, $current190, null, null, null, null, null, 6, 'source_generation:stale-190');
$point190 = static fn (): array => $plan190(
    array_replace($current190, ['generated_path' => '$.rules[1]', 'source_generation' => 'same-190']),
    array_replace($current190, ['generated_path' => '$.rules[1]', 'source_generation' => 'same-190']),
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ],
    [['column' => 'id']],
    null,
    null,
    ['id', 'value'],
    6,
);

$tests = [
    'records next190 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next190', $yield190()['dependencies'], true)),
    'preserves next187 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next187', $yield190()['dependencies'], true)),
    'current reader emits xcolumn row' => static fn (TestRunner $t) => $t->same('emit-current-json-table-generated-path-rowid-xcolumn-next190', $yield190()['currentReaderPolicy']),
    'next reader reparses changed source' => static fn (TestRunner $t) => $t->same('reprepare-next-json-table-generated-path-rowid-xcolumn-next190', $yield190()['nextReaderPolicy']),
    'stable next reader continues' => static fn (TestRunner $t) => $t->same('continue-current-json-table-generated-path-rowid-xcolumn-next190', $stable190()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable190()['next190ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['function']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('source_generation:current-190-a', $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['sourceGeneration']),
    'current expected generation recorded' => static fn (TestRunner $t) => $t->same('source_generation:current-190-a', $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['expectedSourceGeneration']),
    'current observed generation recorded' => static fn (TestRunner $t) => $t->same('source_generation:current-190-a', $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['observedSourceGeneration']),
    'current source matches' => static fn (TestRunner $t) => $t->same(true, $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['sourceGenerationMatches']),
    'next source does not match observed current' => static fn (TestRunner $t) => $t->same(false, $yield190()['nextGeneratedPathRowidCurrentSourceYieldRow190']['sourceGenerationMatches']),
    'cache key is sha256' => static fn (TestRunner $t) => $t->same(64, strlen((string) $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['cacheKey'])),
    'cursor generation is sha256' => static fn (TestRunner $t) => $t->same(64, strlen((string) $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['cursorGeneration'])),
    'snapshot fingerprint is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['snapshotFingerprint'])),
    'final cost fingerprint is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['finalCostFingerprint'])),
    'guard fingerprint matches final cost' => static fn (TestRunner $t) => $t->same($yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['finalCostFingerprint'], $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['guardFinalCostFingerprint']),
    'snapshot matches final cost' => static fn (TestRunner $t) => $t->same(true, $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['snapshotMatchesFinalCost']),
    'projection is preserved' => static fn (TestRunner $t) => $t->same(['id', 'fullkey', 'atom', 'value', 'type'], $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['projection']),
    'selected rowids preserved' => static fn (TestRunner $t) => $t->same([6, 5], $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['selectedRowids']),
    'yielded rowid recorded' => static fn (TestRunner $t) => $t->same(6, $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['yieldedRowid']),
    'active rowid follows yielded rowid' => static fn (TestRunner $t) => $t->same(6, $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['activeRowid']),
    'materialized rowids recorded' => static fn (TestRunner $t) => $t->same([6, 5], $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['materializedRowids']),
    'active row materialized' => static fn (TestRunner $t) => $t->same(true, $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['activeRowMaterialized']),
    'active row fullkey' => static fn (TestRunner $t) => $t->same('$.rules[1].priority', $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['activeRow']['fullkey']),
    'active row atom' => static fn (TestRunner $t) => $t->same(7, $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['activeRow']['atom']),
    'active row value' => static fn (TestRunner $t) => $t->same(7, $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['activeRow']['value']),
    'active row type' => static fn (TestRunner $t) => $t->same('integer', $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['activeRow']['type']),
    'remaining rowids after yielded row' => static fn (TestRunner $t) => $t->same([5], $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['remainingRowids']),
    'remaining rows materialized' => static fn (TestRunner $t) => $t->same(1, count($yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['remainingRows'])),
    'remaining row fullkey' => static fn (TestRunner $t) => $t->same('$.rules[1].slug', $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['remainingRows'][0]['fullkey']),
    'remaining row value' => static fn (TestRunner $t) => $t->same('cache', $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['remainingRows'][0]['value']),
    'current yield accepted' => static fn (TestRunner $t) => $t->same(true, $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['yieldAccepted']),
    'current disposition emits' => static fn (TestRunner $t) => $t->same('emit-current-source-generated-path-rowid-xcolumn-next190', $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['yieldDisposition']),
    'current opcode emits xcolumn' => static fn (TestRunner $t) => $t->same('OP_JsonTableXColumnYieldCurrentSourceNext190', $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['xColumnOpcode']),
    'current estimated rows one' => static fn (TestRunner $t) => $t->same(1, $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['estimatedRows']),
    'current estimated cost preserves guard cost' => static fn (TestRunner $t) => $t->same(2, $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['estimatedCost']),
    'current cost class range' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xcolumn-yield-range-next190', $yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['costClass']),
    'yield row fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($yield190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['yieldRowFingerprint'])),
    'next yield rejected' => static fn (TestRunner $t) => $t->same(false, $yield190()['nextGeneratedPathRowidCurrentSourceYieldRow190']['yieldAccepted']),
    'next disposition stale' => static fn (TestRunner $t) => $t->same('abort-stale-source-generated-path-rowid-xcolumn-next190', $yield190()['nextGeneratedPathRowidCurrentSourceYieldRow190']['yieldDisposition']),
    'next opcode stale' => static fn (TestRunner $t) => $t->same('OP_JsonTableXColumnAbortStaleSourceNext190', $yield190()['nextGeneratedPathRowidCurrentSourceYieldRow190']['xColumnOpcode']),
    'next cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $yield190()['nextGeneratedPathRowidCurrentSourceYieldRow190']['estimatedCost']),
    'next cost class stale' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xcolumn-stale-source-next190', $yield190()['nextGeneratedPathRowidCurrentSourceYieldRow190']['costClass']),
    'first yield uses first selected rowid' => static fn (TestRunner $t) => $t->same(6, $first190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['activeRowid']),
    'first yield keeps both remaining rows' => static fn (TestRunner $t) => $t->same([6, 5], $first190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['remainingRowids']),
    'final yield accepted' => static fn (TestRunner $t) => $t->same(true, $final190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['yieldAccepted']),
    'final yield no remaining rows' => static fn (TestRunner $t) => $t->same([], $final190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['remainingRows']),
    'final cost class final' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xcolumn-yield-final-next190', $final190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['costClass']),
    'missing row rejected' => static fn (TestRunner $t) => $t->same(false, $missing190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['yieldAccepted']),
    'missing row disposition' => static fn (TestRunner $t) => $t->same('reseek-missing-materialized-generated-path-rowid-xcolumn-next190', $missing190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['yieldDisposition']),
    'missing row cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xcolumn-missing-row-next190', $missing190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['costClass']),
    'stale observed rejected' => static fn (TestRunner $t) => $t->same(false, $staleObserved190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['yieldAccepted']),
    'stale observed disposition' => static fn (TestRunner $t) => $t->same('abort-stale-source-generated-path-rowid-xcolumn-next190', $staleObserved190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['yieldDisposition']),
    'point projection preserved' => static fn (TestRunner $t) => $t->same(['id', 'value'], $point190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['projection']),
    'point active row value' => static fn (TestRunner $t) => $t->same(7, $point190()['currentGeneratedPathRowidCurrentSourceYieldRow190']['activeRow']['value']),
    'transition count records xcolumn fields' => static fn (TestRunner $t) => $t->same(22, count($yield190()['generatedPathRowidCurrentSourceYieldRow190Transitions'])),
    'transition source changes' => static fn (TestRunner $t) => $t->same(true, $yield190()['generatedPathRowidCurrentSourceYieldRow190Transitions'][0]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $yield190()['generatedPathRowidCurrentSourceYieldRow190Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $yield190()['generatedPathRowidCurrentSourceYieldRow190Transitions'][15]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $yield190()['generatedPathRowidCurrentSourceYieldRow190Transitions'][19]['changed']),
    'reasons include source changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcolumn-source-changed-next190', $yield190()['next190ReplanReasons'], true)),
    'reasons include fingerprint changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcolumn-fingerprint-changed-next190', $yield190()['next190ReplanReasons'], true)),
    'reasons include rowset changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcolumn-rowset-changed-next190', $yield190()['next190ReplanReasons'], true)),
    'reasons include admission changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcolumn-admission-changed-next190', $yield190()['next190ReplanReasons'], true)),
    'reasons include cost changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcolumn-cost-changed-next190', $yield190()['next190ReplanReasons'], true)),
    'bad projection rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan190(null, null, null, null, null, null, ['bad_column'])),
    'negative limit rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan190(null, null, null, null, -1)),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid xcolumn yield ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
