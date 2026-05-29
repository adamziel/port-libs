<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current358 = [
    'option_id' => 358,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next358',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-358-a',
];
$next358 = [
    'option_id' => 358,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next358',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-358-b',
];

$plan358 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    358,
    $current ?? $current358,
    $next ?? $next358,
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

$changed358 = static fn (): array => $plan358();
$stable358 = static fn (): array => $plan358($current358, $current358);
$staleFingerprint358 = static function () use ($plan358, $current358): array {
    return $plan358($current358, $current358, null, null, str_repeat('0', 64), null);
};
$staleRowid358 = static fn (): array => $plan358($current358, $current358, null, null, null, 5);
$noOrder358 = static fn (): array => $plan358($current358, $current358, null, []);
$oidAlias358 = static fn (): array => $plan358($current358, $current358, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias358 = static fn (): array => $plan358($current358, $current358, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next358 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next358', $changed358()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed358()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next358', $changed358()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next358', $changed358()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next358', $stable358()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable358()['next358ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-358-a', $changed358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next358', $changed358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed358()['nextGeneratedPathRowidCurrentSourceCostSelection358']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed358()['nextGeneratedPathRowidCurrentSourceCostSelection358']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed358()['nextGeneratedPathRowidCurrentSourceCostSelection358']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed358()['nextGeneratedPathRowidCurrentSourceCostSelection358']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed358()['nextGeneratedPathRowidCurrentSourceCostSelection358']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed358()['nextGeneratedPathRowidCurrentSourceCostSelection358']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next358', $changed358()['nextGeneratedPathRowidCurrentSourceCostSelection358']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed358()['generatedPathRowidCurrentSourceCostSelection358Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed358()['generatedPathRowidCurrentSourceCostSelection358Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed358()['generatedPathRowidCurrentSourceCostSelection358Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed358()['generatedPathRowidCurrentSourceCostSelection358Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed358()['generatedPathRowidCurrentSourceCostSelection358Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next358', $changed358()['next358ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next358', $changed358()['next358ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next358', $changed358()['next358ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next358', $changed358()['next358ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next358', $changed358()['next358ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed358()['next358ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next358', $staleFingerprint358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next358', $staleRowid358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next358', $noOrder358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias358()['currentGeneratedPathRowidCurrentSourceCostSelection358']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan358($current358, $current358, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan358(array_replace($current358, ['generated_path' => '$.rules[']), $current358)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next358 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
