<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current260 = [
    'option_id' => 260,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next260',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-260-a',
];
$next260 = [
    'option_id' => 260,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next260',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-260-b',
];

$plan260 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext260(
    'json_tree',
    $current ?? $current260,
    $next ?? $next260,
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

$changed260 = static fn (): array => $plan260();
$stable260 = static fn (): array => $plan260($current260, $current260);
$staleFingerprint260 = static function () use ($plan260, $current260): array {
    return $plan260($current260, $current260, null, null, str_repeat('0', 64), null);
};
$staleRowid260 = static fn (): array => $plan260($current260, $current260, null, null, null, 5);
$noOrder260 = static fn (): array => $plan260($current260, $current260, null, []);
$oidAlias260 = static fn (): array => $plan260($current260, $current260, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias260 = static fn (): array => $plan260($current260, $current260, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next260 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next260', $changed260()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed260()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next260', $changed260()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next260', $changed260()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next260', $stable260()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable260()['next260ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-260-a', $changed260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next260', $changed260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed260()['nextGeneratedPathRowidCurrentSourceCostSelection260']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed260()['nextGeneratedPathRowidCurrentSourceCostSelection260']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed260()['nextGeneratedPathRowidCurrentSourceCostSelection260']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed260()['nextGeneratedPathRowidCurrentSourceCostSelection260']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed260()['nextGeneratedPathRowidCurrentSourceCostSelection260']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed260()['nextGeneratedPathRowidCurrentSourceCostSelection260']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next260', $changed260()['nextGeneratedPathRowidCurrentSourceCostSelection260']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed260()['generatedPathRowidCurrentSourceCostSelection260Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed260()['generatedPathRowidCurrentSourceCostSelection260Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed260()['generatedPathRowidCurrentSourceCostSelection260Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed260()['generatedPathRowidCurrentSourceCostSelection260Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed260()['generatedPathRowidCurrentSourceCostSelection260Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next260', $changed260()['next260ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next260', $changed260()['next260ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next260', $changed260()['next260ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next260', $changed260()['next260ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next260', $changed260()['next260ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed260()['next260ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next260', $staleFingerprint260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next260', $staleRowid260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next260', $noOrder260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias260()['currentGeneratedPathRowidCurrentSourceCostSelection260']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan260($current260, $current260, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan260(array_replace($current260, ['generated_path' => '$.rules[']), $current260)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next260 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
