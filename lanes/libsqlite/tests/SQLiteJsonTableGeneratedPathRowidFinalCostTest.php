<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current184 = [
    'option_id' => 184,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next184',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 18401,
];
$next184 = [
    'option_id' => 184,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next184',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 18402,
];

$plan184 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = null,
    ?int $lastYieldedRowid = null,
    ?array $projection = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidFinalCostPlan(
    'json_tree',
    $current ?? $current184,
    $next ?? $next184,
    'option_value',
    'generated_path',
    $constraints ?? [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'IN', 'value' => [5, 6, 42]],
    ],
    'scan_root',
    $orderBy ?? [['column' => '_rowid_', 'direction' => 'DESC']],
    $limit,
    $lastYieldedRowid,
    $projection ?? ['id', 'fullkey', 'atom', 'value', 'type'],
);

$resume184 = static fn (): array => $plan184(null, null, null, null, null, 6);
$stable184 = static fn (): array => $plan184($current184, $current184, null, null, null, 6);
$first184 = static fn (): array => $plan184($current184, $current184);
$point184 = static fn (): array => $plan184(
    array_replace($current184, ['generated_path' => '$.rules[1]', 'source_generation' => 'same']),
    array_replace($current184, ['generated_path' => '$.rules[1]', 'source_generation' => 'same']),
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ],
    [['column' => 'id']],
    null,
    null,
    ['id', 'fullkey', 'value'],
);
$missing184 = static fn (): array => $plan184($current184, $current184, null, null, null, 99);

