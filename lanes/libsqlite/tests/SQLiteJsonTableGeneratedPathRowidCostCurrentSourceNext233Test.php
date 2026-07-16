<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current233 = [
    'option_id' => 233,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next233',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-233-a',
];
$next233 = [
    'option_id' => 233,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next233',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":8},{"slug":"forms","priority":4}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'next-233-b',
];

$plan233 = static fn (
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
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidYieldAdvancePlan(
    'json_tree',
    $current ?? $current233,
    $next ?? $next233,
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
);

$stable233 = static fn (): array => $plan233($current233, $current233);
$point233 = static fn (): array => $plan233($current233, $current233, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [7, 7]],
]);
$wide233 = static fn (): array => $plan233($current233, $current233, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [6, 9]],
], null, 6);
$staleFingerprint233 = static fn (): array => $plan233($current233, $current233, null, null, 5, null, 3, null, str_repeat('0', 64), 7);
$staleRowid233 = static fn (): array => $plan233($current233, $current233, null, null, 5, null, 3, null, null, 8);
$empty233 = static fn (): array => $plan233($current233, $current233, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [90, 99]],
]);
$projection233 = static fn (): array => $plan233($current233, $current233, null, null, 5, null, 3, ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey']);
$currentProfile233 = static fn (): array => $plan233()['currentGeneratedPathRowidYieldNext'];
$nextProfile233 = static fn (): array => $plan233()['nextGeneratedPathRowidYieldNext'];

$tests = [
    'records next233 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-yield-next', $plan233()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $plan233()['dependencies'], true)),
    'current reader policy advances yield next' => static fn (TestRunner $t) => $t->same('yield-next-json-table-generated-path-rowid', $plan233()['currentReaderPolicy']),
    'changed next reader restarts yield next' => static fn (TestRunner $t) => $t->same('restart-yield-next-json-table-generated-path-rowid', $plan233()['nextReaderPolicy']),
    'stable next reader reuses yield next' => static fn (TestRunner $t) => $t->same('reuse-yield-next-json-table-generated-path-rowid', $stable233()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable233()['generatedPathRowidYieldNextReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $currentProfile233()['function']),
    'current root recorded' => static fn (TestRunner $t) => $t->same('$.rules', $currentProfile233()['root']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $currentProfile233()['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-233-a', $currentProfile233()['sourceGeneration']),
    'current yield guard fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($currentProfile233()['yieldGuardFingerprint'])),
    'current xcurrent fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($currentProfile233()['actualXCurrentFingerprint'])),
    'current observed active rowid' => static fn (TestRunner $t) => $t->same(7, $currentProfile233()['observedActiveRowid']),
    'current actual active rowid' => static fn (TestRunner $t) => $t->same(7, $currentProfile233()['actualActiveRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $currentProfile233()['deliveredRowids']),
    'current remaining rowids' => static fn (TestRunner $t) => $t->same([8], $currentProfile233()['remainingRowids']),
    'current resume rowids' => static fn (TestRunner $t) => $t->same([8], $currentProfile233()['resumeRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $currentProfile233()['restartRowids']),
    'current last delivered rowid' => static fn (TestRunner $t) => $t->same(7, $currentProfile233()['lastDeliveredRowid']),
    'current next rowid' => static fn (TestRunner $t) => $t->same(8, $currentProfile233()['nextRowid']),
    'current eof false' => static fn (TestRunner $t) => $t->same(false, $currentProfile233()['eofAfterYield']),
    'current source reusable' => static fn (TestRunner $t) => $t->same(true, $currentProfile233()['sourceReusable']),
    'current guard reusable' => static fn (TestRunner $t) => $t->same(true, $currentProfile233()['yieldGuardReusable']),
    'current upstream replan false' => static fn (TestRunner $t) => $t->same(false, $currentProfile233()['upstreamReplanRequired']),
    'current yield next reusable' => static fn (TestRunner $t) => $t->same(true, $currentProfile233()['yieldNextReusable']),
    'current opcode next' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidYieldNext', $currentProfile233()['yieldNextOpcode']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $currentProfile233()['estimatedRows']),
    'current estimated cost includes delivered row' => static fn (TestRunner $t) => $t->same(2, $currentProfile233()['estimatedCost']),
    'current cost class single' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-next-single', $currentProfile233()['costClass']),
    'current fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($currentProfile233()['yieldNextFingerprint'])),
    'next generation changed' => static fn (TestRunner $t) => $t->same('next-233-b', $nextProfile233()['sourceGeneration']),
    'next source not reusable' => static fn (TestRunner $t) => $t->same(false, $nextProfile233()['sourceReusable']),
    'next resume rowids empty' => static fn (TestRunner $t) => $t->same([], $nextProfile233()['resumeRowids']),
    'next opcode reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidYieldNextReprepare', $nextProfile233()['yieldNextOpcode']),
    'next cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $nextProfile233()['estimatedCost']),
    'next cost class reprepare' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-next-reprepare', $nextProfile233()['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(24, count($plan233()['generatedPathRowidYieldNextTransitions'])),
    'reasons include source changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-next-source-changed', $plan233()['generatedPathRowidYieldNextReplanReasons'], true)),
    'reasons include rowset changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-next-rowset-changed', $plan233()['generatedPathRowidYieldNextReplanReasons'], true)),
    'reasons include admission changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-next-admission-changed', $plan233()['generatedPathRowidYieldNextReplanReasons'], true)),
    'reasons include cost changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-next-cost-changed', $plan233()['generatedPathRowidYieldNextReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $plan233()['generatedPathRowidYieldNextReplanReasons'], true)),
    'point rowids eof after yield' => static fn (TestRunner $t) => $t->same(true, $point233()['currentGeneratedPathRowidYieldNext']['eofAfterYield']),
    'point next rowid null' => static fn (TestRunner $t) => $t->same(null, $point233()['currentGeneratedPathRowidYieldNext']['nextRowid']),
    'point opcode eof' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidYieldNextEof', $point233()['currentGeneratedPathRowidYieldNext']['yieldNextOpcode']),
    'point cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-next-eof', $point233()['currentGeneratedPathRowidYieldNext']['costClass']),
    'wide resume rowids' => static fn (TestRunner $t) => $t->same([7, 8, 9], $wide233()['currentGeneratedPathRowidYieldNext']['resumeRowids']),
    'wide cost class range' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-next-range', $wide233()['currentGeneratedPathRowidYieldNext']['costClass']),
    'projection aliases available upstream' => static fn (TestRunner $t) => $t->same(7, $projection233()['currentGeneratedPathRowidXCurrentYieldGuard224']['activeProjectedColumns']['_rowid_']),
    'stale fingerprint restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleFingerprint233()['currentGeneratedPathRowidYieldNext']['restartRowids']),
    'stale fingerprint opcode guard restart' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidYieldNextRestartGuard', $staleFingerprint233()['currentGeneratedPathRowidYieldNext']['yieldNextOpcode']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid233()['currentGeneratedPathRowidYieldNext']['restartRowids']),
    'stale rowid reusable false' => static fn (TestRunner $t) => $t->same(false, $staleRowid233()['currentGeneratedPathRowidYieldNext']['yieldNextReusable']),
    'stale rowid cost class guard restart' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-next-guard-restart', $staleRowid233()['currentGeneratedPathRowidYieldNext']['costClass']),
    'empty source reprepare' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidYieldNextReprepare', $empty233()['currentGeneratedPathRowidYieldNext']['yieldNextOpcode']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan233($current233, $current233, null, null, 5, null, 3, null, 'bad-fingerprint', 7)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan233(array_replace($current233, ['generated_path' => '$.rules[']), $current233)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next233 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
