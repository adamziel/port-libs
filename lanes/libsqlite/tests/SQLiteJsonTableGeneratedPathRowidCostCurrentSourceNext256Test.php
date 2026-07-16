<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current256 = [
    'option_id' => 256,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next256',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-256-a',
];
$next256 = [
    'option_id' => 256,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next256',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-256-b',
];

$plan256 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    256,
    $current ?? $current256,
    $next ?? $next256,
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

$changed256 = static fn (): array => $plan256();
$stable256 = static fn (): array => $plan256($current256, $current256);
$staleFingerprint256 = static function () use ($plan256, $current256): array {
    return $plan256($current256, $current256, null, null, str_repeat('0', 64), null);
};
$staleRowid256 = static fn (): array => $plan256($current256, $current256, null, null, null, 5);
$noOrder256 = static fn (): array => $plan256($current256, $current256, null, []);
$oidAlias256 = static fn (): array => $plan256($current256, $current256, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias256 = static fn (): array => $plan256($current256, $current256, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next256 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next256', $changed256()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed256()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next256', $changed256()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next256', $changed256()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next256', $stable256()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable256()['next256ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-256-a', $changed256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next256', $changed256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed256()['nextGeneratedPathRowidCurrentSourceCostSelection256']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed256()['nextGeneratedPathRowidCurrentSourceCostSelection256']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed256()['nextGeneratedPathRowidCurrentSourceCostSelection256']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed256()['nextGeneratedPathRowidCurrentSourceCostSelection256']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed256()['nextGeneratedPathRowidCurrentSourceCostSelection256']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed256()['nextGeneratedPathRowidCurrentSourceCostSelection256']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next256', $changed256()['nextGeneratedPathRowidCurrentSourceCostSelection256']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed256()['generatedPathRowidCurrentSourceCostSelection256Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed256()['generatedPathRowidCurrentSourceCostSelection256Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed256()['generatedPathRowidCurrentSourceCostSelection256Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed256()['generatedPathRowidCurrentSourceCostSelection256Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed256()['generatedPathRowidCurrentSourceCostSelection256Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next256', $changed256()['next256ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next256', $changed256()['next256ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next256', $changed256()['next256ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next256', $changed256()['next256ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next256', $changed256()['next256ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed256()['next256ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next256', $staleFingerprint256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next256', $staleRowid256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next256', $noOrder256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias256()['currentGeneratedPathRowidCurrentSourceCostSelection256']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan256($current256, $current256, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan256(array_replace($current256, ['generated_path' => '$.rules[']), $current256)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next256 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
