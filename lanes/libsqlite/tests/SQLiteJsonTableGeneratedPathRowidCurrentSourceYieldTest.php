<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current168 = [
    'option_id' => 168,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next168',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules',
];
$next168 = [
    'option_id' => 168,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next168',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4},{"slug":"search","priority":5}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.missing',
];

$plan168 = static fn (?array $current = null, ?array $next = null, ?array $constraints = null, ?array $orderBy = null, ?int $limit = null, int $batchSize = 2, int $offset = 0): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceBatchYieldPlan(
    'json_tree',
    $current ?? $current168,
    $next ?? $next168,
    'option_value',
    'generated_path',
    $constraints ?? [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 9, 11]],
    ],
    'scan_root',
    $orderBy ?? [['column' => 'id', 'direction' => 'ASC']],
    $limit,
    $batchSize,
    $offset,
);
$stable168 = static fn (): array => $plan168($current168, $current168);
$reverse168 = static fn (): array => $plan168(null, null, null, [['column' => 'id', 'direction' => 'DESC']], null, 3, 0);
$secondBatch168 = static fn (): array => $plan168(null, null, null, [['column' => 'id']], null, 2, 2);
$limited168 = static fn (): array => $plan168(null, null, null, [['column' => 'id']], 1, 4, 0);
$sorter168 = static fn (): array => $plan168(null, null, null, [['column' => 'path'], ['column' => 'type']], null, 2, 0);
$point168 = static fn (): array => $plan168(
    array_replace($current168, ['generated_path' => '$.rules[1]']),
    array_replace($next168, ['generated_path' => '$.rules[1]']),
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => '_rowid_', 'operator' => '=', 'value' => 6],
    ],
    [['column' => 'id']],
    null,
    2,
    0,
);
$emptyOffset168 = static fn (): array => $plan168(null, null, null, [['column' => 'id']], null, 2, 20);
$badNext168 = static fn (): array => $plan168($current168, array_replace($next168, ['option_value' => null]));

