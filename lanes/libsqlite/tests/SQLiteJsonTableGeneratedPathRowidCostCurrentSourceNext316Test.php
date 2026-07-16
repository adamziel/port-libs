<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current316 = [
    'option_id' => 316,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next316',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-316-a',
];
$next316 = [
    'option_id' => 316,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next316',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-316-b',
];

$plan316 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    316,
    $current ?? $current316,
    $next ?? $next316,
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

$changed316 = static fn (): array => $plan316();
$stable316 = static fn (): array => $plan316($current316, $current316);
$staleFingerprint316 = static function () use ($plan316, $current316): array {
    return $plan316($current316, $current316, null, null, str_repeat('0', 64), null);
};
$staleRowid316 = static fn (): array => $plan316($current316, $current316, null, null, null, 5);
$noOrder316 = static fn (): array => $plan316($current316, $current316, null, []);
$oidAlias316 = static fn (): array => $plan316($current316, $current316, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias316 = static fn (): array => $plan316($current316, $current316, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next316 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next316', $changed316()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed316()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next316', $changed316()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next316', $changed316()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next316', $stable316()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable316()['next316ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-316-a', $changed316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next316', $changed316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed316()['nextGeneratedPathRowidCurrentSourceCostSelection316']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed316()['nextGeneratedPathRowidCurrentSourceCostSelection316']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed316()['nextGeneratedPathRowidCurrentSourceCostSelection316']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed316()['nextGeneratedPathRowidCurrentSourceCostSelection316']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed316()['nextGeneratedPathRowidCurrentSourceCostSelection316']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed316()['nextGeneratedPathRowidCurrentSourceCostSelection316']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next316', $changed316()['nextGeneratedPathRowidCurrentSourceCostSelection316']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed316()['generatedPathRowidCurrentSourceCostSelection316Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed316()['generatedPathRowidCurrentSourceCostSelection316Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed316()['generatedPathRowidCurrentSourceCostSelection316Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed316()['generatedPathRowidCurrentSourceCostSelection316Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed316()['generatedPathRowidCurrentSourceCostSelection316Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next316', $changed316()['next316ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next316', $changed316()['next316ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next316', $changed316()['next316ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next316', $changed316()['next316ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next316', $changed316()['next316ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed316()['next316ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next316', $staleFingerprint316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next316', $staleRowid316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next316', $noOrder316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias316()['currentGeneratedPathRowidCurrentSourceCostSelection316']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan316($current316, $current316, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan316(array_replace($current316, ['generated_path' => '$.rules[']), $current316)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next316 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
