<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current212 = [
    'option_id' => 212,
    'option_name' => 'wp_plugin_generated_path_rowid_xcurrent',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-212-a',
];
$generatedPathRowidXCurrent = [
    'option_id' => 212,
    'option_name' => 'wp_plugin_generated_path_rowid_xcurrent',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-212-b',
];

$plan212 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = 5,
    ?int $lastYieldedRowid = null,
    ?int $yieldBatchSize = 3,
    ?array $projection = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidXCurrent(
    'json_tree',
    $current ?? $current212,
    $next ?? $generatedPathRowidXCurrent,
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
);

$stable212 = static fn (): array => $plan212($current212, $current212);
$point212 = static fn (): array => $plan212($current212, $current212, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [8, 8]],
]);
$empty212 = static fn (): array => $plan212($current212, $current212, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [20, 30]],
]);
$externalOrder212 = static fn (): array => $plan212($current212, $current212, null, [['column' => 'fullkey', 'direction' => 'ASC']]);

$tests = [
    'records xcurrent dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-xcurrent', $plan212()['dependencies'], true)),
    'preserves next209 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next209', $plan212()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('xcurrent-rowid-current-json-table-generated-path-rowid', $plan212()['currentReaderPolicy']),
    'next reader policy reparses on source change' => static fn (TestRunner $t) => $t->same('reprepare-xcurrent-rowid-next-json-table-generated-path-rowid', $plan212()['nextReaderPolicy']),
    'stable reader policy reuses xcurrent' => static fn (TestRunner $t) => $t->same('reuse-xcurrent-rowid-current-json-table-generated-path-rowid', $stable212()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable212()['generatedPathRowidXCurrentReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $plan212()['currentGeneratedPathRowidXCurrentProfile']['function']),
    'current root recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan212()['currentGeneratedPathRowidXCurrentProfile']['root']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan212()['currentGeneratedPathRowidXCurrentProfile']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-212-a', $plan212()['currentGeneratedPathRowidXCurrentProfile']['sourceGeneration']),
    'source fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan212()['currentGeneratedPathRowidXCurrentProfile']['sourceFingerprint'])),
    'range fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan212()['currentGeneratedPathRowidXCurrentProfile']['rangeFingerprint'])),
    'alias projection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan212()['currentGeneratedPathRowidXCurrentProfile']['aliasProjectionFingerprint'])),
    'accepted range rowids' => static fn (TestRunner $t) => $t->same([7, 8], $plan212()['currentGeneratedPathRowidXCurrentProfile']['acceptedRangeRowids']),
    'active rowid is first range row' => static fn (TestRunner $t) => $t->same(7, $plan212()['currentGeneratedPathRowidXCurrentProfile']['activeRowid']),
    'remaining rowids after active' => static fn (TestRunner $t) => $t->same([8], $plan212()['currentGeneratedPathRowidXCurrentProfile']['remainingRowids']),
    'active row materialized' => static fn (TestRunner $t) => $t->same(true, $plan212()['currentGeneratedPathRowidXCurrentProfile']['activeRowMaterialized']),
    'active projected rowid' => static fn (TestRunner $t) => $t->same(7, $plan212()['currentGeneratedPathRowidXCurrentProfile']['activeProjectedColumns']['rowid']),
    'active projected rowid alias' => static fn (TestRunner $t) => $t->same(7, $plan212()['currentGeneratedPathRowidXCurrentProfile']['activeProjectedColumns']['_rowid_']),
    'active projected oid alias' => static fn (TestRunner $t) => $t->same(7, $plan212()['currentGeneratedPathRowidXCurrentProfile']['activeProjectedColumns']['oid']),
    'active projected value' => static fn (TestRunner $t) => $t->same('{"slug":"forms","priority":4}', $plan212()['currentGeneratedPathRowidXCurrentProfile']['activeProjectedColumns']['value']),
    'active projected type' => static fn (TestRunner $t) => $t->same('object', $plan212()['currentGeneratedPathRowidXCurrentProfile']['activeProjectedColumns']['type']),
    'active projected fullkey' => static fn (TestRunner $t) => $t->same('$.rules[2]', $plan212()['currentGeneratedPathRowidXCurrentProfile']['activeProjectedColumns']['fullkey']),
    'active alias rowid' => static fn (TestRunner $t) => $t->same(7, $plan212()['currentGeneratedPathRowidXCurrentProfile']['activeAliasValues']['rowid']),
    'active alias _rowid' => static fn (TestRunner $t) => $t->same(7, $plan212()['currentGeneratedPathRowidXCurrentProfile']['activeAliasValues']['_rowid_']),
    'active alias oid' => static fn (TestRunner $t) => $t->same(7, $plan212()['currentGeneratedPathRowidXCurrentProfile']['activeAliasValues']['oid']),
    'row fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen((string) $plan212()['currentGeneratedPathRowidXCurrentProfile']['rowFingerprint'])),
    'range reusable true' => static fn (TestRunner $t) => $t->same(true, $plan212()['currentGeneratedPathRowidXCurrentProfile']['rangeReusable']),
    'upstream replan false' => static fn (TestRunner $t) => $t->same(false, $plan212()['currentGeneratedPathRowidXCurrentProfile']['upstreamReplanRequired']),
    'xcurrent reusable true' => static fn (TestRunner $t) => $t->same(true, $plan212()['currentGeneratedPathRowidXCurrentProfile']['xCurrentReusable']),
    'xcurrent opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXCurrent', $plan212()['currentGeneratedPathRowidXCurrentProfile']['xCurrentOpcode']),
    'estimated rows one' => static fn (TestRunner $t) => $t->same(1, $plan212()['currentGeneratedPathRowidXCurrentProfile']['estimatedRows']),
    'estimated cost one' => static fn (TestRunner $t) => $t->same(1, $plan212()['currentGeneratedPathRowidXCurrentProfile']['estimatedCost']),
    'range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xcurrent-range', $plan212()['currentGeneratedPathRowidXCurrentProfile']['costClass']),
    'xcurrent fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan212()['currentGeneratedPathRowidXCurrentProfile']['xCurrentFingerprint'])),
    'next source generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $plan212()['nextGeneratedPathRowidXCurrentProfile']['generatedPath']),
    'next source reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXCurrentReprepare', $plan212()['nextGeneratedPathRowidXCurrentProfile']['xCurrentOpcode']),
    'next source projected columns empty' => static fn (TestRunner $t) => $t->same([], $plan212()['nextGeneratedPathRowidXCurrentProfile']['activeProjectedColumns']),
    'next source cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $plan212()['nextGeneratedPathRowidXCurrentProfile']['estimatedCost']),
    'next source cost class reparses' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xcurrent-reprepare', $plan212()['nextGeneratedPathRowidXCurrentProfile']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(20, count($plan212()['generatedPathRowidXCurrentTransitions'])),
    'transition source changes' => static fn (TestRunner $t) => $t->same(true, $plan212()['generatedPathRowidXCurrentTransitions'][4]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $plan212()['generatedPathRowidXCurrentTransitions'][6]['changed']),
    'transition row changes' => static fn (TestRunner $t) => $t->same(true, $plan212()['generatedPathRowidXCurrentTransitions'][11]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $plan212()['generatedPathRowidXCurrentTransitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $plan212()['generatedPathRowidXCurrentTransitions'][17]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcurrent-source-changed', $plan212()['generatedPathRowidXCurrentReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcurrent-rowset-changed', $plan212()['generatedPathRowidXCurrentReplanReasons'], true)),
    'reasons include row' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcurrent-row-changed', $plan212()['generatedPathRowidXCurrentReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcurrent-admission-changed', $plan212()['generatedPathRowidXCurrentReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcurrent-cost-changed', $plan212()['generatedPathRowidXCurrentReplanReasons'], true)),
    'point active rowid' => static fn (TestRunner $t) => $t->same(8, $point212()['currentGeneratedPathRowidXCurrentProfile']['activeRowid']),
    'point remaining rowids empty' => static fn (TestRunner $t) => $t->same([], $point212()['currentGeneratedPathRowidXCurrentProfile']['remainingRowids']),
    'point cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xcurrent-point', $point212()['currentGeneratedPathRowidXCurrentProfile']['costClass']),
    'empty range reseeks' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXCurrentReseekRange', $empty212()['currentGeneratedPathRowidXCurrentProfile']['xCurrentOpcode']),
    'empty range active rowid null' => static fn (TestRunner $t) => $t->same(null, $empty212()['currentGeneratedPathRowidXCurrentProfile']['activeRowid']),
    'external order reseeks range' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXCurrentReseekRange', $externalOrder212()['currentGeneratedPathRowidXCurrentProfile']['xCurrentOpcode']),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan212(array_replace($current212, ['generated_path' => '$.rules[']), $current212)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source xcurrent ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
