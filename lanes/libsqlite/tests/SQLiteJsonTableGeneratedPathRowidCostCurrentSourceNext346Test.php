<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current346 = [
    'option_id' => 346,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next346',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-346-a',
];
$next346 = [
    'option_id' => 346,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next346',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-346-b',
];

$plan346 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    346,
    $current ?? $current346,
    $next ?? $next346,
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

$changed346 = static fn (): array => $plan346();
$stable346 = static fn (): array => $plan346($current346, $current346);
$staleFingerprint346 = static function () use ($plan346, $current346): array {
    return $plan346($current346, $current346, null, null, str_repeat('0', 64), null);
};
$staleRowid346 = static fn (): array => $plan346($current346, $current346, null, null, null, 5);
$noOrder346 = static fn (): array => $plan346($current346, $current346, null, []);
$oidAlias346 = static fn (): array => $plan346($current346, $current346, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias346 = static fn (): array => $plan346($current346, $current346, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next346 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next346', $changed346()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed346()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next346', $changed346()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next346', $changed346()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next346', $stable346()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable346()['next346ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-346-a', $changed346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next346', $changed346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed346()['nextGeneratedPathRowidCurrentSourceCostSelection346']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed346()['nextGeneratedPathRowidCurrentSourceCostSelection346']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed346()['nextGeneratedPathRowidCurrentSourceCostSelection346']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed346()['nextGeneratedPathRowidCurrentSourceCostSelection346']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed346()['nextGeneratedPathRowidCurrentSourceCostSelection346']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed346()['nextGeneratedPathRowidCurrentSourceCostSelection346']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next346', $changed346()['nextGeneratedPathRowidCurrentSourceCostSelection346']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed346()['generatedPathRowidCurrentSourceCostSelection346Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed346()['generatedPathRowidCurrentSourceCostSelection346Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed346()['generatedPathRowidCurrentSourceCostSelection346Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed346()['generatedPathRowidCurrentSourceCostSelection346Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed346()['generatedPathRowidCurrentSourceCostSelection346Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next346', $changed346()['next346ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next346', $changed346()['next346ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next346', $changed346()['next346ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next346', $changed346()['next346ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next346', $changed346()['next346ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed346()['next346ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next346', $staleFingerprint346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next346', $staleRowid346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next346', $noOrder346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias346()['currentGeneratedPathRowidCurrentSourceCostSelection346']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan346($current346, $current346, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan346(array_replace($current346, ['generated_path' => '$.rules[']), $current346)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next346 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
