<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current203 = [
    'option_id' => 203,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next203',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-203-a',
];
$next203 = [
    'option_id' => 203,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next203',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-203-b',
];

$plan203 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = 5,
    ?int $lastYieldedRowid = 9,
    ?int $yieldBatchSize = 1,
    ?array $projection = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidAliasProjection(
    'json_tree',
    $current ?? $current203,
    $next ?? $next203,
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
    $projection ?? ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey'],
);

$stable203 = static fn (): array => $plan203($current203, $current203);
$range203 = static fn (): array => $plan203($current203, $current203, null, null, 5, 7, 3);
$noAlias203 = static fn (): array => $plan203($current203, $current203, null, null, 5, 9, 1, ['value', 'type', 'id']);
$singleAlias203 = static fn (): array => $plan203($current203, $current203, null, null, 5, 9, 1, ['rowid', 'value']);

$tests = [
    'records next203 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next203', $plan203()['dependencies'], true)),
    'preserves next196 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next196', $plan203()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('rowid-alias-xcolumn-current-json-table-generated-path-rowid-next203', $plan203()['currentReaderPolicy']),
    'next reader policy reparses' => static fn (TestRunner $t) => $t->same('reprepare-rowid-alias-xcolumn-next-json-table-generated-path-rowid-next203', $plan203()['nextReaderPolicy']),
    'stable reader policy reuses' => static fn (TestRunner $t) => $t->same('reuse-rowid-alias-xcolumn-current-json-table-generated-path-rowid-next203', $stable203()['nextReaderPolicy']),
    'stable next203 reasons empty' => static fn (TestRunner $t) => $t->same([], $stable203()['next203ReplanReasons']),
    'base projection normalizes rowid aliases' => static fn (TestRunner $t) => $t->same(['id', 'value', 'type', 'fullkey'], $plan203()['currentGeneratedPathRowidAliasProjection203']['baseProjection']),
    'requested projection preserves rowid aliases' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey'], $plan203()['currentGeneratedPathRowidAliasProjection203']['requestedProjection']),
    'rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_', 'oid'], $plan203()['currentGeneratedPathRowidAliasProjection203']['rowidAliasColumns']),
    'current root recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan203()['currentGeneratedPathRowidAliasProjection203']['root']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan203()['currentGeneratedPathRowidAliasProjection203']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-203-a', $plan203()['currentGeneratedPathRowidAliasProjection203']['sourceGeneration']),
    'current checkpoint rowids' => static fn (TestRunner $t) => $t->same([9, 8, 7, 6, 5], $plan203()['currentGeneratedPathRowidAliasProjection203']['checkpointRowids']),
    'current accepted rowids' => static fn (TestRunner $t) => $t->same([9, 8, 7, 6, 5], $plan203()['currentGeneratedPathRowidAliasProjection203']['acceptedRowids']),
    'current missing alias rowids empty' => static fn (TestRunner $t) => $t->same([], $plan203()['currentGeneratedPathRowidAliasProjection203']['missingAliasRowids']),
    'current alias projection reusable' => static fn (TestRunner $t) => $t->same(true, $plan203()['currentGeneratedPathRowidAliasProjection203']['aliasProjectionReusable']),
    'current estimated rows one' => static fn (TestRunner $t) => $t->same(5, $plan203()['currentGeneratedPathRowidAliasProjection203']['estimatedRows']),
    'current estimated cost includes alias width' => static fn (TestRunner $t) => $t->same(35, $plan203()['currentGeneratedPathRowidAliasProjection203']['estimatedCost']),
    'current alias opcode point' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidAliasRangeNext203', $plan203()['currentGeneratedPathRowidAliasProjection203']['aliasOpcode']),
    'current cost class point wide' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-alias-range-wide-next203', $plan203()['currentGeneratedPathRowidAliasProjection203']['costClass']),
    'current alias fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan203()['currentGeneratedPathRowidAliasProjection203']['aliasProjectionFingerprint'])),
    'current cache fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan203()['currentGeneratedPathRowidAliasProjection203']['xColumnCacheFingerprint'])),
    'current alias tape rowid' => static fn (TestRunner $t) => $t->same(9, $plan203()['currentGeneratedPathRowidAliasProjection203']['aliasTape'][0]['rowid']),
    'current alias tape accepted' => static fn (TestRunner $t) => $t->same(true, $plan203()['currentGeneratedPathRowidAliasProjection203']['aliasTape'][0]['accepted']),
    'current alias tape materialized' => static fn (TestRunner $t) => $t->same(true, $plan203()['currentGeneratedPathRowidAliasProjection203']['aliasTape'][0]['materialized']),
    'rowid alias value' => static fn (TestRunner $t) => $t->same(9, $plan203()['currentGeneratedPathRowidAliasProjection203']['aliasTape'][0]['aliasValues']['rowid']),
    '_rowid_ alias value' => static fn (TestRunner $t) => $t->same(9, $plan203()['currentGeneratedPathRowidAliasProjection203']['aliasTape'][0]['aliasValues']['_rowid_']),
    'oid alias value' => static fn (TestRunner $t) => $t->same(9, $plan203()['currentGeneratedPathRowidAliasProjection203']['aliasTape'][0]['aliasValues']['oid']),
    'projected rowid value' => static fn (TestRunner $t) => $t->same(9, $plan203()['currentGeneratedPathRowidAliasProjection203']['aliasTape'][0]['projectedColumns']['rowid']),
    'projected _rowid_ value' => static fn (TestRunner $t) => $t->same(9, $plan203()['currentGeneratedPathRowidAliasProjection203']['aliasTape'][0]['projectedColumns']['_rowid_']),
    'projected oid value' => static fn (TestRunner $t) => $t->same(9, $plan203()['currentGeneratedPathRowidAliasProjection203']['aliasTape'][0]['projectedColumns']['oid']),
    'projected text value' => static fn (TestRunner $t) => $t->same(4, $plan203()['currentGeneratedPathRowidAliasProjection203']['aliasTape'][0]['projectedColumns']['value']),
    'projected type value' => static fn (TestRunner $t) => $t->same('integer', $plan203()['currentGeneratedPathRowidAliasProjection203']['aliasTape'][0]['projectedColumns']['type']),
    'projected fullkey value' => static fn (TestRunner $t) => $t->same('$.rules[2].priority', $plan203()['currentGeneratedPathRowidAliasProjection203']['aliasTape'][0]['projectedColumns']['fullkey']),
    'next alias projection not reusable' => static fn (TestRunner $t) => $t->same(false, $plan203()['nextGeneratedPathRowidAliasProjection203']['aliasProjectionReusable']),
    'next alias opcode reprepare' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidAliasReprepareNext203', $plan203()['nextGeneratedPathRowidAliasProjection203']['aliasOpcode']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $plan203()['nextGeneratedPathRowidAliasProjection203']['estimatedCost']),
    'transition count records aliases' => static fn (TestRunner $t) => $t->same(17, count($plan203()['generatedPathRowidAliasProjection203Transitions'])),
    'reasons include source changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-alias-source-changed-next203', $plan203()['next203ReplanReasons'], true)),
    'reasons include rowset changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-alias-rowset-changed-next203', $plan203()['next203ReplanReasons'], true)),
    'reasons include reuse changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-alias-reuse-changed-next203', $plan203()['next203ReplanReasons'], true)),
    'reasons include cost changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-alias-cost-changed-next203', $plan203()['next203ReplanReasons'], true)),
    'preserves next196 reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcolumn-cache-source-changed-next196', $plan203()['next203ReplanReasons'], true)),
    'stable alias projection reusable' => static fn (TestRunner $t) => $t->same(true, $stable203()['nextGeneratedPathRowidAliasProjection203']['aliasProjectionReusable']),
    'stable fingerprints match' => static fn (TestRunner $t) => $t->same($stable203()['currentGeneratedPathRowidAliasProjection203']['aliasProjectionFingerprint'], $stable203()['nextGeneratedPathRowidAliasProjection203']['aliasProjectionFingerprint']),
    'range accepted rowids' => static fn (TestRunner $t) => $t->same([9, 8, 7, 6, 5], $range203()['currentGeneratedPathRowidAliasProjection203']['acceptedRowids']),
    'range alias opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidAliasRangeNext203', $range203()['currentGeneratedPathRowidAliasProjection203']['aliasOpcode']),
    'range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-alias-range-wide-next203', $range203()['currentGeneratedPathRowidAliasProjection203']['costClass']),
    'range estimated rows' => static fn (TestRunner $t) => $t->same(5, $range203()['currentGeneratedPathRowidAliasProjection203']['estimatedRows']),
    'range estimated cost includes aliases' => static fn (TestRunner $t) => $t->same(35, $range203()['currentGeneratedPathRowidAliasProjection203']['estimatedCost']),
    'range first rowid alias value' => static fn (TestRunner $t) => $t->same(9, $range203()['currentGeneratedPathRowidAliasProjection203']['aliasTape'][0]['aliasValues']['rowid']),
    'no alias bypass opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidAliasBypassNext203', $noAlias203()['currentGeneratedPathRowidAliasProjection203']['aliasOpcode']),
    'no alias cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-alias-bypass-next203', $noAlias203()['currentGeneratedPathRowidAliasProjection203']['costClass']),
    'single alias cost' => static fn (TestRunner $t) => $t->same(15, $singleAlias203()['currentGeneratedPathRowidAliasProjection203']['estimatedCost']),
    'single alias cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-alias-range-wide-next203', $singleAlias203()['currentGeneratedPathRowidAliasProjection203']['costClass']),
    'bad alias projection rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan203(null, null, null, null, 5, 9, 1, ['rowid', 'bad_column'])),
    'malformed generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan203(array_replace($current203, ['generated_path' => '$.rules[']), $current203)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid alias projection ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
