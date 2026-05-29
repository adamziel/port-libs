<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current252 = [
    'option_id' => 252,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next252',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-252-a',
];
$next252 = [
    'option_id' => 252,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next252',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-252-b',
];

$plan252 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext252(
    'json_tree',
    $current ?? $current252,
    $next ?? $next252,
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

$changed252 = static fn (): array => $plan252();
$stable252 = static fn (): array => $plan252($current252, $current252);
$staleFingerprint252 = static function () use ($plan252, $current252): array {
    return $plan252($current252, $current252, null, null, str_repeat('0', 64), null);
};
$staleRowid252 = static fn (): array => $plan252($current252, $current252, null, null, null, 5);
$noOrder252 = static fn (): array => $plan252($current252, $current252, null, []);
$oidAlias252 = static fn (): array => $plan252($current252, $current252, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias252 = static fn (): array => $plan252($current252, $current252, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next252 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next252', $changed252()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed252()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next252', $changed252()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next252', $changed252()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next252', $stable252()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable252()['next252ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-252-a', $changed252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next252', $changed252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed252()['nextGeneratedPathRowidCurrentSourceCostSelection252']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed252()['nextGeneratedPathRowidCurrentSourceCostSelection252']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed252()['nextGeneratedPathRowidCurrentSourceCostSelection252']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed252()['nextGeneratedPathRowidCurrentSourceCostSelection252']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed252()['nextGeneratedPathRowidCurrentSourceCostSelection252']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed252()['nextGeneratedPathRowidCurrentSourceCostSelection252']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next252', $changed252()['nextGeneratedPathRowidCurrentSourceCostSelection252']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed252()['generatedPathRowidCurrentSourceCostSelection252Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed252()['generatedPathRowidCurrentSourceCostSelection252Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed252()['generatedPathRowidCurrentSourceCostSelection252Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed252()['generatedPathRowidCurrentSourceCostSelection252Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed252()['generatedPathRowidCurrentSourceCostSelection252Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next252', $changed252()['next252ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next252', $changed252()['next252ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next252', $changed252()['next252ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next252', $changed252()['next252ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next252', $changed252()['next252ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed252()['next252ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next252', $staleFingerprint252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next252', $staleRowid252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next252', $noOrder252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias252()['currentGeneratedPathRowidCurrentSourceCostSelection252']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan252($current252, $current252, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan252(array_replace($current252, ['generated_path' => '$.rules[']), $current252)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next252 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
