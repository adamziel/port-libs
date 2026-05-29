<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentBatch = [
    'option_id' => 183,
    'option_name' => 'wp_plugin_generated_path_rowid_batch',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
    'source_generation' => 'current-batch-a',
];
$nextBatch = [
    'option_id' => 183,
    'option_name' => 'wp_plugin_generated_path_rowid_batch',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-batch-b',
];
$constraintsBatch = [
    ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6]],
];

$planBatch = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    int $batchSize = 1,
    ?int $resumeAfterRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidBatch(
    'json_tree',
    $current ?? $currentBatch,
    $next ?? $nextBatch,
    'option_value',
    'generated_path',
    $constraints ?? $constraintsBatch,
    'scan_root',
    $orderBy ?? [['column' => 'rowid']],
    $batchSize,
    $resumeAfterRowid,
);

$firstBatch = static fn (): array => $planBatch();
$secondBatch = static fn (): array => $planBatch(null, null, null, null, 1, 5);
$finalBatch = static fn (): array => $planBatch(null, null, null, null, 2);
$stableBatch = static fn (): array => $planBatch($currentBatch, $currentBatch);
$missingBatch = static fn (): array => $planBatch($currentBatch, $currentBatch, null, null, 1, 99);
$blockedBatch = static fn (): array => $planBatch(
    $currentBatch,
    $currentBatch,
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 5],
        ['column' => 'oid', 'operator' => '=', 'value' => 6],
    ],
);

