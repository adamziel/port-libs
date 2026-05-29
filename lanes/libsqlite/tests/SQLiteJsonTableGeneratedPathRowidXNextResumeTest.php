<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current182 = [
    'option_id' => 182,
    'option_name' => 'wp_plugin_generated_path_rowid_xnext_resume',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 21,
];
$next182 = [
    'option_id' => 182,
    'option_name' => 'wp_plugin_generated_path_rowid_xnext_resume',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"security","priority":9},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
    'source_generation' => 22,
];

$plan182 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = 4,
    ?int $lastYieldedRowid = 7,
    ?int $yieldBatchSize = 1,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidBatchedXNextPlan(
    'json_tree',
    $current ?? $current182,
    $next ?? $next182,
    'option_value',
    'generated_path',
    $constraints ?? [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 42]],
    ],
    'scan_root',
    $orderBy ?? [['column' => '_rowid_', 'direction' => 'DESC']],
    $limit,
    $lastYieldedRowid,
    $yieldBatchSize,
);

$stable182 = static fn (): array => $plan182($current182, $current182);
$final182 = static fn (): array => $plan182($current182, $current182, null, null, 4, 6, 4);
$limitFence182 = static fn (): array => $plan182($current182, $current182, null, null, 2, 7, 2);
$missing182 = static fn (): array => $plan182($current182, $current182, null, null, 4, 99, 2);
$unordered182 = static fn (): array => $plan182($current182, $current182, null, [], 4, null, 2);

