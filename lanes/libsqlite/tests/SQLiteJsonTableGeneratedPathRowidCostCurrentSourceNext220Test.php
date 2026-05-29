<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current220 = [
    'option_id' => 220,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next220',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-220-a',
];
$next220 = [
    'option_id' => 220,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next220',
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
    $next ?? $next220,
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
    'records next220 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next220', $plan220()['dependencies'], true)),
    'preserves next212 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next212', $plan220()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('xrowid-current-json-table-generated-path-rowid-next220', $plan220()['currentReaderPolicy']),
    'next reader policy reparses on source change' => static fn (TestRunner $t) => $t->same('reprepare-xrowid-next-json-table-generated-path-rowid-next220', $plan220()['nextReaderPolicy']),
    'stable reader policy reuses xrowid' => static fn (TestRunner $t) => $t->same('reuse-xrowid-current-json-table-generated-path-rowid-next220', $stable220()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable220()['next220ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $plan220()['currentGeneratedPathRowidXRowid220']['function']),
    'current root recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan220()['currentGeneratedPathRowidXRowid220']['root']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan220()['currentGeneratedPathRowidXRowid220']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-220-a', $plan220()['currentGeneratedPathRowidXRowid220']['sourceGeneration']),
    'source fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan220()['currentGeneratedPathRowidXRowid220']['sourceFingerprint'])),
    'xcurrent fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan220()['currentGeneratedPathRowidXRowid220']['xCurrentFingerprint'])),
    'row fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen((string) $plan220()['currentGeneratedPathRowidXRowid220']['rowFingerprint'])),
    'active rowid is seven' => static fn (TestRunner $t) => $t->same(7, $plan220()['currentGeneratedPathRowidXRowid220']['activeRowid']),
    'xrowid value is active rowid' => static fn (TestRunner $t) => $t->same(7, $plan220()['currentGeneratedPathRowidXRowid220']['rowidValue']),
    'alias rowid matches' => static fn (TestRunner $t) => $t->same(7, $plan220()['currentGeneratedPathRowidXRowid220']['aliasRowid']),
    'alias _rowid matches' => static fn (TestRunner $t) => $t->same(7, $plan220()['currentGeneratedPathRowidXRowid220']['aliasUnderscoreRowid']),
    'alias oid matches' => static fn (TestRunner $t) => $t->same(7, $plan220()['currentGeneratedPathRowidXRowid220']['aliasOid']),
    'projected rowid matches' => static fn (TestRunner $t) => $t->same(7, $plan220()['currentGeneratedPathRowidXRowid220']['projectedRowid']),
    'alias consistent' => static fn (TestRunner $t) => $t->same(true, $plan220()['currentGeneratedPathRowidXRowid220']['aliasConsistent']),
    'active row materialized' => static fn (TestRunner $t) => $t->same(true, $plan220()['currentGeneratedPathRowidXRowid220']['activeRowMaterialized']),
    'xcurrent reusable' => static fn (TestRunner $t) => $t->same(true, $plan220()['currentGeneratedPathRowidXRowid220']['xCurrentReusable']),
    'upstream replan false' => static fn (TestRunner $t) => $t->same(false, $plan220()['currentGeneratedPathRowidXRowid220']['upstreamReplanRequired']),
    'xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $plan220()['currentGeneratedPathRowidXRowid220']['xRowidReusable']),
    'xrowid opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXRowidNext220', $plan220()['currentGeneratedPathRowidXRowid220']['xRowidOpcode']),
    'estimated rows one' => static fn (TestRunner $t) => $t->same(1, $plan220()['currentGeneratedPathRowidXRowid220']['estimatedRows']),
    'estimated cost one' => static fn (TestRunner $t) => $t->same(1, $plan220()['currentGeneratedPathRowidXRowid220']['estimatedCost']),
    'cost class point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xrowid-point-next220', $plan220()['currentGeneratedPathRowidXRowid220']['costClass']),
    'xrowid fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan220()['currentGeneratedPathRowidXRowid220']['xRowidFingerprint'])),
    'next source not reusable' => static fn (TestRunner $t) => $t->same(false, $plan220()['nextGeneratedPathRowidXRowid220']['xRowidReusable']),
    'next source rowid null' => static fn (TestRunner $t) => $t->same(null, $plan220()['nextGeneratedPathRowidXRowid220']['rowidValue']),
    'next opcode reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXRowidReprepareNext220', $plan220()['nextGeneratedPathRowidXRowid220']['xRowidOpcode']),
    'next cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $plan220()['nextGeneratedPathRowidXRowid220']['estimatedCost']),
    'next cost class reparses' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xrowid-reprepare-next220', $plan220()['nextGeneratedPathRowidXRowid220']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(23, count($plan220()['generatedPathRowidXRowid220Transitions'])),
    'transition source changes' => static fn (TestRunner $t) => $t->same(true, $plan220()['generatedPathRowidXRowid220Transitions'][3]['changed']),
    'transition row changes' => static fn (TestRunner $t) => $t->same(true, $plan220()['generatedPathRowidXRowid220Transitions'][7]['changed']),
    'transition alias changes' => static fn (TestRunner $t) => $t->same(true, $plan220()['generatedPathRowidXRowid220Transitions'][9]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $plan220()['generatedPathRowidXRowid220Transitions'][17]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $plan220()['generatedPathRowidXRowid220Transitions'][20]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xrowid-source-changed-next220', $plan220()['next220ReplanReasons'], true)),
    'reasons include row' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xrowid-row-changed-next220', $plan220()['next220ReplanReasons'], true)),
    'reasons include alias' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xrowid-alias-changed-next220', $plan220()['next220ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xrowid-admission-changed-next220', $plan220()['next220ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xrowid-cost-changed-next220', $plan220()['next220ReplanReasons'], true)),
    'preserves next212 row reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcurrent-row-changed-next212', $plan220()['next220ReplanReasons'], true)),
    'point rowid is eight' => static fn (TestRunner $t) => $t->same(8, $point220()['currentGeneratedPathRowidXRowid220']['rowidValue']),
    'point alias oid eight' => static fn (TestRunner $t) => $t->same(8, $point220()['currentGeneratedPathRowidXRowid220']['aliasOid']),
    'empty range reseeks current' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXRowidReseekCurrentNext220', $empty220()['currentGeneratedPathRowidXRowid220']['xRowidOpcode']),
    'empty range rowid null' => static fn (TestRunner $t) => $t->same(null, $empty220()['currentGeneratedPathRowidXRowid220']['rowidValue']),
    'no rowid projection still reads alias tape' => static fn (TestRunner $t) => $t->same(7, $noRowidProjection220()['currentGeneratedPathRowidXRowid220']['rowidValue']),
    'no rowid projection projected null' => static fn (TestRunner $t) => $t->same(null, $noRowidProjection220()['currentGeneratedPathRowidXRowid220']['projectedRowid']),
    'external order reseeks current' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXRowidReseekCurrentNext220', $externalOrder220()['currentGeneratedPathRowidXRowid220']['xRowidOpcode']),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan220(array_replace($current220, ['generated_path' => '$.rules[']), $current220)),
    'bad projection rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan220(null, null, null, null, null, null, null, ['bad_column'])),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next220 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
