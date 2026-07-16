<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current207 = [
    'option_id' => 207,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next207',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-207-a',
];
$next207 = [
    'option_id' => 207,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next207',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"cache","priority":8}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-207-b',
];

$plan207 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = 2,
    ?int $lastYieldedRowid = 9,
    ?int $yieldBatchSize = 4,
    ?array $projection = null,
    int $offset = 0,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidAliasLimit(
    'json_tree',
    $current ?? $current207,
    $next ?? $next207,
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
    $offset,
);

$stable207 = static fn (): array => $plan207($current207, $current207);
$offset207 = static fn (): array => $plan207($current207, $current207, null, [['column' => 'rowid', 'direction' => 'DESC']], 2, 9, 4, null, 1);
$ascending207 = static fn (): array => $plan207($current207, $current207, null, [['column' => '_rowid_', 'direction' => 'ASC']], 3, 6, 5, null, 1);
$zero207 = static fn (): array => $plan207($current207, $current207, null, [['column' => 'rowid', 'direction' => 'DESC']], 0, 9, 4);
$noLimit207 = static fn (): array => $plan207($current207, $current207, null, [['column' => 'rowid', 'direction' => 'DESC']], null, 9, 4);
$offsetEof207 = static fn (): array => $plan207($current207, $current207, null, [['column' => 'rowid', 'direction' => 'DESC']], 2, 9, 4, null, 8);
$unsupported207 = static fn (): array => $plan207($current207, $current207, null, [['column' => 'fullkey', 'direction' => 'ASC']], 2, 9, 4);
$noOrder207 = static fn (): array => $plan207($current207, $current207, null, [], 2, 9, 4);

