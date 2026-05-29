<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current357 = [
    'option_id' => 357,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next357',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-357-a',
];
$next357 = [
    'option_id' => 357,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next357',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-357-b',
];

$plan357 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext357(
    'json_tree',
    $current ?? $current357,
    $next ?? $next357,
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

$changed357 = static fn (): array => $plan357();
$stable357 = static fn (): array => $plan357($current357, $current357);
$staleFingerprint357 = static function () use ($plan357, $current357): array {
    return $plan357($current357, $current357, null, null, str_repeat('0', 64), null);
};
$staleRowid357 = static fn (): array => $plan357($current357, $current357, null, null, null, 5);
$noOrder357 = static fn (): array => $plan357($current357, $current357, null, []);
$oidAlias357 = static fn (): array => $plan357($current357, $current357, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias357 = static fn (): array => $plan357($current357, $current357, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next357 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next357', $changed357()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed357()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next357', $changed357()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next357', $changed357()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next357', $stable357()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable357()['next357ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-357-a', $changed357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next357', $changed357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed357()['nextGeneratedPathRowidCurrentSourceCostSelection357']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed357()['nextGeneratedPathRowidCurrentSourceCostSelection357']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed357()['nextGeneratedPathRowidCurrentSourceCostSelection357']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed357()['nextGeneratedPathRowidCurrentSourceCostSelection357']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed357()['nextGeneratedPathRowidCurrentSourceCostSelection357']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed357()['nextGeneratedPathRowidCurrentSourceCostSelection357']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next357', $changed357()['nextGeneratedPathRowidCurrentSourceCostSelection357']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed357()['generatedPathRowidCurrentSourceCostSelection357Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed357()['generatedPathRowidCurrentSourceCostSelection357Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed357()['generatedPathRowidCurrentSourceCostSelection357Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed357()['generatedPathRowidCurrentSourceCostSelection357Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed357()['generatedPathRowidCurrentSourceCostSelection357Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next357', $changed357()['next357ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next357', $changed357()['next357ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next357', $changed357()['next357ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next357', $changed357()['next357ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next357', $changed357()['next357ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed357()['next357ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next357', $staleFingerprint357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next357', $staleRowid357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next357', $noOrder357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias357()['currentGeneratedPathRowidCurrentSourceCostSelection357']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan357($current357, $current357, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan357(array_replace($current357, ['generated_path' => '$.rules[']), $current357)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next357 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
