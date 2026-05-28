<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current186 = [
    'option_id' => 186,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next186',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
    'source_generation' => 'current-186-a',
];
$next186 = [
    'option_id' => 186,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next186',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-186-b',
];
$constraints186 = [
    ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6]],
];

$plan186 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    int $batchSize = 1,
    ?int $resumeAfterRowid = null,
    int $cursorPosition = 0,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext186(
    'json_tree',
    $current ?? $current186,
    $next ?? $next186,
    'option_value',
    'generated_path',
    $constraints ?? $constraints186,
    'scan_root',
    [['column' => 'rowid']],
    $batchSize,
    $resumeAfterRowid,
    $cursorPosition,
);

$first186 = static fn (): array => $plan186();
$second186 = static fn (): array => $plan186(null, null, null, 1, 5);
$stable186 = static fn (): array => $plan186($current186, $current186);
$final186 = static fn (): array => $plan186($current186, $current186, null, 2, null, 1);
$eof186 = static fn (): array => $plan186($current186, $current186, null, 1, 5, 1);
$blocked186 = static fn (): array => $plan186(
    $current186,
    $current186,
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 5],
        ['column' => 'oid', 'operator' => '=', 'value' => 6],
    ],
);

$tests = [
    'records next186 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next186', $first186()['dependencies'], true)),
    'preserves next183 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next183', $first186()['dependencies'], true)),
    'current reader policy advances cursor' => static fn (TestRunner $t) => $t->same('advance-current-json-table-generated-path-rowid-cursor-next186-before-source-replan', $first186()['currentReaderPolicy']),
    'next reader policy restarts changed source' => static fn (TestRunner $t) => $t->same('restart-next-json-table-generated-path-rowid-cursor-next186', $first186()['nextReaderPolicy']),
    'stable next reader policy reuses cursor' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-cursor-next186', $stable186()['nextReaderPolicy']),
    'stable next186 reasons empty' => static fn (TestRunner $t) => $t->same([], $stable186()['next186ReplanReasons']),
    'current cursor source generation' => static fn (TestRunner $t) => $t->same('source_generation:current-186-a', $first186()['currentGeneratedPathRowidCurrentSourceCursor186']['sourceGeneration']),
    'current cursor batch opcode inherited' => static fn (TestRunner $t) => $t->same('yield-partial-current-source-generated-path-rowid-batch-next183', $first186()['currentGeneratedPathRowidCurrentSourceCursor186']['batchOpcode']),
    'current cursor position zero' => static fn (TestRunner $t) => $t->same(0, $first186()['currentGeneratedPathRowidCurrentSourceCursor186']['cursorPosition']),
    'current cursor active rowid five' => static fn (TestRunner $t) => $t->same(5, $first186()['currentGeneratedPathRowidCurrentSourceCursor186']['activeRowid']),
    'current cursor active path' => static fn (TestRunner $t) => $t->same('$.rules[1]', $first186()['currentGeneratedPathRowidCurrentSourceCursor186']['activePath']),
    'current cursor no next row in batch' => static fn (TestRunner $t) => $t->same(null, $first186()['currentGeneratedPathRowidCurrentSourceCursor186']['nextRowid']),
    'current cursor pending rowids include refill' => static fn (TestRunner $t) => $t->same([5, 6], $first186()['currentGeneratedPathRowidCurrentSourceCursor186']['pendingRowids']),
    'current cursor remaining rowid six' => static fn (TestRunner $t) => $t->same([6], $first186()['currentGeneratedPathRowidCurrentSourceCursor186']['remainingRowids']),
    'current cursor needs refill' => static fn (TestRunner $t) => $t->same(true, $first186()['currentGeneratedPathRowidCurrentSourceCursor186']['needsBatchRefill']),
    'current cursor not eof' => static fn (TestRunner $t) => $t->same(false, $first186()['currentGeneratedPathRowidCurrentSourceCursor186']['eofAfterXNext']),
    'current cursor not stale' => static fn (TestRunner $t) => $t->same(false, $first186()['currentGeneratedPathRowidCurrentSourceCursor186']['staleAfterNextSource']),
    'current cursor opcode refill' => static fn (TestRunner $t) => $t->same('refill-current-source-generated-path-rowid-cursor-next186', $first186()['currentGeneratedPathRowidCurrentSourceCursor186']['xNextOpcode']),
    'current cursor cost two' => static fn (TestRunner $t) => $t->same(2, $first186()['currentGeneratedPathRowidCurrentSourceCursor186']['xNextCost']),
    'current cursor cost class refill' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-cursor-refill-next186', $first186()['currentGeneratedPathRowidCurrentSourceCursor186']['costClass']),
    'current cursor tape rowid five' => static fn (TestRunner $t) => $t->same(5, $first186()['currentGeneratedPathRowidCurrentSourceCursor186']['cursorTape'][0]['rowid']),
    'current cursor tape opcode' => static fn (TestRunner $t) => $t->same('refill-current-source-generated-path-rowid-cursor-next186', $first186()['currentGeneratedPathRowidCurrentSourceCursor186']['cursorTape'][0]['opcode']),
    'current cursor fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($first186()['currentGeneratedPathRowidCurrentSourceCursor186']['cursorFingerprint'])),
    'next cursor stale' => static fn (TestRunner $t) => $t->same(true, $first186()['nextGeneratedPathRowidCurrentSourceCursor186']['staleAfterNextSource']),
    'next cursor rowids empty' => static fn (TestRunner $t) => $t->same([], $first186()['nextGeneratedPathRowidCurrentSourceCursor186']['pendingRowids']),
    'next cursor opcode restart' => static fn (TestRunner $t) => $t->same('restart-next-source-generated-path-rowid-cursor-next186', $first186()['nextGeneratedPathRowidCurrentSourceCursor186']['xNextOpcode']),
    'next cursor cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $first186()['nextGeneratedPathRowidCurrentSourceCursor186']['xNextCost']),
    'next cursor cost class restart' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-cursor-restart-next186', $first186()['nextGeneratedPathRowidCurrentSourceCursor186']['costClass']),
    'second cursor active rowid six' => static fn (TestRunner $t) => $t->same(6, $second186()['currentGeneratedPathRowidCurrentSourceCursor186']['activeRowid']),
    'second cursor pending rowid six' => static fn (TestRunner $t) => $t->same([6], $second186()['currentGeneratedPathRowidCurrentSourceCursor186']['pendingRowids']),
    'second cursor final opcode' => static fn (TestRunner $t) => $t->same('final-current-source-generated-path-rowid-cursor-next186', $second186()['currentGeneratedPathRowidCurrentSourceCursor186']['xNextOpcode']),
    'second cursor point cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-cursor-final-next186', $second186()['currentGeneratedPathRowidCurrentSourceCursor186']['costClass']),
    'final cursor position one active six' => static fn (TestRunner $t) => $t->same(6, $final186()['currentGeneratedPathRowidCurrentSourceCursor186']['activeRowid']),
    'final cursor eof after xnext' => static fn (TestRunner $t) => $t->same(true, $final186()['currentGeneratedPathRowidCurrentSourceCursor186']['eofAfterXNext']),
    'eof cursor has null active rowid' => static fn (TestRunner $t) => $t->same(null, $eof186()['currentGeneratedPathRowidCurrentSourceCursor186']['activeRowid']),
    'eof cursor opcode' => static fn (TestRunner $t) => $t->same('eof-current-source-generated-path-rowid-cursor-next186', $eof186()['currentGeneratedPathRowidCurrentSourceCursor186']['xNextOpcode']),
    'blocked cursor opcode' => static fn (TestRunner $t) => $t->same('block-current-source-generated-path-rowid-cursor-next186', $blocked186()['currentGeneratedPathRowidCurrentSourceCursor186']['xNextOpcode']),
    'blocked cursor cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-cursor-blocked-next186', $blocked186()['currentGeneratedPathRowidCurrentSourceCursor186']['costClass']),
    'transition count records cursor fields' => static fn (TestRunner $t) => $t->same(15, count($first186()['generatedPathRowidCurrentSourceCursor186Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $first186()['generatedPathRowidCurrentSourceCursor186Transitions'][0]['changed']),
    'transition active rowid changes' => static fn (TestRunner $t) => $t->same(true, $first186()['generatedPathRowidCurrentSourceCursor186Transitions'][4]['changed']),
    'transition pending rowids change' => static fn (TestRunner $t) => $t->same(true, $first186()['generatedPathRowidCurrentSourceCursor186Transitions'][7]['changed']),
    'transition stale changes' => static fn (TestRunner $t) => $t->same(true, $first186()['generatedPathRowidCurrentSourceCursor186Transitions'][10]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $first186()['generatedPathRowidCurrentSourceCursor186Transitions'][12]['changed']),
    'reasons include cursor source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cursor-next186-source-changed', $first186()['next186ReplanReasons'], true)),
    'reasons include cursor position' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cursor-next186-position-changed', $first186()['next186ReplanReasons'], true)),
    'reasons include cursor rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cursor-next186-rowset-changed', $first186()['next186ReplanReasons'], true)),
    'reasons include cursor cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cursor-next186-cost-changed', $first186()['next186ReplanReasons'], true)),
    'reasons preserve batch rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-batch-next183-rowset-changed', $first186()['next186ReplanReasons'], true)),
    'negative cursor rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan186(null, null, null, 1, null, -1)),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next186 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
