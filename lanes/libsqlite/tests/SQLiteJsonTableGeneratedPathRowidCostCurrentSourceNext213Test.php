<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current213 = [
    'option_id' => 213,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next213',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-213-a',
];
$next213 = [
    'option_id' => 213,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next213',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-213-b',
];

$plan213 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = 5,
    ?int $lastYieldedRowid = 7,
    ?int $yieldBatchSize = 1,
    ?array $projection = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext213(
    'json_tree',
    $current ?? $current213,
    $next ?? $next213,
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

$stable213 = static fn (): array => $plan213($current213, $current213);
$start213 = static fn (): array => $plan213($current213, $current213, null, null, 5, null, 2);
$desc213 = static fn (): array => $plan213($current213, $current213, null, [['column' => 'rowid', 'direction' => 'DESC']], 5, 8, 2);
$eof213 = static fn (): array => $plan213($current213, $current213, null, null, 5, 9, 2);
$zeroBatch213 = static fn (): array => $plan213($current213, $current213, null, null, 5, 7, 0);
$emptyRange213 = static fn (): array => $plan213($current213, $current213, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [20, 30]],
]);
$unsupportedOrder213 = static fn (): array => $plan213($current213, $current213, null, [['column' => 'fullkey', 'direction' => 'ASC']], 5, 7, 1);
$upperBound213 = static fn (): array => $plan213($current213, $current213, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'rowid', 'operator' => '<=', 'value' => 8],
], null, 5, 6, 4);
$greater213 = static fn (): array => $plan213($current213, $current213, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'oid', 'operator' => '>', 'value' => 6],
], null, 5, 7, 3);

