<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current187 = [
    'option_id' => 187,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next187',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-187-a',
];
$next187 = [
    'option_id' => 187,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next187',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-187-b',
];

$plan187 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = null,
    ?int $lastYieldedRowid = null,
    ?array $projection = null,
    ?int $yieldedRowid = null,
    ?string $observedSourceGeneration = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidYieldGuardPlan(
    'json_tree',
    $current ?? $current187,
    $next ?? $next187,
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
);

$yield187 = static fn (): array => $plan187(null, null, null, null, null, null, null, 6);
$resume187 = static fn (): array => $plan187(null, null, null, null, null, 6, null, 5);
$stable187 = static fn (): array => $plan187($current187, $current187, null, null, null, null, null, 6);
$first187 = static fn (): array => $plan187($current187, $current187);
$staleObserved187 = static fn (): array => $plan187($current187, $current187, null, null, null, null, null, 6, 'source_generation:stale-observed');
$missingYield187 = static fn (): array => $plan187($current187, $current187, null, null, null, null, null, 99);
$point187 = static fn (): array => $plan187(
    array_replace($current187, ['generated_path' => '$.rules[1]', 'source_generation' => 'same-187']),
    array_replace($current187, ['generated_path' => '$.rules[1]', 'source_generation' => 'same-187']),
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ],
    [['column' => 'id']],
    null,
    null,
    ['id', 'value'],
    6,
);

