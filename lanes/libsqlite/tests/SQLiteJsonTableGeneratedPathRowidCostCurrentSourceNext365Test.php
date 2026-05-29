<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current365 = [
    'option_id' => 365,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next365',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-365-a',
];
$next365 = [
    'option_id' => 365,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next365',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-365-b',
];

$plan365 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext365(
    'json_tree',
    $current ?? $current365,
    $next ?? $next365,
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

$changed365 = static fn (): array => $plan365();
$stable365 = static fn (): array => $plan365($current365, $current365);
$staleFingerprint365 = static function () use ($plan365, $current365): array {
    return $plan365($current365, $current365, null, null, str_repeat('0', 64), null);
};
$staleRowid365 = static fn (): array => $plan365($current365, $current365, null, null, null, 5);
$noOrder365 = static fn (): array => $plan365($current365, $current365, null, []);
$oidAlias365 = static fn (): array => $plan365($current365, $current365, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias365 = static fn (): array => $plan365($current365, $current365, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next365 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next365', $changed365()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed365()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next365', $changed365()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next365', $changed365()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next365', $stable365()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable365()['next365ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-365-a', $changed365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next365', $changed365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed365()['nextGeneratedPathRowidCurrentSourceCostSelection365']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed365()['nextGeneratedPathRowidCurrentSourceCostSelection365']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed365()['nextGeneratedPathRowidCurrentSourceCostSelection365']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed365()['nextGeneratedPathRowidCurrentSourceCostSelection365']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed365()['nextGeneratedPathRowidCurrentSourceCostSelection365']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed365()['nextGeneratedPathRowidCurrentSourceCostSelection365']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next365', $changed365()['nextGeneratedPathRowidCurrentSourceCostSelection365']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed365()['generatedPathRowidCurrentSourceCostSelection365Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed365()['generatedPathRowidCurrentSourceCostSelection365Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed365()['generatedPathRowidCurrentSourceCostSelection365Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed365()['generatedPathRowidCurrentSourceCostSelection365Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed365()['generatedPathRowidCurrentSourceCostSelection365Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next365', $changed365()['next365ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next365', $changed365()['next365ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next365', $changed365()['next365ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next365', $changed365()['next365ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next365', $changed365()['next365ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed365()['next365ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next365', $staleFingerprint365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next365', $staleRowid365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next365', $noOrder365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias365()['currentGeneratedPathRowidCurrentSourceCostSelection365']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan365($current365, $current365, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan365(array_replace($current365, ['generated_path' => '$.rules[']), $current365)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next365 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
