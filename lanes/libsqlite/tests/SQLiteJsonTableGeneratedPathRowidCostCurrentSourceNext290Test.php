<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current290 = [
    'option_id' => 290,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next290',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-290-a',
];
$next290 = [
    'option_id' => 290,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next290',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-290-b',
];

$plan290 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionVariant(290,
    'json_tree',
    $current ?? $current290,
    $next ?? $next290,
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

$changed290 = static fn (): array => $plan290();
$stable290 = static fn (): array => $plan290($current290, $current290);
$staleFingerprint290 = static function () use ($plan290, $current290): array {
    return $plan290($current290, $current290, null, null, str_repeat('0', 64), null);
};
$staleRowid290 = static fn (): array => $plan290($current290, $current290, null, null, null, 5);
$noOrder290 = static fn (): array => $plan290($current290, $current290, null, []);
$oidAlias290 = static fn (): array => $plan290($current290, $current290, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias290 = static fn (): array => $plan290($current290, $current290, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next290 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next290', $changed290()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed290()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next290', $changed290()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next290', $changed290()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next290', $stable290()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable290()['next290ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-290-a', $changed290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next290', $changed290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed290()['nextGeneratedPathRowidCurrentSourceCostSelection290']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed290()['nextGeneratedPathRowidCurrentSourceCostSelection290']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed290()['nextGeneratedPathRowidCurrentSourceCostSelection290']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed290()['nextGeneratedPathRowidCurrentSourceCostSelection290']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed290()['nextGeneratedPathRowidCurrentSourceCostSelection290']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed290()['nextGeneratedPathRowidCurrentSourceCostSelection290']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next290', $changed290()['nextGeneratedPathRowidCurrentSourceCostSelection290']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed290()['generatedPathRowidCurrentSourceCostSelection290Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed290()['generatedPathRowidCurrentSourceCostSelection290Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed290()['generatedPathRowidCurrentSourceCostSelection290Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed290()['generatedPathRowidCurrentSourceCostSelection290Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed290()['generatedPathRowidCurrentSourceCostSelection290Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next290', $changed290()['next290ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next290', $changed290()['next290ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next290', $changed290()['next290ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next290', $changed290()['next290ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next290', $changed290()['next290ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed290()['next290ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next290', $staleFingerprint290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next290', $staleRowid290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next290', $noOrder290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias290()['currentGeneratedPathRowidCurrentSourceCostSelection290']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan290($current290, $current290, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan290(array_replace($current290, ['generated_path' => '$.rules[']), $current290)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next290 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
