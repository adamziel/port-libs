<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current324 = [
    'option_id' => 324,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next324',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-324-a',
];
$next324 = [
    'option_id' => 324,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next324',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-324-b',
];

$plan324 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext324(
    'json_tree',
    $current ?? $current324,
    $next ?? $next324,
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

$changed324 = static fn (): array => $plan324();
$stable324 = static fn (): array => $plan324($current324, $current324);
$staleFingerprint324 = static function () use ($plan324, $current324): array {
    return $plan324($current324, $current324, null, null, str_repeat('0', 64), null);
};
$staleRowid324 = static fn (): array => $plan324($current324, $current324, null, null, null, 5);
$noOrder324 = static fn (): array => $plan324($current324, $current324, null, []);
$oidAlias324 = static fn (): array => $plan324($current324, $current324, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias324 = static fn (): array => $plan324($current324, $current324, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next324 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next324', $changed324()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed324()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next324', $changed324()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next324', $changed324()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next324', $stable324()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable324()['next324ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-324-a', $changed324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next324', $changed324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed324()['nextGeneratedPathRowidCurrentSourceCostSelection324']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed324()['nextGeneratedPathRowidCurrentSourceCostSelection324']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed324()['nextGeneratedPathRowidCurrentSourceCostSelection324']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed324()['nextGeneratedPathRowidCurrentSourceCostSelection324']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed324()['nextGeneratedPathRowidCurrentSourceCostSelection324']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed324()['nextGeneratedPathRowidCurrentSourceCostSelection324']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next324', $changed324()['nextGeneratedPathRowidCurrentSourceCostSelection324']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed324()['generatedPathRowidCurrentSourceCostSelection324Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed324()['generatedPathRowidCurrentSourceCostSelection324Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed324()['generatedPathRowidCurrentSourceCostSelection324Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed324()['generatedPathRowidCurrentSourceCostSelection324Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed324()['generatedPathRowidCurrentSourceCostSelection324Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next324', $changed324()['next324ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next324', $changed324()['next324ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next324', $changed324()['next324ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next324', $changed324()['next324ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next324', $changed324()['next324ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed324()['next324ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next324', $staleFingerprint324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next324', $staleRowid324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next324', $noOrder324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias324()['currentGeneratedPathRowidCurrentSourceCostSelection324']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan324($current324, $current324, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan324(array_replace($current324, ['generated_path' => '$.rules[']), $current324)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next324 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
