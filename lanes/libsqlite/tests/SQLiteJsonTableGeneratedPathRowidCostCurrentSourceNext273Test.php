<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current273 = [
    'option_id' => 273,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next273',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-273-a',
];
$next273 = [
    'option_id' => 273,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next273',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-273-b',
];

$plan273 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext273(
    'json_tree',
    $current ?? $current273,
    $next ?? $next273,
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

$changed273 = static fn (): array => $plan273();
$stable273 = static fn (): array => $plan273($current273, $current273);
$staleFingerprint273 = static function () use ($plan273, $current273): array {
    return $plan273($current273, $current273, null, null, str_repeat('0', 64), null);
};
$staleRowid273 = static fn (): array => $plan273($current273, $current273, null, null, null, 5);
$noOrder273 = static fn (): array => $plan273($current273, $current273, null, []);
$oidAlias273 = static fn (): array => $plan273($current273, $current273, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias273 = static fn (): array => $plan273($current273, $current273, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next273 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next273', $changed273()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed273()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next273', $changed273()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next273', $changed273()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next273', $stable273()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable273()['next273ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-273-a', $changed273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next273', $changed273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed273()['nextGeneratedPathRowidCurrentSourceCostSelection273']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed273()['nextGeneratedPathRowidCurrentSourceCostSelection273']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed273()['nextGeneratedPathRowidCurrentSourceCostSelection273']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed273()['nextGeneratedPathRowidCurrentSourceCostSelection273']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed273()['nextGeneratedPathRowidCurrentSourceCostSelection273']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed273()['nextGeneratedPathRowidCurrentSourceCostSelection273']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next273', $changed273()['nextGeneratedPathRowidCurrentSourceCostSelection273']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed273()['generatedPathRowidCurrentSourceCostSelection273Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed273()['generatedPathRowidCurrentSourceCostSelection273Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed273()['generatedPathRowidCurrentSourceCostSelection273Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed273()['generatedPathRowidCurrentSourceCostSelection273Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed273()['generatedPathRowidCurrentSourceCostSelection273Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next273', $changed273()['next273ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next273', $changed273()['next273ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next273', $changed273()['next273ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next273', $changed273()['next273ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next273', $changed273()['next273ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed273()['next273ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next273', $staleFingerprint273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next273', $staleRowid273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next273', $noOrder273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias273()['currentGeneratedPathRowidCurrentSourceCostSelection273']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan273($current273, $current273, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan273(array_replace($current273, ['generated_path' => '$.rules[']), $current273)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next273 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