$tests = [
    'records next184 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next184', $resume184()['dependencies'], true)),
    'preserves xcolumn snapshot dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-xcolumn-snapshot', $resume184()['dependencies'], true)),
    'current reader admits final cost' => static fn (TestRunner $t) => $t->same('admit-current-json-table-generated-path-rowid-final-cost-next184', $resume184()['currentReaderPolicy']),
    'next changed source reparses final cost' => static fn (TestRunner $t) => $t->same('reprepare-next-json-table-generated-path-rowid-final-cost-next184', $resume184()['nextReaderPolicy']),
    'stable reuses final cost' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-final-cost-next184', $stable184()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable184()['next184ReplanReasons']),
    'current generation pinned' => static fn (TestRunner $t) => $t->same('source_generation:18401', $resume184()['currentGeneratedPathRowidFinalCost184']['sourceGeneration']),
    'next generation changed' => static fn (TestRunner $t) => $t->same('source_generation:18402', $resume184()['nextGeneratedPathRowidFinalCost184']['sourceGeneration']),
    'cache key is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($resume184()['currentGeneratedPathRowidFinalCost184']['cacheKey'])),
    'cursor generation is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($resume184()['currentGeneratedPathRowidFinalCost184']['cursorGeneration'])),
    'snapshot fingerprint is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($resume184()['currentGeneratedPathRowidFinalCost184']['snapshotFingerprint'])),
    'final fingerprint is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($resume184()['currentGeneratedPathRowidFinalCost184']['finalCostFingerprint'])),
    'rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_', 'oid', 'id'], $resume184()['currentGeneratedPathRowidFinalCost184']['rowidAliasColumns']),
    'projection normalized' => static fn (TestRunner $t) => $t->same(['id', 'fullkey', 'atom', 'value', 'type'], $resume184()['currentGeneratedPathRowidFinalCost184']['projection']),
    'resume selected rowid' => static fn (TestRunner $t) => $t->same([5], $resume184()['currentGeneratedPathRowidFinalCost184']['selectedRowids']),
    'resume selected row count one' => static fn (TestRunner $t) => $t->same(1, $resume184()['currentGeneratedPathRowidFinalCost184']['selectedRowCount']),
    'resume materialized row count one' => static fn (TestRunner $t) => $t->same(1, $resume184()['currentGeneratedPathRowidFinalCost184']['materializedRowCount']),
    'resume missing rowids empty' => static fn (TestRunner $t) => $t->same([], $resume184()['currentGeneratedPathRowidFinalCost184']['missingRowids']),
    'resume residual columns empty' => static fn (TestRunner $t) => $t->same([], $resume184()['currentGeneratedPathRowidFinalCost184']['residualConstraintColumns']),
    'resume source pinned' => static fn (TestRunner $t) => $t->same(true, $resume184()['currentGeneratedPathRowidFinalCost184']['currentSourcePinned']),
    'resume xcolumn reusable' => static fn (TestRunner $t) => $t->same(true, $resume184()['currentGeneratedPathRowidFinalCost184']['xColumnReusable']),
    'resume not stale' => static fn (TestRunner $t) => $t->same(false, $resume184()['currentGeneratedPathRowidFinalCost184']['staleAfterNextSource']),
    'resume covering snapshot true' => static fn (TestRunner $t) => $t->same(true, $resume184()['currentGeneratedPathRowidFinalCost184']['coveringSnapshot']),
    'resume cursor disposition covering' => static fn (TestRunner $t) => $t->same('admit-covering-current-source-generated-path-rowid-snapshot', $resume184()['currentGeneratedPathRowidFinalCost184']['cursorDisposition']),
    'resume estimated rows one' => static fn (TestRunner $t) => $t->same(1, $resume184()['currentGeneratedPathRowidFinalCost184']['estimatedRows']),
    'resume estimated cost one' => static fn (TestRunner $t) => $t->same(1, $resume184()['currentGeneratedPathRowidFinalCost184']['estimatedCost']),
    'resume cost class point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-final-cost-covering-point-next184', $resume184()['currentGeneratedPathRowidFinalCost184']['costClass']),
    'next stale after source' => static fn (TestRunner $t) => $t->same(true, $resume184()['nextGeneratedPathRowidFinalCost184']['staleAfterNextSource']),
    'next not covering' => static fn (TestRunner $t) => $t->same(false, $resume184()['nextGeneratedPathRowidFinalCost184']['coveringSnapshot']),
    'next disposition reprepare' => static fn (TestRunner $t) => $t->same('reprepare-stale-next-source-generated-path-rowid-snapshot', $resume184()['nextGeneratedPathRowidFinalCost184']['cursorDisposition']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $resume184()['nextGeneratedPathRowidFinalCost184']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $resume184()['nextGeneratedPathRowidFinalCost184']['estimatedCost']),
    'next cost class reprepare' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-final-cost-reprepare-next184', $resume184()['nextGeneratedPathRowidFinalCost184']['costClass']),
    'first selected rowids' => static fn (TestRunner $t) => $t->same([6, 5], $first184()['currentGeneratedPathRowidFinalCost184']['selectedRowids']),
    'first cost class range' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-final-cost-covering-range-next184', $first184()['currentGeneratedPathRowidFinalCost184']['costClass']),
    'point projection narrowed' => static fn (TestRunner $t) => $t->same(['id', 'fullkey', 'value'], $point184()['currentGeneratedPathRowidFinalCost184']['projection']),
    'point selected rowid six' => static fn (TestRunner $t) => $t->same([6], $point184()['currentGeneratedPathRowidFinalCost184']['selectedRowids']),
    'point cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-final-cost-covering-point-next184', $point184()['currentGeneratedPathRowidFinalCost184']['costClass']),
    'missing rowid not covering' => static fn (TestRunner $t) => $t->same(false, $missing184()['currentGeneratedPathRowidFinalCost184']['coveringSnapshot']),
    'missing rowid disposition reseek' => static fn (TestRunner $t) => $t->same('reseek-missing-rowid-generated-path-rowid-snapshot', $missing184()['currentGeneratedPathRowidFinalCost184']['cursorDisposition']),
    'missing rowid class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-final-cost-missing-rowid-next184', $missing184()['currentGeneratedPathRowidFinalCost184']['costClass']),
    'transition count records final cost state' => static fn (TestRunner $t) => $t->same(16, count($resume184()['generatedPathRowidFinalCost184Transitions'])),
    'transition generation changes' => static fn (TestRunner $t) => $t->same(true, $resume184()['generatedPathRowidFinalCost184Transitions'][0]['changed']),
    'transition selected rowids change' => static fn (TestRunner $t) => $t->same(true, $resume184()['generatedPathRowidFinalCost184Transitions'][6]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $resume184()['generatedPathRowidFinalCost184Transitions'][10]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $resume184()['generatedPathRowidFinalCost184Transitions'][13]['changed']),
    'reasons include source snapshot' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-final-cost-source-snapshot-changed-next184', $resume184()['next184ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-final-cost-rowset-changed-next184', $resume184()['next184ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-final-cost-admission-changed-next184', $resume184()['next184ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-final-cost-cost-changed-next184', $resume184()['next184ReplanReasons'], true)),
    'bad projection rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan184(null, null, null, null, null, null, ['bad_column'])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid final cost ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
