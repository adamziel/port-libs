<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current284 = [
    'option_id' => 284,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next284',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-284-a',
];
$next284 = [
    'option_id' => 284,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next284',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-284-b',
];

$plan284 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext284(
    'json_tree',
    $current ?? $current284,
    $next ?? $next284,
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

$changed284 = static fn (): array => $plan284();
$stable284 = static fn (): array => $plan284($current284, $current284);
$staleFingerprint284 = static function () use ($plan284, $current284): array {
    return $plan284($current284, $current284, null, null, str_repeat('0', 64), null);
};
$staleRowid284 = static fn (): array => $plan284($current284, $current284, null, null, null, 5);
$noOrder284 = static fn (): array => $plan284($current284, $current284, null, []);
$oidAlias284 = static fn (): array => $plan284($current284, $current284, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias284 = static fn (): array => $plan284($current284, $current284, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next284 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next284', $changed284()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed284()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next284', $changed284()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next284', $changed284()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next284', $stable284()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable284()['next284ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-284-a', $changed284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next284', $changed284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed284()['nextGeneratedPathRowidCurrentSourceCostSelection284']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed284()['nextGeneratedPathRowidCurrentSourceCostSelection284']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed284()['nextGeneratedPathRowidCurrentSourceCostSelection284']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed284()['nextGeneratedPathRowidCurrentSourceCostSelection284']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed284()['nextGeneratedPathRowidCurrentSourceCostSelection284']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed284()['nextGeneratedPathRowidCurrentSourceCostSelection284']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next284', $changed284()['nextGeneratedPathRowidCurrentSourceCostSelection284']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed284()['generatedPathRowidCurrentSourceCostSelection284Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed284()['generatedPathRowidCurrentSourceCostSelection284Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed284()['generatedPathRowidCurrentSourceCostSelection284Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed284()['generatedPathRowidCurrentSourceCostSelection284Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed284()['generatedPathRowidCurrentSourceCostSelection284Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next284', $changed284()['next284ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next284', $changed284()['next284ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next284', $changed284()['next284ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next284', $changed284()['next284ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next284', $changed284()['next284ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed284()['next284ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next284', $staleFingerprint284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next284', $staleRowid284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next284', $noOrder284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias284()['currentGeneratedPathRowidCurrentSourceCostSelection284']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan284($current284, $current284, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan284(array_replace($current284, ['generated_path' => '$.rules[']), $current284)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next284 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
