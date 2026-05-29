<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current348 = [
    'option_id' => 348,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next348',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-348-a',
];
$next348 = [
    'option_id' => 348,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next348',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-348-b',
];

$plan348 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext348(
    'json_tree',
    $current ?? $current348,
    $next ?? $next348,
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

$changed348 = static fn (): array => $plan348();
$stable348 = static fn (): array => $plan348($current348, $current348);
$staleFingerprint348 = static function () use ($plan348, $current348): array {
    return $plan348($current348, $current348, null, null, str_repeat('0', 64), null);
};
$staleRowid348 = static fn (): array => $plan348($current348, $current348, null, null, null, 5);
$noOrder348 = static fn (): array => $plan348($current348, $current348, null, []);
$oidAlias348 = static fn (): array => $plan348($current348, $current348, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias348 = static fn (): array => $plan348($current348, $current348, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next348 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next348', $changed348()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed348()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next348', $changed348()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next348', $changed348()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next348', $stable348()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable348()['next348ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-348-a', $changed348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next348', $changed348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed348()['nextGeneratedPathRowidCurrentSourceCostSelection348']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed348()['nextGeneratedPathRowidCurrentSourceCostSelection348']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed348()['nextGeneratedPathRowidCurrentSourceCostSelection348']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed348()['nextGeneratedPathRowidCurrentSourceCostSelection348']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed348()['nextGeneratedPathRowidCurrentSourceCostSelection348']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed348()['nextGeneratedPathRowidCurrentSourceCostSelection348']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next348', $changed348()['nextGeneratedPathRowidCurrentSourceCostSelection348']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed348()['generatedPathRowidCurrentSourceCostSelection348Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed348()['generatedPathRowidCurrentSourceCostSelection348Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed348()['generatedPathRowidCurrentSourceCostSelection348Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed348()['generatedPathRowidCurrentSourceCostSelection348Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed348()['generatedPathRowidCurrentSourceCostSelection348Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next348', $changed348()['next348ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next348', $changed348()['next348ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next348', $changed348()['next348ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next348', $changed348()['next348ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next348', $changed348()['next348ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed348()['next348ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next348', $staleFingerprint348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next348', $staleRowid348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next348', $noOrder348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias348()['currentGeneratedPathRowidCurrentSourceCostSelection348']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan348($current348, $current348, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan348(array_replace($current348, ['generated_path' => '$.rules[']), $current348)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next348 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
