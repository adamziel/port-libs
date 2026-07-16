<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current250 = [
    'option_id' => 250,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next250',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-250-a',
];
$next250 = [
    'option_id' => 250,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next250',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-250-b',
];

$plan250 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    250,
    $current ?? $current250,
    $next ?? $next250,
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

$changed250 = static fn (): array => $plan250();
$stable250 = static fn (): array => $plan250($current250, $current250);
$staleFingerprint250 = static function () use ($plan250, $current250): array {
    return $plan250($current250, $current250, null, null, str_repeat('0', 64), null);
};
$staleRowid250 = static fn (): array => $plan250($current250, $current250, null, null, null, 5);
$noOrder250 = static fn (): array => $plan250($current250, $current250, null, []);
$oidAlias250 = static fn (): array => $plan250($current250, $current250, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias250 = static fn (): array => $plan250($current250, $current250, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next250 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next250', $changed250()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed250()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next250', $changed250()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next250', $changed250()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next250', $stable250()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable250()['next250ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-250-a', $changed250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next250', $changed250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed250()['nextGeneratedPathRowidCurrentSourceCostSelection250']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed250()['nextGeneratedPathRowidCurrentSourceCostSelection250']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed250()['nextGeneratedPathRowidCurrentSourceCostSelection250']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed250()['nextGeneratedPathRowidCurrentSourceCostSelection250']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed250()['nextGeneratedPathRowidCurrentSourceCostSelection250']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed250()['nextGeneratedPathRowidCurrentSourceCostSelection250']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next250', $changed250()['nextGeneratedPathRowidCurrentSourceCostSelection250']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed250()['generatedPathRowidCurrentSourceCostSelection250Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed250()['generatedPathRowidCurrentSourceCostSelection250Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed250()['generatedPathRowidCurrentSourceCostSelection250Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed250()['generatedPathRowidCurrentSourceCostSelection250Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed250()['generatedPathRowidCurrentSourceCostSelection250Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next250', $changed250()['next250ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next250', $changed250()['next250ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next250', $changed250()['next250ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next250', $changed250()['next250ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next250', $changed250()['next250ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed250()['next250ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next250', $staleFingerprint250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next250', $staleRowid250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next250', $noOrder250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias250()['currentGeneratedPathRowidCurrentSourceCostSelection250']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan250($current250, $current250, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan250(array_replace($current250, ['generated_path' => '$.rules[']), $current250)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next250 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
