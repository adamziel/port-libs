<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current241 = [
    'option_id' => 241,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next241',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-241-a',
];
$next241 = [
    'option_id' => 241,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next241',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-241-b',
];

$plan241 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    241,
    $current ?? $current241,
    $next ?? $next241,
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

$changed241 = static fn (): array => $plan241();
$stable241 = static fn (): array => $plan241($current241, $current241);
$staleFingerprint241 = static function () use ($plan241, $current241): array {
    return $plan241($current241, $current241, null, null, str_repeat('0', 64), null);
};
$staleRowid241 = static fn (): array => $plan241($current241, $current241, null, null, null, 5);
$noOrder241 = static fn (): array => $plan241($current241, $current241, null, []);
$oidAlias241 = static fn (): array => $plan241($current241, $current241, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias241 = static fn (): array => $plan241($current241, $current241, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next241 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next241', $changed241()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed241()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next241', $changed241()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next241', $changed241()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next241', $stable241()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable241()['next241ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-241-a', $changed241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next241', $changed241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed241()['nextGeneratedPathRowidCurrentSourceCostSelection241']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed241()['nextGeneratedPathRowidCurrentSourceCostSelection241']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed241()['nextGeneratedPathRowidCurrentSourceCostSelection241']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed241()['nextGeneratedPathRowidCurrentSourceCostSelection241']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed241()['nextGeneratedPathRowidCurrentSourceCostSelection241']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed241()['nextGeneratedPathRowidCurrentSourceCostSelection241']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next241', $changed241()['nextGeneratedPathRowidCurrentSourceCostSelection241']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed241()['generatedPathRowidCurrentSourceCostSelection241Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed241()['generatedPathRowidCurrentSourceCostSelection241Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed241()['generatedPathRowidCurrentSourceCostSelection241Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed241()['generatedPathRowidCurrentSourceCostSelection241Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed241()['generatedPathRowidCurrentSourceCostSelection241Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next241', $changed241()['next241ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next241', $changed241()['next241ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next241', $changed241()['next241ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next241', $changed241()['next241ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next241', $changed241()['next241ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed241()['next241ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next241', $staleFingerprint241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next241', $staleRowid241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next241', $noOrder241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias241()['currentGeneratedPathRowidCurrentSourceCostSelection241']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan241($current241, $current241, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan241(array_replace($current241, ['generated_path' => '$.rules[']), $current241)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next241 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
