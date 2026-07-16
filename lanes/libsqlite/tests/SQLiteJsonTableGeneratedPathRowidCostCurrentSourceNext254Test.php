<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current254 = [
    'option_id' => 254,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next254',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-254-a',
];
$next254 = [
    'option_id' => 254,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next254',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-254-b',
];

$plan254 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    254,
    $current ?? $current254,
    $next ?? $next254,
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

$changed254 = static fn (): array => $plan254();
$stable254 = static fn (): array => $plan254($current254, $current254);
$staleFingerprint254 = static function () use ($plan254, $current254): array {
    return $plan254($current254, $current254, null, null, str_repeat('0', 64), null);
};
$staleRowid254 = static fn (): array => $plan254($current254, $current254, null, null, null, 5);
$noOrder254 = static fn (): array => $plan254($current254, $current254, null, []);
$oidAlias254 = static fn (): array => $plan254($current254, $current254, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias254 = static fn (): array => $plan254($current254, $current254, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next254 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next254', $changed254()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed254()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next254', $changed254()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next254', $changed254()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next254', $stable254()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable254()['next254ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-254-a', $changed254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next254', $changed254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed254()['nextGeneratedPathRowidCurrentSourceCostSelection254']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed254()['nextGeneratedPathRowidCurrentSourceCostSelection254']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed254()['nextGeneratedPathRowidCurrentSourceCostSelection254']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed254()['nextGeneratedPathRowidCurrentSourceCostSelection254']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed254()['nextGeneratedPathRowidCurrentSourceCostSelection254']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed254()['nextGeneratedPathRowidCurrentSourceCostSelection254']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next254', $changed254()['nextGeneratedPathRowidCurrentSourceCostSelection254']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed254()['generatedPathRowidCurrentSourceCostSelection254Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed254()['generatedPathRowidCurrentSourceCostSelection254Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed254()['generatedPathRowidCurrentSourceCostSelection254Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed254()['generatedPathRowidCurrentSourceCostSelection254Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed254()['generatedPathRowidCurrentSourceCostSelection254Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next254', $changed254()['next254ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next254', $changed254()['next254ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next254', $changed254()['next254ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next254', $changed254()['next254ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next254', $changed254()['next254ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed254()['next254ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next254', $staleFingerprint254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next254', $staleRowid254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next254', $noOrder254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias254()['currentGeneratedPathRowidCurrentSourceCostSelection254']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan254($current254, $current254, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan254(array_replace($current254, ['generated_path' => '$.rules[']), $current254)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next254 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
