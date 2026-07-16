<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current211 = [
    'option_id' => 211,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next211',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-211-a',
];
$next211 = [
    'option_id' => 211,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next211',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-211-b',
];

$plan211 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = 5,
    ?int $lastYieldedRowid = 7,
    ?int $yieldBatchSize = 1,
    ?array $projection = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidResumeCursor(
    'json_tree',
    $current ?? $current211,
    $next ?? $next211,
    'option_value',
    'generated_path',
    $constraints ?? [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
        ['column' => '_rowid_', 'operator' => 'BETWEEN', 'value' => [6, 9]],
    ],
    'scan_root',
    $orderBy ?? [['column' => 'rowid', 'direction' => 'ASC']],
    $limit,
    $lastYieldedRowid,
    $yieldBatchSize,
    $projection ?? ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey'],
);

$stable211 = static fn (): array => $plan211($current211, $current211);
$start211 = static fn (): array => $plan211($current211, $current211, null, null, 5, null, 2);
$desc211 = static fn (): array => $plan211($current211, $current211, null, [['column' => 'rowid', 'direction' => 'DESC']], 5, 8, 2);
$eof211 = static fn (): array => $plan211($current211, $current211, null, null, 5, 9, 2);
$zeroBatch211 = static fn (): array => $plan211($current211, $current211, null, null, 5, 7, 0);
$emptyRange211 = static fn (): array => $plan211($current211, $current211, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [20, 30]],
]);
$unsupportedOrder211 = static fn (): array => $plan211($current211, $current211, null, [['column' => 'fullkey', 'direction' => 'ASC']], 5, 7, 1);
$upperBound211 = static fn (): array => $plan211($current211, $current211, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'rowid', 'operator' => '<=', 'value' => 8],
], null, 5, 6, 4);
$greater211 = static fn (): array => $plan211($current211, $current211, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'oid', 'operator' => '>', 'value' => 6],
], null, 5, 7, 3);

