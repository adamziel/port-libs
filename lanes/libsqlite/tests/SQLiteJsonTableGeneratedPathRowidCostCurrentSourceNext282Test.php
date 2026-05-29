<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current282 = [
    'option_id' => 282,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next282',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-282-a',
];
$next282 = [
    'option_id' => 282,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next282',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-282-b',
];

$plan282 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionVariant(282,
    'json_tree',
    $current ?? $current282,
    $next ?? $next282,
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

$changed282 = static fn (): array => $plan282();
$stable282 = static fn (): array => $plan282($current282, $current282);
$staleFingerprint282 = static function () use ($plan282, $current282): array {
    return $plan282($current282, $current282, null, null, str_repeat('0', 64), null);
};
$staleRowid282 = static fn (): array => $plan282($current282, $current282, null, null, null, 5);
$noOrder282 = static fn (): array => $plan282($current282, $current282, null, []);
$oidAlias282 = static fn (): array => $plan282($current282, $current282, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias282 = static fn (): array => $plan282($current282, $current282, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next282 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next282', $changed282()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed282()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next282', $changed282()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next282', $changed282()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next282', $stable282()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable282()['next282ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-282-a', $changed282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next282', $changed282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed282()['nextGeneratedPathRowidCurrentSourceCostSelection282']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed282()['nextGeneratedPathRowidCurrentSourceCostSelection282']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed282()['nextGeneratedPathRowidCurrentSourceCostSelection282']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed282()['nextGeneratedPathRowidCurrentSourceCostSelection282']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed282()['nextGeneratedPathRowidCurrentSourceCostSelection282']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed282()['nextGeneratedPathRowidCurrentSourceCostSelection282']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next282', $changed282()['nextGeneratedPathRowidCurrentSourceCostSelection282']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed282()['generatedPathRowidCurrentSourceCostSelection282Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed282()['generatedPathRowidCurrentSourceCostSelection282Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed282()['generatedPathRowidCurrentSourceCostSelection282Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed282()['generatedPathRowidCurrentSourceCostSelection282Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed282()['generatedPathRowidCurrentSourceCostSelection282Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next282', $changed282()['next282ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next282', $changed282()['next282ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next282', $changed282()['next282ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next282', $changed282()['next282ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next282', $changed282()['next282ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed282()['next282ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next282', $staleFingerprint282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next282', $staleRowid282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next282', $noOrder282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias282()['currentGeneratedPathRowidCurrentSourceCostSelection282']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan282($current282, $current282, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan282(array_replace($current282, ['generated_path' => '$.rules[']), $current282)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next282 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
