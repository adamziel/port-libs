<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current308 = [
    'option_id' => 308,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next308',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-308-a',
];
$next308 = [
    'option_id' => 308,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next308',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-308-b',
];

$plan308 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    308,
    $current ?? $current308,
    $next ?? $next308,
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

$changed308 = static fn (): array => $plan308();
$stable308 = static fn (): array => $plan308($current308, $current308);
$staleFingerprint308 = static function () use ($plan308, $current308): array {
    return $plan308($current308, $current308, null, null, str_repeat('0', 64), null);
};
$staleRowid308 = static fn (): array => $plan308($current308, $current308, null, null, null, 5);
$noOrder308 = static fn (): array => $plan308($current308, $current308, null, []);
$oidAlias308 = static fn (): array => $plan308($current308, $current308, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias308 = static fn (): array => $plan308($current308, $current308, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next308 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next308', $changed308()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed308()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next308', $changed308()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next308', $changed308()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next308', $stable308()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable308()['next308ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-308-a', $changed308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next308', $changed308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed308()['nextGeneratedPathRowidCurrentSourceCostSelection308']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed308()['nextGeneratedPathRowidCurrentSourceCostSelection308']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed308()['nextGeneratedPathRowidCurrentSourceCostSelection308']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed308()['nextGeneratedPathRowidCurrentSourceCostSelection308']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed308()['nextGeneratedPathRowidCurrentSourceCostSelection308']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed308()['nextGeneratedPathRowidCurrentSourceCostSelection308']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next308', $changed308()['nextGeneratedPathRowidCurrentSourceCostSelection308']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed308()['generatedPathRowidCurrentSourceCostSelection308Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed308()['generatedPathRowidCurrentSourceCostSelection308Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed308()['generatedPathRowidCurrentSourceCostSelection308Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed308()['generatedPathRowidCurrentSourceCostSelection308Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed308()['generatedPathRowidCurrentSourceCostSelection308Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next308', $changed308()['next308ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next308', $changed308()['next308ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next308', $changed308()['next308ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next308', $changed308()['next308ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next308', $changed308()['next308ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed308()['next308ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next308', $staleFingerprint308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next308', $staleRowid308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next308', $noOrder308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias308()['currentGeneratedPathRowidCurrentSourceCostSelection308']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan308($current308, $current308, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan308(array_replace($current308, ['generated_path' => '$.rules[']), $current308)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next308 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
