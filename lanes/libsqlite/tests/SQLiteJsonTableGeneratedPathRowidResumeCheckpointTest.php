<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current185 = [
    'option_id' => 185,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next185',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-185-a',
];
$next185 = [
    'option_id' => 185,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next185',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4},{"slug":"audit","priority":5}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-185-b',
];

$plan185 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = 5,
    ?int $lastYieldedRowid = 9,
    ?int $yieldBatchSize = 1,
    ?array $projection = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidResumeCheckpointPlan(
    'json_tree',
    $current ?? $current185,
    $next ?? $next185,
    'option_value',
    'generated_path',
    $constraints ?? [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ],
    'scan_root',
    $orderBy ?? [['column' => '_rowid_', 'direction' => 'DESC']],
    $limit,
    $lastYieldedRowid,
    $yieldBatchSize,
    $projection ?? ['key', 'value', 'type', 'id', 'fullkey', 'path'],
);

$stable185 = static fn (): array => $plan185($current185, $current185);
$final185 = static fn (): array => $plan185($current185, $current185, null, null, 5, 7, 3);
$limit185 = static fn (): array => $plan185($current185, $current185, null, null, 1, 9, 2);
$missing185 = static fn (): array => $plan185($current185, $current185, null, null, 5, 99, 2);
$projection185 = static fn (): array => $plan185($current185, $current185, null, null, 5, 9, 1, ['value', 'atom', 'id']);

