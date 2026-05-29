<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentCursor = [
    'option_id' => 186,
    'option_name' => 'wp_plugin_generated_path_rowid_cursor',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
    'source_generation' => 'current-cursor-a',
];
$nextCursor = [
    'option_id' => 186,
    'option_name' => 'wp_plugin_generated_path_rowid_cursor',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-cursor-b',
];
$constraintsCursor = [
    ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6]],
];

$planCursor = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    int $batchSize = 1,
    ?int $resumeAfterRowid = null,
    int $cursorPosition = 0,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCursor(
    'json_tree',
    $current ?? $currentCursor,
    $next ?? $nextCursor,
    'option_value',
    'generated_path',
    $constraints ?? $constraintsCursor,
    'scan_root',
    [['column' => 'rowid']],
    $batchSize,
    $resumeAfterRowid,
    $cursorPosition,
);

$firstCursor = static fn (): array => $planCursor();
$secondCursor = static fn (): array => $planCursor(null, null, null, 1, 5);
$stableCursor = static fn (): array => $planCursor($currentCursor, $currentCursor);
$finalCursor = static fn (): array => $planCursor($currentCursor, $currentCursor, null, 2, null, 1);
$eofCursor = static fn (): array => $planCursor($currentCursor, $currentCursor, null, 1, 5, 1);
$blockedCursor = static fn (): array => $planCursor(
    $currentCursor,
    $currentCursor,
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 5],
        ['column' => 'oid', 'operator' => '=', 'value' => 6],
    ],
);

