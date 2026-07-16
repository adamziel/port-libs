<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current194 = [
    'option_id' => 194,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next194',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-194-a',
];
$next194 = [
    'option_id' => 194,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next194',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-194-b',
];

$plan194 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = null,
    ?int $lastYieldedRowid = null,
    ?array $projection = null,
    ?int $yieldedRowid = 6,
    ?string $observedSourceGeneration = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidPinnedSourcePlan(
    'json_tree',
    $current ?? $current194,
    $next ?? $next194,
    'option_value',
    'generated_path',
    $constraints ?? [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 42]],
    ],
    'scan_root',
    $orderBy ?? [['column' => 'rowid', 'direction' => 'DESC']],
    $limit,
    $lastYieldedRowid,
    $projection ?? ['id', 'fullkey', 'atom', 'value', 'type'],
    $yieldedRowid,
    $observedSourceGeneration,
);

$changed194 = static fn (): array => $plan194();
$stable194 = static fn (): array => $plan194($current194, $current194);
$first194 = static fn (): array => $plan194($current194, $current194, null, null, null, null, null, null);
$point194 = static fn (): array => $plan194(
    array_replace($current194, ['generated_path' => '$.rules[1]', 'source_generation' => 'same-194']),
    array_replace($current194, ['generated_path' => '$.rules[1]', 'source_generation' => 'same-194']),
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => '_rowid_', 'operator' => '=', 'value' => 6],
    ],
    [['column' => 'id']],
    null,
    null,
    ['id', 'value'],
    6,
);
$staleObserved194 = static fn (): array => $plan194($current194, $current194, null, null, null, null, null, 6, 'source_generation:stale-194');
$missing194 = static fn (): array => $plan194($current194, $current194, null, null, null, null, null, 99);

