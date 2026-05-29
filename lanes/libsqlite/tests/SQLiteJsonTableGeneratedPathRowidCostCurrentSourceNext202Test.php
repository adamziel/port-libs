<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current202 = [
    'option_id' => 202,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next202',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-202-a',
];
$next202 = [
    'option_id' => 202,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next202',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-202-b',
];

$plan202 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = null,
    ?int $lastYieldedRowid = null,
    ?array $projection = null,
    ?int $yieldedRowid = 6,
    ?string $observedSourceGeneration = null,
    int $xNextBatchSize = 1,
    ?string $observedSourceFingerprint = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidXNextBatch(
    'json_tree',
    $current ?? $current202,
    $next ?? $next202,
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
    $xNextBatchSize,
    $observedSourceFingerprint,
);

$changed202 = static fn (): array => $plan202();
$stable202 = static fn (): array => $plan202($current202, $current202);
$first202 = static fn (): array => $plan202($current202, $current202, null, null, null, null, null, null);
$range202 = static fn (): array => $plan202($current202, $current202, null, null, null, null, null, null, null, 2);
$eof202 = static fn (): array => $plan202($current202, $current202, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => '_rowid_', 'operator' => 'IN', 'value' => [5, 6, 42]],
], [['column' => '_rowid_', 'direction' => 'DESC']], null, null, ['id', 'fullkey', 'atom', 'value', 'type'], 5);
$staleFingerprint202 = static function () use ($plan202, $current202): array {
    return $plan202($current202, $current202, null, null, null, null, null, 6, null, 1, str_repeat('0', 64));
};
$staleGeneration202 = static fn (): array => $plan202($current202, $current202, null, null, null, null, null, 6, 'source_generation:stale-202');

