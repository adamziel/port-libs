<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current214 = [
    'option_id' => 214,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next214',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-214-a',
];
$next214 = [
    'option_id' => 214,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next214',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-214-b',
];

$plan214 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = 5,
    ?int $lastYieldedRowid = null,
    ?int $yieldBatchSize = 3,
    ?array $projection = null,
    ?array $columnReads = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidXColumnCache(
    'json_tree',
    $current ?? $current214,
    $next ?? $next214,
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
    $projection ?? ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey', 'parent'],
    $columnReads ?? ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey', 'parent'],
);

$stable214 = static fn (): array => $plan214($current214, $current214);
$point214 = static fn (): array => $plan214($current214, $current214, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [8, 8]],
]);
$missingProjection214 = static fn (): array => $plan214($current214, $current214, null, null, 5, null, 3, ['rowid', 'value'], ['rowid', 'fullkey']);
$noAlias214 = static fn (): array => $plan214($current214, $current214, null, null, 5, null, 3, ['value', 'type', 'fullkey'], ['value', 'type', 'fullkey']);
$empty214 = static fn (): array => $plan214($current214, $current214, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [20, 30]],
]);
$externalOrder214 = static fn (): array => $plan214($current214, $current214, null, [['column' => 'fullkey', 'direction' => 'ASC']]);

