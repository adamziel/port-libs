<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current259 = [
    'option_id' => 259,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next259',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-259-a',
];
$next259 = [
    'option_id' => 259,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next259',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-259-b',
];

$plan259 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext259(
    'json_tree',
    $current ?? $current259,
    $next ?? $next259,
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

$changed259 = static fn (): array => $plan259();
$stable259 = static fn (): array => $plan259($current259, $current259);
$staleFingerprint259 = static function () use ($plan259, $current259): array {
    return $plan259($current259, $current259, null, null, str_repeat('0', 64), null);
};
$staleRowid259 = static fn (): array => $plan259($current259, $current259, null, null, null, 5);
$noOrder259 = static fn (): array => $plan259($current259, $current259, null, []);
$oidAlias259 = static fn (): array => $plan259($current259, $current259, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias259 = static fn (): array => $plan259($current259, $current259, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next259 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next259', $changed259()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed259()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next259', $changed259()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next259', $changed259()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next259', $stable259()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable259()['next259ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-259-a', $changed259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next259', $changed259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed259()['nextGeneratedPathRowidCurrentSourceCostSelection259']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed259()['nextGeneratedPathRowidCurrentSourceCostSelection259']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed259()['nextGeneratedPathRowidCurrentSourceCostSelection259']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed259()['nextGeneratedPathRowidCurrentSourceCostSelection259']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed259()['nextGeneratedPathRowidCurrentSourceCostSelection259']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed259()['nextGeneratedPathRowidCurrentSourceCostSelection259']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next259', $changed259()['nextGeneratedPathRowidCurrentSourceCostSelection259']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed259()['generatedPathRowidCurrentSourceCostSelection259Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed259()['generatedPathRowidCurrentSourceCostSelection259Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed259()['generatedPathRowidCurrentSourceCostSelection259Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed259()['generatedPathRowidCurrentSourceCostSelection259Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed259()['generatedPathRowidCurrentSourceCostSelection259Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next259', $changed259()['next259ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next259', $changed259()['next259ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next259', $changed259()['next259ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next259', $changed259()['next259ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next259', $changed259()['next259ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed259()['next259ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next259', $staleFingerprint259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next259', $staleRowid259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next259', $noOrder259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias259()['currentGeneratedPathRowidCurrentSourceCostSelection259']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan259($current259, $current259, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan259(array_replace($current259, ['generated_path' => '$.rules[']), $current259)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next259 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
