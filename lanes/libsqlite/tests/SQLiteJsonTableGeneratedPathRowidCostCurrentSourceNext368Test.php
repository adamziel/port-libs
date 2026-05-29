<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current368 = [
    'option_id' => 368,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next368',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-368-a',
];
$next368 = [
    'option_id' => 368,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next368',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-368-b',
];

$plan368 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext368(
    'json_tree',
    $current ?? $current368,
    $next ?? $next368,
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

$changed368 = static fn (): array => $plan368();
$stable368 = static fn (): array => $plan368($current368, $current368);
$staleFingerprint368 = static function () use ($plan368, $current368): array {
    return $plan368($current368, $current368, null, null, str_repeat('0', 64), null);
};
$staleRowid368 = static fn (): array => $plan368($current368, $current368, null, null, null, 5);
$noOrder368 = static fn (): array => $plan368($current368, $current368, null, []);
$oidAlias368 = static fn (): array => $plan368($current368, $current368, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias368 = static fn (): array => $plan368($current368, $current368, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next368 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next368', $changed368()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed368()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next368', $changed368()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next368', $changed368()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next368', $stable368()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable368()['next368ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-368-a', $changed368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next368', $changed368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed368()['nextGeneratedPathRowidCurrentSourceCostSelection368']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed368()['nextGeneratedPathRowidCurrentSourceCostSelection368']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed368()['nextGeneratedPathRowidCurrentSourceCostSelection368']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed368()['nextGeneratedPathRowidCurrentSourceCostSelection368']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed368()['nextGeneratedPathRowidCurrentSourceCostSelection368']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed368()['nextGeneratedPathRowidCurrentSourceCostSelection368']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next368', $changed368()['nextGeneratedPathRowidCurrentSourceCostSelection368']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed368()['generatedPathRowidCurrentSourceCostSelection368Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed368()['generatedPathRowidCurrentSourceCostSelection368Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed368()['generatedPathRowidCurrentSourceCostSelection368Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed368()['generatedPathRowidCurrentSourceCostSelection368Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed368()['generatedPathRowidCurrentSourceCostSelection368Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next368', $changed368()['next368ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next368', $changed368()['next368ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next368', $changed368()['next368ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next368', $changed368()['next368ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next368', $changed368()['next368ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed368()['next368ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next368', $staleFingerprint368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next368', $staleRowid368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next368', $noOrder368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias368()['currentGeneratedPathRowidCurrentSourceCostSelection368']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan368($current368, $current368, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan368(array_replace($current368, ['generated_path' => '$.rules[']), $current368)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next368 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
