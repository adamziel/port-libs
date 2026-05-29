<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current332 = [
    'option_id' => 332,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next332',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-332-a',
];
$next332 = [
    'option_id' => 332,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next332',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-332-b',
];

$plan332 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext332(
    'json_tree',
    $current ?? $current332,
    $next ?? $next332,
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

$changed332 = static fn (): array => $plan332();
$stable332 = static fn (): array => $plan332($current332, $current332);
$staleFingerprint332 = static function () use ($plan332, $current332): array {
    return $plan332($current332, $current332, null, null, str_repeat('0', 64), null);
};
$staleRowid332 = static fn (): array => $plan332($current332, $current332, null, null, null, 5);
$noOrder332 = static fn (): array => $plan332($current332, $current332, null, []);
$oidAlias332 = static fn (): array => $plan332($current332, $current332, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias332 = static fn (): array => $plan332($current332, $current332, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next332 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next332', $changed332()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed332()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next332', $changed332()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next332', $changed332()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next332', $stable332()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable332()['next332ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-332-a', $changed332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next332', $changed332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed332()['nextGeneratedPathRowidCurrentSourceCostSelection332']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed332()['nextGeneratedPathRowidCurrentSourceCostSelection332']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed332()['nextGeneratedPathRowidCurrentSourceCostSelection332']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed332()['nextGeneratedPathRowidCurrentSourceCostSelection332']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed332()['nextGeneratedPathRowidCurrentSourceCostSelection332']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed332()['nextGeneratedPathRowidCurrentSourceCostSelection332']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next332', $changed332()['nextGeneratedPathRowidCurrentSourceCostSelection332']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed332()['generatedPathRowidCurrentSourceCostSelection332Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed332()['generatedPathRowidCurrentSourceCostSelection332Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed332()['generatedPathRowidCurrentSourceCostSelection332Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed332()['generatedPathRowidCurrentSourceCostSelection332Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed332()['generatedPathRowidCurrentSourceCostSelection332Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next332', $changed332()['next332ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next332', $changed332()['next332ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next332', $changed332()['next332ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next332', $changed332()['next332ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next332', $changed332()['next332ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed332()['next332ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next332', $staleFingerprint332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next332', $staleRowid332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next332', $noOrder332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias332()['currentGeneratedPathRowidCurrentSourceCostSelection332']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan332($current332, $current332, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan332(array_replace($current332, ['generated_path' => '$.rules[']), $current332)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next332 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
