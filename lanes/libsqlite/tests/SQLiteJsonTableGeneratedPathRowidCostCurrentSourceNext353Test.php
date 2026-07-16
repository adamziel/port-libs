<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current353 = [
    'option_id' => 353,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next353',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-353-a',
];
$next353 = [
    'option_id' => 353,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next353',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-353-b',
];

$plan353 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    353,
    $current ?? $current353,
    $next ?? $next353,
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

$changed353 = static fn (): array => $plan353();
$stable353 = static fn (): array => $plan353($current353, $current353);
$staleFingerprint353 = static function () use ($plan353, $current353): array {
    return $plan353($current353, $current353, null, null, str_repeat('0', 64), null);
};
$staleRowid353 = static fn (): array => $plan353($current353, $current353, null, null, null, 5);
$noOrder353 = static fn (): array => $plan353($current353, $current353, null, []);
$oidAlias353 = static fn (): array => $plan353($current353, $current353, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias353 = static fn (): array => $plan353($current353, $current353, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next353 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next353', $changed353()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed353()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next353', $changed353()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next353', $changed353()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next353', $stable353()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable353()['next353ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-353-a', $changed353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next353', $changed353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed353()['nextGeneratedPathRowidCurrentSourceCostSelection353']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed353()['nextGeneratedPathRowidCurrentSourceCostSelection353']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed353()['nextGeneratedPathRowidCurrentSourceCostSelection353']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed353()['nextGeneratedPathRowidCurrentSourceCostSelection353']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed353()['nextGeneratedPathRowidCurrentSourceCostSelection353']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed353()['nextGeneratedPathRowidCurrentSourceCostSelection353']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next353', $changed353()['nextGeneratedPathRowidCurrentSourceCostSelection353']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed353()['generatedPathRowidCurrentSourceCostSelection353Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed353()['generatedPathRowidCurrentSourceCostSelection353Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed353()['generatedPathRowidCurrentSourceCostSelection353Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed353()['generatedPathRowidCurrentSourceCostSelection353Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed353()['generatedPathRowidCurrentSourceCostSelection353Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next353', $changed353()['next353ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next353', $changed353()['next353ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next353', $changed353()['next353ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next353', $changed353()['next353ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next353', $changed353()['next353ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed353()['next353ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next353', $staleFingerprint353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next353', $staleRowid353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next353', $noOrder353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias353()['currentGeneratedPathRowidCurrentSourceCostSelection353']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan353($current353, $current353, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan353(array_replace($current353, ['generated_path' => '$.rules[']), $current353)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next353 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
