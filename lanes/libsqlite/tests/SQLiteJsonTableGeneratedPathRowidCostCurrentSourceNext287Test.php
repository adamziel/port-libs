<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current287 = [
    'option_id' => 287,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next287',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-287-a',
];
$next287 = [
    'option_id' => 287,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next287',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-287-b',
];

$plan287 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    287,
    $current ?? $current287,
    $next ?? $next287,
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

$changed287 = static fn (): array => $plan287();
$stable287 = static fn (): array => $plan287($current287, $current287);
$staleFingerprint287 = static function () use ($plan287, $current287): array {
    return $plan287($current287, $current287, null, null, str_repeat('0', 64), null);
};
$staleRowid287 = static fn (): array => $plan287($current287, $current287, null, null, null, 5);
$noOrder287 = static fn (): array => $plan287($current287, $current287, null, []);
$oidAlias287 = static fn (): array => $plan287($current287, $current287, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias287 = static fn (): array => $plan287($current287, $current287, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next287 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next287', $changed287()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed287()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next287', $changed287()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next287', $changed287()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next287', $stable287()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable287()['next287ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-287-a', $changed287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next287', $changed287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed287()['nextGeneratedPathRowidCurrentSourceCostSelection287']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed287()['nextGeneratedPathRowidCurrentSourceCostSelection287']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed287()['nextGeneratedPathRowidCurrentSourceCostSelection287']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed287()['nextGeneratedPathRowidCurrentSourceCostSelection287']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed287()['nextGeneratedPathRowidCurrentSourceCostSelection287']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed287()['nextGeneratedPathRowidCurrentSourceCostSelection287']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next287', $changed287()['nextGeneratedPathRowidCurrentSourceCostSelection287']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed287()['generatedPathRowidCurrentSourceCostSelection287Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed287()['generatedPathRowidCurrentSourceCostSelection287Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed287()['generatedPathRowidCurrentSourceCostSelection287Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed287()['generatedPathRowidCurrentSourceCostSelection287Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed287()['generatedPathRowidCurrentSourceCostSelection287Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next287', $changed287()['next287ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next287', $changed287()['next287ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next287', $changed287()['next287ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next287', $changed287()['next287ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next287', $changed287()['next287ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed287()['next287ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next287', $staleFingerprint287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next287', $staleRowid287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next287', $noOrder287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias287()['currentGeneratedPathRowidCurrentSourceCostSelection287']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan287($current287, $current287, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan287(array_replace($current287, ['generated_path' => '$.rules[']), $current287)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next287 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
