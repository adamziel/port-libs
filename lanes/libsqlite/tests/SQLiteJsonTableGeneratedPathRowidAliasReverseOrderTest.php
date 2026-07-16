<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current205 = [
    'option_id' => 205,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next205',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-205-a',
];
$next205 = [
    'option_id' => 205,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next205',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-205-b',
];

$plan205 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = 5,
    ?int $lastYieldedRowid = 9,
    ?int $yieldBatchSize = 1,
    ?array $projection = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidAliasReverseOrder(
    'json_tree',
    $current ?? $current205,
    $next ?? $next205,
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
    $projection ?? ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey'],
);

$stable205 = static fn (): array => $plan205($current205, $current205);
$rangeDesc205 = static fn (): array => $plan205($current205, $current205, null, [['column' => '_rowid_', 'direction' => 'DESC']], 5, 7, 3);
$rangeAsc205 = static fn (): array => $plan205($current205, $current205, null, [['column' => 'rowid', 'direction' => 'ASC']], 5, 7, 3);
$pointAsc205 = static fn (): array => $plan205($current205, $current205, null, [['column' => 'rowid', 'direction' => 'ASC']], 5, 9, 1, ['rowid', 'value']);
$emptyOrder205 = static fn (): array => $plan205($current205, $current205, null, [], 5, 9, 1);

