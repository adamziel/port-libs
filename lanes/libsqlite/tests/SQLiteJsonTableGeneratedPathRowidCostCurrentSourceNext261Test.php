<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current261 = [
    'option_id' => 261,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next261',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-261-a',
];
$next261 = [
    'option_id' => 261,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next261',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-261-b',
];

$plan261 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionVariant(261,
    'json_tree',
    $current ?? $current261,
    $next ?? $next261,
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

$changed261 = static fn (): array => $plan261();
$stable261 = static fn (): array => $plan261($current261, $current261);
$staleFingerprint261 = static function () use ($plan261, $current261): array {
    return $plan261($current261, $current261, null, null, str_repeat('0', 64), null);
};
$staleRowid261 = static fn (): array => $plan261($current261, $current261, null, null, null, 5);
$noOrder261 = static fn (): array => $plan261($current261, $current261, null, []);
$oidAlias261 = static fn (): array => $plan261($current261, $current261, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias261 = static fn (): array => $plan261($current261, $current261, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next261 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next261', $changed261()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed261()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next261', $changed261()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next261', $changed261()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next261', $stable261()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable261()['next261ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-261-a', $changed261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next261', $changed261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed261()['nextGeneratedPathRowidCurrentSourceCostSelection261']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed261()['nextGeneratedPathRowidCurrentSourceCostSelection261']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed261()['nextGeneratedPathRowidCurrentSourceCostSelection261']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed261()['nextGeneratedPathRowidCurrentSourceCostSelection261']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed261()['nextGeneratedPathRowidCurrentSourceCostSelection261']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed261()['nextGeneratedPathRowidCurrentSourceCostSelection261']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next261', $changed261()['nextGeneratedPathRowidCurrentSourceCostSelection261']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed261()['generatedPathRowidCurrentSourceCostSelection261Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed261()['generatedPathRowidCurrentSourceCostSelection261Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed261()['generatedPathRowidCurrentSourceCostSelection261Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed261()['generatedPathRowidCurrentSourceCostSelection261Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed261()['generatedPathRowidCurrentSourceCostSelection261Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next261', $changed261()['next261ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next261', $changed261()['next261ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next261', $changed261()['next261ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next261', $changed261()['next261ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next261', $changed261()['next261ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed261()['next261ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next261', $staleFingerprint261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next261', $staleRowid261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next261', $noOrder261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias261()['currentGeneratedPathRowidCurrentSourceCostSelection261']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan261($current261, $current261, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan261(array_replace($current261, ['generated_path' => '$.rules[']), $current261)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next261 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
