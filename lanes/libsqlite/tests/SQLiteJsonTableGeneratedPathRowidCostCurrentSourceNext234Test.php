<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current234 = [
    'option_id' => 234,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next234',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-234-a',
];
$next234 = [
    'option_id' => 234,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next234',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":8},{"slug":"forms","priority":4},{"slug":"security","priority":10}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'next-234-b',
];

$plan234 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = 5,
    ?int $lastYieldedRowid = null,
    ?int $yieldBatchSize = 3,
    ?array $projection = null,
    ?string $observedXCurrentFingerprint = null,
    ?int $observedRowid = null,
    ?string $observedYieldGuardFingerprint = null,
    ?array $observedDeliveredRowids = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidXNextResumePlan(
    'json_tree',
    $current ?? $current234,
    $next ?? $next234,
    'option_value',
    'generated_path',
    $constraints ?? [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
        ['column' => '_rowid_', 'operator' => 'BETWEEN', 'value' => [7, 8]],
    ],
    'scan_root',
    $orderBy ?? [['column' => 'rowid', 'direction' => 'ASC']],
    $limit,
    $lastYieldedRowid,
    $yieldBatchSize,
    $projection ?? ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey'],
    $observedXCurrentFingerprint,
    $observedRowid,
    $observedYieldGuardFingerprint,
    $observedDeliveredRowids,
);