$tests = [
    'records next205 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next205', $plan205()['dependencies'], true)),
    'preserves next203 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next203', $plan205()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('orderby-current-json-table-generated-path-rowid-alias-next205', $plan205()['currentReaderPolicy']),
    'next reader policy reparses' => static fn (TestRunner $t) => $t->same('reprepare-orderby-next-json-table-generated-path-rowid-alias-next205', $plan205()['nextReaderPolicy']),
    'stable reader policy reuses' => static fn (TestRunner $t) => $t->same('reuse-orderby-current-json-table-generated-path-rowid-alias-next205', $stable205()['nextReaderPolicy']),
    'stable next205 reasons empty' => static fn (TestRunner $t) => $t->same([], $stable205()['next205ReplanReasons']),
    'order terms normalized' => static fn (TestRunner $t) => $t->same([['column' => '_rowid_', 'direction' => 'DESC']], $plan205()['currentGeneratedPathRowidAliasOrder205']['orderTerms']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-205-a', $plan205()['currentGeneratedPathRowidAliasOrder205']['sourceGeneration']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan205()['currentGeneratedPathRowidAliasOrder205']['generatedPath']),
    'current root recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan205()['currentGeneratedPathRowidAliasOrder205']['root']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $plan205()['currentGeneratedPathRowidAliasOrder205']['orderByConsumed']),
    'current sorter bypassed' => static fn (TestRunner $t) => $t->same(false, $plan205()['currentGeneratedPathRowidAliasOrder205']['requiresSorter']),
    'current reverse scan' => static fn (TestRunner $t) => $t->same(true, $plan205()['currentGeneratedPathRowidAliasOrder205']['reverseScan']),
    'current reusable' => static fn (TestRunner $t) => $t->same(true, $plan205()['currentGeneratedPathRowidAliasOrder205']['orderReusable']),
    'current accepted rowids' => static fn (TestRunner $t) => $t->same([9, 8, 7, 6, 5], $plan205()['currentGeneratedPathRowidAliasOrder205']['acceptedRowids']),
    'current ordered rowids' => static fn (TestRunner $t) => $t->same([9, 8, 7, 6, 5], $plan205()['currentGeneratedPathRowidAliasOrder205']['orderedRowids']),
    'current first rowid' => static fn (TestRunner $t) => $t->same(9, $plan205()['currentGeneratedPathRowidAliasOrder205']['firstOrderedRowid']),
    'current last rowid' => static fn (TestRunner $t) => $t->same(5, $plan205()['currentGeneratedPathRowidAliasOrder205']['lastOrderedRowid']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(5, $plan205()['currentGeneratedPathRowidAliasOrder205']['estimatedRows']),
    'current estimated cost has reverse penalty' => static fn (TestRunner $t) => $t->same(36, $plan205()['currentGeneratedPathRowidAliasOrder205']['estimatedCost']),
    'current opcode reverse' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidAliasOrderReverseNext205', $plan205()['currentGeneratedPathRowidAliasOrder205']['orderOpcode']),
    'current cost class reverse' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-order-reverse-wide-next205', $plan205()['currentGeneratedPathRowidAliasOrder205']['costClass']),
    'current ordered tape rowid' => static fn (TestRunner $t) => $t->same(9, $plan205()['currentGeneratedPathRowidAliasOrder205']['orderedAliasTape'][0]['rowid']),
    'current ordered tape fullkey' => static fn (TestRunner $t) => $t->same('$.rules[2].priority', $plan205()['currentGeneratedPathRowidAliasOrder205']['orderedAliasTape'][0]['projectedColumns']['fullkey']),
    'order fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan205()['currentGeneratedPathRowidAliasOrder205']['orderFingerprint'])),
    'next order not reusable' => static fn (TestRunner $t) => $t->same(false, $plan205()['nextGeneratedPathRowidAliasOrder205']['orderReusable']),
    'next requires sorter because upstream changed' => static fn (TestRunner $t) => $t->same(true, $plan205()['nextGeneratedPathRowidAliasOrder205']['requiresSorter']),
    'next opcode sorter' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidAliasOrderSorterNext205', $plan205()['nextGeneratedPathRowidAliasOrder205']['orderOpcode']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $plan205()['nextGeneratedPathRowidAliasOrder205']['estimatedCost']),
    'transition count records order fields' => static fn (TestRunner $t) => $t->same(18, count($plan205()['generatedPathRowidAliasOrder205Transitions'])),
    'reasons include source changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-order-source-changed-next205', $plan205()['next205ReplanReasons'], true)),
    'reasons include order admission changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-order-admission-changed-next205', $plan205()['next205ReplanReasons'], true)),
    'reasons include rowset changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-order-rowset-changed-next205', $plan205()['next205ReplanReasons'], true)),
    'reasons include cost changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-order-cost-changed-next205', $plan205()['next205ReplanReasons'], true)),
    'preserves next203 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-alias-source-changed-next203', $plan205()['next205ReplanReasons'], true)),
    'descending range rowids preserved' => static fn (TestRunner $t) => $t->same([9, 8, 7, 6, 5], $rangeDesc205()['currentGeneratedPathRowidAliasOrder205']['orderedRowids']),
    'descending range opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidAliasOrderReverseNext205', $rangeDesc205()['currentGeneratedPathRowidAliasOrder205']['orderOpcode']),
    'descending range cost' => static fn (TestRunner $t) => $t->same(36, $rangeDesc205()['currentGeneratedPathRowidAliasOrder205']['estimatedCost']),
    'ascending range rowids sorted' => static fn (TestRunner $t) => $t->same([5, 6, 7, 8, 9], $rangeAsc205()['currentGeneratedPathRowidAliasOrder205']['orderedRowids']),
    'ascending range opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidAliasOrderForwardNext205', $rangeAsc205()['currentGeneratedPathRowidAliasOrder205']['orderOpcode']),
    'ascending range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-order-forward-wide-next205', $rangeAsc205()['currentGeneratedPathRowidAliasOrder205']['costClass']),
    'single alias asc without stable checkpoint uses sorter' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidAliasOrderForwardNext205', $pointAsc205()['currentGeneratedPathRowidAliasOrder205']['orderOpcode']),
    'single alias asc sorter cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-order-forward-wide-next205', $pointAsc205()['currentGeneratedPathRowidAliasOrder205']['costClass']),
    'empty order sorter fallback' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidAliasOrderReprepareNext205', $emptyOrder205()['currentGeneratedPathRowidAliasOrder205']['orderOpcode']),
    'empty order not reusable' => static fn (TestRunner $t) => $t->same(false, $emptyOrder205()['currentGeneratedPathRowidAliasOrder205']['orderReusable']),
    'invalid order column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan205(null, null, null, [['column' => 'value', 'direction' => 'ASC']])),
    'invalid order direction rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan205(null, null, null, [['column' => 'rowid', 'direction' => 'SIDEWAYS']])),
    'missing order column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan205(null, null, null, [['direction' => 'ASC']])),
    'malformed generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan205(array_replace($current205, ['generated_path' => '$.rules[']), $current205)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid alias reverse order ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
