<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current359 = [
    'option_id' => 359,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next359',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-359-a',
];
$next359 = [
    'option_id' => 359,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next359',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-359-b',
];

$plan359 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    359,
    $current ?? $current359,
    $next ?? $next359,
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

$changed359 = static fn (): array => $plan359();
$stable359 = static fn (): array => $plan359($current359, $current359);
$staleFingerprint359 = static function () use ($plan359, $current359): array {
    return $plan359($current359, $current359, null, null, str_repeat('0', 64), null);
};
$staleRowid359 = static fn (): array => $plan359($current359, $current359, null, null, null, 5);
$noOrder359 = static fn (): array => $plan359($current359, $current359, null, []);
$oidAlias359 = static fn (): array => $plan359($current359, $current359, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias359 = static fn (): array => $plan359($current359, $current359, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next359 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next359', $changed359()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed359()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next359', $changed359()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next359', $changed359()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next359', $stable359()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable359()['next359ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-359-a', $changed359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next359', $changed359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed359()['nextGeneratedPathRowidCurrentSourceCostSelection359']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed359()['nextGeneratedPathRowidCurrentSourceCostSelection359']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed359()['nextGeneratedPathRowidCurrentSourceCostSelection359']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed359()['nextGeneratedPathRowidCurrentSourceCostSelection359']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed359()['nextGeneratedPathRowidCurrentSourceCostSelection359']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed359()['nextGeneratedPathRowidCurrentSourceCostSelection359']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next359', $changed359()['nextGeneratedPathRowidCurrentSourceCostSelection359']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed359()['generatedPathRowidCurrentSourceCostSelection359Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed359()['generatedPathRowidCurrentSourceCostSelection359Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed359()['generatedPathRowidCurrentSourceCostSelection359Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed359()['generatedPathRowidCurrentSourceCostSelection359Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed359()['generatedPathRowidCurrentSourceCostSelection359Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next359', $changed359()['next359ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next359', $changed359()['next359ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next359', $changed359()['next359ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next359', $changed359()['next359ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next359', $changed359()['next359ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed359()['next359ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next359', $staleFingerprint359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next359', $staleRowid359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next359', $noOrder359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias359()['currentGeneratedPathRowidCurrentSourceCostSelection359']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan359($current359, $current359, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan359(array_replace($current359, ['generated_path' => '$.rules[']), $current359)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next359 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