$tests = [
    'records next207 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next207', $plan207()['dependencies'], true)),
    'preserves next206 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next206', $plan207()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('limit-rowid-alias-order-current-json-table-generated-path-rowid-next207', $plan207()['currentReaderPolicy']),
    'next reader policy reparses on source change' => static fn (TestRunner $t) => $t->same('reprepare-limit-rowid-alias-order-next-json-table-generated-path-rowid-next207', $plan207()['nextReaderPolicy']),
    'stable reader policy reuses' => static fn (TestRunner $t) => $t->same('reuse-limit-rowid-alias-order-current-json-table-generated-path-rowid-next207', $stable207()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable207()['next207ReplanReasons']),
    'current ordered rowids inherited' => static fn (TestRunner $t) => $t->same([9, 8, 7, 6, 5], $plan207()['currentGeneratedPathRowidAliasLimit207']['orderedRowids']),
    'current limit recorded' => static fn (TestRunner $t) => $t->same(2, $plan207()['currentGeneratedPathRowidAliasLimit207']['limit']),
    'current offset recorded' => static fn (TestRunner $t) => $t->same(0, $plan207()['currentGeneratedPathRowidAliasLimit207']['offset']),
    'current limit applied' => static fn (TestRunner $t) => $t->same(true, $plan207()['currentGeneratedPathRowidAliasLimit207']['limitApplied']),
    'current offset not applied' => static fn (TestRunner $t) => $t->same(false, $plan207()['currentGeneratedPathRowidAliasLimit207']['offsetApplied']),
    'current skipped rowids empty' => static fn (TestRunner $t) => $t->same([], $plan207()['currentGeneratedPathRowidAliasLimit207']['skippedRowids']),
    'current bounded rowids top two' => static fn (TestRunner $t) => $t->same([9, 8], $plan207()['currentGeneratedPathRowidAliasLimit207']['boundedRowids']),
    'current remaining after limit' => static fn (TestRunner $t) => $t->same([7, 6, 5], $plan207()['currentGeneratedPathRowidAliasLimit207']['remainingRowidsAfterLimit']),
    'current limit tape count' => static fn (TestRunner $t) => $t->same(2, count($plan207()['currentGeneratedPathRowidAliasLimit207']['limitTape'])),
    'current limit tape first ordinal' => static fn (TestRunner $t) => $t->same(0, $plan207()['currentGeneratedPathRowidAliasLimit207']['limitTape'][0]['ordinal']),
    'current limit tape first source ordinal' => static fn (TestRunner $t) => $t->same(0, $plan207()['currentGeneratedPathRowidAliasLimit207']['limitTape'][0]['sourceOrdinal']),
    'current limit tape first rowid' => static fn (TestRunner $t) => $t->same(9, $plan207()['currentGeneratedPathRowidAliasLimit207']['limitTape'][0]['rowid']),
    'current limit tape emitted' => static fn (TestRunner $t) => $t->same(true, $plan207()['currentGeneratedPathRowidAliasLimit207']['limitTape'][0]['emitted']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $plan207()['currentGeneratedPathRowidAliasLimit207']['orderByConsumed']),
    'current alias order reusable' => static fn (TestRunner $t) => $t->same(true, $plan207()['currentGeneratedPathRowidAliasLimit207']['aliasOrderReusable']),
    'current limit consumed' => static fn (TestRunner $t) => $t->same(true, $plan207()['currentGeneratedPathRowidAliasLimit207']['limitConsumed']),
    'current limit reusable' => static fn (TestRunner $t) => $t->same(true, $plan207()['currentGeneratedPathRowidAliasLimit207']['limitReusable']),
    'current estimated rows bounded' => static fn (TestRunner $t) => $t->same(2, $plan207()['currentGeneratedPathRowidAliasLimit207']['estimatedRows']),
    'current estimated cost bounded' => static fn (TestRunner $t) => $t->same(2, $plan207()['currentGeneratedPathRowidAliasLimit207']['estimatedCost']),
    'current opcode top n' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidAliasLimitTopNext207', $plan207()['currentGeneratedPathRowidAliasLimit207']['limitOpcode']),
    'current cost class top n' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-alias-limit-topn-next207', $plan207()['currentGeneratedPathRowidAliasLimit207']['costClass']),
    'current fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan207()['currentGeneratedPathRowidAliasLimit207']['limitFingerprint'])),
    'next limit not reusable' => static fn (TestRunner $t) => $t->same(false, $plan207()['nextGeneratedPathRowidAliasLimit207']['limitReusable']),
    'next limit not consumed' => static fn (TestRunner $t) => $t->same(false, $plan207()['nextGeneratedPathRowidAliasLimit207']['limitConsumed']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $plan207()['nextGeneratedPathRowidAliasLimit207']['estimatedRows']),
    'next cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $plan207()['nextGeneratedPathRowidAliasLimit207']['estimatedCost']),
    'next opcode reprepare' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidAliasLimitReprepareNext207', $plan207()['nextGeneratedPathRowidAliasLimit207']['limitOpcode']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($plan207()['generatedPathRowidAliasLimit207Transitions'])),
    'reasons include source changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-alias-limit-source-changed-next207', $plan207()['next207ReplanReasons'], true)),
    'reasons include rowset changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-alias-limit-rowset-changed-next207', $plan207()['next207ReplanReasons'], true)),
    'reasons include admission changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-alias-limit-admission-changed-next207', $plan207()['next207ReplanReasons'], true)),
    'reasons include cost changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-alias-limit-cost-changed-next207', $plan207()['next207ReplanReasons'], true)),
    'preserves next206 reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-alias-order-source-changed-next206', $plan207()['next207ReplanReasons'], true)),
    'offset skipped rowids' => static fn (TestRunner $t) => $t->same([9], $offset207()['currentGeneratedPathRowidAliasLimit207']['skippedRowids']),
    'offset bounded rowids' => static fn (TestRunner $t) => $t->same([8, 7], $offset207()['currentGeneratedPathRowidAliasLimit207']['boundedRowids']),
    'offset remaining rowids' => static fn (TestRunner $t) => $t->same([6, 5], $offset207()['currentGeneratedPathRowidAliasLimit207']['remainingRowidsAfterLimit']),
    'offset opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidAliasLimitOffsetNext207', $offset207()['currentGeneratedPathRowidAliasLimit207']['limitOpcode']),
    'offset cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-alias-limit-offset-next207', $offset207()['currentGeneratedPathRowidAliasLimit207']['costClass']),
    'offset estimated cost includes skip' => static fn (TestRunner $t) => $t->same(3, $offset207()['currentGeneratedPathRowidAliasLimit207']['estimatedCost']),
    'offset source ordinal adjusted' => static fn (TestRunner $t) => $t->same(1, $offset207()['currentGeneratedPathRowidAliasLimit207']['limitTape'][0]['sourceOrdinal']),
    'ascending offset bounded rowids' => static fn (TestRunner $t) => $t->same([6, 7, 8], $ascending207()['currentGeneratedPathRowidAliasLimit207']['boundedRowids']),
    'ascending offset skipped rowid' => static fn (TestRunner $t) => $t->same([5], $ascending207()['currentGeneratedPathRowidAliasLimit207']['skippedRowids']),
    'zero limit bounded empty' => static fn (TestRunner $t) => $t->same([], $zero207()['currentGeneratedPathRowidAliasLimit207']['boundedRowids']),
    'zero limit opcode empty' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidAliasLimitEmptyNext207', $zero207()['currentGeneratedPathRowidAliasLimit207']['limitOpcode']),
    'zero limit cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-alias-limit-empty-next207', $zero207()['currentGeneratedPathRowidAliasLimit207']['costClass']),
    'no limit bypass opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidAliasLimitBypassNext207', $noLimit207()['currentGeneratedPathRowidAliasLimit207']['limitOpcode']),
    'no limit bounded all rowids' => static fn (TestRunner $t) => $t->same([9, 8, 7, 6, 5], $noLimit207()['currentGeneratedPathRowidAliasLimit207']['boundedRowids']),
    'offset eof opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidAliasLimitEofNext207', $offsetEof207()['currentGeneratedPathRowidAliasLimit207']['limitOpcode']),
    'offset eof bounded empty' => static fn (TestRunner $t) => $t->same([], $offsetEof207()['currentGeneratedPathRowidAliasLimit207']['boundedRowids']),
    'unsupported order reprepare limit' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidAliasLimitReprepareNext207', $unsupported207()['currentGeneratedPathRowidAliasLimit207']['limitOpcode']),
    'unsupported order limit not reusable' => static fn (TestRunner $t) => $t->same(false, $unsupported207()['currentGeneratedPathRowidAliasLimit207']['limitReusable']),
    'no order limit reprepare' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidAliasLimitReprepareNext207', $noOrder207()['currentGeneratedPathRowidAliasLimit207']['limitOpcode']),
    'negative offset rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan207($current207, $current207, null, null, 2, 9, 4, null, -1)),
    'bad function rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidAliasLimit('json_bad', $current207, $current207, 'option_value', 'generated_path')),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid alias limit ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
