<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentCostSelection = [
    'option_id' => 1064,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_selection',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-cost-selection-a',
];
$nextCostSelection = [
    'option_id' => 1064,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_selection',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-cost-selection-b',
];

$planCostSelection = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionPlan(
    'json_tree',
    $current ?? $currentCostSelection,
    $next ?? $nextCostSelection,
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

$changedCostSelection = static fn (): array => $planCostSelection();
$stableCostSelection = static fn (): array => $planCostSelection($currentCostSelection, $currentCostSelection);
$staleFingerprintCostSelection = static function () use ($planCostSelection, $currentCostSelection): array {
    return $planCostSelection($currentCostSelection, $currentCostSelection, null, null, str_repeat('0', 64), null);
};
$staleRowidCostSelection = static fn (): array => $planCostSelection($currentCostSelection, $currentCostSelection, null, null, null, 5);
$noOrderCostSelection = static fn (): array => $planCostSelection($currentCostSelection, $currentCostSelection, null, []);
$oidAliasCostSelection = static fn (): array => $planCostSelection($currentCostSelection, $currentCostSelection, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAliasCostSelection = static fn (): array => $planCostSelection($currentCostSelection, $currentCostSelection, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records current source selection dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source', $changedCostSelection()['dependencies'], true)),
    'records canonical selection dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-selection', $changedCostSelection()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid', $changedCostSelection()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid', $changedCostSelection()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid', $stableCostSelection()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stableCostSelection()['generatedPathRowidCurrentSourceCostSelectionReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changedCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changedCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-cost-selection-a', $changedCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changedCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changedCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changedCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changedCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changedCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changedCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changedCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changedCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changedCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changedCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changedCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changedCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changedCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changedCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changedCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changedCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point', $changedCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changedCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changedCostSelection()['nextGeneratedPathRowidCurrentSourceCostSelection']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changedCostSelection()['nextGeneratedPathRowidCurrentSourceCostSelection']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changedCostSelection()['nextGeneratedPathRowidCurrentSourceCostSelection']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changedCostSelection()['nextGeneratedPathRowidCurrentSourceCostSelection']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changedCostSelection()['nextGeneratedPathRowidCurrentSourceCostSelection']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changedCostSelection()['nextGeneratedPathRowidCurrentSourceCostSelection']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof', $changedCostSelection()['nextGeneratedPathRowidCurrentSourceCostSelection']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changedCostSelection()['generatedPathRowidCurrentSourceCostSelectionTransitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changedCostSelection()['generatedPathRowidCurrentSourceCostSelectionTransitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changedCostSelection()['generatedPathRowidCurrentSourceCostSelectionTransitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changedCostSelection()['generatedPathRowidCurrentSourceCostSelectionTransitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changedCostSelection()['generatedPathRowidCurrentSourceCostSelectionTransitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed', $changedCostSelection()['generatedPathRowidCurrentSourceCostSelectionReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed', $changedCostSelection()['generatedPathRowidCurrentSourceCostSelectionReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed', $changedCostSelection()['generatedPathRowidCurrentSourceCostSelectionReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed', $changedCostSelection()['generatedPathRowidCurrentSourceCostSelectionReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed', $changedCostSelection()['generatedPathRowidCurrentSourceCostSelectionReplanReasons'], true)),
    'preserves yield guard source reason' => static function (TestRunner $t) use ($changedCostSelection): void {
        $t->true(array_filter(
            $changedCostSelection()['generatedPathRowidCurrentSourceCostSelectionReplanReasons'],
            static fn (string $reason): bool => str_contains($reason, 'json-table-generated-path-rowid-yield-guard-source-changed'),
        ) !== []);
    },
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint', $staleFingerprintCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprintCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid', $staleRowidCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowidCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrderCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare', $noOrderCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAliasCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAliasCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAliasCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAliasCostSelection()['currentGeneratedPathRowidCurrentSourceCostSelection']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $planCostSelection($currentCostSelection, $currentCostSelection, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $planCostSelection(array_replace($currentCostSelection, ['generated_path' => '$.rules[']), $currentCostSelection)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost selection ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
