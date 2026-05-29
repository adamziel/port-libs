<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current209 = [
    'option_id' => 209,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next209',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-209-a',
];
$next209 = [
    'option_id' => 209,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next209',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-209-b',
];

$plan209 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = 5,
    ?int $lastYieldedRowid = null,
    ?int $yieldBatchSize = 3,
    ?array $projection = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidRangeConstraint(
    'json_tree',
    $current ?? $current209,
    $next ?? $next209,
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

$stable209 = static fn (): array => $plan209($current209, $current209);
$greaterThan209 = static fn (): array => $plan209($current209, $current209, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'oid', 'operator' => '>', 'value' => 7],
]);
$upperBound209 = static fn (): array => $plan209($current209, $current209, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'rowid', 'operator' => '<=', 'value' => 7],
]);
$emptyRange209 = static fn (): array => $plan209($current209, $current209, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => '_rowid_', 'operator' => 'BETWEEN', 'value' => [20, 30]],
]);
$noRange209 = static fn (): array => $plan209($current209, $current209, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
]);
$unsupportedOrder209 = static fn (): array => $plan209($current209, $current209, null, [['column' => 'fullkey', 'direction' => 'ASC']]);
$unusableRange209 = static fn (): array => $plan209($current209, $current209, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [6, 8], 'usable' => false],
]);

