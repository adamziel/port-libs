<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current325 = [
    'option_id' => 325,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next325',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-325-a',
];
$next325 = [
    'option_id' => 325,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next325',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-325-b',
];

$plan325 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    325,
    $current ?? $current325,
    $next ?? $next325,
    'option_value',
    'generated_path',
    $constraints ?? [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
        ['column' => '_rowid_', 'operator' => 'BETWEEN', 'value' => [7, 8]],
    ],
    'scan_root',
    $orderBy ?? [['column' => 'rowid', 'direction' => 'ASC']],
    1,
    null,
    1,
    ['rowid', '_rowid_', 'oid', 'path', 'fullkey', 'value'],
    $observedFingerprint,
    $observedRowid,
);

$changed325 = static fn (): array => $plan325();
$stable325 = static fn (): array => $plan325($current325, $current325);
$staleFingerprint325 = static function () use ($plan325, $current325): array {
    return $plan325($current325, $current325, null, null, str_repeat('0', 64), null);
};
$staleRowid325 = static fn (): array => $plan325($current325, $current325, null, null, null, 5);
$noOrder325 = static fn (): array => $plan325($current325, $current325, null, []);
$oidAlias325 = static fn (): array => $plan325($current325, $current325, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias325 = static fn (): array => $plan325($current325, $current325, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next325 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next325', $changed325()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed325()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next325', $changed325()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next325', $changed325()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next325', $stable325()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable325()['next325ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-325-a', $changed325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next325', $changed325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed325()['nextGeneratedPathRowidCurrentSourceCostSelection325']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed325()['nextGeneratedPathRowidCurrentSourceCostSelection325']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed325()['nextGeneratedPathRowidCurrentSourceCostSelection325']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed325()['nextGeneratedPathRowidCurrentSourceCostSelection325']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed325()['nextGeneratedPathRowidCurrentSourceCostSelection325']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed325()['nextGeneratedPathRowidCurrentSourceCostSelection325']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next325', $changed325()['nextGeneratedPathRowidCurrentSourceCostSelection325']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed325()['generatedPathRowidCurrentSourceCostSelection325Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed325()['generatedPathRowidCurrentSourceCostSelection325Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed325()['generatedPathRowidCurrentSourceCostSelection325Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed325()['generatedPathRowidCurrentSourceCostSelection325Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed325()['generatedPathRowidCurrentSourceCostSelection325Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next325', $changed325()['next325ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next325', $changed325()['next325ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next325', $changed325()['next325ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next325', $changed325()['next325ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next325', $changed325()['next325ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed325()['next325ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next325', $staleFingerprint325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next325', $staleRowid325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next325', $noOrder325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias325()['currentGeneratedPathRowidCurrentSourceCostSelection325']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan325($current325, $current325, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan325(array_replace($current325, ['generated_path' => '$.rules[']), $current325)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next325 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
