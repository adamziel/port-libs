<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current242 = [
    'option_id' => 242,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next242',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-242-a',
];
$next242 = [
    'option_id' => 242,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next242',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-242-b',
];

$plan242 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    242,
    $current ?? $current242,
    $next ?? $next242,
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

$changed242 = static fn (): array => $plan242();
$stable242 = static fn (): array => $plan242($current242, $current242);
$staleFingerprint242 = static function () use ($plan242, $current242): array {
    return $plan242($current242, $current242, null, null, str_repeat('0', 64), null);
};
$staleRowid242 = static fn (): array => $plan242($current242, $current242, null, null, null, 5);
$noOrder242 = static fn (): array => $plan242($current242, $current242, null, []);
$oidAlias242 = static fn (): array => $plan242($current242, $current242, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias242 = static fn (): array => $plan242($current242, $current242, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next242 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next242', $changed242()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed242()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next242', $changed242()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next242', $changed242()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next242', $stable242()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable242()['next242ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-242-a', $changed242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next242', $changed242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed242()['nextGeneratedPathRowidCurrentSourceCostSelection242']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed242()['nextGeneratedPathRowidCurrentSourceCostSelection242']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed242()['nextGeneratedPathRowidCurrentSourceCostSelection242']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed242()['nextGeneratedPathRowidCurrentSourceCostSelection242']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed242()['nextGeneratedPathRowidCurrentSourceCostSelection242']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed242()['nextGeneratedPathRowidCurrentSourceCostSelection242']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next242', $changed242()['nextGeneratedPathRowidCurrentSourceCostSelection242']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed242()['generatedPathRowidCurrentSourceCostSelection242Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed242()['generatedPathRowidCurrentSourceCostSelection242Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed242()['generatedPathRowidCurrentSourceCostSelection242Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed242()['generatedPathRowidCurrentSourceCostSelection242Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed242()['generatedPathRowidCurrentSourceCostSelection242Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next242', $changed242()['next242ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next242', $changed242()['next242ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next242', $changed242()['next242ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next242', $changed242()['next242ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next242', $changed242()['next242ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed242()['next242ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next242', $staleFingerprint242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next242', $staleRowid242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next242', $noOrder242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias242()['currentGeneratedPathRowidCurrentSourceCostSelection242']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan242($current242, $current242, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan242(array_replace($current242, ['generated_path' => '$.rules[']), $current242)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next242 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
