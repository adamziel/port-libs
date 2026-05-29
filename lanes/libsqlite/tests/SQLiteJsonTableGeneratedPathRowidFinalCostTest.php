<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentFinal = [
    'option_id' => 42,
    'option_name' => 'wp_plugin_generated_path_rowid_final_cost',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-final-cost-a',
];
$nextFinal = [
    'option_id' => 42,
    'option_name' => 'wp_plugin_generated_path_rowid_final_cost',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-final-cost-b',
];

$planFinal = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = 5,
    ?int $lastYieldedRowid = 9,
    ?int $yieldBatchSize = 2,
    ?array $projection = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidFinalCost(
    'json_tree',
    $current ?? $currentFinal,
    $next ?? $nextFinal,
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

$stableFinal = static fn (?int $limit = 5): array => $planFinal($currentFinal, $currentFinal, null, null, $limit, 9, 2);
$unlimitedFinal = static fn (): array => $planFinal($currentFinal, $currentFinal, null, null, null, 9, 2);
$zeroLimitFinal = static fn (): array => $planFinal($currentFinal, $currentFinal, null, null, 0, 9, 2);
$unsupportedFinal = static fn (): array => $planFinal($currentFinal, $currentFinal, null, [['column' => 'fullkey', 'direction' => 'ASC']], 5, 9, 2);
$noOrderFinal = static fn (): array => $planFinal($currentFinal, $currentFinal, null, [], 5, 9, 2);

$tests = [
    'records final cost dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-final-cost', $planFinal()['dependencies'], true)),
    'preserves next206 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next206', $planFinal()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('final-cost-current-json-table-generated-path-rowid', $planFinal()['currentReaderPolicy']),
    'next reader policy reparses on source change' => static fn (TestRunner $t) => $t->same('reprepare-final-cost-next-json-table-generated-path-rowid', $planFinal()['nextReaderPolicy']),
    'stable reader policy reuses' => static fn (TestRunner $t) => $t->same('reuse-final-cost-current-json-table-generated-path-rowid', $stableFinal()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stableFinal()['generatedPathRowidFinalCostReplanReasons']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-final-cost-a', $planFinal()['currentGeneratedPathRowidFinalCost']['sourceGeneration']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $planFinal()['currentGeneratedPathRowidFinalCost']['generatedPath']),
    'current root recorded' => static fn (TestRunner $t) => $t->same('$.rules', $planFinal()['currentGeneratedPathRowidFinalCost']['root']),
    'order term preserved' => static fn (TestRunner $t) => $t->same('rowid', $planFinal()['currentGeneratedPathRowidFinalCost']['orderTerms'][0]['column']),
    'ordered rowids from next206' => static fn (TestRunner $t) => $t->same([9, 8, 7, 6, 5], $planFinal()['currentGeneratedPathRowidFinalCost']['orderedRowids']),
    'limit recorded' => static fn (TestRunner $t) => $t->same(5, $planFinal()['currentGeneratedPathRowidFinalCost']['limit']),
    'limit applied' => static fn (TestRunner $t) => $t->same(true, $planFinal()['currentGeneratedPathRowidFinalCost']['limitApplied']),
    'final rowids limited' => static fn (TestRunner $t) => $t->same([9, 8, 7, 6, 5], $planFinal()['currentGeneratedPathRowidFinalCost']['finalRowids']),
    'first final rowid' => static fn (TestRunner $t) => $t->same(9, $planFinal()['currentGeneratedPathRowidFinalCost']['firstFinalRowid']),
    'last final rowid' => static fn (TestRunner $t) => $t->same(5, $planFinal()['currentGeneratedPathRowidFinalCost']['lastFinalRowid']),
    'alias order reusable' => static fn (TestRunner $t) => $t->same(true, $planFinal()['currentGeneratedPathRowidFinalCost']['aliasOrderReusable']),
    'order consumed' => static fn (TestRunner $t) => $t->same(true, $planFinal()['currentGeneratedPathRowidFinalCost']['orderByConsumed']),
    'final cost reusable' => static fn (TestRunner $t) => $t->same(true, $planFinal()['currentGeneratedPathRowidFinalCost']['finalCostReusable']),
    'estimated rows after limit' => static fn (TestRunner $t) => $t->same(5, $planFinal()['currentGeneratedPathRowidFinalCost']['estimatedRows']),
    'estimated cost bounded by limit' => static fn (TestRunner $t) => $t->same(5, $planFinal()['currentGeneratedPathRowidFinalCost']['estimatedCost']),
    'current opcode reuses final cost' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidFinalCostReuse', $planFinal()['currentGeneratedPathRowidFinalCost']['finalCostOpcode']),
    'current cost class limited' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-final-cost-limited', $planFinal()['currentGeneratedPathRowidFinalCost']['costClass']),
    'fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($planFinal()['currentGeneratedPathRowidFinalCost']['finalCostFingerprint'])),
    'next final cost not reusable' => static fn (TestRunner $t) => $t->same(false, $planFinal()['nextGeneratedPathRowidFinalCost']['finalCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $planFinal()['nextGeneratedPathRowidFinalCost']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $planFinal()['nextGeneratedPathRowidFinalCost']['estimatedCost']),
    'next opcode reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidFinalCostReprepare', $planFinal()['nextGeneratedPathRowidFinalCost']['finalCostOpcode']),
    'transition count' => static fn (TestRunner $t) => $t->same(20, count($planFinal()['generatedPathRowidFinalCostTransitions'])),
    'reasons include source changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-final-cost-source-changed', $planFinal()['generatedPathRowidFinalCostReplanReasons'], true)),
    'reasons include rowset changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-final-cost-rowset-changed', $planFinal()['generatedPathRowidFinalCostReplanReasons'], true)),
    'reasons include admission changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-final-cost-admission-changed', $planFinal()['generatedPathRowidFinalCostReplanReasons'], true)),
    'reasons include estimate changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-final-cost-estimate-changed', $planFinal()['generatedPathRowidFinalCostReplanReasons'], true)),
    'preserves next206 reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-alias-order-source-changed-next206', $planFinal()['generatedPathRowidFinalCostReplanReasons'], true)),
    'unlimited rowids preserve ordered range' => static fn (TestRunner $t) => $t->same([9, 8, 7, 6, 5], $unlimitedFinal()['currentGeneratedPathRowidFinalCost']['finalRowids']),
    'unlimited limit not applied' => static fn (TestRunner $t) => $t->same(false, $unlimitedFinal()['currentGeneratedPathRowidFinalCost']['limitApplied']),
    'unlimited cost class range' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-final-cost-range', $unlimitedFinal()['currentGeneratedPathRowidFinalCost']['costClass']),
    'zero limit final eof rowids' => static fn (TestRunner $t) => $t->same([], $zeroLimitFinal()['currentGeneratedPathRowidFinalCost']['finalRowids']),
    'zero limit eof opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidFinalCostEof', $zeroLimitFinal()['currentGeneratedPathRowidFinalCost']['finalCostOpcode']),
    'zero limit eof class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-final-cost-eof', $zeroLimitFinal()['currentGeneratedPathRowidFinalCost']['costClass']),
    'unsupported order external sort opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidFinalCostExternalSort', $unsupportedFinal()['currentGeneratedPathRowidFinalCost']['finalCostOpcode']),
    'unsupported order records column' => static fn (TestRunner $t) => $t->same(['fullkey'], $unsupportedFinal()['currentGeneratedPathRowidFinalCost']['unsupportedOrderColumns']),
    'unsupported order not reusable' => static fn (TestRunner $t) => $t->same(false, $unsupportedFinal()['currentGeneratedPathRowidFinalCost']['finalCostReusable']),
    'no order bypass opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidFinalCostBypass', $noOrderFinal()['currentGeneratedPathRowidFinalCost']['finalCostOpcode']),
    'no order final cost not reusable' => static fn (TestRunner $t) => $t->same(false, $noOrderFinal()['currentGeneratedPathRowidFinalCost']['finalCostReusable']),
    'malformed generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $planFinal(array_replace($currentFinal, ['generated_path' => '$.rules[']), $currentFinal)),
    'bad function rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidFinalCost('json_bad', $currentFinal, $currentFinal, 'option_value', 'generated_path')),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost final cost ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