$tests = [
    'records next213 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next213', $plan213()['dependencies'], true)),
    'preserves next209 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next209', $plan213()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('resume-current-source-rowid-json-table-generated-path-rowid-next213', $plan213()['currentReaderPolicy']),
    'next reader policy reparses' => static fn (TestRunner $t) => $t->same('reprepare-resume-next-source-rowid-json-table-generated-path-rowid-next213', $plan213()['nextReaderPolicy']),
    'stable reader policy reuses' => static fn (TestRunner $t) => $t->same('reuse-resume-current-source-rowid-json-table-generated-path-rowid-next213', $stable213()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable213()['next213ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $plan213()['currentGeneratedPathRowidCurrentSourceResume213']['function']),
    'current root recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan213()['currentGeneratedPathRowidCurrentSourceResume213']['root']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan213()['currentGeneratedPathRowidCurrentSourceResume213']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-213-a', $plan213()['currentGeneratedPathRowidCurrentSourceResume213']['sourceGeneration']),
    'range fingerprint carried' => static fn (TestRunner $t) => $t->same(64, strlen($plan213()['currentGeneratedPathRowidCurrentSourceResume213']['rangeFingerprint'])),
    'order direction asc' => static fn (TestRunner $t) => $t->same('ASC', $plan213()['currentGeneratedPathRowidCurrentSourceResume213']['orderDirection']),
    'last yielded recorded' => static fn (TestRunner $t) => $t->same(7, $plan213()['currentGeneratedPathRowidCurrentSourceResume213']['lastYieldedRowid']),
    'last yielded resume advances to yielded rowid' => static fn (TestRunner $t) => $t->same(8, $plan213()['currentGeneratedPathRowidCurrentSourceResume213']['lastYieldedResumeRowid']),
    'resume checkpoint advanced' => static fn (TestRunner $t) => $t->same(true, $plan213()['currentGeneratedPathRowidCurrentSourceResume213']['resumeCheckpointAdvanced']),
    'accepted range rowids' => static fn (TestRunner $t) => $t->same([6, 7, 8, 9], $plan213()['currentGeneratedPathRowidCurrentSourceResume213']['acceptedRangeRowids']),
    'resume rowids after high water' => static fn (TestRunner $t) => $t->same([8, 9], $plan213()['currentGeneratedPathRowidCurrentSourceResume213']['resumeRowids']),
    'yield rowids batch' => static fn (TestRunner $t) => $t->same([8], $plan213()['currentGeneratedPathRowidCurrentSourceResume213']['yieldRowids']),
    'deferred rowids batch' => static fn (TestRunner $t) => $t->same([9], $plan213()['currentGeneratedPathRowidCurrentSourceResume213']['deferredRowids']),
    'first resume rowid' => static fn (TestRunner $t) => $t->same(8, $plan213()['currentGeneratedPathRowidCurrentSourceResume213']['firstResumeRowid']),
    'last resume rowid' => static fn (TestRunner $t) => $t->same(9, $plan213()['currentGeneratedPathRowidCurrentSourceResume213']['lastResumeRowid']),
    'batch size recorded' => static fn (TestRunner $t) => $t->same(1, $plan213()['currentGeneratedPathRowidCurrentSourceResume213']['batchSize']),
    'range reusable true' => static fn (TestRunner $t) => $t->same(true, $plan213()['currentGeneratedPathRowidCurrentSourceResume213']['rangeReusable']),
    'resume reusable true' => static fn (TestRunner $t) => $t->same(true, $plan213()['currentGeneratedPathRowidCurrentSourceResume213']['resumeReusable']),
    'not eof after resume' => static fn (TestRunner $t) => $t->same(false, $plan213()['currentGeneratedPathRowidCurrentSourceResume213']['eofAfterResume']),
    'resume selectivity' => static fn (TestRunner $t) => $t->same(0.5, $plan213()['currentGeneratedPathRowidCurrentSourceResume213']['resumeSelectivity']),
    'skipped before resume' => static fn (TestRunner $t) => $t->same(2, $plan213()['currentGeneratedPathRowidCurrentSourceResume213']['skippedBeforeResume']),
    'estimated rows' => static fn (TestRunner $t) => $t->same(2, $plan213()['currentGeneratedPathRowidCurrentSourceResume213']['estimatedRows']),
    'estimated cost' => static fn (TestRunner $t) => $t->same(4, $plan213()['currentGeneratedPathRowidCurrentSourceResume213']['estimatedCost']),
    'resume opcode after rowid' => static fn (TestRunner $t) => $t->same('OP_JsonTableCurrentSourceResumeAfterRowidNext213', $plan213()['currentGeneratedPathRowidCurrentSourceResume213']['resumeOpcode']),
    'cost class window' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-resume-window-next213', $plan213()['currentGeneratedPathRowidCurrentSourceResume213']['costClass']),
    'resume fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan213()['currentGeneratedPathRowidCurrentSourceResume213']['resumeFingerprint'])),
    'next resume not reusable' => static fn (TestRunner $t) => $t->same(false, $plan213()['nextGeneratedPathRowidCurrentSourceResume213']['resumeReusable']),
    'next cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $plan213()['nextGeneratedPathRowidCurrentSourceResume213']['estimatedCost']),
    'next opcode reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableCurrentSourceResumeReprepareNext213', $plan213()['nextGeneratedPathRowidCurrentSourceResume213']['resumeOpcode']),
    'transition count' => static fn (TestRunner $t) => $t->same(26, count($plan213()['generatedPathRowidCurrentSourceResume213Transitions'])),
    'reasons include source changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-resume-source-changed-next213', $plan213()['next213ReplanReasons'], true)),
    'reasons include rowset changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-resume-rowset-changed-next213', $plan213()['next213ReplanReasons'], true)),
    'reasons include admission changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-resume-admission-changed-next213', $plan213()['next213ReplanReasons'], true)),
    'reasons include cost changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-resume-cost-changed-next213', $plan213()['next213ReplanReasons'], true)),
    'preserves next209 reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-range-source-changed-next209', $plan213()['next213ReplanReasons'], true)),
    'start rowids no high water' => static fn (TestRunner $t) => $t->same([6, 7, 8, 9], $start213()['currentGeneratedPathRowidCurrentSourceResume213']['resumeRowids']),
    'start yields first batch' => static fn (TestRunner $t) => $t->same([6, 7], $start213()['currentGeneratedPathRowidCurrentSourceResume213']['yieldRowids']),
    'start opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableCurrentSourceResumeStartNext213', $start213()['currentGeneratedPathRowidCurrentSourceResume213']['resumeOpcode']),
    'start cost class range' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-resume-start-range-next213', $start213()['currentGeneratedPathRowidCurrentSourceResume213']['costClass']),
    'desc direction recorded' => static fn (TestRunner $t) => $t->same('DESC', $desc213()['currentGeneratedPathRowidCurrentSourceResume213']['orderDirection']),
    'desc resumes below high water' => static fn (TestRunner $t) => $t->same([7, 6], $desc213()['currentGeneratedPathRowidCurrentSourceResume213']['resumeRowids']),
    'desc yields rowids' => static fn (TestRunner $t) => $t->same([7, 6], $desc213()['currentGeneratedPathRowidCurrentSourceResume213']['yieldRowids']),
    'desc final cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-resume-final-next213', $desc213()['currentGeneratedPathRowidCurrentSourceResume213']['costClass']),
    'eof opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableCurrentSourceResumeEofNext213', $eof213()['currentGeneratedPathRowidCurrentSourceResume213']['resumeOpcode']),
    'eof rowids empty' => static fn (TestRunner $t) => $t->same([], $eof213()['currentGeneratedPathRowidCurrentSourceResume213']['resumeRowids']),
    'eof marker true' => static fn (TestRunner $t) => $t->same(true, $eof213()['currentGeneratedPathRowidCurrentSourceResume213']['eofAfterResume']),
    'zero batch yield blocked opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableCurrentSourceResumeYieldBlockedNext213', $zeroBatch213()['currentGeneratedPathRowidCurrentSourceResume213']['resumeOpcode']),
    'zero batch keeps last yielded resume' => static fn (TestRunner $t) => $t->same(7, $zeroBatch213()['currentGeneratedPathRowidCurrentSourceResume213']['lastYieldedResumeRowid']),
    'zero batch deferred all resume rows' => static fn (TestRunner $t) => $t->same([8, 9], $zeroBatch213()['currentGeneratedPathRowidCurrentSourceResume213']['deferredRowids']),
    'zero batch cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-resume-yield-blocked-next213', $zeroBatch213()['currentGeneratedPathRowidCurrentSourceResume213']['costClass']),
    'empty range not reusable' => static fn (TestRunner $t) => $t->same(false, $emptyRange213()['currentGeneratedPathRowidCurrentSourceResume213']['resumeReusable']),
    'empty range opcode reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableCurrentSourceResumeReprepareNext213', $emptyRange213()['currentGeneratedPathRowidCurrentSourceResume213']['resumeOpcode']),
    'unsupported order blocks resume' => static fn (TestRunner $t) => $t->same(false, $unsupportedOrder213()['currentGeneratedPathRowidCurrentSourceResume213']['rangeReusable']),
    'upper bound resume rows' => static fn (TestRunner $t) => $t->same([7, 8], $upperBound213()['currentGeneratedPathRowidCurrentSourceResume213']['resumeRowids']),
    'upper bound yields all rows' => static fn (TestRunner $t) => $t->same([7, 8], $upperBound213()['currentGeneratedPathRowidCurrentSourceResume213']['yieldRowids']),
    'greater than resume rows' => static fn (TestRunner $t) => $t->same([8, 9], $greater213()['currentGeneratedPathRowidCurrentSourceResume213']['resumeRowids']),
    'malformed generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan213(array_replace($current213, ['generated_path' => '$.rules[']), $current213)),
    'bad function rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext213('json_bad', $current213, $current213, 'option_value', 'generated_path')),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next213 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