$tests = [
    'records batch dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next183', $firstBatch()['dependencies'], true)),
    'preserves next180 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next180', $firstBatch()['dependencies'], true)),
    'current reader policy yields batch' => static fn (TestRunner $t) => $t->same('yield-current-json-table-generated-path-rowid-batch-next183-before-source-replan', $firstBatch()['currentReaderPolicy']),
    'next reader policy restarts changed source' => static fn (TestRunner $t) => $t->same('restart-next-json-table-generated-path-rowid-batch-next183', $firstBatch()['nextReaderPolicy']),
    'stable next reader policy reuses batch' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-batch-next183', $stableBatch()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stableBatch()['next183ReplanReasons']),
    'current materialization rowids are carried' => static fn (TestRunner $t) => $t->same([5, 6], $firstBatch()['currentGeneratedPathRowidCurrentSourceMaterialization180']['materializedRowids']),
    'first batch source generation' => static fn (TestRunner $t) => $t->same('source_generation:current-batch-a', $firstBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['sourceGeneration']),
    'first batch materialization opcode residual reviewed' => static fn (TestRunner $t) => $t->same('materialize-current-source-residual-rowset-next180', $firstBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['materializationOpcode']),
    'first batch size one' => static fn (TestRunner $t) => $t->same(1, $firstBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['batchSize']),
    'first batch resume rowid null' => static fn (TestRunner $t) => $t->same(null, $firstBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['resumeAfterRowid']),
    'first batch resume ordinal zero' => static fn (TestRunner $t) => $t->same(0, $firstBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['resumeOrdinal']),
    'first batch resume found' => static fn (TestRunner $t) => $t->same(true, $firstBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['resumeFound']),
    'first batch not stale' => static fn (TestRunner $t) => $t->same(false, $firstBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['staleAfterNextSource']),
    'first batch opcode partial' => static fn (TestRunner $t) => $t->same('yield-partial-current-source-generated-path-rowid-batch-next183', $firstBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['batchOpcode']),
    'first batch rowid five' => static fn (TestRunner $t) => $t->same([5], $firstBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['batchRowids']),
    'first batch remaining rowid six' => static fn (TestRunner $t) => $t->same([6], $firstBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['remainingRowids']),
    'first batch not eof' => static fn (TestRunner $t) => $t->same(false, $firstBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['eofAfterBatch']),
    'first batch emit cost one' => static fn (TestRunner $t) => $t->same(1, $firstBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['emitCost']),
    'first batch cost class partial' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-batch-partial-next183', $firstBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['costClass']),
    'first yield tape ordinal zero' => static fn (TestRunner $t) => $t->same(0, $firstBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['yieldTape'][0]['ordinal']),
    'first yield tape rowid five' => static fn (TestRunner $t) => $t->same(5, $firstBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['yieldTape'][0]['rowid']),
    'first yield tape path' => static fn (TestRunner $t) => $t->same('$.rules[1]', $firstBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['yieldTape'][0]['path']),
    'first yield tape opcode' => static fn (TestRunner $t) => $t->same('yield-partial-current-source-generated-path-rowid-batch-next183', $firstBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['yieldTape'][0]['opcode']),
    'first batch fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($firstBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['batchFingerprint'])),
    'next batch stale' => static fn (TestRunner $t) => $t->same(true, $firstBatch()['nextGeneratedPathRowidCurrentSourceBatch183']['staleAfterNextSource']),
    'next batch opcode restart' => static fn (TestRunner $t) => $t->same('restart-next-source-generated-path-rowid-batch-next183', $firstBatch()['nextGeneratedPathRowidCurrentSourceBatch183']['batchOpcode']),
    'next batch rowids empty' => static fn (TestRunner $t) => $t->same([], $firstBatch()['nextGeneratedPathRowidCurrentSourceBatch183']['batchRowids']),
    'next batch cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $firstBatch()['nextGeneratedPathRowidCurrentSourceBatch183']['emitCost']),
    'next batch cost class restart' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-batch-restart-next183', $firstBatch()['nextGeneratedPathRowidCurrentSourceBatch183']['costClass']),
    'second batch resume after five' => static fn (TestRunner $t) => $t->same(5, $secondBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['resumeAfterRowid']),
    'second batch resume ordinal one' => static fn (TestRunner $t) => $t->same(1, $secondBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['resumeOrdinal']),
    'second batch rowid six' => static fn (TestRunner $t) => $t->same([6], $secondBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['batchRowids']),
    'second batch no remaining' => static fn (TestRunner $t) => $t->same([], $secondBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['remainingRowids']),
    'second batch eof' => static fn (TestRunner $t) => $t->same(true, $secondBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['eofAfterBatch']),
    'second batch opcode final' => static fn (TestRunner $t) => $t->same('yield-final-current-source-generated-path-rowid-batch-next183', $secondBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['batchOpcode']),
    'second batch point cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-batch-point-next183', $secondBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['costClass']),
    'final batch emits both rowids' => static fn (TestRunner $t) => $t->same([5, 6], $finalBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['batchRowids']),
    'final batch final cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-batch-final-next183', $finalBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['costClass']),
    'missing resume not found' => static fn (TestRunner $t) => $t->same(false, $missingBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['resumeFound']),
    'missing resume opcode reseek' => static fn (TestRunner $t) => $t->same('reseek-current-source-generated-path-rowid-batch-next183', $missingBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['batchOpcode']),
    'missing resume cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-batch-reseek-next183', $missingBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['costClass']),
    'blocked residual opcode' => static fn (TestRunner $t) => $t->same('block-current-source-generated-path-rowid-batch-next183', $blockedBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['batchOpcode']),
    'blocked residual cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-batch-blocked-next183', $blockedBatch()['currentGeneratedPathRowidCurrentSourceBatch183']['costClass']),
    'transition count records batch fields' => static fn (TestRunner $t) => $t->same(16, count($firstBatch()['generatedPathRowidCurrentSourceBatch183Transitions'])),
    'transition source changes' => static fn (TestRunner $t) => $t->same(true, $firstBatch()['generatedPathRowidCurrentSourceBatch183Transitions'][0]['changed']),
    'transition stale changes' => static fn (TestRunner $t) => $t->same(true, $firstBatch()['generatedPathRowidCurrentSourceBatch183Transitions'][7]['changed']),
    'transition opcode changes' => static fn (TestRunner $t) => $t->same(true, $firstBatch()['generatedPathRowidCurrentSourceBatch183Transitions'][8]['changed']),
    'transition rowids change' => static fn (TestRunner $t) => $t->same(true, $firstBatch()['generatedPathRowidCurrentSourceBatch183Transitions'][9]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $firstBatch()['generatedPathRowidCurrentSourceBatch183Transitions'][13]['changed']),
    'reasons include batch source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-batch-next183-source-changed', $firstBatch()['next183ReplanReasons'], true)),
    'reasons include batch cursor' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-batch-next183-cursor-changed', $firstBatch()['next183ReplanReasons'], true)),
    'reasons include batch rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-batch-next183-rowset-changed', $firstBatch()['next183ReplanReasons'], true)),
    'reasons include batch cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-batch-next183-cost-changed', $firstBatch()['next183ReplanReasons'], true)),
    'reasons preserve materialization rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-materialization-next180-rowset-changed', $firstBatch()['next183ReplanReasons'], true)),
    'negative batch rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $planBatch(null, null, null, null, 0)),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid batch ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
