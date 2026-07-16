<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current245 = [
    'option_id' => 245,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next245',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-245-a',
];
$next245 = [
    'option_id' => 245,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next245',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-245-b',
];

$plan245 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    245,
    $current ?? $current245,
    $next ?? $next245,
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

$changed245 = static fn (): array => $plan245();
$stable245 = static fn (): array => $plan245($current245, $current245);
$staleFingerprint245 = static function () use ($plan245, $current245): array {
    return $plan245($current245, $current245, null, null, str_repeat('0', 64), null);
};
$staleRowid245 = static fn (): array => $plan245($current245, $current245, null, null, null, 5);
$noOrder245 = static fn (): array => $plan245($current245, $current245, null, []);
$oidAlias245 = static fn (): array => $plan245($current245, $current245, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias245 = static fn (): array => $plan245($current245, $current245, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next245 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next245', $changed245()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed245()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next245', $changed245()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next245', $changed245()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next245', $stable245()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable245()['next245ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-245-a', $changed245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next245', $changed245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed245()['nextGeneratedPathRowidCurrentSourceCostSelection245']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed245()['nextGeneratedPathRowidCurrentSourceCostSelection245']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed245()['nextGeneratedPathRowidCurrentSourceCostSelection245']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed245()['nextGeneratedPathRowidCurrentSourceCostSelection245']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed245()['nextGeneratedPathRowidCurrentSourceCostSelection245']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed245()['nextGeneratedPathRowidCurrentSourceCostSelection245']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next245', $changed245()['nextGeneratedPathRowidCurrentSourceCostSelection245']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed245()['generatedPathRowidCurrentSourceCostSelection245Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed245()['generatedPathRowidCurrentSourceCostSelection245Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed245()['generatedPathRowidCurrentSourceCostSelection245Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed245()['generatedPathRowidCurrentSourceCostSelection245Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed245()['generatedPathRowidCurrentSourceCostSelection245Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next245', $changed245()['next245ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next245', $changed245()['next245ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next245', $changed245()['next245ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next245', $changed245()['next245ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next245', $changed245()['next245ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed245()['next245ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next245', $staleFingerprint245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next245', $staleRowid245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next245', $noOrder245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias245()['currentGeneratedPathRowidCurrentSourceCostSelection245']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan245($current245, $current245, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan245(array_replace($current245, ['generated_path' => '$.rules[']), $current245)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next245 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
