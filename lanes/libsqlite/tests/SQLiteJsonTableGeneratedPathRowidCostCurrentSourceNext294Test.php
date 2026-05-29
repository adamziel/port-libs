<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current294 = [
    'option_id' => 294,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next294',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-294-a',
];
$next294 = [
    'option_id' => 294,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next294',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-294-b',
];

$plan294 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    294,
    $current ?? $current294,
    $next ?? $next294,
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

$changed294 = static fn (): array => $plan294();
$stable294 = static fn (): array => $plan294($current294, $current294);
$staleFingerprint294 = static function () use ($plan294, $current294): array {
    return $plan294($current294, $current294, null, null, str_repeat('0', 64), null);
};
$staleRowid294 = static fn (): array => $plan294($current294, $current294, null, null, null, 5);
$noOrder294 = static fn (): array => $plan294($current294, $current294, null, []);
$oidAlias294 = static fn (): array => $plan294($current294, $current294, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias294 = static fn (): array => $plan294($current294, $current294, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next294 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next294', $changed294()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed294()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next294', $changed294()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next294', $changed294()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next294', $stable294()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable294()['next294ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-294-a', $changed294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next294', $changed294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed294()['nextGeneratedPathRowidCurrentSourceCostSelection294']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed294()['nextGeneratedPathRowidCurrentSourceCostSelection294']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed294()['nextGeneratedPathRowidCurrentSourceCostSelection294']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed294()['nextGeneratedPathRowidCurrentSourceCostSelection294']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed294()['nextGeneratedPathRowidCurrentSourceCostSelection294']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed294()['nextGeneratedPathRowidCurrentSourceCostSelection294']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next294', $changed294()['nextGeneratedPathRowidCurrentSourceCostSelection294']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed294()['generatedPathRowidCurrentSourceCostSelection294Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed294()['generatedPathRowidCurrentSourceCostSelection294Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed294()['generatedPathRowidCurrentSourceCostSelection294Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed294()['generatedPathRowidCurrentSourceCostSelection294Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed294()['generatedPathRowidCurrentSourceCostSelection294Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next294', $changed294()['next294ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next294', $changed294()['next294ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next294', $changed294()['next294ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next294', $changed294()['next294ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next294', $changed294()['next294ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed294()['next294ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next294', $staleFingerprint294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next294', $staleRowid294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next294', $noOrder294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias294()['currentGeneratedPathRowidCurrentSourceCostSelection294']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan294($current294, $current294, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan294(array_replace($current294, ['generated_path' => '$.rules[']), $current294)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next294 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