$tests = [
    'records next185 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next185', $plan185()['dependencies'], true)),
    'preserves next182 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next182', $plan185()['dependencies'], true)),
    'current reader policy checkpoints' => static fn (TestRunner $t) => $t->same('checkpoint-current-json-table-generated-path-rowid-cost-current-source-next185', $plan185()['currentReaderPolicy']),
    'changed next reader policy restarts' => static fn (TestRunner $t) => $t->same('restart-next-json-table-generated-path-rowid-cost-current-source-next185', $plan185()['nextReaderPolicy']),
    'stable next reader policy resumes' => static fn (TestRunner $t) => $t->same('resume-current-json-table-generated-path-rowid-cost-current-source-next185', $stable185()['nextReaderPolicy']),
    'stable next185 reasons empty' => static fn (TestRunner $t) => $t->same([], $stable185()['next185ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $plan185()['currentGeneratedPathRowidCurrentSourceResume185']['function']),
    'current json kind text' => static fn (TestRunner $t) => $t->same('text', $plan185()['currentGeneratedPathRowidCurrentSourceResume185']['jsonSourceKind']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('source_generation:current-185-a', $plan185()['currentGeneratedPathRowidCurrentSourceResume185']['sourceGeneration']),
    'current cache key is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan185()['currentGeneratedPathRowidCurrentSourceResume185']['cacheKey'])),
    'current cursor generation is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan185()['currentGeneratedPathRowidCurrentSourceResume185']['cursorGeneration'])),
    'current admission fingerprint is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan185()['currentGeneratedPathRowidCurrentSourceResume185']['admissionFingerprint'])),
    'current resume token is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan185()['currentGeneratedPathRowidCurrentSourceResume185']['resumeToken'])),
    'current projection normalized' => static fn (TestRunner $t) => $t->same(['key', 'value', 'type', 'id', 'fullkey', 'path'], $plan185()['currentGeneratedPathRowidCurrentSourceResume185']['projection']),
    'current delivered rowids from xnext batch' => static fn (TestRunner $t) => $t->same([8], $plan185()['currentGeneratedPathRowidCurrentSourceResume185']['deliveredRowids']),
    'current blocked rowids remain' => static fn (TestRunner $t) => $t->same([7, 6, 5], $plan185()['currentGeneratedPathRowidCurrentSourceResume185']['blockedRowids']),
    'current last delivered rowid' => static fn (TestRunner $t) => $t->same(8, $plan185()['currentGeneratedPathRowidCurrentSourceResume185']['lastDeliveredRowid']),
    'current next resume ordinal' => static fn (TestRunner $t) => $t->same(2, $plan185()['currentGeneratedPathRowidCurrentSourceResume185']['nextResumeOrdinal']),
    'current projected one row' => static fn (TestRunner $t) => $t->same(1, count($plan185()['currentGeneratedPathRowidCurrentSourceResume185']['projectedRows'])),
    'current projected rowid' => static fn (TestRunner $t) => $t->same(8, $plan185()['currentGeneratedPathRowidCurrentSourceResume185']['projectedRows'][0]['rowid']),
    'current projected key' => static fn (TestRunner $t) => $t->same('slug', $plan185()['currentGeneratedPathRowidCurrentSourceResume185']['projectedRows'][0]['key']),
    'current projected value' => static fn (TestRunner $t) => $t->same('forms', $plan185()['currentGeneratedPathRowidCurrentSourceResume185']['projectedRows'][0]['value']),
    'current projected type' => static fn (TestRunner $t) => $t->same('text', $plan185()['currentGeneratedPathRowidCurrentSourceResume185']['projectedRows'][0]['type']),
    'current projected fullkey' => static fn (TestRunner $t) => $t->same('$.rules[2].slug', $plan185()['currentGeneratedPathRowidCurrentSourceResume185']['projectedRows'][0]['fullkey']),
    'current projected path' => static fn (TestRunner $t) => $t->same('$.rules[2]', $plan185()['currentGeneratedPathRowidCurrentSourceResume185']['projectedRows'][0]['path']),
    'current missing rowids empty' => static fn (TestRunner $t) => $t->same([], $plan185()['currentGeneratedPathRowidCurrentSourceResume185']['missingRowids']),
    'current checkpoint tape one step' => static fn (TestRunner $t) => $t->same(1, count($plan185()['currentGeneratedPathRowidCurrentSourceResume185']['checkpointTape'])),
    'current checkpoint tape ordinal' => static fn (TestRunner $t) => $t->same(1, $plan185()['currentGeneratedPathRowidCurrentSourceResume185']['checkpointTape'][0]['ordinal']),
    'current checkpoint tape rowid' => static fn (TestRunner $t) => $t->same(8, $plan185()['currentGeneratedPathRowidCurrentSourceResume185']['checkpointTape'][0]['rowid']),
    'current checkpoint tape source pinned' => static fn (TestRunner $t) => $t->same(true, $plan185()['currentGeneratedPathRowidCurrentSourceResume185']['checkpointTape'][0]['sourcePinned']),
    'current checkpoint reusable' => static fn (TestRunner $t) => $t->same(true, $plan185()['currentGeneratedPathRowidCurrentSourceResume185']['checkpointReusable']),
    'current not stale' => static fn (TestRunner $t) => $t->same(false, $plan185()['currentGeneratedPathRowidCurrentSourceResume185']['staleAfterNextSource']),
    'current not eof after batch' => static fn (TestRunner $t) => $t->same(false, $plan185()['currentGeneratedPathRowidCurrentSourceResume185']['eofAfterBatch']),
    'current estimated rows one' => static fn (TestRunner $t) => $t->same(1, $plan185()['currentGeneratedPathRowidCurrentSourceResume185']['estimatedRows']),
    'current estimated cost one' => static fn (TestRunner $t) => $t->same(1, $plan185()['currentGeneratedPathRowidCurrentSourceResume185']['estimatedCost']),
    'current cost class batched' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-resume-batched-current-source-next185', $plan185()['currentGeneratedPathRowidCurrentSourceResume185']['costClass']),
    'next source restart is stale' => static fn (TestRunner $t) => $t->same(true, $plan185()['nextGeneratedPathRowidCurrentSourceResume185']['staleAfterNextSource']),
    'next source delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $plan185()['nextGeneratedPathRowidCurrentSourceResume185']['deliveredRowids']),
    'next source projected rows empty' => static fn (TestRunner $t) => $t->same([], $plan185()['nextGeneratedPathRowidCurrentSourceResume185']['projectedRows']),
    'next source checkpoint not reusable' => static fn (TestRunner $t) => $t->same(false, $plan185()['nextGeneratedPathRowidCurrentSourceResume185']['checkpointReusable']),
    'next source cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $plan185()['nextGeneratedPathRowidCurrentSourceResume185']['estimatedCost']),
    'next source cost class restarts' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-resume-restart-next-source-next185', $plan185()['nextGeneratedPathRowidCurrentSourceResume185']['costClass']),
    'final batch delivered rowids' => static fn (TestRunner $t) => $t->same([6, 5], $final185()['currentGeneratedPathRowidCurrentSourceResume185']['deliveredRowids']),
    'final batch last delivered' => static fn (TestRunner $t) => $t->same(5, $final185()['currentGeneratedPathRowidCurrentSourceResume185']['lastDeliveredRowid']),
    'final batch eof true' => static fn (TestRunner $t) => $t->same(true, $final185()['currentGeneratedPathRowidCurrentSourceResume185']['eofAfterBatch']),
    'final batch cost class final' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-resume-final-current-source-next185', $final185()['currentGeneratedPathRowidCurrentSourceResume185']['costClass']),
    'limit fence blocks checkpoint' => static fn (TestRunner $t) => $t->same([], $limit185()['currentGeneratedPathRowidCurrentSourceResume185']['deliveredRowids']),
    'limit fence cost class blocked' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-resume-blocked-current-source-next185', $limit185()['currentGeneratedPathRowidCurrentSourceResume185']['costClass']),
    'missing rowid blocks checkpoint' => static fn (TestRunner $t) => $t->same(false, $missing185()['currentGeneratedPathRowidCurrentSourceResume185']['checkpointReusable']),
    'projection can be narrowed' => static fn (TestRunner $t) => $t->same(['value', 'atom', 'id'], $projection185()['currentGeneratedPathRowidCurrentSourceResume185']['projection']),
    'projection row keeps atom scalar for text' => static fn (TestRunner $t) => $t->same('forms', $projection185()['currentGeneratedPathRowidCurrentSourceResume185']['projectedRows'][0]['atom']),
    'transition count records resume fields' => static fn (TestRunner $t) => $t->same(19, count($plan185()['generatedPathRowidCurrentSourceResume185Transitions'])),
    'transition resume token changes' => static fn (TestRunner $t) => $t->same(true, $plan185()['generatedPathRowidCurrentSourceResume185Transitions'][5]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $plan185()['generatedPathRowidCurrentSourceResume185Transitions'][7]['changed']),
    'transition reuse changes' => static fn (TestRunner $t) => $t->same(true, $plan185()['generatedPathRowidCurrentSourceResume185Transitions'][13]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $plan185()['generatedPathRowidCurrentSourceResume185Transitions'][17]['changed']),
    'reasons include source fence' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-resume-source-fence-changed-next185', $plan185()['next185ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-resume-rowset-changed-next185', $plan185()['next185ReplanReasons'], true)),
    'reasons include reuse' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-resume-reuse-changed-next185', $plan185()['next185ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-resume-cost-changed-next185', $plan185()['next185ReplanReasons'], true)),
    'bad projection rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan185(null, null, null, null, 5, 9, 1, ['bad_column'])),
    'bad root rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan185(array_replace($current185, ['scan_root' => 12]), $current185)),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid resume checkpoint ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
