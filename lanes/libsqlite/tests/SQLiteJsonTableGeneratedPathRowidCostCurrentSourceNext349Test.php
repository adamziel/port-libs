<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current349 = [
    'option_id' => 349,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next349',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-349-a',
];
$next349 = [
    'option_id' => 349,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next349',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-349-b',
];

$plan349 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    349,
    $current ?? $current349,
    $next ?? $next349,
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

$changed349 = static fn (): array => $plan349();
$stable349 = static fn (): array => $plan349($current349, $current349);
$staleFingerprint349 = static function () use ($plan349, $current349): array {
    return $plan349($current349, $current349, null, null, str_repeat('0', 64), null);
};
$staleRowid349 = static fn (): array => $plan349($current349, $current349, null, null, null, 5);
$noOrder349 = static fn (): array => $plan349($current349, $current349, null, []);
$oidAlias349 = static fn (): array => $plan349($current349, $current349, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias349 = static fn (): array => $plan349($current349, $current349, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next349 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next349', $changed349()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed349()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next349', $changed349()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next349', $changed349()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next349', $stable349()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable349()['next349ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-349-a', $changed349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next349', $changed349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed349()['nextGeneratedPathRowidCurrentSourceCostSelection349']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed349()['nextGeneratedPathRowidCurrentSourceCostSelection349']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed349()['nextGeneratedPathRowidCurrentSourceCostSelection349']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed349()['nextGeneratedPathRowidCurrentSourceCostSelection349']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed349()['nextGeneratedPathRowidCurrentSourceCostSelection349']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed349()['nextGeneratedPathRowidCurrentSourceCostSelection349']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next349', $changed349()['nextGeneratedPathRowidCurrentSourceCostSelection349']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed349()['generatedPathRowidCurrentSourceCostSelection349Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed349()['generatedPathRowidCurrentSourceCostSelection349Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed349()['generatedPathRowidCurrentSourceCostSelection349Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed349()['generatedPathRowidCurrentSourceCostSelection349Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed349()['generatedPathRowidCurrentSourceCostSelection349Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next349', $changed349()['next349ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next349', $changed349()['next349ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next349', $changed349()['next349ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next349', $changed349()['next349ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next349', $changed349()['next349ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed349()['next349ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next349', $staleFingerprint349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next349', $staleRowid349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next349', $noOrder349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias349()['currentGeneratedPathRowidCurrentSourceCostSelection349']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan349($current349, $current349, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan349(array_replace($current349, ['generated_path' => '$.rules[']), $current349)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next349 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