$tests = [
    'records next182 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next182', $plan182()['dependencies'], true)),
    'preserves next178 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next178', $plan182()['dependencies'], true)),
    'current reader policy admits xnext' => static fn (TestRunner $t) => $t->same('admit-current-json-table-generated-path-rowid-cost-current-source-next182-xnext', $plan182()['currentReaderPolicy']),
    'changed next reader policy restarts xfilter' => static fn (TestRunner $t) => $t->same('restart-next-json-table-generated-path-rowid-cost-current-source-next182-xfilter', $plan182()['nextReaderPolicy']),
    'stable next reader policy continues xnext' => static fn (TestRunner $t) => $t->same('continue-current-json-table-generated-path-rowid-cost-current-source-next182-xnext', $stable182()['nextReaderPolicy']),
    'stable next182 reasons empty' => static fn (TestRunner $t) => $t->same([], $stable182()['next182ReplanReasons']),
    'current state admits batched xnext' => static fn (TestRunner $t) => $t->same('admit-batched-current-source-xnext', $plan182()['currentGeneratedPathRowidCurrentSourceXNext182']['admissionState']),
    'current opcode is xnext' => static fn (TestRunner $t) => $t->same('xNext-current-generated-path-rowid', $plan182()['currentGeneratedPathRowidCurrentSourceXNext182']['xNextOpcode']),
    'current next rowid is first deliverable' => static fn (TestRunner $t) => $t->same(6, $plan182()['currentGeneratedPathRowidCurrentSourceXNext182']['nextRowid']),
    'current deliverable rowids obey batch size' => static fn (TestRunner $t) => $t->same([6], $plan182()['currentGeneratedPathRowidCurrentSourceXNext182']['deliverableRowids']),
    'current blocked rowids remain after batch' => static fn (TestRunner $t) => $t->same([5], $plan182()['currentGeneratedPathRowidCurrentSourceXNext182']['blockedRowids']),
    'current remaining rowids preserve order' => static fn (TestRunner $t) => $t->same([6, 5], $plan182()['currentGeneratedPathRowidCurrentSourceXNext182']['remainingRowids']),
    'current resume ordinal preserved' => static fn (TestRunner $t) => $t->same(2, $plan182()['currentGeneratedPathRowidCurrentSourceXNext182']['resumeOrdinal']),
    'current limit fence recorded' => static fn (TestRunner $t) => $t->same(4, $plan182()['currentGeneratedPathRowidCurrentSourceXNext182']['limitFence']),
    'current batch size recorded' => static fn (TestRunner $t) => $t->same(1, $plan182()['currentGeneratedPathRowidCurrentSourceXNext182']['batchSize']),
    'current order fence records rowid desc' => static fn (TestRunner $t) => $t->same('id:DESC', $plan182()['currentGeneratedPathRowidCurrentSourceXNext182']['orderFence']),
    'current source fence is current yield fence' => static fn (TestRunner $t) => $t->same('current-source-generated-path-rowid-yield-fence', $plan182()['currentGeneratedPathRowidCurrentSourceXNext182']['sourceFence']),
    'current cache reusable' => static fn (TestRunner $t) => $t->same(true, $plan182()['currentGeneratedPathRowidCurrentSourceXNext182']['cacheReusable']),
    'current not stale' => static fn (TestRunner $t) => $t->same(false, $plan182()['currentGeneratedPathRowidCurrentSourceXNext182']['staleAfterNextSource']),
    'current not eof after batch' => static fn (TestRunner $t) => $t->same(false, $plan182()['currentGeneratedPathRowidCurrentSourceXNext182']['eofAfterBatch']),
    'current estimated rows one' => static fn (TestRunner $t) => $t->same(1, $plan182()['currentGeneratedPathRowidCurrentSourceXNext182']['estimatedRows']),
    'current estimated cost one' => static fn (TestRunner $t) => $t->same(1, $plan182()['currentGeneratedPathRowidCurrentSourceXNext182']['estimatedCost']),
    'current cost class is batched' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xnext-batched-current-source', $plan182()['currentGeneratedPathRowidCurrentSourceXNext182']['costClass']),
    'current fingerprint is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan182()['currentGeneratedPathRowidCurrentSourceXNext182']['admissionFingerprint'])),
    'next state restarts before xnext' => static fn (TestRunner $t) => $t->same('restart-next-source-before-xnext', $plan182()['nextGeneratedPathRowidCurrentSourceXNext182']['admissionState']),
    'next opcode restarts xfilter' => static fn (TestRunner $t) => $t->same('xFilter-restart-generated-path-rowid', $plan182()['nextGeneratedPathRowidCurrentSourceXNext182']['xNextOpcode']),
    'next deliverable empty when stale' => static fn (TestRunner $t) => $t->same([], $plan182()['nextGeneratedPathRowidCurrentSourceXNext182']['deliverableRowids']),
    'next source fence restarts' => static fn (TestRunner $t) => $t->same('next-source-xfilter-fence', $plan182()['nextGeneratedPathRowidCurrentSourceXNext182']['sourceFence']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $plan182()['nextGeneratedPathRowidCurrentSourceXNext182']['estimatedCost']),
    'next cost class restarts' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xnext-restart-next-source', $plan182()['nextGeneratedPathRowidCurrentSourceXNext182']['costClass']),
    'final batch state final' => static fn (TestRunner $t) => $t->same('admit-final-current-source-xnext', $final182()['currentGeneratedPathRowidCurrentSourceXNext182']['admissionState']),
    'final batch eof' => static fn (TestRunner $t) => $t->same(true, $final182()['currentGeneratedPathRowidCurrentSourceXNext182']['eofAfterBatch']),
    'final batch deliverable rowids' => static fn (TestRunner $t) => $t->same([5], $final182()['currentGeneratedPathRowidCurrentSourceXNext182']['deliverableRowids']),
    'final batch cost class final' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xnext-final-current-source', $final182()['currentGeneratedPathRowidCurrentSourceXNext182']['costClass']),
    'limit fence stops delivery' => static fn (TestRunner $t) => $t->same([], $limitFence182()['currentGeneratedPathRowidCurrentSourceXNext182']['deliverableRowids']),
    'limit fence state eof' => static fn (TestRunner $t) => $t->same('xnext-eof-or-limit-fence', $limitFence182()['currentGeneratedPathRowidCurrentSourceXNext182']['admissionState']),
    'limit fence estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $limitFence182()['currentGeneratedPathRowidCurrentSourceXNext182']['estimatedRows']),
    'missing rowid reseeks current source' => static fn (TestRunner $t) => $t->same('reseek-current-source-before-xnext', $missing182()['currentGeneratedPathRowidCurrentSourceXNext182']['admissionState']),
    'missing rowid deliverable rowids blocked by reseek' => static fn (TestRunner $t) => $t->same([], $missing182()['currentGeneratedPathRowidCurrentSourceXNext182']['deliverableRowids']),
    'missing rowid xfilter restart opcode' => static fn (TestRunner $t) => $t->same('xFilter-restart-generated-path-rowid', $missing182()['currentGeneratedPathRowidCurrentSourceXNext182']['xNextOpcode']),
    'unordered order fence recorded' => static fn (TestRunner $t) => $t->same('unordered-json-table-generated-path-rowid-xnext', $unordered182()['currentGeneratedPathRowidCurrentSourceXNext182']['orderFence']),
    'transition count records xnext fields' => static fn (TestRunner $t) => $t->same(16, count($plan182()['generatedPathRowidCurrentSourceXNext182Transitions'])),
    'transition fingerprint changes' => static fn (TestRunner $t) => $t->same(true, $plan182()['generatedPathRowidCurrentSourceXNext182Transitions'][0]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $plan182()['generatedPathRowidCurrentSourceXNext182Transitions'][1]['changed']),
    'transition opcode changes' => static fn (TestRunner $t) => $t->same(true, $plan182()['generatedPathRowidCurrentSourceXNext182Transitions'][2]['changed']),
    'transition rowid changes' => static fn (TestRunner $t) => $t->same(true, $plan182()['generatedPathRowidCurrentSourceXNext182Transitions'][3]['changed']),
    'transition source fence changes' => static fn (TestRunner $t) => $t->same(true, $plan182()['generatedPathRowidCurrentSourceXNext182Transitions'][9]['changed']),
    'transition stale changes' => static fn (TestRunner $t) => $t->same(true, $plan182()['generatedPathRowidCurrentSourceXNext182Transitions'][11]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $plan182()['generatedPathRowidCurrentSourceXNext182Transitions'][14]['changed']),
    'reasons include source fence' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xnext-source-fence-changed', $plan182()['next182ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xnext-admission-changed', $plan182()['next182ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xnext-rowset-changed', $plan182()['next182ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xnext-cost-changed', $plan182()['next182ReplanReasons'], true)),
    'zero batch rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan182(null, null, null, null, 4, 7, 0)),
    'bad order direction rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan182(null, null, null, [['column' => 'id', 'direction' => 'DOWN']], 4, 7, 1)),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid batched xnext ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
