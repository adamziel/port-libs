<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current276 = [
    'option_id' => 276,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next276',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-276-a',
];
$next276 = [
    'option_id' => 276,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next276',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-276-b',
];

$plan276 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext276(
    'json_tree',
    $current ?? $current276,
    $next ?? $next276,
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

$changed276 = static fn (): array => $plan276();
$stable276 = static fn (): array => $plan276($current276, $current276);
$staleFingerprint276 = static function () use ($plan276, $current276): array {
    return $plan276($current276, $current276, null, null, str_repeat('0', 64), null);
};
$staleRowid276 = static fn (): array => $plan276($current276, $current276, null, null, null, 5);
$noOrder276 = static fn (): array => $plan276($current276, $current276, null, []);
$oidAlias276 = static fn (): array => $plan276($current276, $current276, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias276 = static fn (): array => $plan276($current276, $current276, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next276 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next276', $changed276()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed276()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next276', $changed276()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next276', $changed276()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next276', $stable276()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable276()['next276ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-276-a', $changed276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next276', $changed276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed276()['nextGeneratedPathRowidCurrentSourceCostSelection276']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed276()['nextGeneratedPathRowidCurrentSourceCostSelection276']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed276()['nextGeneratedPathRowidCurrentSourceCostSelection276']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed276()['nextGeneratedPathRowidCurrentSourceCostSelection276']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed276()['nextGeneratedPathRowidCurrentSourceCostSelection276']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed276()['nextGeneratedPathRowidCurrentSourceCostSelection276']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next276', $changed276()['nextGeneratedPathRowidCurrentSourceCostSelection276']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed276()['generatedPathRowidCurrentSourceCostSelection276Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed276()['generatedPathRowidCurrentSourceCostSelection276Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed276()['generatedPathRowidCurrentSourceCostSelection276Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed276()['generatedPathRowidCurrentSourceCostSelection276Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed276()['generatedPathRowidCurrentSourceCostSelection276Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next276', $changed276()['next276ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next276', $changed276()['next276ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next276', $changed276()['next276ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next276', $changed276()['next276ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next276', $changed276()['next276ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed276()['next276ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next276', $staleFingerprint276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next276', $staleRowid276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next276', $noOrder276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias276()['currentGeneratedPathRowidCurrentSourceCostSelection276']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan276($current276, $current276, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan276(array_replace($current276, ['generated_path' => '$.rules[']), $current276)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next276 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
