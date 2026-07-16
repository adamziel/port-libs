<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current363 = [
    'option_id' => 363,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_stable',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-363-a',
];
$stable = [
    'option_id' => 363,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_stable',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-363-b',
];

$plan363 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelection(
    'json_tree',
    $current ?? $current363,
    $next ?? $stable,
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

$changed363 = static fn (): array => $plan363();
$stable363 = static fn (): array => $plan363($current363, $current363);
$staleFingerprint363 = static function () use ($plan363, $current363): array {
    return $plan363($current363, $current363, null, null, str_repeat('0', 64), null);
};
$staleRowid363 = static fn (): array => $plan363($current363, $current363, null, null, null, 5);
$noOrder363 = static fn (): array => $plan363($current363, $current363, null, []);
$oidAlias363 = static fn (): array => $plan363($current363, $current363, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias363 = static fn (): array => $plan363($current363, $current363, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records canonical dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source', $changed363()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed363()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid', $changed363()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid', $changed363()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid', $stable363()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable363()['generatedPathRowidCurrentSourceCostSelectionReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-363-a', $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point', $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed363()['currentGeneratedPathRowidCurrentSourceCostSelection']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed363()['nextGeneratedPathRowidCurrentSourceCostSelection']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed363()['nextGeneratedPathRowidCurrentSourceCostSelection']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed363()['nextGeneratedPathRowidCurrentSourceCostSelection']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed363()['nextGeneratedPathRowidCurrentSourceCostSelection']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed363()['nextGeneratedPathRowidCurrentSourceCostSelection']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed363()['nextGeneratedPathRowidCurrentSourceCostSelection']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof', $changed363()['nextGeneratedPathRowidCurrentSourceCostSelection']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed363()['generatedPathRowidCurrentSourceCostSelectionTransitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed363()['generatedPathRowidCurrentSourceCostSelectionTransitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed363()['generatedPathRowidCurrentSourceCostSelectionTransitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed363()['generatedPathRowidCurrentSourceCostSelectionTransitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed363()['generatedPathRowidCurrentSourceCostSelectionTransitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed', $changed363()['generatedPathRowidCurrentSourceCostSelectionReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed', $changed363()['generatedPathRowidCurrentSourceCostSelectionReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed', $changed363()['generatedPathRowidCurrentSourceCostSelectionReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed', $changed363()['generatedPathRowidCurrentSourceCostSelectionReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed', $changed363()['generatedPathRowidCurrentSourceCostSelectionReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed363()['generatedPathRowidCurrentSourceCostSelectionReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint', $staleFingerprint363()['currentGeneratedPathRowidCurrentSourceCostSelection']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint363()['currentGeneratedPathRowidCurrentSourceCostSelection']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid', $staleRowid363()['currentGeneratedPathRowidCurrentSourceCostSelection']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid363()['currentGeneratedPathRowidCurrentSourceCostSelection']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder363()['currentGeneratedPathRowidCurrentSourceCostSelection']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare', $noOrder363()['currentGeneratedPathRowidCurrentSourceCostSelection']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias363()['currentGeneratedPathRowidCurrentSourceCostSelection']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias363()['currentGeneratedPathRowidCurrentSourceCostSelection']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias363()['currentGeneratedPathRowidCurrentSourceCostSelection']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias363()['currentGeneratedPathRowidCurrentSourceCostSelection']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan363($current363, $current363, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan363(array_replace($current363, ['generated_path' => '$.rules[']), $current363)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source canonical ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
