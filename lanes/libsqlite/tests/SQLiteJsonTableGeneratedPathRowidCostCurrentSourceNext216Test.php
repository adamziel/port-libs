<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current216 = [
    'option_id' => 216,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next216',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-216-a',
];
$next216 = [
    'option_id' => 216,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next216',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"cache","priority":8}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-216-b',
];

$plan216 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = 5,
    ?int $lastYieldedRowid = null,
    ?int $yieldBatchSize = 3,
    ?array $projection = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext216(
    'json_tree',
    $current ?? $current216,
    $next ?? $next216,
    'option_value',
    'generated_path',
    $constraints ?? [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
        ['column' => '_rowid_', 'operator' => 'BETWEEN', 'value' => [6, 8]],
    ],
    'scan_root',
    $orderBy ?? [['column' => 'rowid', 'direction' => 'ASC']],
    $limit,
    $lastYieldedRowid,
    $yieldBatchSize,
    $projection ?? ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey'],
);

$stable216 = static fn (): array => $plan216($current216, $current216);
$point216 = static fn (): array => $plan216($current216, $current216, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [8, 8]],
]);
$emptyRange216 = static fn (): array => $plan216($current216, $current216, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [20, 30]],
]);
$externalOrder216 = static fn (): array => $plan216($current216, $current216, null, [['column' => 'fullkey', 'direction' => 'ASC']]);
$unusableRange216 = static fn (): array => $plan216($current216, $current216, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [6, 8], 'usable' => false],
]);

