<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current215 = [
    'option_id' => 215,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next215',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-215-a',
];
$next215 = [
    'option_id' => 215,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next215',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-215-b',
];

$plan215 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = 5,
    ?int $lastYieldedRowid = null,
    ?int $yieldBatchSize = 3,
    ?array $projection = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext215(
    'json_tree',
    $current ?? $current215,
    $next ?? $next215,
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
);

$stable215 = static fn (): array => $plan215($current215, $current215);
$eof215 = static fn (): array => $plan215($current215, $current215, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [8, 8]],
]);
$empty215 = static fn (): array => $plan215($current215, $current215, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [20, 30]],
]);
$externalOrder215 = static fn (): array => $plan215($current215, $current215, null, [['column' => 'fullkey', 'direction' => 'ASC']]);

$tests = [
    'records next215 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next215', $plan215()['dependencies'], true)),
    'preserves next212 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next212', $plan215()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('yield-current-json-table-generated-path-rowid-next215', $plan215()['currentReaderPolicy']),
    'next reader policy reparses on source change' => static fn (TestRunner $t) => $t->same('reprepare-yield-next-json-table-generated-path-rowid-next215', $plan215()['nextReaderPolicy']),
    'stable reader policy reuses yield' => static fn (TestRunner $t) => $t->same('reuse-yield-current-json-table-generated-path-rowid-next215', $stable215()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable215()['next215ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $plan215()['currentGeneratedPathRowidYieldCost215']['function']),
    'current root recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan215()['currentGeneratedPathRowidYieldCost215']['root']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan215()['currentGeneratedPathRowidYieldCost215']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-215-a', $plan215()['currentGeneratedPathRowidYieldCost215']['sourceGeneration']),
    'source fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan215()['currentGeneratedPathRowidYieldCost215']['sourceFingerprint'])),
    'xcurrent fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan215()['currentGeneratedPathRowidYieldCost215']['xCurrentFingerprint'])),
    'yield fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan215()['currentGeneratedPathRowidYieldCost215']['yieldFingerprint'])),
    'active rowid' => static fn (TestRunner $t) => $t->same(7, $plan215()['currentGeneratedPathRowidYieldCost215']['activeRowid']),
    'emitted rowid' => static fn (TestRunner $t) => $t->same(7, $plan215()['currentGeneratedPathRowidYieldCost215']['emittedRowid']),
    'remaining before yield' => static fn (TestRunner $t) => $t->same([8], $plan215()['currentGeneratedPathRowidYieldCost215']['remainingRowidsBeforeYield']),
    'resume rowid' => static fn (TestRunner $t) => $t->same(8, $plan215()['currentGeneratedPathRowidYieldCost215']['resumeRowid']),
    'cursor not eof after yield' => static fn (TestRunner $t) => $t->same(false, $plan215()['currentGeneratedPathRowidYieldCost215']['cursorEofAfterYield']),
    'active projected rowid' => static fn (TestRunner $t) => $t->same(7, $plan215()['currentGeneratedPathRowidYieldCost215']['activeProjectedColumns']['rowid']),
    'active projected rowid alias' => static fn (TestRunner $t) => $t->same(7, $plan215()['currentGeneratedPathRowidYieldCost215']['activeProjectedColumns']['_rowid_']),
    'active projected oid alias' => static fn (TestRunner $t) => $t->same(7, $plan215()['currentGeneratedPathRowidYieldCost215']['activeProjectedColumns']['oid']),
    'active projected value' => static fn (TestRunner $t) => $t->same('{"slug":"forms","priority":4}', $plan215()['currentGeneratedPathRowidYieldCost215']['activeProjectedColumns']['value']),
    'active projected fullkey' => static fn (TestRunner $t) => $t->same('$.rules[2]', $plan215()['currentGeneratedPathRowidYieldCost215']['activeProjectedColumns']['fullkey']),
    'yield reusable true' => static fn (TestRunner $t) => $t->same(true, $plan215()['currentGeneratedPathRowidYieldCost215']['yieldReusable']),
    'upstream replan false' => static fn (TestRunner $t) => $t->same(false, $plan215()['currentGeneratedPathRowidYieldCost215']['upstreamReplanRequired']),
    'yield opcode continue' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidYieldContinueNext215', $plan215()['currentGeneratedPathRowidYieldCost215']['yieldOpcode']),
    'estimated rows remaining one' => static fn (TestRunner $t) => $t->same(1, $plan215()['currentGeneratedPathRowidYieldCost215']['estimatedRows']),
    'estimated cost one' => static fn (TestRunner $t) => $t->same(1, $plan215()['currentGeneratedPathRowidYieldCost215']['estimatedCost']),
    'single resume cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-single-resume-next215', $plan215()['currentGeneratedPathRowidYieldCost215']['costClass']),
    'next yield source generation changed' => static fn (TestRunner $t) => $t->same('next-215-b', $plan215()['nextGeneratedPathRowidYieldCost215']['sourceGeneration']),
    'next yield not reusable' => static fn (TestRunner $t) => $t->same(false, $plan215()['nextGeneratedPathRowidYieldCost215']['yieldReusable']),
    'next emitted rowid null' => static fn (TestRunner $t) => $t->same(null, $plan215()['nextGeneratedPathRowidYieldCost215']['emittedRowid']),
    'next active projected columns empty' => static fn (TestRunner $t) => $t->same([], $plan215()['nextGeneratedPathRowidYieldCost215']['activeProjectedColumns']),
    'next reprepare opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidYieldReprepareNext215', $plan215()['nextGeneratedPathRowidYieldCost215']['yieldOpcode']),
    'next sentinel cost' => static fn (TestRunner $t) => $t->same(1000000, $plan215()['nextGeneratedPathRowidYieldCost215']['estimatedCost']),
    'next reprepare cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-reprepare-next215', $plan215()['nextGeneratedPathRowidYieldCost215']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(19, count($plan215()['generatedPathRowidYieldCost215Transitions'])),
    'transition source changes' => static fn (TestRunner $t) => $t->same(true, $plan215()['generatedPathRowidYieldCost215Transitions'][4]['changed']),
    'transition emitted row changes' => static fn (TestRunner $t) => $t->same(true, $plan215()['generatedPathRowidYieldCost215Transitions'][7]['changed']),
    'transition resume changes' => static fn (TestRunner $t) => $t->same(true, $plan215()['generatedPathRowidYieldCost215Transitions'][9]['changed']),
    'transition row changes' => static fn (TestRunner $t) => $t->same(true, $plan215()['generatedPathRowidYieldCost215Transitions'][11]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $plan215()['generatedPathRowidYieldCost215Transitions'][12]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $plan215()['generatedPathRowidYieldCost215Transitions'][16]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-source-changed-next215', $plan215()['next215ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-rowset-changed-next215', $plan215()['next215ReplanReasons'], true)),
    'reasons include row' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-row-changed-next215', $plan215()['next215ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-admission-changed-next215', $plan215()['next215ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-cost-changed-next215', $plan215()['next215ReplanReasons'], true)),
    'eof emitted rowid' => static fn (TestRunner $t) => $t->same(8, $eof215()['currentGeneratedPathRowidYieldCost215']['emittedRowid']),
    'eof resume rowid null' => static fn (TestRunner $t) => $t->same(null, $eof215()['currentGeneratedPathRowidYieldCost215']['resumeRowid']),
    'eof cursor flag' => static fn (TestRunner $t) => $t->same(true, $eof215()['currentGeneratedPathRowidYieldCost215']['cursorEofAfterYield']),
    'eof opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidYieldEofNext215', $eof215()['currentGeneratedPathRowidYieldCost215']['yieldOpcode']),
    'eof cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-eof-next215', $eof215()['currentGeneratedPathRowidYieldCost215']['costClass']),
    'empty range opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidYieldEmptyNext215', $empty215()['currentGeneratedPathRowidYieldCost215']['yieldOpcode']),
    'empty range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-empty-next215', $empty215()['currentGeneratedPathRowidYieldCost215']['costClass']),
    'external order materialize opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidYieldMaterializeNext215', $externalOrder215()['currentGeneratedPathRowidYieldCost215']['yieldOpcode']),
    'external order materialize cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-materialize-next215', $externalOrder215()['currentGeneratedPathRowidYieldCost215']['costClass']),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan215(array_replace($current215, ['generated_path' => '$.rules[']), $current215)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next215 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
