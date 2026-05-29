<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current363 = [
    'option_id' => 363,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next363',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-363-a',
];
$next363 = [
    'option_id' => 363,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next363',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-363-b',
];

$plan363 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    363,
    $current ?? $current363,
    $next ?? $next363,
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

$changed363 = static fn (): array => $plan363();
$stable363 = static fn (): array => $plan363($current363, $current363);
$staleFingerprint363 = static function () use ($plan363, $current363): array {
    return $plan363($current363, $current363, null, null, str_repeat('0', 64), null);
};
$staleRowid363 = static fn (): array => $plan363($current363, $current363, null, null, null, 5);
$noOrder363 = static fn (): array => $plan363($current363, $current363, null, []);
$oidAlias363 = static fn (): array => $plan363($current363, $current363, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias363 = static fn (): array => $plan363($current363, $current363, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next363 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next363', $changed363()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed363()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next363', $changed363()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next363', $changed363()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next363', $stable363()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable363()['next363ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-363-a', $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next363', $changed363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed363()['nextGeneratedPathRowidCurrentSourceCostSelection363']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed363()['nextGeneratedPathRowidCurrentSourceCostSelection363']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed363()['nextGeneratedPathRowidCurrentSourceCostSelection363']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed363()['nextGeneratedPathRowidCurrentSourceCostSelection363']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed363()['nextGeneratedPathRowidCurrentSourceCostSelection363']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed363()['nextGeneratedPathRowidCurrentSourceCostSelection363']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next363', $changed363()['nextGeneratedPathRowidCurrentSourceCostSelection363']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed363()['generatedPathRowidCurrentSourceCostSelection363Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed363()['generatedPathRowidCurrentSourceCostSelection363Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed363()['generatedPathRowidCurrentSourceCostSelection363Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed363()['generatedPathRowidCurrentSourceCostSelection363Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed363()['generatedPathRowidCurrentSourceCostSelection363Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next363', $changed363()['next363ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next363', $changed363()['next363ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next363', $changed363()['next363ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next363', $changed363()['next363ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next363', $changed363()['next363ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed363()['next363ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next363', $staleFingerprint363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next363', $staleRowid363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next363', $noOrder363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias363()['currentGeneratedPathRowidCurrentSourceCostSelection363']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan363($current363, $current363, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan363(array_replace($current363, ['generated_path' => '$.rules[']), $current363)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next363 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
