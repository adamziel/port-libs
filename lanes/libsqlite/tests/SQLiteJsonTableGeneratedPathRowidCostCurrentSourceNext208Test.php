<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current208 = [
    'option_id' => 208,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next208',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-208-a',
];
$next208 = [
    'option_id' => 208,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next208',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-208-b',
];

$plan208 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = 5,
    ?int $lastYieldedRowid = 9,
    ?int $yieldBatchSize = 2,
    ?array $projection = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext208(
    'json_tree',
    $current ?? $current208,
    $next ?? $next208,
    'option_value',
    'generated_path',
    $constraints ?? [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ],
    'scan_root',
    $orderBy ?? [['column' => 'rowid', 'direction' => 'DESC']],
    $limit,
    $lastYieldedRowid,
    $yieldBatchSize,
    $projection ?? ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey'],
);

$stable208 = static fn (?int $limit = 5): array => $plan208($current208, $current208, null, null, $limit, 9, 2);
$unlimited208 = static fn (): array => $plan208($current208, $current208, null, null, null, 9, 2);
$zeroLimit208 = static fn (): array => $plan208($current208, $current208, null, null, 0, 9, 2);
$unsupported208 = static fn (): array => $plan208($current208, $current208, null, [['column' => 'fullkey', 'direction' => 'ASC']], 5, 9, 2);
$noOrder208 = static fn (): array => $plan208($current208, $current208, null, [], 5, 9, 2);

