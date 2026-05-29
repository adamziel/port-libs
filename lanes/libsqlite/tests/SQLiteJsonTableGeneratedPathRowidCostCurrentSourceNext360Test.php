<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current360 = [
    'option_id' => 360,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next360',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-360-a',
];
$next360 = [
    'option_id' => 360,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next360',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-360-b',
];

$plan360 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    360,
    $current ?? $current360,
    $next ?? $next360,
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

$changed360 = static fn (): array => $plan360();
$stable360 = static fn (): array => $plan360($current360, $current360);
$staleFingerprint360 = static function () use ($plan360, $current360): array {
    return $plan360($current360, $current360, null, null, str_repeat('0', 64), null);
};
$staleRowid360 = static fn (): array => $plan360($current360, $current360, null, null, null, 5);
$noOrder360 = static fn (): array => $plan360($current360, $current360, null, []);
$oidAlias360 = static fn (): array => $plan360($current360, $current360, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias360 = static fn (): array => $plan360($current360, $current360, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next360 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next360', $changed360()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed360()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next360', $changed360()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next360', $changed360()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next360', $stable360()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable360()['next360ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-360-a', $changed360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next360', $changed360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed360()['nextGeneratedPathRowidCurrentSourceCostSelection360']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed360()['nextGeneratedPathRowidCurrentSourceCostSelection360']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed360()['nextGeneratedPathRowidCurrentSourceCostSelection360']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed360()['nextGeneratedPathRowidCurrentSourceCostSelection360']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed360()['nextGeneratedPathRowidCurrentSourceCostSelection360']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed360()['nextGeneratedPathRowidCurrentSourceCostSelection360']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next360', $changed360()['nextGeneratedPathRowidCurrentSourceCostSelection360']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed360()['generatedPathRowidCurrentSourceCostSelection360Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed360()['generatedPathRowidCurrentSourceCostSelection360Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed360()['generatedPathRowidCurrentSourceCostSelection360Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed360()['generatedPathRowidCurrentSourceCostSelection360Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed360()['generatedPathRowidCurrentSourceCostSelection360Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next360', $changed360()['next360ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next360', $changed360()['next360ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next360', $changed360()['next360ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next360', $changed360()['next360ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next360', $changed360()['next360ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed360()['next360ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next360', $staleFingerprint360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next360', $staleRowid360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next360', $noOrder360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias360()['currentGeneratedPathRowidCurrentSourceCostSelection360']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan360($current360, $current360, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan360(array_replace($current360, ['generated_path' => '$.rules[']), $current360)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next360 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
