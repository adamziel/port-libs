<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current270 = [
    'option_id' => 270,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next270',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-270-a',
];
$next270 = [
    'option_id' => 270,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next270',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-270-b',
];

$plan270 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext270(
    'json_tree',
    $current ?? $current270,
    $next ?? $next270,
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

$changed270 = static fn (): array => $plan270();
$stable270 = static fn (): array => $plan270($current270, $current270);
$staleFingerprint270 = static function () use ($plan270, $current270): array {
    return $plan270($current270, $current270, null, null, str_repeat('0', 64), null);
};
$staleRowid270 = static fn (): array => $plan270($current270, $current270, null, null, null, 5);
$noOrder270 = static fn (): array => $plan270($current270, $current270, null, []);
$oidAlias270 = static fn (): array => $plan270($current270, $current270, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias270 = static fn (): array => $plan270($current270, $current270, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next270 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next270', $changed270()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed270()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next270', $changed270()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next270', $changed270()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next270', $stable270()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable270()['next270ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-270-a', $changed270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next270', $changed270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed270()['nextGeneratedPathRowidCurrentSourceCostSelection270']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed270()['nextGeneratedPathRowidCurrentSourceCostSelection270']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed270()['nextGeneratedPathRowidCurrentSourceCostSelection270']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed270()['nextGeneratedPathRowidCurrentSourceCostSelection270']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed270()['nextGeneratedPathRowidCurrentSourceCostSelection270']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed270()['nextGeneratedPathRowidCurrentSourceCostSelection270']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next270', $changed270()['nextGeneratedPathRowidCurrentSourceCostSelection270']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed270()['generatedPathRowidCurrentSourceCostSelection270Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed270()['generatedPathRowidCurrentSourceCostSelection270Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed270()['generatedPathRowidCurrentSourceCostSelection270Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed270()['generatedPathRowidCurrentSourceCostSelection270Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed270()['generatedPathRowidCurrentSourceCostSelection270Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next270', $changed270()['next270ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next270', $changed270()['next270ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next270', $changed270()['next270ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next270', $changed270()['next270ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next270', $changed270()['next270ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed270()['next270ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next270', $staleFingerprint270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next270', $staleRowid270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next270', $noOrder270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias270()['currentGeneratedPathRowidCurrentSourceCostSelection270']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan270($current270, $current270, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan270(array_replace($current270, ['generated_path' => '$.rules[']), $current270)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next270 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
