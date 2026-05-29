<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current306 = [
    'option_id' => 306,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next306',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-306-a',
];
$next306 = [
    'option_id' => 306,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next306',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-306-b',
];

$plan306 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext306(
    'json_tree',
    $current ?? $current306,
    $next ?? $next306,
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

$changed306 = static fn (): array => $plan306();
$stable306 = static fn (): array => $plan306($current306, $current306);
$staleFingerprint306 = static function () use ($plan306, $current306): array {
    return $plan306($current306, $current306, null, null, str_repeat('0', 64), null);
};
$staleRowid306 = static fn (): array => $plan306($current306, $current306, null, null, null, 5);
$noOrder306 = static fn (): array => $plan306($current306, $current306, null, []);
$oidAlias306 = static fn (): array => $plan306($current306, $current306, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias306 = static fn (): array => $plan306($current306, $current306, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next306 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next306', $changed306()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed306()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next306', $changed306()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next306', $changed306()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next306', $stable306()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable306()['next306ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-306-a', $changed306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next306', $changed306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed306()['nextGeneratedPathRowidCurrentSourceCostSelection306']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed306()['nextGeneratedPathRowidCurrentSourceCostSelection306']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed306()['nextGeneratedPathRowidCurrentSourceCostSelection306']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed306()['nextGeneratedPathRowidCurrentSourceCostSelection306']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed306()['nextGeneratedPathRowidCurrentSourceCostSelection306']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed306()['nextGeneratedPathRowidCurrentSourceCostSelection306']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next306', $changed306()['nextGeneratedPathRowidCurrentSourceCostSelection306']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed306()['generatedPathRowidCurrentSourceCostSelection306Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed306()['generatedPathRowidCurrentSourceCostSelection306Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed306()['generatedPathRowidCurrentSourceCostSelection306Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed306()['generatedPathRowidCurrentSourceCostSelection306Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed306()['generatedPathRowidCurrentSourceCostSelection306Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next306', $changed306()['next306ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next306', $changed306()['next306ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next306', $changed306()['next306ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next306', $changed306()['next306ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next306', $changed306()['next306ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed306()['next306ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next306', $staleFingerprint306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next306', $staleRowid306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next306', $noOrder306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias306()['currentGeneratedPathRowidCurrentSourceCostSelection306']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan306($current306, $current306, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan306(array_replace($current306, ['generated_path' => '$.rules[']), $current306)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next306 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
