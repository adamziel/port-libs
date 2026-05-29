<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current196 = [
    'option_id' => 196,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next196',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-196-a',
];
$next196 = [
    'option_id' => 196,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next196',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-196-b',
];

$plan196 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = 5,
    ?int $lastYieldedRowid = 9,
    ?int $yieldBatchSize = 1,
    ?array $projection = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidXColumnCheckpoint(
    'json_tree',
    $current ?? $current196,
    $next ?? $next196,
    'option_value',
    'generated_path',
    $constraints ?? [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ],
    'scan_root',
    $orderBy ?? [['column' => '_rowid_', 'direction' => 'DESC']],
    $limit,
    $lastYieldedRowid,
    $yieldBatchSize,
    $projection ?? ['key', 'value', 'type', 'id', 'fullkey', 'path'],
);

$stable196 = static fn (): array => $plan196($current196, $current196);
$range196 = static fn (): array => $plan196($current196, $current196, null, null, 5, 7, 3);
$narrowProjection196 = static fn (): array => $plan196($current196, $current196, null, null, 5, 9, 1, ['value', 'atom', 'id']);
$generationOnly196 = static fn (): array => $plan196($current196, array_replace($current196, ['source_generation' => 'next-196-generation-only']));

$tests = [
    'records next196 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next196', $plan196()['dependencies'], true)),
    'preserves next191 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next191', $plan196()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('xcolumn-cache-current-json-table-generated-path-rowid-next196', $plan196()['currentReaderPolicy']),
    'next reader policy reparses' => static fn (TestRunner $t) => $t->same('reprepare-xcolumn-cache-next-json-table-generated-path-rowid-next196', $plan196()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-xcolumn-cache-current-json-table-generated-path-rowid-next196', $stable196()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable196()['next196ReplanReasons']),
    'current function' => static fn (TestRunner $t) => $t->same('json_tree', $plan196()['currentGeneratedPathRowidXColumnCache196']['function']),
    'current root' => static fn (TestRunner $t) => $t->same('$.rules', $plan196()['currentGeneratedPathRowidXColumnCache196']['root']),
    'current generated path' => static fn (TestRunner $t) => $t->same('$.rules', $plan196()['currentGeneratedPathRowidXColumnCache196']['generatedPath']),
    'current source generation' => static fn (TestRunner $t) => $t->same('current-196-a', $plan196()['currentGeneratedPathRowidXColumnCache196']['sourceGeneration']),
    'current resume token sha256' => static fn (TestRunner $t) => $t->same(64, strlen((string) $plan196()['currentGeneratedPathRowidXColumnCache196']['resumeToken'])),
    'current filter fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan196()['currentGeneratedPathRowidXColumnCache196']['filterFingerprint'])),
    'current projection' => static fn (TestRunner $t) => $t->same(['key', 'value', 'type', 'id', 'fullkey', 'path'], $plan196()['currentGeneratedPathRowidXColumnCache196']['projection']),
    'current checkpoint rowids' => static fn (TestRunner $t) => $t->same([9, 8, 7, 6, 5], $plan196()['currentGeneratedPathRowidXColumnCache196']['checkpointRowids']),
    'current accepted rowids' => static fn (TestRunner $t) => $t->same([9, 8, 7, 6, 5], $plan196()['currentGeneratedPathRowidXColumnCache196']['acceptedRowids']),
    'current missing rowids empty' => static fn (TestRunner $t) => $t->same([], $plan196()['currentGeneratedPathRowidXColumnCache196']['missingRowids']),
    'current cache reusable' => static fn (TestRunner $t) => $t->same(true, $plan196()['currentGeneratedPathRowidXColumnCache196']['cacheReusable']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(5, $plan196()['currentGeneratedPathRowidXColumnCache196']['estimatedRows']),
    'current estimated cost uses projection width' => static fn (TestRunner $t) => $t->same(30, $plan196()['currentGeneratedPathRowidXColumnCache196']['estimatedCost']),
    'current opcode range' => static fn (TestRunner $t) => $t->same('OP_JsonTableXColumnCacheRangeNext196', $plan196()['currentGeneratedPathRowidXColumnCache196']['xColumnOpcode']),
    'current cost range' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xcolumn-cache-range-next196', $plan196()['currentGeneratedPathRowidXColumnCache196']['costClass']),
    'current cache fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan196()['currentGeneratedPathRowidXColumnCache196']['xColumnCacheFingerprint'])),
    'current tape rowid' => static fn (TestRunner $t) => $t->same(9, $plan196()['currentGeneratedPathRowidXColumnCache196']['xColumnTape'][0]['rowid']),
    'current tape accepted' => static fn (TestRunner $t) => $t->same(true, $plan196()['currentGeneratedPathRowidXColumnCache196']['xColumnTape'][0]['accepted']),
    'current tape materialized' => static fn (TestRunner $t) => $t->same(true, $plan196()['currentGeneratedPathRowidXColumnCache196']['xColumnTape'][0]['materialized']),
    'current tape value' => static fn (TestRunner $t) => $t->same(4, $plan196()['currentGeneratedPathRowidXColumnCache196']['xColumnTape'][0]['columns']['value']),
    'current tape type' => static fn (TestRunner $t) => $t->same('integer', $plan196()['currentGeneratedPathRowidXColumnCache196']['xColumnTape'][0]['columns']['type']),
    'current tape fullkey' => static fn (TestRunner $t) => $t->same('$.rules[2].priority', $plan196()['currentGeneratedPathRowidXColumnCache196']['xColumnTape'][0]['columns']['fullkey']),
    'next generated path changes' => static fn (TestRunner $t) => $t->same('$.rules[0]', $plan196()['nextGeneratedPathRowidXColumnCache196']['generatedPath']),
    'next accepted rowids empty' => static fn (TestRunner $t) => $t->same([], $plan196()['nextGeneratedPathRowidXColumnCache196']['acceptedRowids']),
    'next cache not reusable' => static fn (TestRunner $t) => $t->same(false, $plan196()['nextGeneratedPathRowidXColumnCache196']['cacheReusable']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $plan196()['nextGeneratedPathRowidXColumnCache196']['estimatedCost']),
    'next opcode restart' => static fn (TestRunner $t) => $t->same('OP_JsonTableXColumnCacheRestartNext196', $plan196()['nextGeneratedPathRowidXColumnCache196']['xColumnOpcode']),
    'next cost restart' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xcolumn-cache-restart-next196', $plan196()['nextGeneratedPathRowidXColumnCache196']['costClass']),
    'next tape columns empty' => static fn (TestRunner $t) => $t->same([], $plan196()['nextGeneratedPathRowidXColumnCache196']['xColumnTape'][0]['columns']),
    'transition count' => static fn (TestRunner $t) => $t->same(16, count($plan196()['generatedPathRowidXColumnCache196Transitions'])),
    'reasons include source changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcolumn-cache-source-changed-next196', $plan196()['next196ReplanReasons'], true)),
    'reasons include rowset changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcolumn-cache-rowset-changed-next196', $plan196()['next196ReplanReasons'], true)),
    'reasons include reuse changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcolumn-cache-reuse-changed-next196', $plan196()['next196ReplanReasons'], true)),
    'reasons include cost changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcolumn-cache-cost-changed-next196', $plan196()['next196ReplanReasons'], true)),
    'preserves next191 reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xfilter-source-changed-next191', $plan196()['next196ReplanReasons'], true)),
    'stable next cache reusable' => static fn (TestRunner $t) => $t->same(true, $stable196()['nextGeneratedPathRowidXColumnCache196']['cacheReusable']),
    'stable fingerprints match' => static fn (TestRunner $t) => $t->same($stable196()['currentGeneratedPathRowidXColumnCache196']['xColumnCacheFingerprint'], $stable196()['nextGeneratedPathRowidXColumnCache196']['xColumnCacheFingerprint']),
    'range accepted rowids' => static fn (TestRunner $t) => $t->same([9, 8, 7, 6, 5], $range196()['currentGeneratedPathRowidXColumnCache196']['acceptedRowids']),
    'range opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableXColumnCacheRangeNext196', $range196()['currentGeneratedPathRowidXColumnCache196']['xColumnOpcode']),
    'range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xcolumn-cache-range-next196', $range196()['currentGeneratedPathRowidXColumnCache196']['costClass']),
    'range estimated cost' => static fn (TestRunner $t) => $t->same(30, $range196()['currentGeneratedPathRowidXColumnCache196']['estimatedCost']),
    'narrow projection normalized' => static fn (TestRunner $t) => $t->same(['value', 'atom', 'id'], $narrowProjection196()['currentGeneratedPathRowidXColumnCache196']['projection']),
    'narrow projection cost' => static fn (TestRunner $t) => $t->same(15, $narrowProjection196()['currentGeneratedPathRowidXColumnCache196']['estimatedCost']),
    'narrow projection atom preserves scalar integer' => static fn (TestRunner $t) => $t->same(4, $narrowProjection196()['currentGeneratedPathRowidXColumnCache196']['xColumnTape'][0]['columns']['atom']),
    'generation-only change rejects reuse' => static fn (TestRunner $t) => $t->same(false, $generationOnly196()['nextGeneratedPathRowidXColumnCache196']['cacheReusable']),
    'generation-only change preserves checkpoint rowids' => static fn (TestRunner $t) => $t->same([9, 8, 7, 6, 5], $generationOnly196()['nextGeneratedPathRowidXColumnCache196']['checkpointRowids']),
    'bad projection rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan196(null, null, null, null, 5, 9, 1, ['bad_column'])),
    'malformed generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan196(array_replace($current196, ['generated_path' => '$.rules[']), $current196)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid xcolumn checkpoint ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
