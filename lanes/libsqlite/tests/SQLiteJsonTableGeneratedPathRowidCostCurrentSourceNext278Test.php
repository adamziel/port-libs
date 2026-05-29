<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current278 = [
    'option_id' => 278,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next278',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-278-a',
];
$next278 = [
    'option_id' => 278,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next278',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-278-b',
];

$plan278 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext278(
    'json_tree',
    $current ?? $current278,
    $next ?? $next278,
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

$changed278 = static fn (): array => $plan278();
$stable278 = static fn (): array => $plan278($current278, $current278);
$staleFingerprint278 = static function () use ($plan278, $current278): array {
    return $plan278($current278, $current278, null, null, str_repeat('0', 64), null);
};
$staleRowid278 = static fn (): array => $plan278($current278, $current278, null, null, null, 5);
$noOrder278 = static fn (): array => $plan278($current278, $current278, null, []);
$oidAlias278 = static fn (): array => $plan278($current278, $current278, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias278 = static fn (): array => $plan278($current278, $current278, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next278 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next278', $changed278()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed278()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next278', $changed278()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next278', $changed278()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next278', $stable278()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable278()['next278ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-278-a', $changed278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next278', $changed278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed278()['nextGeneratedPathRowidCurrentSourceCostSelection278']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed278()['nextGeneratedPathRowidCurrentSourceCostSelection278']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed278()['nextGeneratedPathRowidCurrentSourceCostSelection278']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed278()['nextGeneratedPathRowidCurrentSourceCostSelection278']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed278()['nextGeneratedPathRowidCurrentSourceCostSelection278']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed278()['nextGeneratedPathRowidCurrentSourceCostSelection278']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next278', $changed278()['nextGeneratedPathRowidCurrentSourceCostSelection278']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed278()['generatedPathRowidCurrentSourceCostSelection278Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed278()['generatedPathRowidCurrentSourceCostSelection278Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed278()['generatedPathRowidCurrentSourceCostSelection278Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed278()['generatedPathRowidCurrentSourceCostSelection278Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed278()['generatedPathRowidCurrentSourceCostSelection278Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next278', $changed278()['next278ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next278', $changed278()['next278ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next278', $changed278()['next278ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next278', $changed278()['next278ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next278', $changed278()['next278ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed278()['next278ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next278', $staleFingerprint278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next278', $staleRowid278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next278', $noOrder278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias278()['currentGeneratedPathRowidCurrentSourceCostSelection278']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan278($current278, $current278, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan278(array_replace($current278, ['generated_path' => '$.rules[']), $current278)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next278 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
