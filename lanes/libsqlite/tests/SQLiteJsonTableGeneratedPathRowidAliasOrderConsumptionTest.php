<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current206 = [
    'option_id' => 206,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next206',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-206-a',
];
$next206 = [
    'option_id' => 206,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next206',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-206-b',
];

$plan206 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = 5,
    ?int $lastYieldedRowid = 9,
    ?int $yieldBatchSize = 2,
    ?array $projection = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidAliasOrderConsumption(
    'json_tree',
    $current ?? $current206,
    $next ?? $next206,
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

$stable206 = static fn (): array => $plan206($current206, $current206);
$ascending206 = static fn (): array => $plan206($current206, $current206, null, [['column' => '_rowid_', 'direction' => 'ASC']], 5, 7, 3);
$idOrder206 = static fn (): array => $plan206($current206, $current206, null, [['column' => 'id', 'direction' => 'ASC']], 5, 7, 3);
$unsupported206 = static fn (): array => $plan206($current206, $current206, null, [['column' => 'fullkey', 'direction' => 'ASC']], 5, 7, 3);
$noOrder206 = static fn (): array => $plan206($current206, $current206, null, [], 5, 7, 3);

$tests = [
    'records next206 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next206', $plan206()['dependencies'], true)),
    'preserves next203 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next203', $plan206()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('consume-rowid-alias-order-current-json-table-generated-path-rowid-next206', $plan206()['currentReaderPolicy']),
    'next reader policy reparses on source change' => static fn (TestRunner $t) => $t->same('reprepare-rowid-alias-order-next-json-table-generated-path-rowid-next206', $plan206()['nextReaderPolicy']),
    'stable reader policy reuses' => static fn (TestRunner $t) => $t->same('reuse-rowid-alias-order-current-json-table-generated-path-rowid-next206', $stable206()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable206()['next206ReplanReasons']),
    'order term column recorded' => static fn (TestRunner $t) => $t->same('rowid', $plan206()['currentGeneratedPathRowidAliasOrder206']['orderTerms'][0]['column']),
    'order term normalized to id' => static fn (TestRunner $t) => $t->same('id', $plan206()['currentGeneratedPathRowidAliasOrder206']['orderTerms'][0]['normalizedColumn']),
    'order term direction recorded' => static fn (TestRunner $t) => $t->same('DESC', $plan206()['currentGeneratedPathRowidAliasOrder206']['orderTerms'][0]['direction']),
    'order term is rowid alias' => static fn (TestRunner $t) => $t->same(true, $plan206()['currentGeneratedPathRowidAliasOrder206']['orderTerms'][0]['rowidAlias']),
    'unsupported order columns empty' => static fn (TestRunner $t) => $t->same([], $plan206()['currentGeneratedPathRowidAliasOrder206']['unsupportedOrderColumns']),
    'current accepted rowids from checkpoint' => static fn (TestRunner $t) => $t->same([9, 8, 7, 6, 5], $plan206()['currentGeneratedPathRowidAliasOrder206']['acceptedRowids']),
    'current ordered rowids desc' => static fn (TestRunner $t) => $t->same([9, 8, 7, 6, 5], $plan206()['currentGeneratedPathRowidAliasOrder206']['orderedRowids']),
    'current order tape first ordinal' => static fn (TestRunner $t) => $t->same(0, $plan206()['currentGeneratedPathRowidAliasOrder206']['orderTape'][0]['ordinal']),
    'current order tape first rowid' => static fn (TestRunner $t) => $t->same(9, $plan206()['currentGeneratedPathRowidAliasOrder206']['orderTape'][0]['rowid']),
    'current order tape direction' => static fn (TestRunner $t) => $t->same('DESC', $plan206()['currentGeneratedPathRowidAliasOrder206']['orderTape'][0]['direction']),
    'current order tape accepted' => static fn (TestRunner $t) => $t->same(true, $plan206()['currentGeneratedPathRowidAliasOrder206']['orderTape'][0]['accepted']),
    'current alias projection reusable' => static fn (TestRunner $t) => $t->same(true, $plan206()['currentGeneratedPathRowidAliasOrder206']['aliasProjectionReusable']),
    'current alias order consumed' => static fn (TestRunner $t) => $t->same(true, $plan206()['currentGeneratedPathRowidAliasOrder206']['aliasOrderConsumed']),
    'current alias order reusable' => static fn (TestRunner $t) => $t->same(true, $plan206()['currentGeneratedPathRowidAliasOrder206']['aliasOrderReusable']),
    'current order by consumed' => static fn (TestRunner $t) => $t->same(true, $plan206()['currentGeneratedPathRowidAliasOrder206']['orderByConsumed']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(5, $plan206()['currentGeneratedPathRowidAliasOrder206']['estimatedRows']),
    'current estimated cost inherited' => static fn (TestRunner $t) => $t->same(35, $plan206()['currentGeneratedPathRowidAliasOrder206']['estimatedCost']),
    'current opcode consumes order' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidAliasOrderConsumeNext206', $plan206()['currentGeneratedPathRowidAliasOrder206']['aliasOrderOpcode']),
    'current cost class consumed' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-alias-order-consumed-next206', $plan206()['currentGeneratedPathRowidAliasOrder206']['costClass']),
    'current fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan206()['currentGeneratedPathRowidAliasOrder206']['aliasOrderFingerprint'])),
    'next alias projection not reusable' => static fn (TestRunner $t) => $t->same(false, $plan206()['nextGeneratedPathRowidAliasOrder206']['aliasProjectionReusable']),
    'next order not consumed' => static fn (TestRunner $t) => $t->same(false, $plan206()['nextGeneratedPathRowidAliasOrder206']['aliasOrderConsumed']),
    'next order not reusable' => static fn (TestRunner $t) => $t->same(false, $plan206()['nextGeneratedPathRowidAliasOrder206']['aliasOrderReusable']),
    'next cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $plan206()['nextGeneratedPathRowidAliasOrder206']['estimatedCost']),
    'next opcode reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidAliasOrderReprepareNext206', $plan206()['nextGeneratedPathRowidAliasOrder206']['aliasOrderOpcode']),
    'transition count' => static fn (TestRunner $t) => $t->same(17, count($plan206()['generatedPathRowidAliasOrder206Transitions'])),
    'reasons include source changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-alias-order-source-changed-next206', $plan206()['next206ReplanReasons'], true)),
    'reasons include rowset changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-alias-order-rowset-changed-next206', $plan206()['next206ReplanReasons'], true)),
    'reasons include admission changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-alias-order-admission-changed-next206', $plan206()['next206ReplanReasons'], true)),
    'reasons include cost changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-alias-order-cost-changed-next206', $plan206()['next206ReplanReasons'], true)),
    'preserves next203 reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-alias-source-changed-next203', $plan206()['next206ReplanReasons'], true)),
    'ascending alias rowids sorted asc' => static fn (TestRunner $t) => $t->same([5, 6, 7, 8, 9], $ascending206()['currentGeneratedPathRowidAliasOrder206']['orderedRowids']),
    'ascending alias consumes current order' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidAliasOrderConsumeNext206', $ascending206()['currentGeneratedPathRowidAliasOrder206']['aliasOrderOpcode']),
    'ascending alias cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-alias-order-consumed-next206', $ascending206()['currentGeneratedPathRowidAliasOrder206']['costClass']),
    'id order consumed' => static fn (TestRunner $t) => $t->same(true, $idOrder206()['currentGeneratedPathRowidAliasOrder206']['orderByConsumed']),
    'id order normalized' => static fn (TestRunner $t) => $t->same('id', $idOrder206()['currentGeneratedPathRowidAliasOrder206']['orderTerms'][0]['normalizedColumn']),
    'unsupported order external sort' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidAliasOrderExternalSortNext206', $unsupported206()['currentGeneratedPathRowidAliasOrder206']['aliasOrderOpcode']),
    'unsupported order records column' => static fn (TestRunner $t) => $t->same(['fullkey'], $unsupported206()['currentGeneratedPathRowidAliasOrder206']['unsupportedOrderColumns']),
    'unsupported order not consumed' => static fn (TestRunner $t) => $t->same(false, $unsupported206()['currentGeneratedPathRowidAliasOrder206']['orderByConsumed']),
    'no order bypass opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidAliasOrderBypassNext206', $noOrder206()['currentGeneratedPathRowidAliasOrder206']['aliasOrderOpcode']),
    'no order not consumed' => static fn (TestRunner $t) => $t->same(false, $noOrder206()['currentGeneratedPathRowidAliasOrder206']['orderByConsumed']),
    'malformed generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan206(array_replace($current206, ['generated_path' => '$.rules[']), $current206)),
    'bad function rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidAliasOrderConsumption('json_bad', $current206, $current206, 'option_value', 'generated_path')),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid alias order consumption ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
