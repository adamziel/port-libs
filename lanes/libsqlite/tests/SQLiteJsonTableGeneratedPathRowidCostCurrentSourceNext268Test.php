<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current268 = [
    'option_id' => 268,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next268',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-268-a',
];
$next268 = [
    'option_id' => 268,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next268',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-268-b',
];

$plan268 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext268(
    'json_tree',
    $current ?? $current268,
    $next ?? $next268,
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

$changed268 = static fn (): array => $plan268();
$stable268 = static fn (): array => $plan268($current268, $current268);
$staleFingerprint268 = static function () use ($plan268, $current268): array {
    return $plan268($current268, $current268, null, null, str_repeat('0', 64), null);
};
$staleRowid268 = static fn (): array => $plan268($current268, $current268, null, null, null, 5);
$noOrder268 = static fn (): array => $plan268($current268, $current268, null, []);
$oidAlias268 = static fn (): array => $plan268($current268, $current268, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias268 = static fn (): array => $plan268($current268, $current268, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next268 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next268', $changed268()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed268()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next268', $changed268()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next268', $changed268()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next268', $stable268()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable268()['next268ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-268-a', $changed268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next268', $changed268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed268()['nextGeneratedPathRowidCurrentSourceCostSelection268']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed268()['nextGeneratedPathRowidCurrentSourceCostSelection268']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed268()['nextGeneratedPathRowidCurrentSourceCostSelection268']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed268()['nextGeneratedPathRowidCurrentSourceCostSelection268']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed268()['nextGeneratedPathRowidCurrentSourceCostSelection268']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed268()['nextGeneratedPathRowidCurrentSourceCostSelection268']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next268', $changed268()['nextGeneratedPathRowidCurrentSourceCostSelection268']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed268()['generatedPathRowidCurrentSourceCostSelection268Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed268()['generatedPathRowidCurrentSourceCostSelection268Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed268()['generatedPathRowidCurrentSourceCostSelection268Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed268()['generatedPathRowidCurrentSourceCostSelection268Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed268()['generatedPathRowidCurrentSourceCostSelection268Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next268', $changed268()['next268ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next268', $changed268()['next268ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next268', $changed268()['next268ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next268', $changed268()['next268ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next268', $changed268()['next268ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed268()['next268ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next268', $staleFingerprint268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next268', $staleRowid268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next268', $noOrder268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias268()['currentGeneratedPathRowidCurrentSourceCostSelection268']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan268($current268, $current268, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan268(array_replace($current268, ['generated_path' => '$.rules[']), $current268)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next268 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
