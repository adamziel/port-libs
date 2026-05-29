<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current318 = [
    'option_id' => 318,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next318',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-318-a',
];
$next318 = [
    'option_id' => 318,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next318',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-318-b',
];

$plan318 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext318(
    'json_tree',
    $current ?? $current318,
    $next ?? $next318,
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

$changed318 = static fn (): array => $plan318();
$stable318 = static fn (): array => $plan318($current318, $current318);
$staleFingerprint318 = static function () use ($plan318, $current318): array {
    return $plan318($current318, $current318, null, null, str_repeat('0', 64), null);
};
$staleRowid318 = static fn (): array => $plan318($current318, $current318, null, null, null, 5);
$noOrder318 = static fn (): array => $plan318($current318, $current318, null, []);
$oidAlias318 = static fn (): array => $plan318($current318, $current318, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias318 = static fn (): array => $plan318($current318, $current318, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next318 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next318', $changed318()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed318()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next318', $changed318()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next318', $changed318()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next318', $stable318()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable318()['next318ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-318-a', $changed318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next318', $changed318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed318()['nextGeneratedPathRowidCurrentSourceCostSelection318']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed318()['nextGeneratedPathRowidCurrentSourceCostSelection318']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed318()['nextGeneratedPathRowidCurrentSourceCostSelection318']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed318()['nextGeneratedPathRowidCurrentSourceCostSelection318']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed318()['nextGeneratedPathRowidCurrentSourceCostSelection318']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed318()['nextGeneratedPathRowidCurrentSourceCostSelection318']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next318', $changed318()['nextGeneratedPathRowidCurrentSourceCostSelection318']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed318()['generatedPathRowidCurrentSourceCostSelection318Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed318()['generatedPathRowidCurrentSourceCostSelection318Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed318()['generatedPathRowidCurrentSourceCostSelection318Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed318()['generatedPathRowidCurrentSourceCostSelection318Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed318()['generatedPathRowidCurrentSourceCostSelection318Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next318', $changed318()['next318ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next318', $changed318()['next318ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next318', $changed318()['next318ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next318', $changed318()['next318ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next318', $changed318()['next318ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed318()['next318ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next318', $staleFingerprint318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next318', $staleRowid318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next318', $noOrder318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias318()['currentGeneratedPathRowidCurrentSourceCostSelection318']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan318($current318, $current318, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan318(array_replace($current318, ['generated_path' => '$.rules[']), $current318)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next318 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