$tests = [
    'records next209 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next209', $plan209()['dependencies'], true)),
    'preserves next206 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next206', $plan209()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('range-rowid-current-json-table-generated-path-rowid-next209', $plan209()['currentReaderPolicy']),
    'next reader policy reparses on source change' => static fn (TestRunner $t) => $t->same('reprepare-range-rowid-next-json-table-generated-path-rowid-next209', $plan209()['nextReaderPolicy']),
    'stable reader policy reuses range' => static fn (TestRunner $t) => $t->same('reuse-range-rowid-current-json-table-generated-path-rowid-next209', $stable209()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable209()['next209ReplanReasons']),
    'range constraint recorded' => static fn (TestRunner $t) => $t->same('BETWEEN', $plan209()['currentGeneratedPathRowidRangeConstraint209']['rangeConstraints'][0]['operator']),
    'range original column recorded' => static fn (TestRunner $t) => $t->same('_rowid_', $plan209()['currentGeneratedPathRowidRangeConstraint209']['rangeConstraints'][0]['column']),
    'range lower bound recorded' => static fn (TestRunner $t) => $t->same(7, $plan209()['currentGeneratedPathRowidRangeConstraint209']['rangeConstraints'][0]['lower']),
    'range upper bound recorded' => static fn (TestRunner $t) => $t->same(8, $plan209()['currentGeneratedPathRowidRangeConstraint209']['rangeConstraints'][0]['upper']),
    'range lower inclusive' => static fn (TestRunner $t) => $t->same(true, $plan209()['currentGeneratedPathRowidRangeConstraint209']['rangeConstraints'][0]['lowerInclusive']),
    'range upper inclusive' => static fn (TestRunner $t) => $t->same(true, $plan209()['currentGeneratedPathRowidRangeConstraint209']['rangeConstraints'][0]['upperInclusive']),
    'ordered rowids before range' => static fn (TestRunner $t) => $t->same([7, 8], $plan209()['currentGeneratedPathRowidRangeConstraint209']['orderedRowidsBeforeRange']),
    'accepted range rowids preserve order' => static fn (TestRunner $t) => $t->same([7, 8], $plan209()['currentGeneratedPathRowidRangeConstraint209']['acceptedRangeRowids']),
    'rejected range rowids' => static fn (TestRunner $t) => $t->same([], $plan209()['currentGeneratedPathRowidRangeConstraint209']['rejectedRangeRowids']),
    'range constraint count' => static fn (TestRunner $t) => $t->same(1, $plan209()['currentGeneratedPathRowidRangeConstraint209']['rangeConstraintCount']),
    'base order reusable' => static fn (TestRunner $t) => $t->same(true, $plan209()['currentGeneratedPathRowidRangeConstraint209']['baseOrderReusable']),
    'order by consumed' => static fn (TestRunner $t) => $t->same(true, $plan209()['currentGeneratedPathRowidRangeConstraint209']['orderByConsumed']),
    'range usable' => static fn (TestRunner $t) => $t->same(true, $plan209()['currentGeneratedPathRowidRangeConstraint209']['rangeUsable']),
    'range reusable' => static fn (TestRunner $t) => $t->same(true, $plan209()['currentGeneratedPathRowidRangeConstraint209']['rangeReusable']),
    'range selectivity covers accepted rowset' => static fn (TestRunner $t) => $t->same(1, $plan209()['currentGeneratedPathRowidRangeConstraint209']['rangeSelectivity']),
    'estimated rows after range' => static fn (TestRunner $t) => $t->same(2, $plan209()['currentGeneratedPathRowidRangeConstraint209']['estimatedRows']),
    'estimated cost after range' => static fn (TestRunner $t) => $t->same(3, $plan209()['currentGeneratedPathRowidRangeConstraint209']['estimatedCost']),
    'range opcode seek' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidRangeSeekNext209', $plan209()['currentGeneratedPathRowidRangeConstraint209']['rangeOpcode']),
    'range cost class seek' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-range-seek-next209', $plan209()['currentGeneratedPathRowidRangeConstraint209']['costClass']),
    'range fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan209()['currentGeneratedPathRowidRangeConstraint209']['rangeFingerprint'])),
    'next range not reusable' => static fn (TestRunner $t) => $t->same(false, $plan209()['nextGeneratedPathRowidRangeConstraint209']['rangeReusable']),
    'next range cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $plan209()['nextGeneratedPathRowidRangeConstraint209']['estimatedCost']),
    'next opcode reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidRangeReprepareNext209', $plan209()['nextGeneratedPathRowidRangeConstraint209']['rangeOpcode']),
    'transition count' => static fn (TestRunner $t) => $t->same(18, count($plan209()['generatedPathRowidRangeConstraint209Transitions'])),
    'reasons include source changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-range-source-changed-next209', $plan209()['next209ReplanReasons'], true)),
    'reasons include rowset changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-range-rowset-changed-next209', $plan209()['next209ReplanReasons'], true)),
    'reasons include admission changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-range-admission-changed-next209', $plan209()['next209ReplanReasons'], true)),
    'reasons include cost changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-range-cost-changed-next209', $plan209()['next209ReplanReasons'], true)),
    'preserves next206 reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-alias-order-source-changed-next206', $plan209()['next209ReplanReasons'], true)),
    'greater than rowids' => static fn (TestRunner $t) => $t->same([8, 9], $greaterThan209()['currentGeneratedPathRowidRangeConstraint209']['acceptedRangeRowids']),
    'greater than lower exclusive' => static fn (TestRunner $t) => $t->same(false, $greaterThan209()['currentGeneratedPathRowidRangeConstraint209']['rangeConstraints'][0]['lowerInclusive']),
    'upper bound rowids' => static fn (TestRunner $t) => $t->same([5, 6, 7], $upperBound209()['currentGeneratedPathRowidRangeConstraint209']['acceptedRangeRowids']),
    'upper bound inclusive' => static fn (TestRunner $t) => $t->same(true, $upperBound209()['currentGeneratedPathRowidRangeConstraint209']['rangeConstraints'][0]['upperInclusive']),
    'empty range opcode reparses empty base rowset' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidRangeReprepareNext209', $emptyRange209()['currentGeneratedPathRowidRangeConstraint209']['rangeOpcode']),
    'empty range cost class reparses' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-range-reprepare-next209', $emptyRange209()['currentGeneratedPathRowidRangeConstraint209']['costClass']),
    'empty range reusable false' => static fn (TestRunner $t) => $t->same(false, $emptyRange209()['currentGeneratedPathRowidRangeConstraint209']['rangeReusable']),
    'no range bypass opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidRangeBypassNext209', $noRange209()['currentGeneratedPathRowidRangeConstraint209']['rangeOpcode']),
    'no range usable false' => static fn (TestRunner $t) => $t->same(false, $noRange209()['currentGeneratedPathRowidRangeConstraint209']['rangeUsable']),
    'unsupported order external order opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidRangeExternalOrderNext209', $unsupportedOrder209()['currentGeneratedPathRowidRangeConstraint209']['rangeOpcode']),
    'unusable range bypassed' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidRangeBypassNext209', $unusableRange209()['currentGeneratedPathRowidRangeConstraint209']['rangeOpcode']),
    'unusable range constraints empty' => static fn (TestRunner $t) => $t->same([], $unusableRange209()['currentGeneratedPathRowidRangeConstraint209']['rangeConstraints']),
    'malformed generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan209(array_replace($current209, ['generated_path' => '$.rules[']), $current209)),
    'bad function rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidRangeConstraint('json_bad', $current209, $current209, 'option_value', 'generated_path')),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid range constraint ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
