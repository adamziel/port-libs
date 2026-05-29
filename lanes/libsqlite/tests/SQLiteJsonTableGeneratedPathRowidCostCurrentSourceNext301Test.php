<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current301 = [
    'option_id' => 301,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next301',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-301-a',
];
$next301 = [
    'option_id' => 301,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next301',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-301-b',
];

$plan301 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext301(
    'json_tree',
    $current ?? $current301,
    $next ?? $next301,
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

$changed301 = static fn (): array => $plan301();
$stable301 = static fn (): array => $plan301($current301, $current301);
$staleFingerprint301 = static function () use ($plan301, $current301): array {
    return $plan301($current301, $current301, null, null, str_repeat('0', 64), null);
};
$staleRowid301 = static fn (): array => $plan301($current301, $current301, null, null, null, 5);
$noOrder301 = static fn (): array => $plan301($current301, $current301, null, []);
$oidAlias301 = static fn (): array => $plan301($current301, $current301, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias301 = static fn (): array => $plan301($current301, $current301, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next301 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next301', $changed301()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed301()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next301', $changed301()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next301', $changed301()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next301', $stable301()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable301()['next301ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-301-a', $changed301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next301', $changed301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed301()['nextGeneratedPathRowidCurrentSourceCostSelection301']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed301()['nextGeneratedPathRowidCurrentSourceCostSelection301']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed301()['nextGeneratedPathRowidCurrentSourceCostSelection301']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed301()['nextGeneratedPathRowidCurrentSourceCostSelection301']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed301()['nextGeneratedPathRowidCurrentSourceCostSelection301']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed301()['nextGeneratedPathRowidCurrentSourceCostSelection301']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next301', $changed301()['nextGeneratedPathRowidCurrentSourceCostSelection301']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed301()['generatedPathRowidCurrentSourceCostSelection301Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed301()['generatedPathRowidCurrentSourceCostSelection301Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed301()['generatedPathRowidCurrentSourceCostSelection301Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed301()['generatedPathRowidCurrentSourceCostSelection301Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed301()['generatedPathRowidCurrentSourceCostSelection301Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next301', $changed301()['next301ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next301', $changed301()['next301ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next301', $changed301()['next301ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next301', $changed301()['next301ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next301', $changed301()['next301ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed301()['next301ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next301', $staleFingerprint301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next301', $staleRowid301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next301', $noOrder301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias301()['currentGeneratedPathRowidCurrentSourceCostSelection301']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan301($current301, $current301, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan301(array_replace($current301, ['generated_path' => '$.rules[']), $current301)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next301 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