$tests = [
    'records next187 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next187', $yield187()['dependencies'], true)),
    'preserves next184 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next184', $yield187()['dependencies'], true)),
    'current reader uses yield guard' => static fn (TestRunner $t) => $t->same('yield-current-json-table-generated-path-rowid-cost-next187-with-source-guard', $yield187()['currentReaderPolicy']),
    'next changed source reparses after guard' => static fn (TestRunner $t) => $t->same('reprepare-next-json-table-generated-path-rowid-cost-next187-after-source-guard', $yield187()['nextReaderPolicy']),
    'stable continues current yield' => static fn (TestRunner $t) => $t->same('continue-current-json-table-generated-path-rowid-cost-next187-yield', $stable187()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable187()['next187ReplanReasons']),
    'current expected generation recorded' => static fn (TestRunner $t) => $t->same('source_generation:current-187-a', $yield187()['currentGeneratedPathRowidYieldGuard187']['expectedSourceGeneration']),
    'current observed defaults to expected' => static fn (TestRunner $t) => $t->same('source_generation:current-187-a', $yield187()['currentGeneratedPathRowidYieldGuard187']['observedSourceGeneration']),
    'next compares against current observed generation' => static fn (TestRunner $t) => $t->same('source_generation:current-187-a', $yield187()['nextGeneratedPathRowidYieldGuard187']['observedSourceGeneration']),
    'next expected generation records next source' => static fn (TestRunner $t) => $t->same('source_generation:next-187-b', $yield187()['nextGeneratedPathRowidYieldGuard187']['expectedSourceGeneration']),
    'current source matches' => static fn (TestRunner $t) => $t->same(true, $yield187()['currentGeneratedPathRowidYieldGuard187']['sourceGenerationMatches']),
    'next source mismatch detected' => static fn (TestRunner $t) => $t->same(false, $yield187()['nextGeneratedPathRowidYieldGuard187']['sourceGenerationMatches']),
    'yielded rowid recorded' => static fn (TestRunner $t) => $t->same(6, $yield187()['currentGeneratedPathRowidYieldGuard187']['yieldedRowid']),
    'selected rowids preserved' => static fn (TestRunner $t) => $t->same([6, 5], $yield187()['currentGeneratedPathRowidYieldGuard187']['selectedRowids']),
    'yielded rowid found in snapshot' => static fn (TestRunner $t) => $t->same(true, $yield187()['currentGeneratedPathRowidYieldGuard187']['yieldedRowidInSnapshot']),
    'current covering snapshot true' => static fn (TestRunner $t) => $t->same(true, $yield187()['currentGeneratedPathRowidYieldGuard187']['coveringSnapshot']),
    'current yield accepted' => static fn (TestRunner $t) => $t->same(true, $yield187()['currentGeneratedPathRowidYieldGuard187']['yieldAccepted']),
    'remaining rowids after six' => static fn (TestRunner $t) => $t->same([5], $yield187()['currentGeneratedPathRowidYieldGuard187']['remainingRowids']),
    'current disposition continue' => static fn (TestRunner $t) => $t->same('continue-current-source-generated-path-rowid-yield-next187', $yield187()['currentGeneratedPathRowidYieldGuard187']['yieldDisposition']),
    'current estimated rows one' => static fn (TestRunner $t) => $t->same(1, $yield187()['currentGeneratedPathRowidYieldGuard187']['estimatedRows']),
    'current estimated cost preserves cursor cost' => static fn (TestRunner $t) => $t->same(2, $yield187()['currentGeneratedPathRowidYieldGuard187']['estimatedCost']),
    'current cost class continue' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-continue-next187', $yield187()['currentGeneratedPathRowidYieldGuard187']['costClass']),
    'yield guard fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($yield187()['currentGeneratedPathRowidYieldGuard187']['yieldGuardFingerprint'])),
    'final cost fingerprint carried' => static fn (TestRunner $t) => $t->same($yield187()['currentGeneratedPathRowidFinalCost184']['finalCostFingerprint'], $yield187()['currentGeneratedPathRowidYieldGuard187']['finalCostFingerprint']),
    'next yield rejected' => static fn (TestRunner $t) => $t->same(false, $yield187()['nextGeneratedPathRowidYieldGuard187']['yieldAccepted']),
    'next remaining rowids empty' => static fn (TestRunner $t) => $t->same([], $yield187()['nextGeneratedPathRowidYieldGuard187']['remainingRowids']),
    'next disposition stale' => static fn (TestRunner $t) => $t->same('abort-stale-current-source-generated-path-rowid-yield-next187', $yield187()['nextGeneratedPathRowidYieldGuard187']['yieldDisposition']),
    'next cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $yield187()['nextGeneratedPathRowidYieldGuard187']['estimatedCost']),
    'next cost class stale' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-stale-source-next187', $yield187()['nextGeneratedPathRowidYieldGuard187']['costClass']),
    'resume yielded last rowid exhausts' => static fn (TestRunner $t) => $t->same([], $resume187()['currentGeneratedPathRowidYieldGuard187']['remainingRowids']),
    'resume exhausted class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-exhausted-next187', $resume187()['currentGeneratedPathRowidYieldGuard187']['costClass']),
    'first yield no yielded rowid keeps all rowids' => static fn (TestRunner $t) => $t->same([6, 5], $first187()['currentGeneratedPathRowidYieldGuard187']['remainingRowids']),
    'first yield accepted' => static fn (TestRunner $t) => $t->same(true, $first187()['currentGeneratedPathRowidYieldGuard187']['yieldAccepted']),
    'stale observed source rejected' => static fn (TestRunner $t) => $t->same(false, $staleObserved187()['currentGeneratedPathRowidYieldGuard187']['yieldAccepted']),
    'stale observed disposition' => static fn (TestRunner $t) => $t->same('abort-stale-current-source-generated-path-rowid-yield-next187', $staleObserved187()['currentGeneratedPathRowidYieldGuard187']['yieldDisposition']),
    'missing yielded rowid rejected' => static fn (TestRunner $t) => $t->same(false, $missingYield187()['currentGeneratedPathRowidYieldGuard187']['yieldAccepted']),
    'missing yielded rowid disposition' => static fn (TestRunner $t) => $t->same('reseek-missing-generated-path-rowid-yield-next187', $missingYield187()['currentGeneratedPathRowidYieldGuard187']['yieldDisposition']),
    'missing yielded rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-missing-rowid-next187', $missingYield187()['currentGeneratedPathRowidYieldGuard187']['costClass']),
    'point yield exhausts' => static fn (TestRunner $t) => $t->same([], $point187()['currentGeneratedPathRowidYieldGuard187']['remainingRowids']),
    'point projection preserved from final cost' => static fn (TestRunner $t) => $t->same(['id', 'value'], $point187()['currentGeneratedPathRowidFinalCost184']['projection']),
    'transition count records yield guard fields' => static fn (TestRunner $t) => $t->same(15, count($yield187()['generatedPathRowidYieldGuard187Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $yield187()['generatedPathRowidYieldGuard187Transitions'][0]['changed']),
    'transition source match changes' => static fn (TestRunner $t) => $t->same(true, $yield187()['generatedPathRowidYieldGuard187Transitions'][2]['changed']),
    'transition selected rowids changes' => static fn (TestRunner $t) => $t->same(true, $yield187()['generatedPathRowidYieldGuard187Transitions'][4]['changed']),
    'transition yield accepted changes' => static fn (TestRunner $t) => $t->same(true, $yield187()['generatedPathRowidYieldGuard187Transitions'][8]['changed']),
    'transition cost class changes' => static fn (TestRunner $t) => $t->same(true, $yield187()['generatedPathRowidYieldGuard187Transitions'][13]['changed']),
    'reasons include source changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next187', $yield187()['next187ReplanReasons'], true)),
    'reasons include rowset changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-rowset-changed-next187', $yield187()['next187ReplanReasons'], true)),
    'reasons include admission changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-admission-changed-next187', $yield187()['next187ReplanReasons'], true)),
    'reasons include cost changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-cost-changed-next187', $yield187()['next187ReplanReasons'], true)),
    'bad projection rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan187(null, null, null, null, null, null, ['bad_column'])),
    'negative limit rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan187(null, null, null, null, -1)),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid yield guard ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