$tests = [
    'records next194 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next194', $changed194()['dependencies'], true)),
    'preserves next190 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next190', $changed194()['dependencies'], true)),
    'current reader pins source' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-path-rowid-source-next194', $changed194()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-next-json-table-generated-path-rowid-source-next194', $changed194()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-source-next194', $stable194()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable194()['next194ReplanReasons']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('source_generation:current-194-a', $changed194()['currentGeneratedPathRowidPinnedSource194']['sourceGeneration']),
    'current observed source generation recorded' => static fn (TestRunner $t) => $t->same('source_generation:current-194-a', $changed194()['currentGeneratedPathRowidPinnedSource194']['observedSourceGeneration']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed194()['currentGeneratedPathRowidPinnedSource194']['generatedPath']),
    'current root path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed194()['currentGeneratedPathRowidPinnedSource194']['rootPath']),
    'current active rowid recorded' => static fn (TestRunner $t) => $t->same(6, $changed194()['currentGeneratedPathRowidPinnedSource194']['activeRowid']),
    'current active fullkey recorded' => static fn (TestRunner $t) => $t->same('$.rules[1].priority', $changed194()['currentGeneratedPathRowidPinnedSource194']['activeFullkey']),
    'current active path omitted by xcolumn snapshot' => static fn (TestRunner $t) => $t->same(null, $changed194()['currentGeneratedPathRowidPinnedSource194']['activePath']),
    'current active type recorded' => static fn (TestRunner $t) => $t->same('integer', $changed194()['currentGeneratedPathRowidPinnedSource194']['activeType']),
    'current active value recorded' => static fn (TestRunner $t) => $t->same(7, $changed194()['currentGeneratedPathRowidPinnedSource194']['activeValue']),
    'row fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen((string) $changed194()['currentGeneratedPathRowidPinnedSource194']['rowFingerprint'])),
    'final cost fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen((string) $changed194()['currentGeneratedPathRowidPinnedSource194']['finalCostFingerprint'])),
    'yield row fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen((string) $changed194()['currentGeneratedPathRowidPinnedSource194']['yieldRowFingerprint'])),
    'selected rowids preserved' => static fn (TestRunner $t) => $t->same([6, 5], $changed194()['currentGeneratedPathRowidPinnedSource194']['selectedRowids']),
    'remaining rowids preserved' => static fn (TestRunner $t) => $t->same([5], $changed194()['currentGeneratedPathRowidPinnedSource194']['remainingRowids']),
    'current upstream replan false' => static fn (TestRunner $t) => $t->same(false, $changed194()['currentGeneratedPathRowidPinnedSource194']['upstreamReplanRequired']),
    'current yield accepted true' => static fn (TestRunner $t) => $t->same(true, $changed194()['currentGeneratedPathRowidPinnedSource194']['yieldAccepted']),
    'current source pinned true' => static fn (TestRunner $t) => $t->same(true, $changed194()['currentGeneratedPathRowidPinnedSource194']['sourcePinned']),
    'current disposition pins' => static fn (TestRunner $t) => $t->same('pin-current-source-generated-path-rowid-next194', $changed194()['currentGeneratedPathRowidPinnedSource194']['sourceDisposition']),
    'current opcode pins' => static fn (TestRunner $t) => $t->same('OP_JsonTablePinGeneratedPathRowidSourceNext194', $changed194()['currentGeneratedPathRowidPinnedSource194']['sourceOpcode']),
    'current estimated rows includes active and remaining' => static fn (TestRunner $t) => $t->same(2, $changed194()['currentGeneratedPathRowidPinnedSource194']['estimatedRows']),
    'current estimated cost bounded' => static fn (TestRunner $t) => $t->same(2, $changed194()['currentGeneratedPathRowidPinnedSource194']['estimatedCost']),
    'current cost class range' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-source-pinned-range-next194', $changed194()['currentGeneratedPathRowidPinnedSource194']['costClass']),
    'source fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed194()['currentGeneratedPathRowidPinnedSource194']['sourceFingerprint'])),
    'next upstream replan true' => static fn (TestRunner $t) => $t->same(true, $changed194()['nextGeneratedPathRowidPinnedSource194']['upstreamReplanRequired']),
    'next source pinned false' => static fn (TestRunner $t) => $t->same(false, $changed194()['nextGeneratedPathRowidPinnedSource194']['sourcePinned']),
    'next disposition upstream reprepare' => static fn (TestRunner $t) => $t->same('reprepare-upstream-generated-path-rowid-source-next194', $changed194()['nextGeneratedPathRowidPinnedSource194']['sourceDisposition']),
    'next opcode upstream reprepare' => static fn (TestRunner $t) => $t->same('OP_JsonTableReprepareUpstreamSourceNext194', $changed194()['nextGeneratedPathRowidPinnedSource194']['sourceOpcode']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed194()['nextGeneratedPathRowidPinnedSource194']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed194()['nextGeneratedPathRowidPinnedSource194']['estimatedCost']),
    'next cost class upstream reprepare' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-source-upstream-reprepare-next194', $changed194()['nextGeneratedPathRowidPinnedSource194']['costClass']),
    'stable source pinned true' => static fn (TestRunner $t) => $t->same(true, $stable194()['nextGeneratedPathRowidPinnedSource194']['sourcePinned']),
    'stable next disposition pins' => static fn (TestRunner $t) => $t->same('pin-current-source-generated-path-rowid-next194', $stable194()['nextGeneratedPathRowidPinnedSource194']['sourceDisposition']),
    'stable source fingerprint matches' => static fn (TestRunner $t) => $t->same($stable194()['currentGeneratedPathRowidPinnedSource194']['sourceFingerprint'], $stable194()['nextGeneratedPathRowidPinnedSource194']['sourceFingerprint']),
    'first yield active rowid first selected' => static fn (TestRunner $t) => $t->same(6, $first194()['currentGeneratedPathRowidPinnedSource194']['activeRowid']),
    'first yield estimated rows includes all selected rows' => static fn (TestRunner $t) => $t->same(3, $first194()['currentGeneratedPathRowidPinnedSource194']['estimatedRows']),
    'point generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules[1]', $point194()['currentGeneratedPathRowidPinnedSource194']['generatedPath']),
    'point selected rowid one' => static fn (TestRunner $t) => $t->same([6], $point194()['currentGeneratedPathRowidPinnedSource194']['selectedRowids']),
    'point cost class point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-source-pinned-point-next194', $point194()['currentGeneratedPathRowidPinnedSource194']['costClass']),
    'stale observed not pinned' => static fn (TestRunner $t) => $t->same(false, $staleObserved194()['currentGeneratedPathRowidPinnedSource194']['sourcePinned']),
    'stale observed disposition' => static fn (TestRunner $t) => $t->same('abort-stale-generated-path-rowid-source-next194', $staleObserved194()['currentGeneratedPathRowidPinnedSource194']['sourceDisposition']),
    'stale observed cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-source-stale-generation-next194', $staleObserved194()['currentGeneratedPathRowidPinnedSource194']['costClass']),
    'missing row not pinned' => static fn (TestRunner $t) => $t->same(false, $missing194()['currentGeneratedPathRowidPinnedSource194']['sourcePinned']),
    'missing row disposition' => static fn (TestRunner $t) => $t->same('reseek-unaccepted-generated-path-rowid-source-next194', $missing194()['currentGeneratedPathRowidPinnedSource194']['sourceDisposition']),
    'transition count records source fields' => static fn (TestRunner $t) => $t->same(23, count($changed194()['generatedPathRowidPinnedSource194Transitions'])),
    'transition source changes' => static fn (TestRunner $t) => $t->same(true, $changed194()['generatedPathRowidPinnedSource194Transitions'][0]['changed']),
    'transition generated path changes' => static fn (TestRunner $t) => $t->same(true, $changed194()['generatedPathRowidPinnedSource194Transitions'][2]['changed']),
    'transition row fingerprint changes' => static fn (TestRunner $t) => $t->same(true, $changed194()['generatedPathRowidPinnedSource194Transitions'][9]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed194()['generatedPathRowidPinnedSource194Transitions'][16]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed194()['generatedPathRowidPinnedSource194Transitions'][20]['changed']),
    'reasons include source changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-source-changed-next194', $changed194()['next194ReplanReasons'], true)),
    'reasons include active row changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-source-active-row-changed-next194', $changed194()['next194ReplanReasons'], true)),
    'reasons include fingerprint changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-source-fingerprint-changed-next194', $changed194()['next194ReplanReasons'], true)),
    'reasons include rowset changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-source-rowset-changed-next194', $changed194()['next194ReplanReasons'], true)),
    'reasons include admission changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-source-admission-changed-next194', $changed194()['next194ReplanReasons'], true)),
    'reasons include cost changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-source-cost-changed-next194', $changed194()['next194ReplanReasons'], true)),
    'bad projection rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan194(null, null, null, null, null, null, ['bad_column'])),
    'negative limit rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan194(null, null, null, null, -1)),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid pinned source ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
