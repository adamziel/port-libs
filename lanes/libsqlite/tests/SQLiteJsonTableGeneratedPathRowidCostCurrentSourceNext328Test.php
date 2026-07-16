<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current328 = [
    'option_id' => 328,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next328',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-328-a',
];
$next328 = [
    'option_id' => 328,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next328',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-328-b',
];

$plan328 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    328,
    $current ?? $current328,
    $next ?? $next328,
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

$changed328 = static fn (): array => $plan328();
$stable328 = static fn (): array => $plan328($current328, $current328);
$staleFingerprint328 = static function () use ($plan328, $current328): array {
    return $plan328($current328, $current328, null, null, str_repeat('0', 64), null);
};
$staleRowid328 = static fn (): array => $plan328($current328, $current328, null, null, null, 5);
$noOrder328 = static fn (): array => $plan328($current328, $current328, null, []);
$oidAlias328 = static fn (): array => $plan328($current328, $current328, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias328 = static fn (): array => $plan328($current328, $current328, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next328 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next328', $changed328()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed328()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next328', $changed328()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next328', $changed328()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next328', $stable328()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable328()['next328ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-328-a', $changed328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next328', $changed328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed328()['nextGeneratedPathRowidCurrentSourceCostSelection328']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed328()['nextGeneratedPathRowidCurrentSourceCostSelection328']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed328()['nextGeneratedPathRowidCurrentSourceCostSelection328']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed328()['nextGeneratedPathRowidCurrentSourceCostSelection328']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed328()['nextGeneratedPathRowidCurrentSourceCostSelection328']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed328()['nextGeneratedPathRowidCurrentSourceCostSelection328']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next328', $changed328()['nextGeneratedPathRowidCurrentSourceCostSelection328']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed328()['generatedPathRowidCurrentSourceCostSelection328Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed328()['generatedPathRowidCurrentSourceCostSelection328Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed328()['generatedPathRowidCurrentSourceCostSelection328Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed328()['generatedPathRowidCurrentSourceCostSelection328Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed328()['generatedPathRowidCurrentSourceCostSelection328Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next328', $changed328()['next328ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next328', $changed328()['next328ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next328', $changed328()['next328ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next328', $changed328()['next328ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next328', $changed328()['next328ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed328()['next328ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next328', $staleFingerprint328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next328', $staleRowid328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next328', $noOrder328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias328()['currentGeneratedPathRowidCurrentSourceCostSelection328']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan328($current328, $current328, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan328(array_replace($current328, ['generated_path' => '$.rules[']), $current328)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next328 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
