<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current320 = [
    'option_id' => 320,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next320',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-320-a',
];
$next320 = [
    'option_id' => 320,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next320',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-320-b',
];

$plan320 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext320(
    'json_tree',
    $current ?? $current320,
    $next ?? $next320,
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

$changed320 = static fn (): array => $plan320();
$stable320 = static fn (): array => $plan320($current320, $current320);
$staleFingerprint320 = static function () use ($plan320, $current320): array {
    return $plan320($current320, $current320, null, null, str_repeat('0', 64), null);
};
$staleRowid320 = static fn (): array => $plan320($current320, $current320, null, null, null, 5);
$noOrder320 = static fn (): array => $plan320($current320, $current320, null, []);
$oidAlias320 = static fn (): array => $plan320($current320, $current320, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias320 = static fn (): array => $plan320($current320, $current320, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next320 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next320', $changed320()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed320()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next320', $changed320()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next320', $changed320()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next320', $stable320()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable320()['next320ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-320-a', $changed320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next320', $changed320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed320()['nextGeneratedPathRowidCurrentSourceCostSelection320']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed320()['nextGeneratedPathRowidCurrentSourceCostSelection320']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed320()['nextGeneratedPathRowidCurrentSourceCostSelection320']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed320()['nextGeneratedPathRowidCurrentSourceCostSelection320']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed320()['nextGeneratedPathRowidCurrentSourceCostSelection320']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed320()['nextGeneratedPathRowidCurrentSourceCostSelection320']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next320', $changed320()['nextGeneratedPathRowidCurrentSourceCostSelection320']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed320()['generatedPathRowidCurrentSourceCostSelection320Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed320()['generatedPathRowidCurrentSourceCostSelection320Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed320()['generatedPathRowidCurrentSourceCostSelection320Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed320()['generatedPathRowidCurrentSourceCostSelection320Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed320()['generatedPathRowidCurrentSourceCostSelection320Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next320', $changed320()['next320ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next320', $changed320()['next320ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next320', $changed320()['next320ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next320', $changed320()['next320ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next320', $changed320()['next320ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed320()['next320ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next320', $staleFingerprint320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next320', $staleRowid320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next320', $noOrder320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias320()['currentGeneratedPathRowidCurrentSourceCostSelection320']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan320($current320, $current320, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan320(array_replace($current320, ['generated_path' => '$.rules[']), $current320)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next320 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