$tests = [
    'records next216 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next216', $plan216()['dependencies'], true)),
    'preserves next212 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next212', $plan216()['dependencies'], true)),
    'current reader policy xnext' => static fn (TestRunner $t) => $t->same('xnext-rowid-current-json-table-generated-path-rowid-next216', $plan216()['currentReaderPolicy']),
    'next reader policy reparses changed source' => static fn (TestRunner $t) => $t->same('reprepare-xnext-rowid-next-json-table-generated-path-rowid-next216', $plan216()['nextReaderPolicy']),
    'stable reader policy reuses xnext' => static fn (TestRunner $t) => $t->same('reuse-xnext-rowid-current-json-table-generated-path-rowid-next216', $stable216()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable216()['next216ReplanReasons']),
    'current opcode advances' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXNextAdvanceNext216', $plan216()['currentGeneratedPathRowidCurrentSourceXNext216']['xNextOpcode']),
    'current cost class range' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xnext-range-next216', $plan216()['currentGeneratedPathRowidCurrentSourceXNext216']['costClass']),
    'current yielded rowid' => static fn (TestRunner $t) => $t->same(6, $plan216()['currentGeneratedPathRowidCurrentSourceXNext216']['yieldedRowid']),
    'current next rowid' => static fn (TestRunner $t) => $t->same(7, $plan216()['currentGeneratedPathRowidCurrentSourceXNext216']['nextRowid']),
    'current remaining before advance' => static fn (TestRunner $t) => $t->same([7, 8], $plan216()['currentGeneratedPathRowidCurrentSourceXNext216']['remainingRowidsBeforeAdvance']),
    'current remaining after advance' => static fn (TestRunner $t) => $t->same([8], $plan216()['currentGeneratedPathRowidCurrentSourceXNext216']['remainingRowidsAfterAdvance']),
    'current accepted range rowids' => static fn (TestRunner $t) => $t->same([6, 7, 8], $plan216()['currentGeneratedPathRowidCurrentSourceXNext216']['acceptedRangeRowids']),
    'current xcurrent reusable' => static fn (TestRunner $t) => $t->same(true, $plan216()['currentGeneratedPathRowidCurrentSourceXNext216']['xCurrentReusable']),
    'current xnext reusable' => static fn (TestRunner $t) => $t->same(true, $plan216()['currentGeneratedPathRowidCurrentSourceXNext216']['xNextReusable']),
    'current eof false' => static fn (TestRunner $t) => $t->same(false, $plan216()['currentGeneratedPathRowidCurrentSourceXNext216']['eofAfterAdvance']),
    'current estimated rows remaining' => static fn (TestRunner $t) => $t->same(2, $plan216()['currentGeneratedPathRowidCurrentSourceXNext216']['estimatedRows']),
    'current estimated cost remaining' => static fn (TestRunner $t) => $t->same(2, $plan216()['currentGeneratedPathRowidCurrentSourceXNext216']['estimatedCost']),
    'current row fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan216()['currentGeneratedPathRowidCurrentSourceXNext216']['rowFingerprint'])),
    'current xnext fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan216()['currentGeneratedPathRowidCurrentSourceXNext216']['xNextFingerprint'])),
    'next opcode reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXNextReprepareNext216', $plan216()['nextGeneratedPathRowidCurrentSourceXNext216']['xNextOpcode']),
    'next reusable false' => static fn (TestRunner $t) => $t->same(false, $plan216()['nextGeneratedPathRowidCurrentSourceXNext216']['xNextReusable']),
    'next cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $plan216()['nextGeneratedPathRowidCurrentSourceXNext216']['estimatedCost']),
    'next upstream replan required' => static fn (TestRunner $t) => $t->same(true, $plan216()['nextGeneratedPathRowidCurrentSourceXNext216']['upstreamReplanRequired']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($plan216()['generatedPathRowidCurrentSourceXNext216Transitions'])),
    'transition source changed' => static fn (TestRunner $t) => $t->same(true, $plan216()['generatedPathRowidCurrentSourceXNext216Transitions'][4]['changed']),
    'transition rowset changed' => static fn (TestRunner $t) => $t->same(true, $plan216()['generatedPathRowidCurrentSourceXNext216Transitions'][8]['changed']),
    'transition admission changed' => static fn (TestRunner $t) => $t->same(true, $plan216()['generatedPathRowidCurrentSourceXNext216Transitions'][14]['changed']),
    'transition cost changed' => static fn (TestRunner $t) => $t->same(true, $plan216()['generatedPathRowidCurrentSourceXNext216Transitions'][19]['changed']),
    'reasons include source changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xnext-source-changed-next216', $plan216()['next216ReplanReasons'], true)),
    'reasons include rowset changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xnext-rowset-changed-next216', $plan216()['next216ReplanReasons'], true)),
    'reasons include admission changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xnext-admission-changed-next216', $plan216()['next216ReplanReasons'], true)),
    'reasons include cost changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xnext-cost-changed-next216', $plan216()['next216ReplanReasons'], true)),
    'preserves next212 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcurrent-source-changed-next212', $plan216()['next216ReplanReasons'], true)),
    'point opcode eof' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXNextEofNext216', $point216()['currentGeneratedPathRowidCurrentSourceXNext216']['xNextOpcode']),
    'point cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xnext-eof-next216', $point216()['currentGeneratedPathRowidCurrentSourceXNext216']['costClass']),
    'point next rowid null' => static fn (TestRunner $t) => $t->same(null, $point216()['currentGeneratedPathRowidCurrentSourceXNext216']['nextRowid']),
    'point eof true' => static fn (TestRunner $t) => $t->same(true, $point216()['currentGeneratedPathRowidCurrentSourceXNext216']['eofAfterAdvance']),
    'point estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $point216()['currentGeneratedPathRowidCurrentSourceXNext216']['estimatedRows']),
    'empty range opcode reseeks' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXNextReseekRangeNext216', $emptyRange216()['currentGeneratedPathRowidCurrentSourceXNext216']['xNextOpcode']),
    'empty range cost class reseek' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xnext-reseek-range-next216', $emptyRange216()['currentGeneratedPathRowidCurrentSourceXNext216']['costClass']),
    'empty range reusable false' => static fn (TestRunner $t) => $t->same(false, $emptyRange216()['currentGeneratedPathRowidCurrentSourceXNext216']['rangeReusable']),
    'external order opcode reseeks' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXNextReseekRangeNext216', $externalOrder216()['currentGeneratedPathRowidCurrentSourceXNext216']['xNextOpcode']),
    'unusable range reseeks range' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXNextReseekRangeNext216', $unusableRange216()['currentGeneratedPathRowidCurrentSourceXNext216']['xNextOpcode']),
    'unusable range xcurrent false' => static fn (TestRunner $t) => $t->same(false, $unusableRange216()['currentGeneratedPathRowidCurrentSourceXNext216']['xCurrentReusable']),
    'unusable range cost class reseek' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xnext-reseek-range-next216', $unusableRange216()['currentGeneratedPathRowidCurrentSourceXNext216']['costClass']),
    'malformed generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan216(array_replace($current216, ['generated_path' => '$.rules[']), $current216)),
    'bad root rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan216(array_replace($current216, ['scan_root' => 216]), $current216)),
    'bad function rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext216('json_bad', $current216, $current216, 'option_value', 'generated_path')),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next216 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
