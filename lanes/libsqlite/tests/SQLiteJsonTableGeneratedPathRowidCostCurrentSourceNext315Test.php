<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current315 = [
    'option_id' => 315,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next315',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-315-a',
];
$next315 = [
    'option_id' => 315,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next315',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-315-b',
];

$plan315 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext315(
    'json_tree',
    $current ?? $current315,
    $next ?? $next315,
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

$changed315 = static fn (): array => $plan315();
$stable315 = static fn (): array => $plan315($current315, $current315);
$staleFingerprint315 = static function () use ($plan315, $current315): array {
    return $plan315($current315, $current315, null, null, str_repeat('0', 64), null);
};
$staleRowid315 = static fn (): array => $plan315($current315, $current315, null, null, null, 5);
$noOrder315 = static fn (): array => $plan315($current315, $current315, null, []);
$oidAlias315 = static fn (): array => $plan315($current315, $current315, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias315 = static fn (): array => $plan315($current315, $current315, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next315 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next315', $changed315()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed315()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next315', $changed315()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next315', $changed315()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next315', $stable315()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable315()['next315ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-315-a', $changed315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next315', $changed315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed315()['nextGeneratedPathRowidCurrentSourceCostSelection315']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed315()['nextGeneratedPathRowidCurrentSourceCostSelection315']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed315()['nextGeneratedPathRowidCurrentSourceCostSelection315']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed315()['nextGeneratedPathRowidCurrentSourceCostSelection315']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed315()['nextGeneratedPathRowidCurrentSourceCostSelection315']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed315()['nextGeneratedPathRowidCurrentSourceCostSelection315']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next315', $changed315()['nextGeneratedPathRowidCurrentSourceCostSelection315']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed315()['generatedPathRowidCurrentSourceCostSelection315Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed315()['generatedPathRowidCurrentSourceCostSelection315Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed315()['generatedPathRowidCurrentSourceCostSelection315Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed315()['generatedPathRowidCurrentSourceCostSelection315Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed315()['generatedPathRowidCurrentSourceCostSelection315Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next315', $changed315()['next315ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next315', $changed315()['next315ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next315', $changed315()['next315ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next315', $changed315()['next315ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next315', $changed315()['next315ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed315()['next315ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next315', $staleFingerprint315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next315', $staleRowid315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next315', $noOrder315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias315()['currentGeneratedPathRowidCurrentSourceCostSelection315']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan315($current315, $current315, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan315(array_replace($current315, ['generated_path' => '$.rules[']), $current315)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next315 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
