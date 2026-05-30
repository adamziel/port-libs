<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current216 = [
    'option_id' => 216,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_xnext',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-216-a',
];
$xnext = [
    'option_id' => 216,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_xnext',
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
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidAdvancePlan(
    'json_tree',
    $current ?? $current216,
    $next ?? $xnext,
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
    'records xnext dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-xnext-current-source', $plan216()['dependencies'], true)),
    'preserves next212 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next212', $plan216()['dependencies'], true)),
    'current reader policy xnext' => static fn (TestRunner $t) => $t->same('xnext-rowid-current-json-table-generated-path-rowid', $plan216()['currentReaderPolicy']),
    'next reader policy reparses changed source' => static fn (TestRunner $t) => $t->same('reprepare-xnext-rowid-next-json-table-generated-path-rowid', $plan216()['nextReaderPolicy']),
    'stable reader policy reuses xnext' => static fn (TestRunner $t) => $t->same('reuse-xnext-rowid-current-json-table-generated-path-rowid', $stable216()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable216()['generatedPathRowidXNextReplanReasons']),
    'current opcode advances' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXNextAdvance', $plan216()['currentGeneratedPathRowidCurrentSourceXNext']['xNextOpcode']),
    'current cost class range' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xnext-range', $plan216()['currentGeneratedPathRowidCurrentSourceXNext']['costClass']),
    'current yielded rowid' => static fn (TestRunner $t) => $t->same(6, $plan216()['currentGeneratedPathRowidCurrentSourceXNext']['yieldedRowid']),
    'current next rowid' => static fn (TestRunner $t) => $t->same(7, $plan216()['currentGeneratedPathRowidCurrentSourceXNext']['nextRowid']),
    'current remaining before advance' => static fn (TestRunner $t) => $t->same([7, 8], $plan216()['currentGeneratedPathRowidCurrentSourceXNext']['remainingRowidsBeforeAdvance']),
    'current remaining after advance' => static fn (TestRunner $t) => $t->same([8], $plan216()['currentGeneratedPathRowidCurrentSourceXNext']['remainingRowidsAfterAdvance']),
    'current accepted range rowids' => static fn (TestRunner $t) => $t->same([6, 7, 8], $plan216()['currentGeneratedPathRowidCurrentSourceXNext']['acceptedRangeRowids']),
    'current xcurrent reusable' => static fn (TestRunner $t) => $t->same(true, $plan216()['currentGeneratedPathRowidCurrentSourceXNext']['xCurrentReusable']),
    'current xnext reusable' => static fn (TestRunner $t) => $t->same(true, $plan216()['currentGeneratedPathRowidCurrentSourceXNext']['xNextReusable']),
    'current eof false' => static fn (TestRunner $t) => $t->same(false, $plan216()['currentGeneratedPathRowidCurrentSourceXNext']['eofAfterAdvance']),
    'current estimated rows remaining' => static fn (TestRunner $t) => $t->same(2, $plan216()['currentGeneratedPathRowidCurrentSourceXNext']['estimatedRows']),
    'current estimated cost remaining' => static fn (TestRunner $t) => $t->same(2, $plan216()['currentGeneratedPathRowidCurrentSourceXNext']['estimatedCost']),
    'current row fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan216()['currentGeneratedPathRowidCurrentSourceXNext']['rowFingerprint'])),
    'current xnext fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan216()['currentGeneratedPathRowidCurrentSourceXNext']['xNextFingerprint'])),
    'next opcode reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXNextReprepare', $plan216()['nextGeneratedPathRowidCurrentSourceXNext']['xNextOpcode']),
    'next reusable false' => static fn (TestRunner $t) => $t->same(false, $plan216()['nextGeneratedPathRowidCurrentSourceXNext']['xNextReusable']),
    'next cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $plan216()['nextGeneratedPathRowidCurrentSourceXNext']['estimatedCost']),
    'next upstream replan required' => static fn (TestRunner $t) => $t->same(true, $plan216()['nextGeneratedPathRowidCurrentSourceXNext']['upstreamReplanRequired']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($plan216()['generatedPathRowidCurrentSourceXNextTransitions'])),
    'transition source changed' => static fn (TestRunner $t) => $t->same(true, $plan216()['generatedPathRowidCurrentSourceXNextTransitions'][4]['changed']),
    'transition rowset changed' => static fn (TestRunner $t) => $t->same(true, $plan216()['generatedPathRowidCurrentSourceXNextTransitions'][8]['changed']),
    'transition admission changed' => static fn (TestRunner $t) => $t->same(true, $plan216()['generatedPathRowidCurrentSourceXNextTransitions'][14]['changed']),
    'transition cost changed' => static fn (TestRunner $t) => $t->same(true, $plan216()['generatedPathRowidCurrentSourceXNextTransitions'][19]['changed']),
    'reasons include source changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xnext-source-changed', $plan216()['generatedPathRowidXNextReplanReasons'], true)),
    'reasons include rowset changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xnext-rowset-changed', $plan216()['generatedPathRowidXNextReplanReasons'], true)),
    'reasons include admission changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xnext-admission-changed', $plan216()['generatedPathRowidXNextReplanReasons'], true)),
    'reasons include cost changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xnext-cost-changed', $plan216()['generatedPathRowidXNextReplanReasons'], true)),
    'preserves next212 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcurrent-source-changed-next212', $plan216()['generatedPathRowidXNextReplanReasons'], true)),
    'point opcode eof' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXNextEof', $point216()['currentGeneratedPathRowidCurrentSourceXNext']['xNextOpcode']),
    'point cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xnext-eof', $point216()['currentGeneratedPathRowidCurrentSourceXNext']['costClass']),
    'point next rowid null' => static fn (TestRunner $t) => $t->same(null, $point216()['currentGeneratedPathRowidCurrentSourceXNext']['nextRowid']),
    'point eof true' => static fn (TestRunner $t) => $t->same(true, $point216()['currentGeneratedPathRowidCurrentSourceXNext']['eofAfterAdvance']),
    'point estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $point216()['currentGeneratedPathRowidCurrentSourceXNext']['estimatedRows']),
    'empty range opcode reseeks' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXNextReseekRange', $emptyRange216()['currentGeneratedPathRowidCurrentSourceXNext']['xNextOpcode']),
    'empty range cost class reseek' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xnext-reseek-range', $emptyRange216()['currentGeneratedPathRowidCurrentSourceXNext']['costClass']),
    'empty range reusable false' => static fn (TestRunner $t) => $t->same(false, $emptyRange216()['currentGeneratedPathRowidCurrentSourceXNext']['rangeReusable']),
    'external order opcode reseeks' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXNextReseekRange', $externalOrder216()['currentGeneratedPathRowidCurrentSourceXNext']['xNextOpcode']),
    'unusable range reseeks range' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidXNextReseekRange', $unusableRange216()['currentGeneratedPathRowidCurrentSourceXNext']['xNextOpcode']),
    'unusable range xcurrent false' => static fn (TestRunner $t) => $t->same(false, $unusableRange216()['currentGeneratedPathRowidCurrentSourceXNext']['xCurrentReusable']),
    'unusable range cost class reseek' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xnext-reseek-range', $unusableRange216()['currentGeneratedPathRowidCurrentSourceXNext']['costClass']),
    'malformed generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan216(array_replace($current216, ['generated_path' => '$.rules[']), $current216)),
    'bad root rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan216(array_replace($current216, ['scan_root' => 216]), $current216)),
    'bad function rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidAdvancePlan('json_bad', $current216, $current216, 'option_value', 'generated_path')),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source xnext ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
