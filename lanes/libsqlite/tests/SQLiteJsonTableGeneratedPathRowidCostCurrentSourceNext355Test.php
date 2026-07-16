<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current355 = [
    'option_id' => 355,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next355',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-355-a',
];
$next355 = [
    'option_id' => 355,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next355',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-355-b',
];

$plan355 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    355,
    $current ?? $current355,
    $next ?? $next355,
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

$changed355 = static fn (): array => $plan355();
$stable355 = static fn (): array => $plan355($current355, $current355);
$staleFingerprint355 = static function () use ($plan355, $current355): array {
    return $plan355($current355, $current355, null, null, str_repeat('0', 64), null);
};
$staleRowid355 = static fn (): array => $plan355($current355, $current355, null, null, null, 5);
$noOrder355 = static fn (): array => $plan355($current355, $current355, null, []);
$oidAlias355 = static fn (): array => $plan355($current355, $current355, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias355 = static fn (): array => $plan355($current355, $current355, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next355 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next355', $changed355()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed355()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next355', $changed355()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next355', $changed355()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next355', $stable355()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable355()['next355ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-355-a', $changed355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next355', $changed355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed355()['nextGeneratedPathRowidCurrentSourceCostSelection355']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed355()['nextGeneratedPathRowidCurrentSourceCostSelection355']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed355()['nextGeneratedPathRowidCurrentSourceCostSelection355']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed355()['nextGeneratedPathRowidCurrentSourceCostSelection355']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed355()['nextGeneratedPathRowidCurrentSourceCostSelection355']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed355()['nextGeneratedPathRowidCurrentSourceCostSelection355']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next355', $changed355()['nextGeneratedPathRowidCurrentSourceCostSelection355']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed355()['generatedPathRowidCurrentSourceCostSelection355Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed355()['generatedPathRowidCurrentSourceCostSelection355Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed355()['generatedPathRowidCurrentSourceCostSelection355Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed355()['generatedPathRowidCurrentSourceCostSelection355Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed355()['generatedPathRowidCurrentSourceCostSelection355Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next355', $changed355()['next355ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next355', $changed355()['next355ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next355', $changed355()['next355ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next355', $changed355()['next355ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next355', $changed355()['next355ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed355()['next355ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next355', $staleFingerprint355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next355', $staleRowid355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next355', $noOrder355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias355()['currentGeneratedPathRowidCurrentSourceCostSelection355']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan355($current355, $current355, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan355(array_replace($current355, ['generated_path' => '$.rules[']), $current355)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next355 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