$tests = [
    'records next214 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next214', $plan214()['dependencies'], true)),
    'preserves next212 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next212', $plan214()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('xcolumn-current-json-table-generated-path-rowid-next214', $plan214()['currentReaderPolicy']),
    'next reader policy reparses' => static fn (TestRunner $t) => $t->same('reprepare-xcolumn-next-json-table-generated-path-rowid-next214', $plan214()['nextReaderPolicy']),
    'stable reader policy reuses' => static fn (TestRunner $t) => $t->same('reuse-xcolumn-current-json-table-generated-path-rowid-next214', $stable214()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable214()['next214ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['function']),
    'current root recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['root']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-214-a', $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['sourceGeneration']),
    'active rowid recorded' => static fn (TestRunner $t) => $t->same(7, $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['activeRowid']),
    'xcurrent fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['xCurrentFingerprint'])),
    'requested columns recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey', 'parent'], $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['requestedColumns']),
    'read count' => static fn (TestRunner $t) => $t->same(7, $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['readCount']),
    'cache hit count' => static fn (TestRunner $t) => $t->same(7, $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['cacheHitCount']),
    'alias read count' => static fn (TestRunner $t) => $t->same(3, $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['aliasReadCount']),
    'null read count' => static fn (TestRunner $t) => $t->same(0, $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['nullReadCount']),
    'xcolumn reusable' => static fn (TestRunner $t) => $t->same(true, $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['xColumnReusable']),
    'xcolumn opcode cache' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXColumnCacheNext214', $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['xColumnOpcode']),
    'estimated rows one' => static fn (TestRunner $t) => $t->same(1, $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['estimatedRows']),
    'estimated cost read count' => static fn (TestRunner $t) => $t->same(7, $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['estimatedCost']),
    'cost class alias cache' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xcolumn-alias-cache-next214', $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['costClass']),
    'xcolumn fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['xColumnFingerprint'])),
    'read fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['readFingerprint'])),
    'rowid read normalized' => static fn (TestRunner $t) => $t->same('rowid', $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['columnReads'][0]['normalizedColumn']),
    'rowid read ordinal' => static fn (TestRunner $t) => $t->same(-1, $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['columnReads'][0]['ordinal']),
    'rowid read value' => static fn (TestRunner $t) => $t->same(7, $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['columnReads'][0]['value']),
    'rowid value type' => static fn (TestRunner $t) => $t->same('integer', $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['columnReads'][0]['valueType']),
    'rowid cache source' => static fn (TestRunner $t) => $t->same('rowid-alias-cache-next214', $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['columnReads'][0]['source']),
    '_rowid alias read' => static fn (TestRunner $t) => $t->same(true, $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['columnReads'][1]['aliasRead']),
    'oid read value' => static fn (TestRunner $t) => $t->same(7, $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['columnReads'][2]['value']),
    'value read ordinal' => static fn (TestRunner $t) => $t->same(1, $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['columnReads'][3]['ordinal']),
    'value read text' => static fn (TestRunner $t) => $t->same('{"slug":"forms","priority":4}', $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['columnReads'][3]['value']),
    'value read source' => static fn (TestRunner $t) => $t->same('xcurrent-projection-cache-next214', $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['columnReads'][3]['source']),
    'type read object' => static fn (TestRunner $t) => $t->same('object', $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['columnReads'][4]['value']),
    'fullkey read path' => static fn (TestRunner $t) => $t->same('$.rules[2]', $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['columnReads'][5]['value']),
    'parent read integer' => static fn (TestRunner $t) => $t->same('integer', $plan214()['currentGeneratedPathRowidCurrentSourceXColumn214']['columnReads'][6]['valueType']),
    'next xcolumn opcode reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXColumnReprepareNext214', $plan214()['nextGeneratedPathRowidCurrentSourceXColumn214']['xColumnOpcode']),
    'next xcolumn not reusable' => static fn (TestRunner $t) => $t->same(false, $plan214()['nextGeneratedPathRowidCurrentSourceXColumn214']['xColumnReusable']),
    'next cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $plan214()['nextGeneratedPathRowidCurrentSourceXColumn214']['estimatedCost']),
    'next cost class reparses' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xcolumn-reprepare-next214', $plan214()['nextGeneratedPathRowidCurrentSourceXColumn214']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(19, count($plan214()['generatedPathRowidCurrentSourceXColumn214Transitions'])),
    'transition source changes' => static fn (TestRunner $t) => $t->same(true, $plan214()['generatedPathRowidCurrentSourceXColumn214Transitions'][3]['changed']),
    'transition row changes' => static fn (TestRunner $t) => $t->same(true, $plan214()['generatedPathRowidCurrentSourceXColumn214Transitions'][4]['changed']),
    'transition read cache changes' => static fn (TestRunner $t) => $t->same(true, $plan214()['generatedPathRowidCurrentSourceXColumn214Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $plan214()['generatedPathRowidCurrentSourceXColumn214Transitions'][12]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $plan214()['generatedPathRowidCurrentSourceXColumn214Transitions'][15]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcolumn-source-changed-next214', $plan214()['next214ReplanReasons'], true)),
    'reasons include row' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcolumn-row-changed-next214', $plan214()['next214ReplanReasons'], true)),
    'reasons include read cache' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcolumn-read-cache-changed-next214', $plan214()['next214ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcolumn-admission-changed-next214', $plan214()['next214ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcolumn-cost-changed-next214', $plan214()['next214ReplanReasons'], true)),
    'preserves next212 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcurrent-source-changed-next212', $plan214()['next214ReplanReasons'], true)),
    'point rowid active' => static fn (TestRunner $t) => $t->same(8, $point214()['currentGeneratedPathRowidCurrentSourceXColumn214']['activeRowid']),
    'point rowid read value' => static fn (TestRunner $t) => $t->same(8, $point214()['currentGeneratedPathRowidCurrentSourceXColumn214']['columnReads'][0]['value']),
    'point cost class alias' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xcolumn-alias-cache-next214', $point214()['currentGeneratedPathRowidCurrentSourceXColumn214']['costClass']),
    'missing projection cache misses' => static fn (TestRunner $t) => $t->same(1, $missingProjection214()['currentGeneratedPathRowidCurrentSourceXColumn214']['cacheHitCount']),
    'missing projection null reads' => static fn (TestRunner $t) => $t->same(1, $missingProjection214()['currentGeneratedPathRowidCurrentSourceXColumn214']['nullReadCount']),
    'missing projection opcode null' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXColumnNullNext214', $missingProjection214()['currentGeneratedPathRowidCurrentSourceXColumn214']['xColumnOpcode']),
    'missing projection cost class null' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xcolumn-null-next214', $missingProjection214()['currentGeneratedPathRowidCurrentSourceXColumn214']['costClass']),
    'no alias cost class cache' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xcolumn-cache-next214', $noAlias214()['currentGeneratedPathRowidCurrentSourceXColumn214']['costClass']),
    'no alias alias count zero' => static fn (TestRunner $t) => $t->same(0, $noAlias214()['currentGeneratedPathRowidCurrentSourceXColumn214']['aliasReadCount']),
    'empty range xcolumn null opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXColumnNullNext214', $empty214()['currentGeneratedPathRowidCurrentSourceXColumn214']['xColumnOpcode']),
    'external order xcolumn null opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXColumnNullNext214', $externalOrder214()['currentGeneratedPathRowidCurrentSourceXColumn214']['xColumnOpcode']),
    'bad column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan214(null, null, null, null, 5, null, 3, null, ['bad_column'])),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan214(array_replace($current214, ['generated_path' => '$.rules[']), $current214)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next214 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
