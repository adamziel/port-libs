<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current274 = [
    'option_id' => 274,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next274',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-274-a',
];
$next274 = [
    'option_id' => 274,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next274',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-274-b',
];

$plan274 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext274(
    'json_tree',
    $current ?? $current274,
    $next ?? $next274,
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

$changed274 = static fn (): array => $plan274();
$stable274 = static fn (): array => $plan274($current274, $current274);
$staleFingerprint274 = static function () use ($plan274, $current274): array {
    return $plan274($current274, $current274, null, null, str_repeat('0', 64), null);
};
$staleRowid274 = static fn (): array => $plan274($current274, $current274, null, null, null, 5);
$noOrder274 = static fn (): array => $plan274($current274, $current274, null, []);
$oidAlias274 = static fn (): array => $plan274($current274, $current274, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias274 = static fn (): array => $plan274($current274, $current274, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next274 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next274', $changed274()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed274()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next274', $changed274()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next274', $changed274()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next274', $stable274()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable274()['next274ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-274-a', $changed274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next274', $changed274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed274()['nextGeneratedPathRowidCurrentSourceCostSelection274']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed274()['nextGeneratedPathRowidCurrentSourceCostSelection274']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed274()['nextGeneratedPathRowidCurrentSourceCostSelection274']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed274()['nextGeneratedPathRowidCurrentSourceCostSelection274']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed274()['nextGeneratedPathRowidCurrentSourceCostSelection274']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed274()['nextGeneratedPathRowidCurrentSourceCostSelection274']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next274', $changed274()['nextGeneratedPathRowidCurrentSourceCostSelection274']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed274()['generatedPathRowidCurrentSourceCostSelection274Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed274()['generatedPathRowidCurrentSourceCostSelection274Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed274()['generatedPathRowidCurrentSourceCostSelection274Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed274()['generatedPathRowidCurrentSourceCostSelection274Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed274()['generatedPathRowidCurrentSourceCostSelection274Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next274', $changed274()['next274ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next274', $changed274()['next274ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next274', $changed274()['next274ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next274', $changed274()['next274ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next274', $changed274()['next274ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed274()['next274ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next274', $staleFingerprint274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next274', $staleRowid274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next274', $noOrder274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias274()['currentGeneratedPathRowidCurrentSourceCostSelection274']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan274($current274, $current274, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan274(array_replace($current274, ['generated_path' => '$.rules[']), $current274)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next274 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
