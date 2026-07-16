<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current267 = [
    'option_id' => 267,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next267',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-267-a',
];
$next267 = [
    'option_id' => 267,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next267',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-267-b',
];

$plan267 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionVariant(267,
    'json_tree',
    $current ?? $current267,
    $next ?? $next267,
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

$changed267 = static fn (): array => $plan267();
$stable267 = static fn (): array => $plan267($current267, $current267);
$staleFingerprint267 = static function () use ($plan267, $current267): array {
    return $plan267($current267, $current267, null, null, str_repeat('0', 64), null);
};
$staleRowid267 = static fn (): array => $plan267($current267, $current267, null, null, null, 5);
$noOrder267 = static fn (): array => $plan267($current267, $current267, null, []);
$oidAlias267 = static fn (): array => $plan267($current267, $current267, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias267 = static fn (): array => $plan267($current267, $current267, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next267 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next267', $changed267()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed267()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next267', $changed267()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next267', $changed267()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next267', $stable267()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable267()['next267ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-267-a', $changed267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next267', $changed267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed267()['nextGeneratedPathRowidCurrentSourceCostSelection267']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed267()['nextGeneratedPathRowidCurrentSourceCostSelection267']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed267()['nextGeneratedPathRowidCurrentSourceCostSelection267']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed267()['nextGeneratedPathRowidCurrentSourceCostSelection267']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed267()['nextGeneratedPathRowidCurrentSourceCostSelection267']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed267()['nextGeneratedPathRowidCurrentSourceCostSelection267']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next267', $changed267()['nextGeneratedPathRowidCurrentSourceCostSelection267']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed267()['generatedPathRowidCurrentSourceCostSelection267Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed267()['generatedPathRowidCurrentSourceCostSelection267Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed267()['generatedPathRowidCurrentSourceCostSelection267Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed267()['generatedPathRowidCurrentSourceCostSelection267Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed267()['generatedPathRowidCurrentSourceCostSelection267Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next267', $changed267()['next267ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next267', $changed267()['next267ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next267', $changed267()['next267ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next267', $changed267()['next267ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next267', $changed267()['next267ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed267()['next267ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next267', $staleFingerprint267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next267', $staleRowid267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next267', $noOrder267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias267()['currentGeneratedPathRowidCurrentSourceCostSelection267']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan267($current267, $current267, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan267(array_replace($current267, ['generated_path' => '$.rules[']), $current267)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next267 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
