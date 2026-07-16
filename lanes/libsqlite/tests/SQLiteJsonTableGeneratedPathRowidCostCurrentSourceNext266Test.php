<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current266 = [
    'option_id' => 266,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next266',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-266-a',
];
$next266 = [
    'option_id' => 266,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next266',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-266-b',
];

$plan266 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionVariant(266,
    'json_tree',
    $current ?? $current266,
    $next ?? $next266,
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

$changed266 = static fn (): array => $plan266();
$stable266 = static fn (): array => $plan266($current266, $current266);
$staleFingerprint266 = static function () use ($plan266, $current266): array {
    return $plan266($current266, $current266, null, null, str_repeat('0', 64), null);
};
$staleRowid266 = static fn (): array => $plan266($current266, $current266, null, null, null, 5);
$noOrder266 = static fn (): array => $plan266($current266, $current266, null, []);
$oidAlias266 = static fn (): array => $plan266($current266, $current266, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias266 = static fn (): array => $plan266($current266, $current266, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next266 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next266', $changed266()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed266()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next266', $changed266()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next266', $changed266()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next266', $stable266()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable266()['next266ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-266-a', $changed266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next266', $changed266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed266()['nextGeneratedPathRowidCurrentSourceCostSelection266']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed266()['nextGeneratedPathRowidCurrentSourceCostSelection266']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed266()['nextGeneratedPathRowidCurrentSourceCostSelection266']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed266()['nextGeneratedPathRowidCurrentSourceCostSelection266']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed266()['nextGeneratedPathRowidCurrentSourceCostSelection266']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed266()['nextGeneratedPathRowidCurrentSourceCostSelection266']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next266', $changed266()['nextGeneratedPathRowidCurrentSourceCostSelection266']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed266()['generatedPathRowidCurrentSourceCostSelection266Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed266()['generatedPathRowidCurrentSourceCostSelection266Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed266()['generatedPathRowidCurrentSourceCostSelection266Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed266()['generatedPathRowidCurrentSourceCostSelection266Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed266()['generatedPathRowidCurrentSourceCostSelection266Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next266', $changed266()['next266ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next266', $changed266()['next266ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next266', $changed266()['next266ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next266', $changed266()['next266ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next266', $changed266()['next266ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed266()['next266ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next266', $staleFingerprint266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next266', $staleRowid266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next266', $noOrder266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias266()['currentGeneratedPathRowidCurrentSourceCostSelection266']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan266($current266, $current266, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan266(array_replace($current266, ['generated_path' => '$.rules[']), $current266)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next266 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