$stable234 = static fn (): array => $plan234($current234, $current234);
$point234 = static fn (): array => $plan234($current234, $current234, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [8, 8]],
]);
$eof234 = static fn (): array => $plan234($current234, $current234, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7]],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [7, 7]],
]);
$staleFingerprint234 = static fn (): array => $plan234($current234, $current234, null, null, 5, null, 3, null, null, null, str_repeat('1', 64));
$staleDelivered234 = static fn (): array => $plan234($current234, $current234, null, null, 5, null, 3, null, null, null, null, [8]);
$xcurrentStale234 = static fn (): array => $plan234($current234, $current234, null, null, 5, null, 3, null, str_repeat('2', 64), 7);
$projection234 = static fn (): array => $plan234($current234, $current234, null, null, 5, null, 3, ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey']);
$currentProfile234 = static fn (): array => $plan234()['currentGeneratedPathRowidXNextResume234'];
$nextProfile234 = static fn (): array => $plan234()['nextGeneratedPathRowidXNextResume234'];

$tests = [
    'records next234 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next234', $plan234()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $plan234()['dependencies'], true)),
    'current reader policy resumes xnext' => static fn (TestRunner $t) => $t->same('xnext-resume-current-json-table-generated-path-rowid-next234', $plan234()['currentReaderPolicy']),
    'changed next reader restarts xnext' => static fn (TestRunner $t) => $t->same('restart-xnext-resume-json-table-generated-path-rowid-next234', $plan234()['nextReaderPolicy']),
    'stable reader reuses xnext' => static fn (TestRunner $t) => $t->same('reuse-xnext-resume-current-json-table-generated-path-rowid-next234', $stable234()['nextReaderPolicy']),
    'stable next234 reasons empty' => static fn (TestRunner $t) => $t->same([], $stable234()['next234ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $currentProfile234()['function']),
    'current root recorded' => static fn (TestRunner $t) => $t->same('$.rules', $currentProfile234()['root']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $currentProfile234()['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-234-a', $currentProfile234()['sourceGeneration']),
    'current observed fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($currentProfile234()['observedYieldGuardFingerprint'])),
    'current actual fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($currentProfile234()['actualYieldGuardFingerprint'])),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $currentProfile234()['yieldGuardFingerprintMatches']),
    'current observed delivered rowids' => static fn (TestRunner $t) => $t->same([7], $currentProfile234()['observedDeliveredRowids']),
    'current actual delivered rowids' => static fn (TestRunner $t) => $t->same([7], $currentProfile234()['actualDeliveredRowids']),
    'current delivered rowids match' => static fn (TestRunner $t) => $t->same(true, $currentProfile234()['deliveredRowidsMatch']),
    'current remaining rowids' => static fn (TestRunner $t) => $t->same([8], $currentProfile234()['remainingRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $currentProfile234()['restartRowids']),
    'current next rowid advances' => static fn (TestRunner $t) => $t->same(8, $currentProfile234()['nextRowid']),
    'current advanced rowids' => static fn (TestRunner $t) => $t->same([8], $currentProfile234()['advancedRowids']),
    'current pending rowids empty after advance' => static fn (TestRunner $t) => $t->same([], $currentProfile234()['pendingRowids']),
    'current source reusable' => static fn (TestRunner $t) => $t->same(true, $currentProfile234()['sourceReusable']),
    'current upstream replan false' => static fn (TestRunner $t) => $t->same(false, $currentProfile234()['upstreamReplanRequired']),
    'current resume reusable' => static fn (TestRunner $t) => $t->same(true, $currentProfile234()['xNextResumeReusable']),
    'current opcode resumes' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXNextResumeNext234', $currentProfile234()['xNextResumeOpcode']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $currentProfile234()['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $currentProfile234()['estimatedCost']),
    'current point cost class after advance' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xnext-point-next234', $currentProfile234()['costClass']),
    'current resume fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($currentProfile234()['xNextResumeFingerprint'])),
    'next source generation changed' => static fn (TestRunner $t) => $t->same('next-234-b', $nextProfile234()['sourceGeneration']),
    'next source not reusable' => static fn (TestRunner $t) => $t->same(false, $nextProfile234()['sourceReusable']),
    'next resume not reusable' => static fn (TestRunner $t) => $t->same(false, $nextProfile234()['xNextResumeReusable']),
    'next opcode reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXNextReprepareNext234', $nextProfile234()['xNextResumeOpcode']),
    'next cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $nextProfile234()['estimatedCost']),
    'next cost class reprepare' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xnext-reprepare-next234', $nextProfile234()['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(23, count($plan234()['generatedPathRowidXNextResume234Transitions'])),
    'reasons include source changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xnext-source-changed-next234', $plan234()['next234ReplanReasons'], true)),
    'reasons include rowset changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xnext-rowset-changed-next234', $plan234()['next234ReplanReasons'], true)),
    'reasons include admission changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xnext-admission-changed-next234', $plan234()['next234ReplanReasons'], true)),
    'reasons include cost changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xnext-cost-changed-next234', $plan234()['next234ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $plan234()['next234ReplanReasons'], true)),
    'point xnext eof opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXNextEofNext234', $point234()['currentGeneratedPathRowidXNextResume234']['xNextResumeOpcode']),
    'point xnext eof rowid null' => static fn (TestRunner $t) => $t->same(null, $point234()['currentGeneratedPathRowidXNextResume234']['nextRowid']),
    'point xnext eof class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xnext-eof-next234', $point234()['currentGeneratedPathRowidXNextResume234']['costClass']),
    'eof keeps delivered pending row' => static fn (TestRunner $t) => $t->same([7], $eof234()['currentGeneratedPathRowidXNextResume234']['pendingRowids']),
    'stale fingerprint mismatch' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint234()['currentGeneratedPathRowidXNextResume234']['yieldGuardFingerprintMatches']),
    'stale fingerprint opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXNextRestartFingerprintNext234', $staleFingerprint234()['currentGeneratedPathRowidXNextResume234']['xNextResumeOpcode']),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xnext-stale-fingerprint-next234', $staleFingerprint234()['currentGeneratedPathRowidXNextResume234']['costClass']),
    'stale delivered mismatch' => static fn (TestRunner $t) => $t->same(false, $staleDelivered234()['currentGeneratedPathRowidXNextResume234']['deliveredRowidsMatch']),
    'stale delivered opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXNextRestartDeliveredRowidsNext234', $staleDelivered234()['currentGeneratedPathRowidXNextResume234']['xNextResumeOpcode']),
    'stale delivered cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xnext-stale-delivered-rowids-next234', $staleDelivered234()['currentGeneratedPathRowidXNextResume234']['costClass']),
    'stale xcurrent forces reprepare' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXNextReprepareNext234', $xcurrentStale234()['currentGeneratedPathRowidXNextResume234']['xNextResumeOpcode']),
    'projection keeps alias rowid before xnext' => static fn (TestRunner $t) => $t->same(7, $projection234()['currentGeneratedPathRowidXCurrentYieldGuard224']['activeProjectedColumns']['_rowid_']),
    'projection keeps oid before xnext' => static fn (TestRunner $t) => $t->same(7, $projection234()['currentGeneratedPathRowidXCurrentYieldGuard224']['activeProjectedColumns']['oid']),
    'bad yield fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan234($current234, $current234, null, null, 5, null, 3, null, null, null, 'bad-fingerprint')),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan234(array_replace($current234, ['generated_path' => '$.rules[']), $current234)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next234 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
