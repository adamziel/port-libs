<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current285 = [
    'option_id' => 285,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next285',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-285-a',
];
$next285 = [
    'option_id' => 285,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next285',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-285-b',
];

$plan285 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext285(
    'json_tree',
    $current ?? $current285,
    $next ?? $next285,
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

$changed285 = static fn (): array => $plan285();
$stable285 = static fn (): array => $plan285($current285, $current285);
$staleFingerprint285 = static function () use ($plan285, $current285): array {
    return $plan285($current285, $current285, null, null, str_repeat('0', 64), null);
};
$staleRowid285 = static fn (): array => $plan285($current285, $current285, null, null, null, 5);
$noOrder285 = static fn (): array => $plan285($current285, $current285, null, []);
$oidAlias285 = static fn (): array => $plan285($current285, $current285, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias285 = static fn (): array => $plan285($current285, $current285, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next285 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next285', $changed285()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed285()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next285', $changed285()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next285', $changed285()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next285', $stable285()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable285()['next285ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-285-a', $changed285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next285', $changed285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed285()['nextGeneratedPathRowidCurrentSourceCostSelection285']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed285()['nextGeneratedPathRowidCurrentSourceCostSelection285']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed285()['nextGeneratedPathRowidCurrentSourceCostSelection285']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed285()['nextGeneratedPathRowidCurrentSourceCostSelection285']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed285()['nextGeneratedPathRowidCurrentSourceCostSelection285']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed285()['nextGeneratedPathRowidCurrentSourceCostSelection285']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next285', $changed285()['nextGeneratedPathRowidCurrentSourceCostSelection285']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed285()['generatedPathRowidCurrentSourceCostSelection285Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed285()['generatedPathRowidCurrentSourceCostSelection285Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed285()['generatedPathRowidCurrentSourceCostSelection285Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed285()['generatedPathRowidCurrentSourceCostSelection285Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed285()['generatedPathRowidCurrentSourceCostSelection285Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next285', $changed285()['next285ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next285', $changed285()['next285ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next285', $changed285()['next285ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next285', $changed285()['next285ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next285', $changed285()['next285ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed285()['next285ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next285', $staleFingerprint285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next285', $staleRowid285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next285', $noOrder285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias285()['currentGeneratedPathRowidCurrentSourceCostSelection285']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan285($current285, $current285, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan285(array_replace($current285, ['generated_path' => '$.rules[']), $current285)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next285 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
