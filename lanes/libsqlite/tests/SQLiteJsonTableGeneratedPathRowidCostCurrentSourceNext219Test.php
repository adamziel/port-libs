<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current219 = [
    'option_id' => 219,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next219',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-219-a',
];
$next219 = [
    'option_id' => 219,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next219',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-219-b',
];

$plan219 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = 1,
    ?int $lastYieldedRowid = null,
    ?int $yieldBatchSize = 3,
    ?array $projection = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidAliasLimit(
    'json_tree',
    $current ?? $current219,
    $next ?? $next219,
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

$changed219 = static fn (): array => $plan219();
$stable219 = static fn (): array => $plan219($current219, $current219);
$unbounded219 = static fn (): array => $plan219($current219, $current219, null, null, null);
$limitZero219 = static fn (): array => $plan219($current219, $current219, null, null, 0);
$descLimit219 = static fn (): array => $plan219($current219, $current219, null, [['column' => 'rowid', 'direction' => 'DESC']], 1);
$point219 = static fn (): array => $plan219($current219, $current219, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [8, 8]],
], null, 1);
$externalOrder219 = static fn (): array => $plan219($current219, $current219, null, [['column' => 'fullkey', 'direction' => 'ASC']], 1);

$tests = [
    'records next219 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next219', $changed219()['dependencies'], true)),
    'preserves next212 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next212', $changed219()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('limit-admission-current-json-table-generated-path-rowid-next219', $changed219()['currentReaderPolicy']),
    'next reader policy reparses on source change' => static fn (TestRunner $t) => $t->same('reprepare-limit-admission-next-json-table-generated-path-rowid-next219', $changed219()['nextReaderPolicy']),
    'stable next reader policy reuses limit admission' => static fn (TestRunner $t) => $t->same('reuse-limit-admission-current-json-table-generated-path-rowid-next219', $stable219()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable219()['next219ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed219()['currentGeneratedPathRowidLimitAdmission219']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed219()['currentGeneratedPathRowidLimitAdmission219']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-219-a', $changed219()['currentGeneratedPathRowidLimitAdmission219']['sourceGeneration']),
    'source fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed219()['currentGeneratedPathRowidLimitAdmission219']['sourceFingerprint'])),
    'xcurrent fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed219()['currentGeneratedPathRowidLimitAdmission219']['xCurrentFingerprint'])),
    'alias order fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed219()['currentGeneratedPathRowidLimitAdmission219']['aliasOrderFingerprint'])),
    'limit fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed219()['currentGeneratedPathRowidLimitAdmission219']['limitFingerprint'])),
    'ordered rowids' => static fn (TestRunner $t) => $t->same([7, 8], $changed219()['currentGeneratedPathRowidLimitAdmission219']['orderedRowids']),
    'bounded rowids limited' => static fn (TestRunner $t) => $t->same([7], $changed219()['currentGeneratedPathRowidLimitAdmission219']['boundedRowids']),
    'active rowid' => static fn (TestRunner $t) => $t->same(7, $changed219()['currentGeneratedPathRowidLimitAdmission219']['activeRowid']),
    'active ordinal' => static fn (TestRunner $t) => $t->same(0, $changed219()['currentGeneratedPathRowidLimitAdmission219']['activeOrdinal']),
    'active within limit' => static fn (TestRunner $t) => $t->same(true, $changed219()['currentGeneratedPathRowidLimitAdmission219']['activeWithinLimit']),
    'remaining rowids' => static fn (TestRunner $t) => $t->same([8], $changed219()['currentGeneratedPathRowidLimitAdmission219']['remainingRowids']),
    'limit recorded' => static fn (TestRunner $t) => $t->same(1, $changed219()['currentGeneratedPathRowidLimitAdmission219']['limit']),
    'limit applied' => static fn (TestRunner $t) => $t->same(true, $changed219()['currentGeneratedPathRowidLimitAdmission219']['limitApplied']),
    'xcurrent reusable' => static fn (TestRunner $t) => $t->same(true, $changed219()['currentGeneratedPathRowidLimitAdmission219']['xCurrentReusable']),
    'alias order reusable' => static fn (TestRunner $t) => $t->same(true, $changed219()['currentGeneratedPathRowidLimitAdmission219']['aliasOrderReusable']),
    'order by consumed' => static fn (TestRunner $t) => $t->same(true, $changed219()['currentGeneratedPathRowidLimitAdmission219']['orderByConsumed']),
    'limit admission reusable' => static fn (TestRunner $t) => $t->same(true, $changed219()['currentGeneratedPathRowidLimitAdmission219']['limitAdmissionReusable']),
    'limit opcode current' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidLimitCurrentNext219', $changed219()['currentGeneratedPathRowidLimitAdmission219']['limitOpcode']),
    'estimated rows current' => static fn (TestRunner $t) => $t->same(1, $changed219()['currentGeneratedPathRowidLimitAdmission219']['estimatedRows']),
    'estimated cost current' => static fn (TestRunner $t) => $t->same(1, $changed219()['currentGeneratedPathRowidLimitAdmission219']['estimatedCost']),
    'cost class limited current' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-limit-current-next219', $changed219()['currentGeneratedPathRowidLimitAdmission219']['costClass']),
    'next source opcode reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidLimitReprepareNext219', $changed219()['nextGeneratedPathRowidLimitAdmission219']['limitOpcode']),
    'next source cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed219()['nextGeneratedPathRowidLimitAdmission219']['estimatedCost']),
    'next source not reusable' => static fn (TestRunner $t) => $t->same(false, $changed219()['nextGeneratedPathRowidLimitAdmission219']['limitAdmissionReusable']),
    'transition count' => static fn (TestRunner $t) => $t->same(24, count($changed219()['generatedPathRowidLimitAdmission219Transitions'])),
    'transition source changes' => static fn (TestRunner $t) => $t->same(true, $changed219()['generatedPathRowidLimitAdmission219Transitions'][4]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed219()['generatedPathRowidLimitAdmission219Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed219()['generatedPathRowidLimitAdmission219Transitions'][18]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed219()['generatedPathRowidLimitAdmission219Transitions'][21]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-limit-source-changed-next219', $changed219()['next219ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-limit-rowset-changed-next219', $changed219()['next219ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-limit-admission-changed-next219', $changed219()['next219ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-limit-cost-changed-next219', $changed219()['next219ReplanReasons'], true)),
    'preserves next212 reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcurrent-source-changed-next212', $changed219()['next219ReplanReasons'], true)),
    'unbounded rowids all' => static fn (TestRunner $t) => $t->same([7, 8], $unbounded219()['currentGeneratedPathRowidLimitAdmission219']['boundedRowids']),
    'unbounded cost class range' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-unbounded-range-next219', $unbounded219()['currentGeneratedPathRowidLimitAdmission219']['costClass']),
    'limit zero eof opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidLimitEofNext219', $limitZero219()['currentGeneratedPathRowidLimitAdmission219']['limitOpcode']),
    'limit zero admission false' => static fn (TestRunner $t) => $t->same(false, $limitZero219()['currentGeneratedPathRowidLimitAdmission219']['limitAdmissionReusable']),
    'descending order keeps active current rowid' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidLimitCurrentNext219', $descLimit219()['currentGeneratedPathRowidLimitAdmission219']['limitOpcode']),
    'descending bounded rowid is highest range row' => static fn (TestRunner $t) => $t->same([8], $descLimit219()['currentGeneratedPathRowidLimitAdmission219']['boundedRowids']),
    'point cost class limited' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-limit-current-next219', $point219()['currentGeneratedPathRowidLimitAdmission219']['costClass']),
    'external order reparses before limit' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidLimitReprepareNext219', $externalOrder219()['currentGeneratedPathRowidLimitAdmission219']['limitOpcode']),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan219(array_replace($current219, ['generated_path' => '$.rules[']), $current219)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next219 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
