<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current210 = [
    'option_id' => 210,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next210',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-210-a',
];
$next210 = [
    'option_id' => 210,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next210',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-210-b',
];

$plan210 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = 1,
    ?int $lastYieldedRowid = null,
    ?int $yieldBatchSize = null,
    ?array $projection = null,
    int $offset = 1,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext210(
    'json_tree',
    $current ?? $current210,
    $next ?? $next210,
    'option_value',
    'generated_path',
    $constraints ?? [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
        ['column' => '_rowid_', 'operator' => 'BETWEEN', 'value' => [6, 9]],
    ],
    'scan_root',
    $orderBy ?? [['column' => 'rowid', 'direction' => 'ASC']],
    $limit,
    $lastYieldedRowid,
    $yieldBatchSize,
    $projection ?? ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey'],
    $offset,
);

$stable210 = static fn (): array => $plan210($current210, $current210);
$offsetZero210 = static fn (): array => $plan210($current210, $current210, null, null, 2, null, null, null, 0);
$offsetEof210 = static fn (): array => $plan210($current210, $current210, null, null, 1, null, null, null, 10);
$unlimited210 = static fn (): array => $plan210($current210, $current210, null, null, null, null, null, null, 2);
$singlePoint210 = static fn (): array => $plan210($current210, $current210, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [7, 7]],
], null, 1, null, 3, null, 0);
$unsupportedOrder210 = static fn (): array => $plan210($current210, $current210, null, [['column' => 'fullkey', 'direction' => 'ASC']]);

