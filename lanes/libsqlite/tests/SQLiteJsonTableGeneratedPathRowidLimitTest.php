<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current219 = [
    'option_id' => 219,
    'option_name' => 'wp_plugin_generated_path_rowid_limit',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-219-a',
];
$generatedPathRowidLimit = [
    'option_id' => 219,
    'option_name' => 'wp_plugin_generated_path_rowid_limit',
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
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceLimitAdmission(
    'json_tree',
    $current ?? $current219,
    $next ?? $generatedPathRowidLimit,
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
    'records limit dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-limit', $changed219()['dependencies'], true)),
    'preserves xcurrent dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-xcurrent', $changed219()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('limit-admission-current-json-table-generated-path-rowid', $changed219()['currentReaderPolicy']),
    'next reader policy reparses on source change' => static fn (TestRunner $t) => $t->same('reprepare-limit-admission-next-json-table-generated-path-rowid', $changed219()['nextReaderPolicy']),
    'stable next reader policy reuses limit admission' => static fn (TestRunner $t) => $t->same('reuse-limit-admission-current-json-table-generated-path-rowid', $stable219()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable219()['generatedPathRowidLimitReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed219()['currentGeneratedPathRowidLimitAdmissionProfile']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed219()['currentGeneratedPathRowidLimitAdmissionProfile']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-219-a', $changed219()['currentGeneratedPathRowidLimitAdmissionProfile']['sourceGeneration']),
    'source fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed219()['currentGeneratedPathRowidLimitAdmissionProfile']['sourceFingerprint'])),
    'xcurrent fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed219()['currentGeneratedPathRowidLimitAdmissionProfile']['xCurrentFingerprint'])),
    'alias order fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed219()['currentGeneratedPathRowidLimitAdmissionProfile']['aliasOrderFingerprint'])),
    'limit fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed219()['currentGeneratedPathRowidLimitAdmissionProfile']['limitFingerprint'])),
    'ordered rowids' => static fn (TestRunner $t) => $t->same([7, 8], $changed219()['currentGeneratedPathRowidLimitAdmissionProfile']['orderedRowids']),
    'bounded rowids limited' => static fn (TestRunner $t) => $t->same([7], $changed219()['currentGeneratedPathRowidLimitAdmissionProfile']['boundedRowids']),
    'active rowid' => static fn (TestRunner $t) => $t->same(7, $changed219()['currentGeneratedPathRowidLimitAdmissionProfile']['activeRowid']),
    'active ordinal' => static fn (TestRunner $t) => $t->same(0, $changed219()['currentGeneratedPathRowidLimitAdmissionProfile']['activeOrdinal']),
    'active within limit' => static fn (TestRunner $t) => $t->same(true, $changed219()['currentGeneratedPathRowidLimitAdmissionProfile']['activeWithinLimit']),
    'remaining rowids' => static fn (TestRunner $t) => $t->same([8], $changed219()['currentGeneratedPathRowidLimitAdmissionProfile']['remainingRowids']),
    'limit recorded' => static fn (TestRunner $t) => $t->same(1, $changed219()['currentGeneratedPathRowidLimitAdmissionProfile']['limit']),
    'limit applied' => static fn (TestRunner $t) => $t->same(true, $changed219()['currentGeneratedPathRowidLimitAdmissionProfile']['limitApplied']),
    'xcurrent reusable' => static fn (TestRunner $t) => $t->same(true, $changed219()['currentGeneratedPathRowidLimitAdmissionProfile']['xCurrentReusable']),
    'alias order reusable' => static fn (TestRunner $t) => $t->same(true, $changed219()['currentGeneratedPathRowidLimitAdmissionProfile']['aliasOrderReusable']),
    'order by consumed' => static fn (TestRunner $t) => $t->same(true, $changed219()['currentGeneratedPathRowidLimitAdmissionProfile']['orderByConsumed']),
    'limit admission reusable' => static fn (TestRunner $t) => $t->same(true, $changed219()['currentGeneratedPathRowidLimitAdmissionProfile']['limitAdmissionReusable']),
    'limit opcode current' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidLimitCurrent', $changed219()['currentGeneratedPathRowidLimitAdmissionProfile']['limitOpcode']),
    'estimated rows current' => static fn (TestRunner $t) => $t->same(1, $changed219()['currentGeneratedPathRowidLimitAdmissionProfile']['estimatedRows']),
    'estimated cost current' => static fn (TestRunner $t) => $t->same(1, $changed219()['currentGeneratedPathRowidLimitAdmissionProfile']['estimatedCost']),
    'cost class limited current' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-limit-current', $changed219()['currentGeneratedPathRowidLimitAdmissionProfile']['costClass']),
    'next source opcode reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidLimitReprepare', $changed219()['nextGeneratedPathRowidLimitAdmissionProfile']['limitOpcode']),
    'next source cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed219()['nextGeneratedPathRowidLimitAdmissionProfile']['estimatedCost']),
    'next source not reusable' => static fn (TestRunner $t) => $t->same(false, $changed219()['nextGeneratedPathRowidLimitAdmissionProfile']['limitAdmissionReusable']),
    'transition count' => static fn (TestRunner $t) => $t->same(24, count($changed219()['generatedPathRowidLimitAdmissionTransitions'])),
    'transition source changes' => static fn (TestRunner $t) => $t->same(true, $changed219()['generatedPathRowidLimitAdmissionTransitions'][4]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed219()['generatedPathRowidLimitAdmissionTransitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed219()['generatedPathRowidLimitAdmissionTransitions'][18]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed219()['generatedPathRowidLimitAdmissionTransitions'][21]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-limit-source-changed', $changed219()['generatedPathRowidLimitReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-limit-rowset-changed', $changed219()['generatedPathRowidLimitReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-limit-admission-changed', $changed219()['generatedPathRowidLimitReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-limit-cost-changed', $changed219()['generatedPathRowidLimitReplanReasons'], true)),
    'preserves xcurrent reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcurrent-source-changed', $changed219()['generatedPathRowidLimitReplanReasons'], true)),
    'unbounded rowids all' => static fn (TestRunner $t) => $t->same([7, 8], $unbounded219()['currentGeneratedPathRowidLimitAdmissionProfile']['boundedRowids']),
    'unbounded cost class range' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-unbounded-range', $unbounded219()['currentGeneratedPathRowidLimitAdmissionProfile']['costClass']),
    'limit zero eof opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidLimitEof', $limitZero219()['currentGeneratedPathRowidLimitAdmissionProfile']['limitOpcode']),
    'limit zero admission false' => static fn (TestRunner $t) => $t->same(false, $limitZero219()['currentGeneratedPathRowidLimitAdmissionProfile']['limitAdmissionReusable']),
    'descending order keeps active current rowid' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidLimitCurrent', $descLimit219()['currentGeneratedPathRowidLimitAdmissionProfile']['limitOpcode']),
    'descending bounded rowid is highest range row' => static fn (TestRunner $t) => $t->same([8], $descLimit219()['currentGeneratedPathRowidLimitAdmissionProfile']['boundedRowids']),
    'point cost class limited' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-limit-current', $point219()['currentGeneratedPathRowidLimitAdmissionProfile']['costClass']),
    'external order reparses before limit' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidLimitReprepare', $externalOrder219()['currentGeneratedPathRowidLimitAdmissionProfile']['limitOpcode']),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan219(array_replace($current219, ['generated_path' => '$.rules[']), $current219)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source limit ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
