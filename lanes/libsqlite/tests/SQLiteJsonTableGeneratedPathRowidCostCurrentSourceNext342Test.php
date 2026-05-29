<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current342 = [
    'option_id' => 342,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next342',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-342-a',
];
$next342 = [
    'option_id' => 342,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next342',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-342-b',
];

$plan342 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext342(
    'json_tree',
    $current ?? $current342,
    $next ?? $next342,
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

$changed342 = static fn (): array => $plan342();
$stable342 = static fn (): array => $plan342($current342, $current342);
$staleFingerprint342 = static function () use ($plan342, $current342): array {
    return $plan342($current342, $current342, null, null, str_repeat('0', 64), null);
};
$staleRowid342 = static fn (): array => $plan342($current342, $current342, null, null, null, 5);
$noOrder342 = static fn (): array => $plan342($current342, $current342, null, []);
$oidAlias342 = static fn (): array => $plan342($current342, $current342, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias342 = static fn (): array => $plan342($current342, $current342, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next342 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next342', $changed342()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed342()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next342', $changed342()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next342', $changed342()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next342', $stable342()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable342()['next342ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-342-a', $changed342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next342', $changed342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed342()['nextGeneratedPathRowidCurrentSourceCostSelection342']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed342()['nextGeneratedPathRowidCurrentSourceCostSelection342']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed342()['nextGeneratedPathRowidCurrentSourceCostSelection342']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed342()['nextGeneratedPathRowidCurrentSourceCostSelection342']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed342()['nextGeneratedPathRowidCurrentSourceCostSelection342']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed342()['nextGeneratedPathRowidCurrentSourceCostSelection342']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next342', $changed342()['nextGeneratedPathRowidCurrentSourceCostSelection342']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed342()['generatedPathRowidCurrentSourceCostSelection342Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed342()['generatedPathRowidCurrentSourceCostSelection342Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed342()['generatedPathRowidCurrentSourceCostSelection342Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed342()['generatedPathRowidCurrentSourceCostSelection342Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed342()['generatedPathRowidCurrentSourceCostSelection342Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next342', $changed342()['next342ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next342', $changed342()['next342ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next342', $changed342()['next342ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next342', $changed342()['next342ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next342', $changed342()['next342ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed342()['next342ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next342', $staleFingerprint342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next342', $staleRowid342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next342', $noOrder342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias342()['currentGeneratedPathRowidCurrentSourceCostSelection342']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan342($current342, $current342, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan342(array_replace($current342, ['generated_path' => '$.rules[']), $current342)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next342 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