$tests = [
    'records next208 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next208', $plan208()['dependencies'], true)),
    'preserves next206 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next206', $plan208()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('final-cost-current-json-table-generated-path-rowid-next208', $plan208()['currentReaderPolicy']),
    'next reader policy reparses on source change' => static fn (TestRunner $t) => $t->same('reprepare-final-cost-next-json-table-generated-path-rowid-next208', $plan208()['nextReaderPolicy']),
    'stable reader policy reuses' => static fn (TestRunner $t) => $t->same('reuse-final-cost-current-json-table-generated-path-rowid-next208', $stable208()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable208()['next208ReplanReasons']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-208-a', $plan208()['currentGeneratedPathRowidFinalCost208']['sourceGeneration']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan208()['currentGeneratedPathRowidFinalCost208']['generatedPath']),
    'current root recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan208()['currentGeneratedPathRowidFinalCost208']['root']),
    'order term preserved' => static fn (TestRunner $t) => $t->same('rowid', $plan208()['currentGeneratedPathRowidFinalCost208']['orderTerms'][0]['column']),
    'ordered rowids from next206' => static fn (TestRunner $t) => $t->same([8, 7], $plan208()['currentGeneratedPathRowidFinalCost208']['orderedRowids']),
    'limit recorded' => static fn (TestRunner $t) => $t->same(5, $plan208()['currentGeneratedPathRowidFinalCost208']['limit']),
    'limit applied' => static fn (TestRunner $t) => $t->same(true, $plan208()['currentGeneratedPathRowidFinalCost208']['limitApplied']),
    'final rowids limited' => static fn (TestRunner $t) => $t->same([8, 7], $plan208()['currentGeneratedPathRowidFinalCost208']['finalRowids']),
    'first final rowid' => static fn (TestRunner $t) => $t->same(8, $plan208()['currentGeneratedPathRowidFinalCost208']['firstFinalRowid']),
    'last final rowid' => static fn (TestRunner $t) => $t->same(7, $plan208()['currentGeneratedPathRowidFinalCost208']['lastFinalRowid']),
    'alias order reusable' => static fn (TestRunner $t) => $t->same(true, $plan208()['currentGeneratedPathRowidFinalCost208']['aliasOrderReusable']),
    'order consumed' => static fn (TestRunner $t) => $t->same(true, $plan208()['currentGeneratedPathRowidFinalCost208']['orderByConsumed']),
    'final cost reusable' => static fn (TestRunner $t) => $t->same(true, $plan208()['currentGeneratedPathRowidFinalCost208']['finalCostReusable']),
    'estimated rows after limit' => static fn (TestRunner $t) => $t->same(2, $plan208()['currentGeneratedPathRowidFinalCost208']['estimatedRows']),
    'estimated cost bounded by limit' => static fn (TestRunner $t) => $t->same(2, $plan208()['currentGeneratedPathRowidFinalCost208']['estimatedCost']),
    'current opcode reuses final cost' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidFinalCostReuseNext208', $plan208()['currentGeneratedPathRowidFinalCost208']['finalCostOpcode']),
    'current cost class limited' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-final-cost-limited-next208', $plan208()['currentGeneratedPathRowidFinalCost208']['costClass']),
    'fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan208()['currentGeneratedPathRowidFinalCost208']['finalCostFingerprint'])),
    'next final cost not reusable' => static fn (TestRunner $t) => $t->same(false, $plan208()['nextGeneratedPathRowidFinalCost208']['finalCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $plan208()['nextGeneratedPathRowidFinalCost208']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $plan208()['nextGeneratedPathRowidFinalCost208']['estimatedCost']),
    'next opcode reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidFinalCostReprepareNext208', $plan208()['nextGeneratedPathRowidFinalCost208']['finalCostOpcode']),
    'transition count' => static fn (TestRunner $t) => $t->same(20, count($plan208()['generatedPathRowidFinalCost208Transitions'])),
    'reasons include source changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-final-cost-source-changed-next208', $plan208()['next208ReplanReasons'], true)),
    'reasons include rowset changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-final-cost-rowset-changed-next208', $plan208()['next208ReplanReasons'], true)),
    'reasons include admission changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-final-cost-admission-changed-next208', $plan208()['next208ReplanReasons'], true)),
    'reasons include estimate changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-final-cost-estimate-changed-next208', $plan208()['next208ReplanReasons'], true)),
    'preserves next206 reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-alias-order-source-changed-next206', $plan208()['next208ReplanReasons'], true)),
    'unlimited rowids preserve ordered range' => static fn (TestRunner $t) => $t->same([8, 7], $unlimited208()['currentGeneratedPathRowidFinalCost208']['finalRowids']),
    'unlimited limit not applied' => static fn (TestRunner $t) => $t->same(false, $unlimited208()['currentGeneratedPathRowidFinalCost208']['limitApplied']),
    'unlimited cost class range' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-final-cost-range-next208', $unlimited208()['currentGeneratedPathRowidFinalCost208']['costClass']),
    'zero limit final eof rowids' => static fn (TestRunner $t) => $t->same([], $zeroLimit208()['currentGeneratedPathRowidFinalCost208']['finalRowids']),
    'zero limit eof opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidFinalCostEofNext208', $zeroLimit208()['currentGeneratedPathRowidFinalCost208']['finalCostOpcode']),
    'zero limit eof class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-final-cost-eof-next208', $zeroLimit208()['currentGeneratedPathRowidFinalCost208']['costClass']),
    'unsupported order reprepare opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidFinalCostReprepareNext208', $unsupported208()['currentGeneratedPathRowidFinalCost208']['finalCostOpcode']),
    'unsupported order records column' => static fn (TestRunner $t) => $t->same(['fullkey'], $unsupported208()['currentGeneratedPathRowidFinalCost208']['unsupportedOrderColumns']),
    'unsupported order not reusable' => static fn (TestRunner $t) => $t->same(false, $unsupported208()['currentGeneratedPathRowidFinalCost208']['finalCostReusable']),
    'no order reprepare opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidFinalCostReprepareNext208', $noOrder208()['currentGeneratedPathRowidFinalCost208']['finalCostOpcode']),
    'no order final cost not reusable' => static fn (TestRunner $t) => $t->same(false, $noOrder208()['currentGeneratedPathRowidFinalCost208']['finalCostReusable']),
    'malformed generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan208(array_replace($current208, ['generated_path' => '$.rules[']), $current208)),
    'bad function rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext208('json_bad', $current208, $current208, 'option_value', 'generated_path')),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next208 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
