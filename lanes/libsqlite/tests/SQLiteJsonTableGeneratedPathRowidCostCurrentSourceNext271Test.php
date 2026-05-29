<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current271 = [
    'option_id' => 271,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next271',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-271-a',
];
$next271 = [
    'option_id' => 271,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next271',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-271-b',
];

$plan271 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext271(
    'json_tree',
    $current ?? $current271,
    $next ?? $next271,
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

$changed271 = static fn (): array => $plan271();
$stable271 = static fn (): array => $plan271($current271, $current271);
$staleFingerprint271 = static function () use ($plan271, $current271): array {
    return $plan271($current271, $current271, null, null, str_repeat('0', 64), null);
};
$staleRowid271 = static fn (): array => $plan271($current271, $current271, null, null, null, 5);
$noOrder271 = static fn (): array => $plan271($current271, $current271, null, []);
$oidAlias271 = static fn (): array => $plan271($current271, $current271, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias271 = static fn (): array => $plan271($current271, $current271, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next271 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next271', $changed271()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed271()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next271', $changed271()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next271', $changed271()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next271', $stable271()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable271()['next271ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-271-a', $changed271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next271', $changed271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed271()['nextGeneratedPathRowidCurrentSourceCostSelection271']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed271()['nextGeneratedPathRowidCurrentSourceCostSelection271']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed271()['nextGeneratedPathRowidCurrentSourceCostSelection271']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed271()['nextGeneratedPathRowidCurrentSourceCostSelection271']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed271()['nextGeneratedPathRowidCurrentSourceCostSelection271']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed271()['nextGeneratedPathRowidCurrentSourceCostSelection271']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next271', $changed271()['nextGeneratedPathRowidCurrentSourceCostSelection271']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed271()['generatedPathRowidCurrentSourceCostSelection271Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed271()['generatedPathRowidCurrentSourceCostSelection271Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed271()['generatedPathRowidCurrentSourceCostSelection271Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed271()['generatedPathRowidCurrentSourceCostSelection271Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed271()['generatedPathRowidCurrentSourceCostSelection271Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next271', $changed271()['next271ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next271', $changed271()['next271ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next271', $changed271()['next271ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next271', $changed271()['next271ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next271', $changed271()['next271ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed271()['next271ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next271', $staleFingerprint271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next271', $staleRowid271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next271', $noOrder271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias271()['currentGeneratedPathRowidCurrentSourceCostSelection271']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan271($current271, $current271, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan271(array_replace($current271, ['generated_path' => '$.rules[']), $current271)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next271 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
