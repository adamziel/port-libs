<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current263 = [
    'option_id' => 263,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next263',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-263-a',
];
$next263 = [
    'option_id' => 263,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next263',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-263-b',
];

$plan263 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionVariant(263,
    'json_tree',
    $current ?? $current263,
    $next ?? $next263,
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

$changed263 = static fn (): array => $plan263();
$stable263 = static fn (): array => $plan263($current263, $current263);
$staleFingerprint263 = static function () use ($plan263, $current263): array {
    return $plan263($current263, $current263, null, null, str_repeat('0', 64), null);
};
$staleRowid263 = static fn (): array => $plan263($current263, $current263, null, null, null, 5);
$noOrder263 = static fn (): array => $plan263($current263, $current263, null, []);
$oidAlias263 = static fn (): array => $plan263($current263, $current263, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias263 = static fn (): array => $plan263($current263, $current263, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next263 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next263', $changed263()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed263()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next263', $changed263()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next263', $changed263()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next263', $stable263()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable263()['next263ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-263-a', $changed263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next263', $changed263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed263()['nextGeneratedPathRowidCurrentSourceCostSelection263']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed263()['nextGeneratedPathRowidCurrentSourceCostSelection263']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed263()['nextGeneratedPathRowidCurrentSourceCostSelection263']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed263()['nextGeneratedPathRowidCurrentSourceCostSelection263']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed263()['nextGeneratedPathRowidCurrentSourceCostSelection263']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed263()['nextGeneratedPathRowidCurrentSourceCostSelection263']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next263', $changed263()['nextGeneratedPathRowidCurrentSourceCostSelection263']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed263()['generatedPathRowidCurrentSourceCostSelection263Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed263()['generatedPathRowidCurrentSourceCostSelection263Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed263()['generatedPathRowidCurrentSourceCostSelection263Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed263()['generatedPathRowidCurrentSourceCostSelection263Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed263()['generatedPathRowidCurrentSourceCostSelection263Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next263', $changed263()['next263ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next263', $changed263()['next263ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next263', $changed263()['next263ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next263', $changed263()['next263ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next263', $changed263()['next263ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed263()['next263ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next263', $staleFingerprint263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next263', $staleRowid263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next263', $noOrder263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias263()['currentGeneratedPathRowidCurrentSourceCostSelection263']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan263($current263, $current263, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan263(array_replace($current263, ['generated_path' => '$.rules[']), $current263)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next263 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
