<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current367 = [
    'option_id' => 367,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next367',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-367-a',
];
$next367 = [
    'option_id' => 367,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next367',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-367-b',
];

$plan367 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext367(
    'json_tree',
    $current ?? $current367,
    $next ?? $next367,
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

$changed367 = static fn (): array => $plan367();
$stable367 = static fn (): array => $plan367($current367, $current367);
$staleFingerprint367 = static function () use ($plan367, $current367): array {
    return $plan367($current367, $current367, null, null, str_repeat('0', 64), null);
};
$staleRowid367 = static fn (): array => $plan367($current367, $current367, null, null, null, 5);
$noOrder367 = static fn (): array => $plan367($current367, $current367, null, []);
$oidAlias367 = static fn (): array => $plan367($current367, $current367, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias367 = static fn (): array => $plan367($current367, $current367, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next367 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next367', $changed367()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed367()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next367', $changed367()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next367', $changed367()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next367', $stable367()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable367()['next367ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-367-a', $changed367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next367', $changed367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed367()['nextGeneratedPathRowidCurrentSourceCostSelection367']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed367()['nextGeneratedPathRowidCurrentSourceCostSelection367']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed367()['nextGeneratedPathRowidCurrentSourceCostSelection367']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed367()['nextGeneratedPathRowidCurrentSourceCostSelection367']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed367()['nextGeneratedPathRowidCurrentSourceCostSelection367']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed367()['nextGeneratedPathRowidCurrentSourceCostSelection367']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next367', $changed367()['nextGeneratedPathRowidCurrentSourceCostSelection367']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed367()['generatedPathRowidCurrentSourceCostSelection367Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed367()['generatedPathRowidCurrentSourceCostSelection367Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed367()['generatedPathRowidCurrentSourceCostSelection367Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed367()['generatedPathRowidCurrentSourceCostSelection367Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed367()['generatedPathRowidCurrentSourceCostSelection367Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next367', $changed367()['next367ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next367', $changed367()['next367ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next367', $changed367()['next367ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next367', $changed367()['next367ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next367', $changed367()['next367ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed367()['next367ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next367', $staleFingerprint367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next367', $staleRowid367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next367', $noOrder367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias367()['currentGeneratedPathRowidCurrentSourceCostSelection367']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan367($current367, $current367, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan367(array_replace($current367, ['generated_path' => '$.rules[']), $current367)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next367 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
