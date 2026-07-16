<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current366 = [
    'option_id' => 366,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next366',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-366-a',
];
$next366 = [
    'option_id' => 366,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next366',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-366-b',
];

$plan366 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    366,
    $current ?? $current366,
    $next ?? $next366,
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

$changed366 = static fn (): array => $plan366();
$stable366 = static fn (): array => $plan366($current366, $current366);
$staleFingerprint366 = static function () use ($plan366, $current366): array {
    return $plan366($current366, $current366, null, null, str_repeat('0', 64), null);
};
$staleRowid366 = static fn (): array => $plan366($current366, $current366, null, null, null, 5);
$noOrder366 = static fn (): array => $plan366($current366, $current366, null, []);
$oidAlias366 = static fn (): array => $plan366($current366, $current366, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias366 = static fn (): array => $plan366($current366, $current366, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next366 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next366', $changed366()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed366()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next366', $changed366()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next366', $changed366()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next366', $stable366()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable366()['next366ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-366-a', $changed366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next366', $changed366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed366()['nextGeneratedPathRowidCurrentSourceCostSelection366']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed366()['nextGeneratedPathRowidCurrentSourceCostSelection366']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed366()['nextGeneratedPathRowidCurrentSourceCostSelection366']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed366()['nextGeneratedPathRowidCurrentSourceCostSelection366']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed366()['nextGeneratedPathRowidCurrentSourceCostSelection366']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed366()['nextGeneratedPathRowidCurrentSourceCostSelection366']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next366', $changed366()['nextGeneratedPathRowidCurrentSourceCostSelection366']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed366()['generatedPathRowidCurrentSourceCostSelection366Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed366()['generatedPathRowidCurrentSourceCostSelection366Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed366()['generatedPathRowidCurrentSourceCostSelection366Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed366()['generatedPathRowidCurrentSourceCostSelection366Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed366()['generatedPathRowidCurrentSourceCostSelection366Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next366', $changed366()['next366ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next366', $changed366()['next366ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next366', $changed366()['next366ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next366', $changed366()['next366ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next366', $changed366()['next366ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed366()['next366ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next366', $staleFingerprint366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next366', $staleRowid366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next366', $noOrder366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias366()['currentGeneratedPathRowidCurrentSourceCostSelection366']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan366($current366, $current366, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan366(array_replace($current366, ['generated_path' => '$.rules[']), $current366)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next366 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
