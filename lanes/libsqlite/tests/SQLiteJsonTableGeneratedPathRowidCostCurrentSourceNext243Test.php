<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current243 = [
    'option_id' => 243,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next243',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-243-a',
];
$next243 = [
    'option_id' => 243,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next243',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-243-b',
];

$plan243 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    243,
    $current ?? $current243,
    $next ?? $next243,
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

$changed243 = static fn (): array => $plan243();
$stable243 = static fn (): array => $plan243($current243, $current243);
$staleFingerprint243 = static function () use ($plan243, $current243): array {
    return $plan243($current243, $current243, null, null, str_repeat('0', 64), null);
};
$staleRowid243 = static fn (): array => $plan243($current243, $current243, null, null, null, 5);
$noOrder243 = static fn (): array => $plan243($current243, $current243, null, []);
$oidAlias243 = static fn (): array => $plan243($current243, $current243, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias243 = static fn (): array => $plan243($current243, $current243, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next243 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next243', $changed243()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed243()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next243', $changed243()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next243', $changed243()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next243', $stable243()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable243()['next243ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-243-a', $changed243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next243', $changed243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed243()['nextGeneratedPathRowidCurrentSourceCostSelection243']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed243()['nextGeneratedPathRowidCurrentSourceCostSelection243']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed243()['nextGeneratedPathRowidCurrentSourceCostSelection243']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed243()['nextGeneratedPathRowidCurrentSourceCostSelection243']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed243()['nextGeneratedPathRowidCurrentSourceCostSelection243']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed243()['nextGeneratedPathRowidCurrentSourceCostSelection243']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next243', $changed243()['nextGeneratedPathRowidCurrentSourceCostSelection243']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed243()['generatedPathRowidCurrentSourceCostSelection243Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed243()['generatedPathRowidCurrentSourceCostSelection243Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed243()['generatedPathRowidCurrentSourceCostSelection243Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed243()['generatedPathRowidCurrentSourceCostSelection243Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed243()['generatedPathRowidCurrentSourceCostSelection243Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next243', $changed243()['next243ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next243', $changed243()['next243ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next243', $changed243()['next243ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next243', $changed243()['next243ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next243', $changed243()['next243ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed243()['next243ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next243', $staleFingerprint243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next243', $staleRowid243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next243', $noOrder243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias243()['currentGeneratedPathRowidCurrentSourceCostSelection243']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan243($current243, $current243, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan243(array_replace($current243, ['generated_path' => '$.rules[']), $current243)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next243 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
