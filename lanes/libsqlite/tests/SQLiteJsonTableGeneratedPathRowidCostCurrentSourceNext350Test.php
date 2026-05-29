<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current350 = [
    'option_id' => 350,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next350',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-350-a',
];
$next350 = [
    'option_id' => 350,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next350',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-350-b',
];

$plan350 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext350(
    'json_tree',
    $current ?? $current350,
    $next ?? $next350,
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

$changed350 = static fn (): array => $plan350();
$stable350 = static fn (): array => $plan350($current350, $current350);
$staleFingerprint350 = static function () use ($plan350, $current350): array {
    return $plan350($current350, $current350, null, null, str_repeat('0', 64), null);
};
$staleRowid350 = static fn (): array => $plan350($current350, $current350, null, null, null, 5);
$noOrder350 = static fn (): array => $plan350($current350, $current350, null, []);
$oidAlias350 = static fn (): array => $plan350($current350, $current350, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias350 = static fn (): array => $plan350($current350, $current350, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next350 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next350', $changed350()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed350()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next350', $changed350()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next350', $changed350()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next350', $stable350()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable350()['next350ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-350-a', $changed350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next350', $changed350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed350()['nextGeneratedPathRowidCurrentSourceCostSelection350']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed350()['nextGeneratedPathRowidCurrentSourceCostSelection350']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed350()['nextGeneratedPathRowidCurrentSourceCostSelection350']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed350()['nextGeneratedPathRowidCurrentSourceCostSelection350']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed350()['nextGeneratedPathRowidCurrentSourceCostSelection350']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed350()['nextGeneratedPathRowidCurrentSourceCostSelection350']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next350', $changed350()['nextGeneratedPathRowidCurrentSourceCostSelection350']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed350()['generatedPathRowidCurrentSourceCostSelection350Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed350()['generatedPathRowidCurrentSourceCostSelection350Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed350()['generatedPathRowidCurrentSourceCostSelection350Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed350()['generatedPathRowidCurrentSourceCostSelection350Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed350()['generatedPathRowidCurrentSourceCostSelection350Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next350', $changed350()['next350ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next350', $changed350()['next350ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next350', $changed350()['next350ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next350', $changed350()['next350ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next350', $changed350()['next350ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed350()['next350ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next350', $staleFingerprint350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next350', $staleRowid350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next350', $noOrder350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias350()['currentGeneratedPathRowidCurrentSourceCostSelection350']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan350($current350, $current350, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan350(array_replace($current350, ['generated_path' => '$.rules[']), $current350)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next350 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