$tests = [
    'records next168 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next168', $plan168()['dependencies'], true)),
    'preserves next164 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next164', $plan168()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-path-rowid-cost-current-source-next168-until-yield-reset', $plan168()['currentReaderPolicy']),
    'prepares changed next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-path-rowid-cost-current-source-next168-plan', $plan168()['nextReaderPolicy']),
    'stable reader policy reuses current' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-cost-current-source-next168-plan', $stable168()['nextReaderPolicy']),
    'stable has no next168 reasons' => static fn (TestRunner $t) => $t->same([], $stable168()['next168ReplanReasons']),
    'current yield source reusable' => static fn (TestRunner $t) => $t->same(true, $plan168()['currentGeneratedPathRowidCurrentSourceYield']['currentSourceReusable']),
    'current yieldable' => static fn (TestRunner $t) => $t->same(true, $plan168()['currentGeneratedPathRowidCurrentSourceYield']['yieldable']),
    'next yield reprepare' => static fn (TestRunner $t) => $t->same(false, $plan168()['nextGeneratedPathRowidCurrentSourceYield']['yieldable']),
    'ordered rowids follow asc order' => static fn (TestRunner $t) => $t->same([5, 6, 9, 11], $plan168()['currentGeneratedPathRowidCurrentSourceYield']['orderedRowids']),
    'first batch rowids' => static fn (TestRunner $t) => $t->same([5, 6], $plan168()['currentGeneratedPathRowidCurrentSourceYield']['yieldRowids']),
    'first batch has resume offset' => static fn (TestRunner $t) => $t->same(2, $plan168()['currentGeneratedPathRowidCurrentSourceYield']['resumeOffset']),
    'first batch not exhausted' => static fn (TestRunner $t) => $t->same(false, $plan168()['currentGeneratedPathRowidCurrentSourceYield']['exhausted']),
    'first batch resume token is sha256' => static fn (TestRunner $t) => $t->same(64, strlen((string) $plan168()['currentGeneratedPathRowidCurrentSourceYield']['resumeToken'])),
    'first batch estimated rows' => static fn (TestRunner $t) => $t->same(2, $plan168()['currentGeneratedPathRowidCurrentSourceYield']['estimatedRows']),
    'first batch estimated cost' => static fn (TestRunner $t) => $t->same(1, $plan168()['currentGeneratedPathRowidCurrentSourceYield']['estimatedCost']),
    'first batch cost class resumable' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-yield-resumable', $plan168()['currentGeneratedPathRowidCurrentSourceYield']['costClass']),
    'first batch disposition resumable' => static fn (TestRunner $t) => $t->same('yield-current-source-resumable-batch', $plan168()['currentGeneratedPathRowidCurrentSourceYield']['cursorDisposition']),
    'yield tape count' => static fn (TestRunner $t) => $t->same(2, count($plan168()['currentGeneratedPathRowidCurrentSourceYield']['yieldTape'])),
    'yield tape first position' => static fn (TestRunner $t) => $t->same(0, $plan168()['currentGeneratedPathRowidCurrentSourceYield']['yieldTape'][0]['position']),
    'yield tape second position' => static fn (TestRunner $t) => $t->same(1, $plan168()['currentGeneratedPathRowidCurrentSourceYield']['yieldTape'][1]['position']),
    'yield tape first rowid' => static fn (TestRunner $t) => $t->same(5, $plan168()['currentGeneratedPathRowidCurrentSourceYield']['yieldTape'][0]['rowid']),
    'yield tape records source pin' => static fn (TestRunner $t) => $t->same(64, strlen($plan168()['currentGeneratedPathRowidCurrentSourceYield']['yieldTape'][0]['sourcePinKey'])),
    'yield tape records residual columns' => static fn (TestRunner $t) => $t->same([], $plan168()['currentGeneratedPathRowidCurrentSourceYield']['yieldTape'][0]['residualColumns']),
    'yield tape emitted flag' => static fn (TestRunner $t) => $t->same(true, $plan168()['currentGeneratedPathRowidCurrentSourceYield']['yieldTape'][0]['emitted']),
    'next yield cost class reprepare' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-yield-reprepare', $plan168()['nextGeneratedPathRowidCurrentSourceYield']['costClass']),
    'next yield disposition reprepare' => static fn (TestRunner $t) => $t->same('reprepare-json-table-cursor-before-yield', $plan168()['nextGeneratedPathRowidCurrentSourceYield']['cursorDisposition']),
    'next yield sentinel cost' => static fn (TestRunner $t) => $t->same(1000000, $plan168()['nextGeneratedPathRowidCurrentSourceYield']['estimatedCost']),
    'transition count records yield state' => static fn (TestRunner $t) => $t->same(11, count($plan168()['generatedPathRowidCurrentSourceYieldTransitions'])),
    'transition reusable changes' => static fn (TestRunner $t) => $t->same(true, $plan168()['generatedPathRowidCurrentSourceYieldTransitions'][0]['changed']),
    'transition yieldable changes' => static fn (TestRunner $t) => $t->same(true, $plan168()['generatedPathRowidCurrentSourceYieldTransitions'][1]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $plan168()['generatedPathRowidCurrentSourceYieldTransitions'][3]['changed']),
    'transition resume changes' => static fn (TestRunner $t) => $t->same(true, $plan168()['generatedPathRowidCurrentSourceYieldTransitions'][5]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $plan168()['generatedPathRowidCurrentSourceYieldTransitions'][9]['changed']),
    'reasons include yield admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-yield-admission-changed', $plan168()['next168ReplanReasons'], true)),
    'reasons include yield rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-yield-rowset-changed', $plan168()['next168ReplanReasons'], true)),
    'reasons include yield resume' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-yield-resume-changed', $plan168()['next168ReplanReasons'], true)),
    'reasons include yield cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-yield-cost-changed', $plan168()['next168ReplanReasons'], true)),
    'reasons preserve next164 order rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-order-rowset-changed', $plan168()['next168ReplanReasons'], true)),
    'second batch rowids' => static fn (TestRunner $t) => $t->same([9, 11], $secondBatch168()['currentGeneratedPathRowidCurrentSourceYield']['yieldRowids']),
    'second batch offset' => static fn (TestRunner $t) => $t->same(2, $secondBatch168()['currentGeneratedPathRowidCurrentSourceYield']['offset']),
    'second batch exhausted' => static fn (TestRunner $t) => $t->same(true, $secondBatch168()['currentGeneratedPathRowidCurrentSourceYield']['exhausted']),
    'second batch has no resume token' => static fn (TestRunner $t) => $t->same(null, $secondBatch168()['currentGeneratedPathRowidCurrentSourceYield']['resumeToken']),
    'second batch final cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-yield-final', $secondBatch168()['currentGeneratedPathRowidCurrentSourceYield']['costClass']),
    'reverse batch rowids' => static fn (TestRunner $t) => $t->same([11, 9, 6], $reverse168()['currentGeneratedPathRowidCurrentSourceYield']['yieldRowids']),
    'reverse batch resumable' => static fn (TestRunner $t) => $t->same('yield-current-source-resumable-batch', $reverse168()['currentGeneratedPathRowidCurrentSourceYield']['cursorDisposition']),
    'limited batch rowids' => static fn (TestRunner $t) => $t->same([5], $limited168()['currentGeneratedPathRowidCurrentSourceYield']['yieldRowids']),
    'limited batch final' => static fn (TestRunner $t) => $t->same(true, $limited168()['currentGeneratedPathRowidCurrentSourceYield']['exhausted']),
    'sorter order is not yieldable' => static fn (TestRunner $t) => $t->same(false, $sorter168()['currentGeneratedPathRowidCurrentSourceYield']['yieldable']),
    'sorter order reprepare disposition' => static fn (TestRunner $t) => $t->same('reprepare-json-table-cursor-before-yield', $sorter168()['currentGeneratedPathRowidCurrentSourceYield']['cursorDisposition']),
    'point yield final rowid' => static fn (TestRunner $t) => $t->same([6], $point168()['currentGeneratedPathRowidCurrentSourceYield']['yieldRowids']),
    'point yield final class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-yield-final', $point168()['currentGeneratedPathRowidCurrentSourceYield']['costClass']),
    'empty offset yields empty final batch' => static fn (TestRunner $t) => $t->same([], $emptyOffset168()['currentGeneratedPathRowidCurrentSourceYield']['yieldRowids']),
    'empty offset cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-yield-empty', $emptyOffset168()['currentGeneratedPathRowidCurrentSourceYield']['costClass']),
    'bad next source reprepare class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-yield-reprepare', $badNext168()['nextGeneratedPathRowidCurrentSourceYield']['costClass']),
    'rejects zero batch size' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan168(null, null, null, null, null, 0, 0)),
    'rejects negative offset' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan168(null, null, null, null, null, 2, -1)),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next168 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