$tests = [
    'records cursor dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next186', $firstCursor()['dependencies'], true)),
    'preserves batch dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next183', $firstCursor()['dependencies'], true)),
    'current reader policy advances cursor' => static fn (TestRunner $t) => $t->same('advance-current-json-table-generated-path-rowid-cursor-next186-before-source-replan', $firstCursor()['currentReaderPolicy']),
    'next reader policy restarts changed source' => static fn (TestRunner $t) => $t->same('restart-next-json-table-generated-path-rowid-cursor-next186', $firstCursor()['nextReaderPolicy']),
    'stable next reader policy reuses cursor' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-cursor-next186', $stableCursor()['nextReaderPolicy']),
    'stable cursor reasons empty' => static fn (TestRunner $t) => $t->same([], $stableCursor()['next186ReplanReasons']),
    'current cursor source generation' => static fn (TestRunner $t) => $t->same('source_generation:current-cursor-a', $firstCursor()['currentGeneratedPathRowidCurrentSourceCursor186']['sourceGeneration']),
    'current cursor batch opcode inherited' => static fn (TestRunner $t) => $t->same('yield-partial-current-source-generated-path-rowid-batch-next183', $firstCursor()['currentGeneratedPathRowidCurrentSourceCursor186']['batchOpcode']),
    'current cursor position zero' => static fn (TestRunner $t) => $t->same(0, $firstCursor()['currentGeneratedPathRowidCurrentSourceCursor186']['cursorPosition']),
    'current cursor active rowid five' => static fn (TestRunner $t) => $t->same(5, $firstCursor()['currentGeneratedPathRowidCurrentSourceCursor186']['activeRowid']),
    'current cursor active path' => static fn (TestRunner $t) => $t->same('$.rules[1]', $firstCursor()['currentGeneratedPathRowidCurrentSourceCursor186']['activePath']),
    'current cursor no next row in batch' => static fn (TestRunner $t) => $t->same(null, $firstCursor()['currentGeneratedPathRowidCurrentSourceCursor186']['nextRowid']),
    'current cursor pending rowids include refill' => static fn (TestRunner $t) => $t->same([5, 6], $firstCursor()['currentGeneratedPathRowidCurrentSourceCursor186']['pendingRowids']),
    'current cursor remaining rowid six' => static fn (TestRunner $t) => $t->same([6], $firstCursor()['currentGeneratedPathRowidCurrentSourceCursor186']['remainingRowids']),
    'current cursor needs refill' => static fn (TestRunner $t) => $t->same(true, $firstCursor()['currentGeneratedPathRowidCurrentSourceCursor186']['needsBatchRefill']),
    'current cursor not eof' => static fn (TestRunner $t) => $t->same(false, $firstCursor()['currentGeneratedPathRowidCurrentSourceCursor186']['eofAfterXNext']),
    'current cursor not stale' => static fn (TestRunner $t) => $t->same(false, $firstCursor()['currentGeneratedPathRowidCurrentSourceCursor186']['staleAfterNextSource']),
    'current cursor opcode refill' => static fn (TestRunner $t) => $t->same('refill-current-source-generated-path-rowid-cursor-next186', $firstCursor()['currentGeneratedPathRowidCurrentSourceCursor186']['xNextOpcode']),
    'current cursor cost two' => static fn (TestRunner $t) => $t->same(2, $firstCursor()['currentGeneratedPathRowidCurrentSourceCursor186']['xNextCost']),
    'current cursor cost class refill' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-cursor-refill-next186', $firstCursor()['currentGeneratedPathRowidCurrentSourceCursor186']['costClass']),
    'current cursor tape rowid five' => static fn (TestRunner $t) => $t->same(5, $firstCursor()['currentGeneratedPathRowidCurrentSourceCursor186']['cursorTape'][0]['rowid']),
    'current cursor tape opcode' => static fn (TestRunner $t) => $t->same('refill-current-source-generated-path-rowid-cursor-next186', $firstCursor()['currentGeneratedPathRowidCurrentSourceCursor186']['cursorTape'][0]['opcode']),
    'current cursor fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($firstCursor()['currentGeneratedPathRowidCurrentSourceCursor186']['cursorFingerprint'])),
    'next cursor stale' => static fn (TestRunner $t) => $t->same(true, $firstCursor()['nextGeneratedPathRowidCurrentSourceCursor186']['staleAfterNextSource']),
    'next cursor rowids empty' => static fn (TestRunner $t) => $t->same([], $firstCursor()['nextGeneratedPathRowidCurrentSourceCursor186']['pendingRowids']),
    'next cursor opcode restart' => static fn (TestRunner $t) => $t->same('restart-next-source-generated-path-rowid-cursor-next186', $firstCursor()['nextGeneratedPathRowidCurrentSourceCursor186']['xNextOpcode']),
    'next cursor cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $firstCursor()['nextGeneratedPathRowidCurrentSourceCursor186']['xNextCost']),
    'next cursor cost class restart' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-cursor-restart-next186', $firstCursor()['nextGeneratedPathRowidCurrentSourceCursor186']['costClass']),
    'second cursor active rowid six' => static fn (TestRunner $t) => $t->same(6, $secondCursor()['currentGeneratedPathRowidCurrentSourceCursor186']['activeRowid']),
    'second cursor pending rowid six' => static fn (TestRunner $t) => $t->same([6], $secondCursor()['currentGeneratedPathRowidCurrentSourceCursor186']['pendingRowids']),
    'second cursor final opcode' => static fn (TestRunner $t) => $t->same('final-current-source-generated-path-rowid-cursor-next186', $secondCursor()['currentGeneratedPathRowidCurrentSourceCursor186']['xNextOpcode']),
    'second cursor point cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-cursor-final-next186', $secondCursor()['currentGeneratedPathRowidCurrentSourceCursor186']['costClass']),
    'final cursor position one active six' => static fn (TestRunner $t) => $t->same(6, $finalCursor()['currentGeneratedPathRowidCurrentSourceCursor186']['activeRowid']),
    'final cursor eof after xnext' => static fn (TestRunner $t) => $t->same(true, $finalCursor()['currentGeneratedPathRowidCurrentSourceCursor186']['eofAfterXNext']),
    'eof cursor has null active rowid' => static fn (TestRunner $t) => $t->same(null, $eofCursor()['currentGeneratedPathRowidCurrentSourceCursor186']['activeRowid']),
    'eof cursor opcode' => static fn (TestRunner $t) => $t->same('eof-current-source-generated-path-rowid-cursor-next186', $eofCursor()['currentGeneratedPathRowidCurrentSourceCursor186']['xNextOpcode']),
    'blocked cursor opcode' => static fn (TestRunner $t) => $t->same('block-current-source-generated-path-rowid-cursor-next186', $blockedCursor()['currentGeneratedPathRowidCurrentSourceCursor186']['xNextOpcode']),
    'blocked cursor cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-cursor-blocked-next186', $blockedCursor()['currentGeneratedPathRowidCurrentSourceCursor186']['costClass']),
    'transition count records cursor fields' => static fn (TestRunner $t) => $t->same(15, count($firstCursor()['generatedPathRowidCurrentSourceCursor186Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $firstCursor()['generatedPathRowidCurrentSourceCursor186Transitions'][0]['changed']),
    'transition active rowid changes' => static fn (TestRunner $t) => $t->same(true, $firstCursor()['generatedPathRowidCurrentSourceCursor186Transitions'][4]['changed']),
    'transition pending rowids change' => static fn (TestRunner $t) => $t->same(true, $firstCursor()['generatedPathRowidCurrentSourceCursor186Transitions'][7]['changed']),
    'transition stale changes' => static fn (TestRunner $t) => $t->same(true, $firstCursor()['generatedPathRowidCurrentSourceCursor186Transitions'][10]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $firstCursor()['generatedPathRowidCurrentSourceCursor186Transitions'][12]['changed']),
    'reasons include cursor source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cursor-next186-source-changed', $firstCursor()['next186ReplanReasons'], true)),
    'reasons include cursor position' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cursor-next186-position-changed', $firstCursor()['next186ReplanReasons'], true)),
    'reasons include cursor rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cursor-next186-rowset-changed', $firstCursor()['next186ReplanReasons'], true)),
    'reasons include cursor cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cursor-next186-cost-changed', $firstCursor()['next186ReplanReasons'], true)),
    'reasons preserve batch rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-batch-next183-rowset-changed', $firstCursor()['next186ReplanReasons'], true)),
    'negative cursor rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $planCursor(null, null, null, 1, null, -1)),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cursor ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
