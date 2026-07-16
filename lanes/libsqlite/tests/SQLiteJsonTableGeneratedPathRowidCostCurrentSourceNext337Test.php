<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current337 = [
    'option_id' => 337,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next337',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-337-a',
];
$next337 = [
    'option_id' => 337,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next337',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-337-b',
];

$plan337 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    337,
    $current ?? $current337,
    $next ?? $next337,
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

$changed337 = static fn (): array => $plan337();
$stable337 = static fn (): array => $plan337($current337, $current337);
$staleFingerprint337 = static function () use ($plan337, $current337): array {
    return $plan337($current337, $current337, null, null, str_repeat('0', 64), null);
};
$staleRowid337 = static fn (): array => $plan337($current337, $current337, null, null, null, 5);
$noOrder337 = static fn (): array => $plan337($current337, $current337, null, []);
$oidAlias337 = static fn (): array => $plan337($current337, $current337, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias337 = static fn (): array => $plan337($current337, $current337, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next337 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next337', $changed337()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed337()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next337', $changed337()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next337', $changed337()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next337', $stable337()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable337()['next337ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-337-a', $changed337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next337', $changed337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed337()['nextGeneratedPathRowidCurrentSourceCostSelection337']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed337()['nextGeneratedPathRowidCurrentSourceCostSelection337']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed337()['nextGeneratedPathRowidCurrentSourceCostSelection337']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed337()['nextGeneratedPathRowidCurrentSourceCostSelection337']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed337()['nextGeneratedPathRowidCurrentSourceCostSelection337']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed337()['nextGeneratedPathRowidCurrentSourceCostSelection337']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next337', $changed337()['nextGeneratedPathRowidCurrentSourceCostSelection337']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed337()['generatedPathRowidCurrentSourceCostSelection337Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed337()['generatedPathRowidCurrentSourceCostSelection337Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed337()['generatedPathRowidCurrentSourceCostSelection337Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed337()['generatedPathRowidCurrentSourceCostSelection337Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed337()['generatedPathRowidCurrentSourceCostSelection337Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next337', $changed337()['next337ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next337', $changed337()['next337ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next337', $changed337()['next337ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next337', $changed337()['next337ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next337', $changed337()['next337ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed337()['next337ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next337', $staleFingerprint337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next337', $staleRowid337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next337', $noOrder337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias337()['currentGeneratedPathRowidCurrentSourceCostSelection337']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan337($current337, $current337, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan337(array_replace($current337, ['generated_path' => '$.rules[']), $current337)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next337 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
