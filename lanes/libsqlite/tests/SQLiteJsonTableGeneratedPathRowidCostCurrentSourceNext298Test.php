<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current298 = [
    'option_id' => 298,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next298',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-298-a',
];
$next298 = [
    'option_id' => 298,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next298',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-298-b',
];

$plan298 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    298,
    $current ?? $current298,
    $next ?? $next298,
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

$changed298 = static fn (): array => $plan298();
$stable298 = static fn (): array => $plan298($current298, $current298);
$staleFingerprint298 = static function () use ($plan298, $current298): array {
    return $plan298($current298, $current298, null, null, str_repeat('0', 64), null);
};
$staleRowid298 = static fn (): array => $plan298($current298, $current298, null, null, null, 5);
$noOrder298 = static fn (): array => $plan298($current298, $current298, null, []);
$oidAlias298 = static fn (): array => $plan298($current298, $current298, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias298 = static fn (): array => $plan298($current298, $current298, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next298 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next298', $changed298()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed298()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next298', $changed298()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next298', $changed298()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next298', $stable298()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable298()['next298ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-298-a', $changed298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next298', $changed298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed298()['nextGeneratedPathRowidCurrentSourceCostSelection298']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed298()['nextGeneratedPathRowidCurrentSourceCostSelection298']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed298()['nextGeneratedPathRowidCurrentSourceCostSelection298']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed298()['nextGeneratedPathRowidCurrentSourceCostSelection298']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed298()['nextGeneratedPathRowidCurrentSourceCostSelection298']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed298()['nextGeneratedPathRowidCurrentSourceCostSelection298']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next298', $changed298()['nextGeneratedPathRowidCurrentSourceCostSelection298']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed298()['generatedPathRowidCurrentSourceCostSelection298Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed298()['generatedPathRowidCurrentSourceCostSelection298Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed298()['generatedPathRowidCurrentSourceCostSelection298Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed298()['generatedPathRowidCurrentSourceCostSelection298Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed298()['generatedPathRowidCurrentSourceCostSelection298Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next298', $changed298()['next298ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next298', $changed298()['next298ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next298', $changed298()['next298ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next298', $changed298()['next298ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next298', $changed298()['next298ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed298()['next298ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next298', $staleFingerprint298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next298', $staleRowid298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next298', $noOrder298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias298()['currentGeneratedPathRowidCurrentSourceCostSelection298']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan298($current298, $current298, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan298(array_replace($current298, ['generated_path' => '$.rules[']), $current298)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next298 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