$tests = [
    'records next211 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next211', $plan211()['dependencies'], true)),
    'preserves next209 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next209', $plan211()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('resume-current-source-rowid-json-table-generated-path-rowid-next211', $plan211()['currentReaderPolicy']),
    'next reader policy reparses' => static fn (TestRunner $t) => $t->same('reprepare-resume-next-source-rowid-json-table-generated-path-rowid-next211', $plan211()['nextReaderPolicy']),
    'stable reader policy reuses' => static fn (TestRunner $t) => $t->same('reuse-resume-current-source-rowid-json-table-generated-path-rowid-next211', $stable211()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable211()['next211ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $plan211()['currentGeneratedPathRowidCurrentSourceResume211']['function']),
    'current root recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan211()['currentGeneratedPathRowidCurrentSourceResume211']['root']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan211()['currentGeneratedPathRowidCurrentSourceResume211']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-211-a', $plan211()['currentGeneratedPathRowidCurrentSourceResume211']['sourceGeneration']),
    'range fingerprint carried' => static fn (TestRunner $t) => $t->same(64, strlen($plan211()['currentGeneratedPathRowidCurrentSourceResume211']['rangeFingerprint'])),
    'order direction asc' => static fn (TestRunner $t) => $t->same('ASC', $plan211()['currentGeneratedPathRowidCurrentSourceResume211']['orderDirection']),
    'last yielded recorded' => static fn (TestRunner $t) => $t->same(7, $plan211()['currentGeneratedPathRowidCurrentSourceResume211']['lastYieldedRowid']),
    'accepted range rowids' => static fn (TestRunner $t) => $t->same([6, 7, 8, 9], $plan211()['currentGeneratedPathRowidCurrentSourceResume211']['acceptedRangeRowids']),
    'resume rowids after high water' => static fn (TestRunner $t) => $t->same([8, 9], $plan211()['currentGeneratedPathRowidCurrentSourceResume211']['resumeRowids']),
    'yield rowids batch' => static fn (TestRunner $t) => $t->same([8], $plan211()['currentGeneratedPathRowidCurrentSourceResume211']['yieldRowids']),
    'deferred rowids batch' => static fn (TestRunner $t) => $t->same([9], $plan211()['currentGeneratedPathRowidCurrentSourceResume211']['deferredRowids']),
    'first resume rowid' => static fn (TestRunner $t) => $t->same(8, $plan211()['currentGeneratedPathRowidCurrentSourceResume211']['firstResumeRowid']),
    'last resume rowid' => static fn (TestRunner $t) => $t->same(9, $plan211()['currentGeneratedPathRowidCurrentSourceResume211']['lastResumeRowid']),
    'batch size recorded' => static fn (TestRunner $t) => $t->same(1, $plan211()['currentGeneratedPathRowidCurrentSourceResume211']['batchSize']),
    'range reusable true' => static fn (TestRunner $t) => $t->same(true, $plan211()['currentGeneratedPathRowidCurrentSourceResume211']['rangeReusable']),
    'resume reusable true' => static fn (TestRunner $t) => $t->same(true, $plan211()['currentGeneratedPathRowidCurrentSourceResume211']['resumeReusable']),
    'not eof after resume' => static fn (TestRunner $t) => $t->same(false, $plan211()['currentGeneratedPathRowidCurrentSourceResume211']['eofAfterResume']),
    'resume selectivity' => static fn (TestRunner $t) => $t->same(0.5, $plan211()['currentGeneratedPathRowidCurrentSourceResume211']['resumeSelectivity']),
    'skipped before resume' => static fn (TestRunner $t) => $t->same(2, $plan211()['currentGeneratedPathRowidCurrentSourceResume211']['skippedBeforeResume']),
    'estimated rows' => static fn (TestRunner $t) => $t->same(2, $plan211()['currentGeneratedPathRowidCurrentSourceResume211']['estimatedRows']),
    'estimated cost' => static fn (TestRunner $t) => $t->same(4, $plan211()['currentGeneratedPathRowidCurrentSourceResume211']['estimatedCost']),
    'resume opcode after rowid' => static fn (TestRunner $t) => $t->same('OP_JsonTableCurrentSourceResumeAfterRowidNext211', $plan211()['currentGeneratedPathRowidCurrentSourceResume211']['resumeOpcode']),
    'cost class window' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-resume-window-next211', $plan211()['currentGeneratedPathRowidCurrentSourceResume211']['costClass']),
    'resume fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan211()['currentGeneratedPathRowidCurrentSourceResume211']['resumeFingerprint'])),
    'next resume not reusable' => static fn (TestRunner $t) => $t->same(false, $plan211()['nextGeneratedPathRowidCurrentSourceResume211']['resumeReusable']),
    'next cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $plan211()['nextGeneratedPathRowidCurrentSourceResume211']['estimatedCost']),
    'next opcode reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableCurrentSourceResumeReprepareNext211', $plan211()['nextGeneratedPathRowidCurrentSourceResume211']['resumeOpcode']),
    'transition count' => static fn (TestRunner $t) => $t->same(23, count($plan211()['generatedPathRowidCurrentSourceResume211Transitions'])),
    'reasons include source changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-resume-source-changed-next211', $plan211()['next211ReplanReasons'], true)),
    'reasons include rowset changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-resume-rowset-changed-next211', $plan211()['next211ReplanReasons'], true)),
    'reasons include admission changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-resume-admission-changed-next211', $plan211()['next211ReplanReasons'], true)),
    'reasons include cost changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-resume-cost-changed-next211', $plan211()['next211ReplanReasons'], true)),
    'preserves next209 reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-range-source-changed-next209', $plan211()['next211ReplanReasons'], true)),
    'start rowids no high water' => static fn (TestRunner $t) => $t->same([6, 7, 8, 9], $start211()['currentGeneratedPathRowidCurrentSourceResume211']['resumeRowids']),
    'start yields first batch' => static fn (TestRunner $t) => $t->same([6, 7], $start211()['currentGeneratedPathRowidCurrentSourceResume211']['yieldRowids']),
    'start opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableCurrentSourceResumeStartNext211', $start211()['currentGeneratedPathRowidCurrentSourceResume211']['resumeOpcode']),
    'start cost class range' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-resume-start-range-next211', $start211()['currentGeneratedPathRowidCurrentSourceResume211']['costClass']),
    'desc direction recorded' => static fn (TestRunner $t) => $t->same('DESC', $desc211()['currentGeneratedPathRowidCurrentSourceResume211']['orderDirection']),
    'desc resumes below high water' => static fn (TestRunner $t) => $t->same([7, 6], $desc211()['currentGeneratedPathRowidCurrentSourceResume211']['resumeRowids']),
    'desc yields rowids' => static fn (TestRunner $t) => $t->same([7, 6], $desc211()['currentGeneratedPathRowidCurrentSourceResume211']['yieldRowids']),
    'desc final cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-resume-final-next211', $desc211()['currentGeneratedPathRowidCurrentSourceResume211']['costClass']),
    'eof opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableCurrentSourceResumeEofNext211', $eof211()['currentGeneratedPathRowidCurrentSourceResume211']['resumeOpcode']),
    'eof rowids empty' => static fn (TestRunner $t) => $t->same([], $eof211()['currentGeneratedPathRowidCurrentSourceResume211']['resumeRowids']),
    'eof marker true' => static fn (TestRunner $t) => $t->same(true, $eof211()['currentGeneratedPathRowidCurrentSourceResume211']['eofAfterResume']),
    'zero batch yield blocked opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableCurrentSourceResumeYieldBlockedNext211', $zeroBatch211()['currentGeneratedPathRowidCurrentSourceResume211']['resumeOpcode']),
    'zero batch deferred all resume rows' => static fn (TestRunner $t) => $t->same([8, 9], $zeroBatch211()['currentGeneratedPathRowidCurrentSourceResume211']['deferredRowids']),
    'zero batch cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-resume-yield-blocked-next211', $zeroBatch211()['currentGeneratedPathRowidCurrentSourceResume211']['costClass']),
    'empty range not reusable' => static fn (TestRunner $t) => $t->same(false, $emptyRange211()['currentGeneratedPathRowidCurrentSourceResume211']['resumeReusable']),
    'empty range opcode reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableCurrentSourceResumeReprepareNext211', $emptyRange211()['currentGeneratedPathRowidCurrentSourceResume211']['resumeOpcode']),
    'unsupported order blocks resume' => static fn (TestRunner $t) => $t->same(false, $unsupportedOrder211()['currentGeneratedPathRowidCurrentSourceResume211']['rangeReusable']),
    'upper bound resume rows' => static fn (TestRunner $t) => $t->same([7, 8], $upperBound211()['currentGeneratedPathRowidCurrentSourceResume211']['resumeRowids']),
    'upper bound yields all rows' => static fn (TestRunner $t) => $t->same([7, 8], $upperBound211()['currentGeneratedPathRowidCurrentSourceResume211']['yieldRowids']),
    'greater than resume rows' => static fn (TestRunner $t) => $t->same([8, 9], $greater211()['currentGeneratedPathRowidCurrentSourceResume211']['resumeRowids']),
    'malformed generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan211(array_replace($current211, ['generated_path' => '$.rules[']), $current211)),
    'bad function rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidResumeCursor('json_bad', $current211, $current211, 'option_value', 'generated_path')),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next211 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
