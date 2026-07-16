<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current279 = [
    'option_id' => 279,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next279',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-279-a',
];
$next279 = [
    'option_id' => 279,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next279',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-279-b',
];

$plan279 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    279,
    $current ?? $current279,
    $next ?? $next279,
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

$changed279 = static fn (): array => $plan279();
$stable279 = static fn (): array => $plan279($current279, $current279);
$staleFingerprint279 = static function () use ($plan279, $current279): array {
    return $plan279($current279, $current279, null, null, str_repeat('0', 64), null);
};
$staleRowid279 = static fn (): array => $plan279($current279, $current279, null, null, null, 5);
$noOrder279 = static fn (): array => $plan279($current279, $current279, null, []);
$oidAlias279 = static fn (): array => $plan279($current279, $current279, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias279 = static fn (): array => $plan279($current279, $current279, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next279 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next279', $changed279()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed279()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next279', $changed279()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next279', $changed279()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next279', $stable279()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable279()['next279ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-279-a', $changed279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next279', $changed279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed279()['nextGeneratedPathRowidCurrentSourceCostSelection279']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed279()['nextGeneratedPathRowidCurrentSourceCostSelection279']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed279()['nextGeneratedPathRowidCurrentSourceCostSelection279']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed279()['nextGeneratedPathRowidCurrentSourceCostSelection279']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed279()['nextGeneratedPathRowidCurrentSourceCostSelection279']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed279()['nextGeneratedPathRowidCurrentSourceCostSelection279']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next279', $changed279()['nextGeneratedPathRowidCurrentSourceCostSelection279']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed279()['generatedPathRowidCurrentSourceCostSelection279Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed279()['generatedPathRowidCurrentSourceCostSelection279Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed279()['generatedPathRowidCurrentSourceCostSelection279Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed279()['generatedPathRowidCurrentSourceCostSelection279Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed279()['generatedPathRowidCurrentSourceCostSelection279Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next279', $changed279()['next279ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next279', $changed279()['next279ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next279', $changed279()['next279ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next279', $changed279()['next279ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next279', $changed279()['next279ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed279()['next279ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next279', $staleFingerprint279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next279', $staleRowid279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next279', $noOrder279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias279()['currentGeneratedPathRowidCurrentSourceCostSelection279']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan279($current279, $current279, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan279(array_replace($current279, ['generated_path' => '$.rules[']), $current279)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next279 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
