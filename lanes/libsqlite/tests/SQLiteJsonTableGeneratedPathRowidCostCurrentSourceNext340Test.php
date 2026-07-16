<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current340 = [
    'option_id' => 340,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next340',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-340-a',
];
$next340 = [
    'option_id' => 340,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next340',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-340-b',
];

$plan340 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    340,
    $current ?? $current340,
    $next ?? $next340,
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

$changed340 = static fn (): array => $plan340();
$stable340 = static fn (): array => $plan340($current340, $current340);
$staleFingerprint340 = static function () use ($plan340, $current340): array {
    return $plan340($current340, $current340, null, null, str_repeat('0', 64), null);
};
$staleRowid340 = static fn (): array => $plan340($current340, $current340, null, null, null, 5);
$noOrder340 = static fn (): array => $plan340($current340, $current340, null, []);
$oidAlias340 = static fn (): array => $plan340($current340, $current340, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias340 = static fn (): array => $plan340($current340, $current340, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next340 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next340', $changed340()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed340()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next340', $changed340()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next340', $changed340()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next340', $stable340()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable340()['next340ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-340-a', $changed340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next340', $changed340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed340()['nextGeneratedPathRowidCurrentSourceCostSelection340']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed340()['nextGeneratedPathRowidCurrentSourceCostSelection340']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed340()['nextGeneratedPathRowidCurrentSourceCostSelection340']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed340()['nextGeneratedPathRowidCurrentSourceCostSelection340']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed340()['nextGeneratedPathRowidCurrentSourceCostSelection340']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed340()['nextGeneratedPathRowidCurrentSourceCostSelection340']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next340', $changed340()['nextGeneratedPathRowidCurrentSourceCostSelection340']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed340()['generatedPathRowidCurrentSourceCostSelection340Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed340()['generatedPathRowidCurrentSourceCostSelection340Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed340()['generatedPathRowidCurrentSourceCostSelection340Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed340()['generatedPathRowidCurrentSourceCostSelection340Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed340()['generatedPathRowidCurrentSourceCostSelection340Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next340', $changed340()['next340ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next340', $changed340()['next340ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next340', $changed340()['next340ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next340', $changed340()['next340ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next340', $changed340()['next340ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed340()['next340ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next340', $staleFingerprint340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next340', $staleRowid340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next340', $noOrder340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias340()['currentGeneratedPathRowidCurrentSourceCostSelection340']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan340($current340, $current340, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan340(array_replace($current340, ['generated_path' => '$.rules[']), $current340)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next340 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
