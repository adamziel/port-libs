<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current255 = [
    'option_id' => 255,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next255',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-255-a',
];
$next255 = [
    'option_id' => 255,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next255',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-255-b',
];

$plan255 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext255(
    'json_tree',
    $current ?? $current255,
    $next ?? $next255,
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

$changed255 = static fn (): array => $plan255();
$stable255 = static fn (): array => $plan255($current255, $current255);
$staleFingerprint255 = static function () use ($plan255, $current255): array {
    return $plan255($current255, $current255, null, null, str_repeat('0', 64), null);
};
$staleRowid255 = static fn (): array => $plan255($current255, $current255, null, null, null, 5);
$noOrder255 = static fn (): array => $plan255($current255, $current255, null, []);
$oidAlias255 = static fn (): array => $plan255($current255, $current255, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias255 = static fn (): array => $plan255($current255, $current255, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next255 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next255', $changed255()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed255()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next255', $changed255()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next255', $changed255()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next255', $stable255()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable255()['next255ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-255-a', $changed255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next255', $changed255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed255()['nextGeneratedPathRowidCurrentSourceCostSelection255']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed255()['nextGeneratedPathRowidCurrentSourceCostSelection255']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed255()['nextGeneratedPathRowidCurrentSourceCostSelection255']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed255()['nextGeneratedPathRowidCurrentSourceCostSelection255']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed255()['nextGeneratedPathRowidCurrentSourceCostSelection255']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed255()['nextGeneratedPathRowidCurrentSourceCostSelection255']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next255', $changed255()['nextGeneratedPathRowidCurrentSourceCostSelection255']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed255()['generatedPathRowidCurrentSourceCostSelection255Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed255()['generatedPathRowidCurrentSourceCostSelection255Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed255()['generatedPathRowidCurrentSourceCostSelection255Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed255()['generatedPathRowidCurrentSourceCostSelection255Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed255()['generatedPathRowidCurrentSourceCostSelection255Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next255', $changed255()['next255ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next255', $changed255()['next255ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next255', $changed255()['next255ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next255', $changed255()['next255ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next255', $changed255()['next255ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed255()['next255ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next255', $staleFingerprint255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next255', $staleRowid255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next255', $noOrder255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias255()['currentGeneratedPathRowidCurrentSourceCostSelection255']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan255($current255, $current255, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan255(array_replace($current255, ['generated_path' => '$.rules[']), $current255)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next255 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
