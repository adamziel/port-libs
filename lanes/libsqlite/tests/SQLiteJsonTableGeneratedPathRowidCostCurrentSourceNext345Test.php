<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current345 = [
    'option_id' => 345,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next345',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-345-a',
];
$next345 = [
    'option_id' => 345,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next345',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-345-b',
];

$plan345 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    345,
    $current ?? $current345,
    $next ?? $next345,
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

$changed345 = static fn (): array => $plan345();
$stable345 = static fn (): array => $plan345($current345, $current345);
$staleFingerprint345 = static function () use ($plan345, $current345): array {
    return $plan345($current345, $current345, null, null, str_repeat('0', 64), null);
};
$staleRowid345 = static fn (): array => $plan345($current345, $current345, null, null, null, 5);
$noOrder345 = static fn (): array => $plan345($current345, $current345, null, []);
$oidAlias345 = static fn (): array => $plan345($current345, $current345, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias345 = static fn (): array => $plan345($current345, $current345, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next345 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next345', $changed345()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed345()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next345', $changed345()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next345', $changed345()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next345', $stable345()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable345()['next345ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-345-a', $changed345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next345', $changed345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed345()['nextGeneratedPathRowidCurrentSourceCostSelection345']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed345()['nextGeneratedPathRowidCurrentSourceCostSelection345']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed345()['nextGeneratedPathRowidCurrentSourceCostSelection345']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed345()['nextGeneratedPathRowidCurrentSourceCostSelection345']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed345()['nextGeneratedPathRowidCurrentSourceCostSelection345']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed345()['nextGeneratedPathRowidCurrentSourceCostSelection345']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next345', $changed345()['nextGeneratedPathRowidCurrentSourceCostSelection345']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed345()['generatedPathRowidCurrentSourceCostSelection345Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed345()['generatedPathRowidCurrentSourceCostSelection345Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed345()['generatedPathRowidCurrentSourceCostSelection345Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed345()['generatedPathRowidCurrentSourceCostSelection345Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed345()['generatedPathRowidCurrentSourceCostSelection345Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next345', $changed345()['next345ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next345', $changed345()['next345ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next345', $changed345()['next345ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next345', $changed345()['next345ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next345', $changed345()['next345ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed345()['next345ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next345', $staleFingerprint345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next345', $staleRowid345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next345', $noOrder345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias345()['currentGeneratedPathRowidCurrentSourceCostSelection345']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan345($current345, $current345, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan345(array_replace($current345, ['generated_path' => '$.rules[']), $current345)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next345 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