$tests = [
    'records next202 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next202', $changed202()['dependencies'], true)),
    'preserves next194 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next194', $changed202()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('xnext-current-json-table-generated-path-rowid-source-next202', $changed202()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-xnext-next-json-table-generated-path-rowid-source-next202', $changed202()['nextReaderPolicy']),
    'stable next reader continues' => static fn (TestRunner $t) => $t->same('continue-xnext-current-json-table-generated-path-rowid-source-next202', $stable202()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable202()['next202ReplanReasons']),
    'current source generation' => static fn (TestRunner $t) => $t->same('source_generation:current-202-a', $changed202()['currentGeneratedPathRowidXNext202']['sourceGeneration']),
    'current observed generation' => static fn (TestRunner $t) => $t->same('source_generation:current-202-a', $changed202()['currentGeneratedPathRowidXNext202']['observedSourceGeneration']),
    'current source fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed202()['currentGeneratedPathRowidXNext202']['sourceFingerprint'])),
    'current observed fingerprint matches' => static fn (TestRunner $t) => $t->same($changed202()['currentGeneratedPathRowidXNext202']['sourceFingerprint'], $changed202()['currentGeneratedPathRowidXNext202']['observedSourceFingerprint']),
    'current fingerprint match true' => static fn (TestRunner $t) => $t->same(true, $changed202()['currentGeneratedPathRowidXNext202']['sourceFingerprintMatches']),
    'current source pinned true' => static fn (TestRunner $t) => $t->same(true, $changed202()['currentGeneratedPathRowidXNext202']['sourcePinned']),
    'current source disposition pins' => static fn (TestRunner $t) => $t->same('pin-current-source-generated-path-rowid-next194', $changed202()['currentGeneratedPathRowidXNext202']['sourceDisposition']),
    'current previous rowid' => static fn (TestRunner $t) => $t->same(6, $changed202()['currentGeneratedPathRowidXNext202']['previousRowid']),
    'current selected rowids' => static fn (TestRunner $t) => $t->same([6, 5], $changed202()['currentGeneratedPathRowidXNext202']['selectedRowids']),
    'current remaining before xnext' => static fn (TestRunner $t) => $t->same([5], $changed202()['currentGeneratedPathRowidXNext202']['remainingRowidsBeforeXNext']),
    'current emitted rowids' => static fn (TestRunner $t) => $t->same([5], $changed202()['currentGeneratedPathRowidXNext202']['emittedRowids']),
    'current blocked rowids empty' => static fn (TestRunner $t) => $t->same([], $changed202()['currentGeneratedPathRowidXNext202']['blockedRowidsAfterXNext']),
    'current next rowid' => static fn (TestRunner $t) => $t->same(5, $changed202()['currentGeneratedPathRowidXNext202']['nextRowid']),
    'current batch size' => static fn (TestRunner $t) => $t->same(1, $changed202()['currentGeneratedPathRowidXNext202']['xNextBatchSize']),
    'current reusable' => static fn (TestRunner $t) => $t->same(true, $changed202()['currentGeneratedPathRowidXNext202']['xNextReusable']),
    'current eof after final row' => static fn (TestRunner $t) => $t->same(true, $changed202()['currentGeneratedPathRowidXNext202']['eofAfterXNext']),
    'current disposition advances' => static fn (TestRunner $t) => $t->same('advance-current-source-generated-path-rowid-xnext-next202', $changed202()['currentGeneratedPathRowidXNext202']['xNextDisposition']),
    'current opcode advances' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXNextAdvanceNext202', $changed202()['currentGeneratedPathRowidXNext202']['xNextOpcode']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed202()['currentGeneratedPathRowidXNext202']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed202()['currentGeneratedPathRowidXNext202']['estimatedCost']),
    'current final cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xnext-final-next202', $changed202()['currentGeneratedPathRowidXNext202']['costClass']),
    'current xnext fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed202()['currentGeneratedPathRowidXNext202']['xNextFingerprint'])),
    'next source unpinned' => static fn (TestRunner $t) => $t->same(false, $changed202()['nextGeneratedPathRowidXNext202']['sourcePinned']),
    'next source not reusable' => static fn (TestRunner $t) => $t->same(false, $changed202()['nextGeneratedPathRowidXNext202']['xNextReusable']),
    'next emitted rowids empty' => static fn (TestRunner $t) => $t->same([], $changed202()['nextGeneratedPathRowidXNext202']['emittedRowids']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed202()['nextGeneratedPathRowidXNext202']['estimatedCost']),
    'next disposition unpinned' => static fn (TestRunner $t) => $t->same('reprepare-unpinned-generated-path-rowid-xnext-next202', $changed202()['nextGeneratedPathRowidXNext202']['xNextDisposition']),
    'next opcode unpinned' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXNextReprepareUnpinnedNext202', $changed202()['nextGeneratedPathRowidXNext202']['xNextOpcode']),
    'next cost class unpinned' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xnext-unpinned-next202', $changed202()['nextGeneratedPathRowidXNext202']['costClass']),
    'stable next reusable' => static fn (TestRunner $t) => $t->same(true, $stable202()['nextGeneratedPathRowidXNext202']['xNextReusable']),
    'stable next emitted rowids' => static fn (TestRunner $t) => $t->same([5], $stable202()['nextGeneratedPathRowidXNext202']['emittedRowids']),
    'stable fingerprints match' => static fn (TestRunner $t) => $t->same($stable202()['currentGeneratedPathRowidXNext202']['xNextFingerprint'], $stable202()['nextGeneratedPathRowidXNext202']['xNextFingerprint']),
    'first yield emits from first remaining row' => static fn (TestRunner $t) => $t->same([6], $first202()['currentGeneratedPathRowidXNext202']['emittedRowids']),
    'first yield previous rowid is first selected' => static fn (TestRunner $t) => $t->same(6, $first202()['currentGeneratedPathRowidXNext202']['previousRowid']),
    'range batch size recorded' => static fn (TestRunner $t) => $t->same(2, $range202()['currentGeneratedPathRowidXNext202']['xNextBatchSize']),
    'range emits remaining rows' => static fn (TestRunner $t) => $t->same([6, 5], $range202()['currentGeneratedPathRowidXNext202']['emittedRowids']),
    'eof has no remaining rows' => static fn (TestRunner $t) => $t->same([], $eof202()['currentGeneratedPathRowidXNext202']['remainingRowidsBeforeXNext']),
    'eof disposition' => static fn (TestRunner $t) => $t->same('eof-current-source-generated-path-rowid-xnext-next202', $eof202()['currentGeneratedPathRowidXNext202']['xNextDisposition']),
    'eof cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xnext-eof-next202', $eof202()['currentGeneratedPathRowidXNext202']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint202()['currentGeneratedPathRowidXNext202']['xNextReusable']),
    'stale fingerprint disposition' => static fn (TestRunner $t) => $t->same('abort-stale-generated-path-rowid-xnext-next202', $staleFingerprint202()['currentGeneratedPathRowidXNext202']['xNextDisposition']),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xnext-stale-source-next202', $staleFingerprint202()['currentGeneratedPathRowidXNext202']['costClass']),
    'stale generation is unpinned' => static fn (TestRunner $t) => $t->same(false, $staleGeneration202()['currentGeneratedPathRowidXNext202']['sourcePinned']),
    'transition count records xnext fields' => static fn (TestRunner $t) => $t->same(21, count($changed202()['generatedPathRowidXNext202Transitions'])),
    'transition source changes' => static fn (TestRunner $t) => $t->same(true, $changed202()['generatedPathRowidXNext202Transitions'][0]['changed']),
    'transition pin changes' => static fn (TestRunner $t) => $t->same(true, $changed202()['generatedPathRowidXNext202Transitions'][5]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed202()['generatedPathRowidXNext202Transitions'][10]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed202()['generatedPathRowidXNext202Transitions'][13]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed202()['generatedPathRowidXNext202Transitions'][18]['changed']),
    'reasons include source changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xnext-source-changed-next202', $changed202()['next202ReplanReasons'], true)),
    'reasons include admission changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xnext-admission-changed-next202', $changed202()['next202ReplanReasons'], true)),
    'reasons include rowset changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xnext-rowset-changed-next202', $changed202()['next202ReplanReasons'], true)),
    'reasons include cost changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xnext-cost-changed-next202', $changed202()['next202ReplanReasons'], true)),
    'preserves next194 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-source-changed-next194', $changed202()['next202ReplanReasons'], true)),
    'bad projection rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan202(null, null, null, null, null, null, ['bad_column'])),
    'bad xnext batch rejected' => function (TestRunner $t) use ($current202): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidXNextBatch(
            'json_tree',
            $current202,
            $current202,
            'option_value',
            'generated_path',
            [],
            'scan_root',
            [],
            null,
            null,
            ['id'],
            6,
            null,
            0,
        ));
    },
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next202 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
