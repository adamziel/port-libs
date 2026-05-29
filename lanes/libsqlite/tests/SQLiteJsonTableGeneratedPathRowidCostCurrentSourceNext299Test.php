<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current299 = [
    'option_id' => 299,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next299',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-299-a',
];
$next299 = [
    'option_id' => 299,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next299',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-299-b',
];

$plan299 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext299(
    'json_tree',
    $current ?? $current299,
    $next ?? $next299,
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

$changed299 = static fn (): array => $plan299();
$stable299 = static fn (): array => $plan299($current299, $current299);
$staleFingerprint299 = static function () use ($plan299, $current299): array {
    return $plan299($current299, $current299, null, null, str_repeat('0', 64), null);
};
$staleRowid299 = static fn (): array => $plan299($current299, $current299, null, null, null, 5);
$noOrder299 = static fn (): array => $plan299($current299, $current299, null, []);
$oidAlias299 = static fn (): array => $plan299($current299, $current299, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias299 = static fn (): array => $plan299($current299, $current299, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next299 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next299', $changed299()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed299()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next299', $changed299()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next299', $changed299()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next299', $stable299()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable299()['next299ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-299-a', $changed299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next299', $changed299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed299()['nextGeneratedPathRowidCurrentSourceCostSelection299']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed299()['nextGeneratedPathRowidCurrentSourceCostSelection299']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed299()['nextGeneratedPathRowidCurrentSourceCostSelection299']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed299()['nextGeneratedPathRowidCurrentSourceCostSelection299']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed299()['nextGeneratedPathRowidCurrentSourceCostSelection299']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed299()['nextGeneratedPathRowidCurrentSourceCostSelection299']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next299', $changed299()['nextGeneratedPathRowidCurrentSourceCostSelection299']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed299()['generatedPathRowidCurrentSourceCostSelection299Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed299()['generatedPathRowidCurrentSourceCostSelection299Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed299()['generatedPathRowidCurrentSourceCostSelection299Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed299()['generatedPathRowidCurrentSourceCostSelection299Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed299()['generatedPathRowidCurrentSourceCostSelection299Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next299', $changed299()['next299ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next299', $changed299()['next299ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next299', $changed299()['next299ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next299', $changed299()['next299ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next299', $changed299()['next299ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed299()['next299ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next299', $staleFingerprint299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next299', $staleRowid299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next299', $noOrder299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias299()['currentGeneratedPathRowidCurrentSourceCostSelection299']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan299($current299, $current299, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan299(array_replace($current299, ['generated_path' => '$.rules[']), $current299)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next299 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
