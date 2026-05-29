<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current280 = [
    'option_id' => 280,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next280',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-280-a',
];
$next280 = [
    'option_id' => 280,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next280',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-280-b',
];

$plan280 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext280(
    'json_tree',
    $current ?? $current280,
    $next ?? $next280,
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

$changed280 = static fn (): array => $plan280();
$stable280 = static fn (): array => $plan280($current280, $current280);
$staleFingerprint280 = static function () use ($plan280, $current280): array {
    return $plan280($current280, $current280, null, null, str_repeat('0', 64), null);
};
$staleRowid280 = static fn (): array => $plan280($current280, $current280, null, null, null, 5);
$noOrder280 = static fn (): array => $plan280($current280, $current280, null, []);
$oidAlias280 = static fn (): array => $plan280($current280, $current280, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias280 = static fn (): array => $plan280($current280, $current280, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next280 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next280', $changed280()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed280()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next280', $changed280()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next280', $changed280()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next280', $stable280()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable280()['next280ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-280-a', $changed280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next280', $changed280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed280()['nextGeneratedPathRowidCurrentSourceCostSelection280']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed280()['nextGeneratedPathRowidCurrentSourceCostSelection280']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed280()['nextGeneratedPathRowidCurrentSourceCostSelection280']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed280()['nextGeneratedPathRowidCurrentSourceCostSelection280']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed280()['nextGeneratedPathRowidCurrentSourceCostSelection280']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed280()['nextGeneratedPathRowidCurrentSourceCostSelection280']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next280', $changed280()['nextGeneratedPathRowidCurrentSourceCostSelection280']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed280()['generatedPathRowidCurrentSourceCostSelection280Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed280()['generatedPathRowidCurrentSourceCostSelection280Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed280()['generatedPathRowidCurrentSourceCostSelection280Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed280()['generatedPathRowidCurrentSourceCostSelection280Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed280()['generatedPathRowidCurrentSourceCostSelection280Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next280', $changed280()['next280ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next280', $changed280()['next280ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next280', $changed280()['next280ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next280', $changed280()['next280ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next280', $changed280()['next280ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed280()['next280ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next280', $staleFingerprint280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next280', $staleRowid280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next280', $noOrder280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias280()['currentGeneratedPathRowidCurrentSourceCostSelection280']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan280($current280, $current280, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan280(array_replace($current280, ['generated_path' => '$.rules[']), $current280)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next280 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
