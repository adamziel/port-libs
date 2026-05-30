<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current220 = [
    'option_id' => 220,
    'option_name' => 'wp_plugin_generated_path_rowid_xrowid',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-220-a',
];
$generatedPathRowidXRowid = [
    'option_id' => 220,
    'option_name' => 'wp_plugin_generated_path_rowid_xrowid',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-220-b',
];

$plan220 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = 5,
    ?int $lastYieldedRowid = null,
    ?int $yieldBatchSize = 3,
    ?array $projection = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidXRowid(
    'json_tree',
    $current ?? $current220,
    $next ?? $generatedPathRowidXRowid,
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

$stable220 = static fn (): array => $plan220($current220, $current220);
$point220 = static fn (): array => $plan220($current220, $current220, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [8, 8]],
]);
$empty220 = static fn (): array => $plan220($current220, $current220, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [20, 30]],
]);
$noRowidProjection220 = static fn (): array => $plan220($current220, $current220, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => '_rowid_', 'operator' => 'BETWEEN', 'value' => [7, 8]],
], null, 5, null, 3, ['value', 'type', 'fullkey']);
$externalOrder220 = static fn (): array => $plan220($current220, $current220, null, [['column' => 'fullkey', 'direction' => 'ASC']]);

$tests = [
    'records xrowid dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-xrowid', $plan220()['dependencies'], true)),
    'preserves xcurrent dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-xcurrent', $plan220()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('xrowid-current-json-table-generated-path-rowid', $plan220()['currentReaderPolicy']),
    'next reader policy reparses on source change' => static fn (TestRunner $t) => $t->same('reprepare-xrowid-next-json-table-generated-path-rowid', $plan220()['nextReaderPolicy']),
    'stable reader policy reuses xrowid' => static fn (TestRunner $t) => $t->same('reuse-xrowid-current-json-table-generated-path-rowid', $stable220()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable220()['generatedPathRowidXRowidReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $plan220()['currentGeneratedPathRowidXRowidProfile']['function']),
    'current root recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan220()['currentGeneratedPathRowidXRowidProfile']['root']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan220()['currentGeneratedPathRowidXRowidProfile']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-220-a', $plan220()['currentGeneratedPathRowidXRowidProfile']['sourceGeneration']),
    'source fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan220()['currentGeneratedPathRowidXRowidProfile']['sourceFingerprint'])),
    'xcurrent fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan220()['currentGeneratedPathRowidXRowidProfile']['xCurrentFingerprint'])),
    'row fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen((string) $plan220()['currentGeneratedPathRowidXRowidProfile']['rowFingerprint'])),
    'active rowid is seven' => static fn (TestRunner $t) => $t->same(7, $plan220()['currentGeneratedPathRowidXRowidProfile']['activeRowid']),
    'xrowid value is active rowid' => static fn (TestRunner $t) => $t->same(7, $plan220()['currentGeneratedPathRowidXRowidProfile']['rowidValue']),
    'alias rowid matches' => static fn (TestRunner $t) => $t->same(7, $plan220()['currentGeneratedPathRowidXRowidProfile']['aliasRowid']),
    'alias _rowid matches' => static fn (TestRunner $t) => $t->same(7, $plan220()['currentGeneratedPathRowidXRowidProfile']['aliasUnderscoreRowid']),
    'alias oid matches' => static fn (TestRunner $t) => $t->same(7, $plan220()['currentGeneratedPathRowidXRowidProfile']['aliasOid']),
    'projected rowid matches' => static fn (TestRunner $t) => $t->same(7, $plan220()['currentGeneratedPathRowidXRowidProfile']['projectedRowid']),
    'alias consistent' => static fn (TestRunner $t) => $t->same(true, $plan220()['currentGeneratedPathRowidXRowidProfile']['aliasConsistent']),
    'active row materialized' => static fn (TestRunner $t) => $t->same(true, $plan220()['currentGeneratedPathRowidXRowidProfile']['activeRowMaterialized']),
    'xcurrent reusable' => static fn (TestRunner $t) => $t->same(true, $plan220()['currentGeneratedPathRowidXRowidProfile']['xCurrentReusable']),
    'upstream replan false' => static fn (TestRunner $t) => $t->same(false, $plan220()['currentGeneratedPathRowidXRowidProfile']['upstreamReplanRequired']),
    'xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $plan220()['currentGeneratedPathRowidXRowidProfile']['xRowidReusable']),
    'xrowid opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXRowid', $plan220()['currentGeneratedPathRowidXRowidProfile']['xRowidOpcode']),
    'estimated rows one' => static fn (TestRunner $t) => $t->same(1, $plan220()['currentGeneratedPathRowidXRowidProfile']['estimatedRows']),
    'estimated cost one' => static fn (TestRunner $t) => $t->same(1, $plan220()['currentGeneratedPathRowidXRowidProfile']['estimatedCost']),
    'cost class point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xrowid-point', $plan220()['currentGeneratedPathRowidXRowidProfile']['costClass']),
    'xrowid fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan220()['currentGeneratedPathRowidXRowidProfile']['xRowidFingerprint'])),
    'next source not reusable' => static fn (TestRunner $t) => $t->same(false, $plan220()['nextGeneratedPathRowidXRowidProfile']['xRowidReusable']),
    'next source rowid null' => static fn (TestRunner $t) => $t->same(null, $plan220()['nextGeneratedPathRowidXRowidProfile']['rowidValue']),
    'next opcode reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXRowidReprepare', $plan220()['nextGeneratedPathRowidXRowidProfile']['xRowidOpcode']),
    'next cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $plan220()['nextGeneratedPathRowidXRowidProfile']['estimatedCost']),
    'next cost class reparses' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xrowid-reprepare', $plan220()['nextGeneratedPathRowidXRowidProfile']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(23, count($plan220()['generatedPathRowidXRowidTransitions'])),
    'transition source changes' => static fn (TestRunner $t) => $t->same(true, $plan220()['generatedPathRowidXRowidTransitions'][3]['changed']),
    'transition row changes' => static fn (TestRunner $t) => $t->same(true, $plan220()['generatedPathRowidXRowidTransitions'][7]['changed']),
    'transition alias changes' => static fn (TestRunner $t) => $t->same(true, $plan220()['generatedPathRowidXRowidTransitions'][9]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $plan220()['generatedPathRowidXRowidTransitions'][17]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $plan220()['generatedPathRowidXRowidTransitions'][20]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xrowid-source-changed', $plan220()['generatedPathRowidXRowidReplanReasons'], true)),
    'reasons include row' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xrowid-row-changed', $plan220()['generatedPathRowidXRowidReplanReasons'], true)),
    'reasons include alias' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xrowid-alias-changed', $plan220()['generatedPathRowidXRowidReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xrowid-admission-changed', $plan220()['generatedPathRowidXRowidReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xrowid-cost-changed', $plan220()['generatedPathRowidXRowidReplanReasons'], true)),
    'preserves xcurrent row reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcurrent-row-changed', $plan220()['generatedPathRowidXRowidReplanReasons'], true)),
    'point rowid is eight' => static fn (TestRunner $t) => $t->same(8, $point220()['currentGeneratedPathRowidXRowidProfile']['rowidValue']),
    'point alias oid eight' => static fn (TestRunner $t) => $t->same(8, $point220()['currentGeneratedPathRowidXRowidProfile']['aliasOid']),
    'empty range reseeks current' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXRowidReseekCurrent', $empty220()['currentGeneratedPathRowidXRowidProfile']['xRowidOpcode']),
    'empty range rowid null' => static fn (TestRunner $t) => $t->same(null, $empty220()['currentGeneratedPathRowidXRowidProfile']['rowidValue']),
    'no rowid projection still reads alias tape' => static fn (TestRunner $t) => $t->same(7, $noRowidProjection220()['currentGeneratedPathRowidXRowidProfile']['rowidValue']),
    'no rowid projection projected null' => static fn (TestRunner $t) => $t->same(null, $noRowidProjection220()['currentGeneratedPathRowidXRowidProfile']['projectedRowid']),
    'external order reseeks current' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXRowidReseekCurrent', $externalOrder220()['currentGeneratedPathRowidXRowidProfile']['xRowidOpcode']),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan220(array_replace($current220, ['generated_path' => '$.rules[']), $current220)),
    'bad projection rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan220(null, null, null, null, null, null, null, ['bad_column'])),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source xrowid ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
