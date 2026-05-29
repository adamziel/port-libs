<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current249 = [
    'option_id' => 249,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next249',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-249-a',
];
$next249 = [
    'option_id' => 249,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next249',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-249-b',
];

$plan249 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext249(
    'json_tree',
    $current ?? $current249,
    $next ?? $next249,
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

$changed249 = static fn (): array => $plan249();
$stable249 = static fn (): array => $plan249($current249, $current249);
$staleFingerprint249 = static function () use ($plan249, $current249): array {
    return $plan249($current249, $current249, null, null, str_repeat('0', 64), null);
};
$staleRowid249 = static fn (): array => $plan249($current249, $current249, null, null, null, 5);
$noOrder249 = static fn (): array => $plan249($current249, $current249, null, []);
$oidAlias249 = static fn (): array => $plan249($current249, $current249, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias249 = static fn (): array => $plan249($current249, $current249, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next249 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next249', $changed249()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed249()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next249', $changed249()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next249', $changed249()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next249', $stable249()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable249()['next249ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-249-a', $changed249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next249', $changed249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed249()['nextGeneratedPathRowidCurrentSourceCostSelection249']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed249()['nextGeneratedPathRowidCurrentSourceCostSelection249']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed249()['nextGeneratedPathRowidCurrentSourceCostSelection249']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed249()['nextGeneratedPathRowidCurrentSourceCostSelection249']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed249()['nextGeneratedPathRowidCurrentSourceCostSelection249']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed249()['nextGeneratedPathRowidCurrentSourceCostSelection249']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next249', $changed249()['nextGeneratedPathRowidCurrentSourceCostSelection249']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed249()['generatedPathRowidCurrentSourceCostSelection249Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed249()['generatedPathRowidCurrentSourceCostSelection249Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed249()['generatedPathRowidCurrentSourceCostSelection249Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed249()['generatedPathRowidCurrentSourceCostSelection249Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed249()['generatedPathRowidCurrentSourceCostSelection249Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next249', $changed249()['next249ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next249', $changed249()['next249ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next249', $changed249()['next249ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next249', $changed249()['next249ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next249', $changed249()['next249ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed249()['next249ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next249', $staleFingerprint249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next249', $staleRowid249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next249', $noOrder249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias249()['currentGeneratedPathRowidCurrentSourceCostSelection249']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan249($current249, $current249, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan249(array_replace($current249, ['generated_path' => '$.rules[']), $current249)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next249 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
