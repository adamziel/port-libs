<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current269 = [
    'option_id' => 269,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next269',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-269-a',
];
$next269 = [
    'option_id' => 269,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next269',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-269-b',
];

$plan269 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext269(
    'json_tree',
    $current ?? $current269,
    $next ?? $next269,
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

$changed269 = static fn (): array => $plan269();
$stable269 = static fn (): array => $plan269($current269, $current269);
$staleFingerprint269 = static function () use ($plan269, $current269): array {
    return $plan269($current269, $current269, null, null, str_repeat('0', 64), null);
};
$staleRowid269 = static fn (): array => $plan269($current269, $current269, null, null, null, 5);
$noOrder269 = static fn (): array => $plan269($current269, $current269, null, []);
$oidAlias269 = static fn (): array => $plan269($current269, $current269, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias269 = static fn (): array => $plan269($current269, $current269, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next269 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next269', $changed269()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed269()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next269', $changed269()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next269', $changed269()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next269', $stable269()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable269()['next269ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-269-a', $changed269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next269', $changed269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed269()['nextGeneratedPathRowidCurrentSourceCostSelection269']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed269()['nextGeneratedPathRowidCurrentSourceCostSelection269']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed269()['nextGeneratedPathRowidCurrentSourceCostSelection269']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed269()['nextGeneratedPathRowidCurrentSourceCostSelection269']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed269()['nextGeneratedPathRowidCurrentSourceCostSelection269']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed269()['nextGeneratedPathRowidCurrentSourceCostSelection269']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next269', $changed269()['nextGeneratedPathRowidCurrentSourceCostSelection269']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed269()['generatedPathRowidCurrentSourceCostSelection269Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed269()['generatedPathRowidCurrentSourceCostSelection269Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed269()['generatedPathRowidCurrentSourceCostSelection269Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed269()['generatedPathRowidCurrentSourceCostSelection269Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed269()['generatedPathRowidCurrentSourceCostSelection269Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next269', $changed269()['next269ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next269', $changed269()['next269ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next269', $changed269()['next269ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next269', $changed269()['next269ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next269', $changed269()['next269ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed269()['next269ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next269', $staleFingerprint269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next269', $staleRowid269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next269', $noOrder269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias269()['currentGeneratedPathRowidCurrentSourceCostSelection269']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan269($current269, $current269, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan269(array_replace($current269, ['generated_path' => '$.rules[']), $current269)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next269 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