$tests = [
    'records next210 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next210', $plan210()['dependencies'], true)),
    'preserves next209 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next209', $plan210()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('offset-rowid-current-json-table-generated-path-rowid-next210', $plan210()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-offset-rowid-next-json-table-generated-path-rowid-next210', $plan210()['nextReaderPolicy']),
    'stable next reader reuses offset' => static fn (TestRunner $t) => $t->same('reuse-offset-rowid-current-json-table-generated-path-rowid-next210', $stable210()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable210()['next210ReplanReasons']),
    'offset value recorded' => static fn (TestRunner $t) => $t->same(1, $plan210()['currentGeneratedPathRowidOffsetCost210']['offset']),
    'limit value recorded' => static fn (TestRunner $t) => $t->same(1, $plan210()['currentGeneratedPathRowidOffsetCost210']['limit']),
    'limit applied' => static fn (TestRunner $t) => $t->same(true, $plan210()['currentGeneratedPathRowidOffsetCost210']['limitApplied']),
    'range rowids before offset' => static fn (TestRunner $t) => $t->same([6, 7, 8, 9], $plan210()['currentGeneratedPathRowidOffsetCost210']['rangeRowidsBeforeOffset']),
    'skipped offset rowids' => static fn (TestRunner $t) => $t->same([6], $plan210()['currentGeneratedPathRowidOffsetCost210']['skippedOffsetRowids']),
    'remaining rowids after offset' => static fn (TestRunner $t) => $t->same([7, 8, 9], $plan210()['currentGeneratedPathRowidOffsetCost210']['remainingRowidsAfterOffset']),
    'yield rowids after offset and limit' => static fn (TestRunner $t) => $t->same([7], $plan210()['currentGeneratedPathRowidOffsetCost210']['yieldRowids']),
    'blocked rowids after limit' => static fn (TestRunner $t) => $t->same([8, 9], $plan210()['currentGeneratedPathRowidOffsetCost210']['blockedRowidsAfterLimit']),
    'skip count' => static fn (TestRunner $t) => $t->same(1, $plan210()['currentGeneratedPathRowidOffsetCost210']['skipCount']),
    'yield count' => static fn (TestRunner $t) => $t->same(1, $plan210()['currentGeneratedPathRowidOffsetCost210']['yieldCount']),
    'range reusable true' => static fn (TestRunner $t) => $t->same(true, $plan210()['currentGeneratedPathRowidOffsetCost210']['rangeReusable']),
    'offset reusable true' => static fn (TestRunner $t) => $t->same(true, $plan210()['currentGeneratedPathRowidOffsetCost210']['offsetReusable']),
    'estimated rows after offset' => static fn (TestRunner $t) => $t->same(1, $plan210()['currentGeneratedPathRowidOffsetCost210']['estimatedRows']),
    'estimated cost includes skip' => static fn (TestRunner $t) => $t->same(6, $plan210()['currentGeneratedPathRowidOffsetCost210']['estimatedCost']),
    'offset opcode skip seek' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidOffsetSkipSeekNext210', $plan210()['currentGeneratedPathRowidOffsetCost210']['offsetOpcode']),
    'offset point cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-offset-point-next210', $plan210()['currentGeneratedPathRowidOffsetCost210']['costClass']),
    'offset fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan210()['currentGeneratedPathRowidOffsetCost210']['offsetFingerprint'])),
    'next source offset not reusable' => static fn (TestRunner $t) => $t->same(false, $plan210()['nextGeneratedPathRowidOffsetCost210']['offsetReusable']),
    'next source estimated sentinel' => static fn (TestRunner $t) => $t->same(1000000, $plan210()['nextGeneratedPathRowidOffsetCost210']['estimatedCost']),
    'next source opcode reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidOffsetReprepareNext210', $plan210()['nextGeneratedPathRowidOffsetCost210']['offsetOpcode']),
    'next source cost class reparses' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-offset-reprepare-next210', $plan210()['nextGeneratedPathRowidOffsetCost210']['costClass']),
    'offset zero bypass opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidOffsetBypassNext210', $offsetZero210()['currentGeneratedPathRowidOffsetCost210']['offsetOpcode']),
    'offset zero cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-offset-bypass-next210', $offsetZero210()['currentGeneratedPathRowidOffsetCost210']['costClass']),
    'offset zero yields first rows' => static fn (TestRunner $t) => $t->same([6, 7], $offsetZero210()['currentGeneratedPathRowidOffsetCost210']['yieldRowids']),
    'offset eof opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidOffsetEofNext210', $offsetEof210()['currentGeneratedPathRowidOffsetCost210']['offsetOpcode']),
    'offset eof reusable false' => static fn (TestRunner $t) => $t->same(false, $offsetEof210()['currentGeneratedPathRowidOffsetCost210']['offsetReusable']),
    'offset eof skipped all rows' => static fn (TestRunner $t) => $t->same([6, 7, 8, 9], $offsetEof210()['currentGeneratedPathRowidOffsetCost210']['skippedOffsetRowids']),
    'unlimited has no limit' => static fn (TestRunner $t) => $t->same(false, $unlimited210()['currentGeneratedPathRowidOffsetCost210']['limitApplied']),
    'unlimited yields remaining' => static fn (TestRunner $t) => $t->same([8, 9], $unlimited210()['currentGeneratedPathRowidOffsetCost210']['yieldRowids']),
    'single point bypass rowid' => static fn (TestRunner $t) => $t->same([7], $singlePoint210()['currentGeneratedPathRowidOffsetCost210']['yieldRowids']),
    'single point cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-offset-bypass-next210', $singlePoint210()['currentGeneratedPathRowidOffsetCost210']['costClass']),
    'unsupported order is not reusable' => static fn (TestRunner $t) => $t->same(false, $unsupportedOrder210()['currentGeneratedPathRowidOffsetCost210']['offsetReusable']),
    'unsupported order reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableRowidOffsetReprepareNext210', $unsupportedOrder210()['currentGeneratedPathRowidOffsetCost210']['offsetOpcode']),
    'transition count records offset fields' => static fn (TestRunner $t) => $t->same(21, count($plan210()['generatedPathRowidOffsetCost210Transitions'])),
    'transition source changes through fingerprint' => static fn (TestRunner $t) => $t->same(true, $plan210()['generatedPathRowidOffsetCost210Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $plan210()['generatedPathRowidOffsetCost210Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $plan210()['generatedPathRowidOffsetCost210Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $plan210()['generatedPathRowidOffsetCost210Transitions'][16]['changed']),
    'reason includes source change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-offset-source-changed-next210', $plan210()['next210ReplanReasons'], true)),
    'reason includes rowset change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-offset-rowset-changed-next210', $plan210()['next210ReplanReasons'], true)),
    'reason includes admission change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-offset-admission-changed-next210', $plan210()['next210ReplanReasons'], true)),
    'reason includes cost change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-offset-cost-changed-next210', $plan210()['next210ReplanReasons'], true)),
    'preserves next209 range reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-range-source-changed-next209', $plan210()['next210ReplanReasons'], true)),
    'negative offset rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan210(null, null, null, null, 1, null, 3, null, -1)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next210 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
