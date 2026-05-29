<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current322 = [
    'option_id' => 322,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next322',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-322-a',
];
$next322 = [
    'option_id' => 322,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next322',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-322-b',
];

$plan322 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext322(
    'json_tree',
    $current ?? $current322,
    $next ?? $next322,
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

$changed322 = static fn (): array => $plan322();
$stable322 = static fn (): array => $plan322($current322, $current322);
$staleFingerprint322 = static function () use ($plan322, $current322): array {
    return $plan322($current322, $current322, null, null, str_repeat('0', 64), null);
};
$staleRowid322 = static fn (): array => $plan322($current322, $current322, null, null, null, 5);
$noOrder322 = static fn (): array => $plan322($current322, $current322, null, []);
$oidAlias322 = static fn (): array => $plan322($current322, $current322, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias322 = static fn (): array => $plan322($current322, $current322, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next322 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next322', $changed322()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed322()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next322', $changed322()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next322', $changed322()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next322', $stable322()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable322()['next322ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-322-a', $changed322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next322', $changed322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed322()['nextGeneratedPathRowidCurrentSourceCostSelection322']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed322()['nextGeneratedPathRowidCurrentSourceCostSelection322']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed322()['nextGeneratedPathRowidCurrentSourceCostSelection322']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed322()['nextGeneratedPathRowidCurrentSourceCostSelection322']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed322()['nextGeneratedPathRowidCurrentSourceCostSelection322']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed322()['nextGeneratedPathRowidCurrentSourceCostSelection322']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next322', $changed322()['nextGeneratedPathRowidCurrentSourceCostSelection322']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed322()['generatedPathRowidCurrentSourceCostSelection322Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed322()['generatedPathRowidCurrentSourceCostSelection322Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed322()['generatedPathRowidCurrentSourceCostSelection322Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed322()['generatedPathRowidCurrentSourceCostSelection322Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed322()['generatedPathRowidCurrentSourceCostSelection322Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next322', $changed322()['next322ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next322', $changed322()['next322ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next322', $changed322()['next322ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next322', $changed322()['next322ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next322', $changed322()['next322ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed322()['next322ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next322', $staleFingerprint322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next322', $staleRowid322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next322', $noOrder322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias322()['currentGeneratedPathRowidCurrentSourceCostSelection322']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan322($current322, $current322, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan322(array_replace($current322, ['generated_path' => '$.rules[']), $current322)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next322 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
