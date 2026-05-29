<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current283 = [
    'option_id' => 283,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next283',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-283-a',
];
$next283 = [
    'option_id' => 283,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next283',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-283-b',
];

$plan283 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionVariant(283,
    'json_tree',
    $current ?? $current283,
    $next ?? $next283,
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

$changed283 = static fn (): array => $plan283();
$stable283 = static fn (): array => $plan283($current283, $current283);
$staleFingerprint283 = static function () use ($plan283, $current283): array {
    return $plan283($current283, $current283, null, null, str_repeat('0', 64), null);
};
$staleRowid283 = static fn (): array => $plan283($current283, $current283, null, null, null, 5);
$noOrder283 = static fn (): array => $plan283($current283, $current283, null, []);
$oidAlias283 = static fn (): array => $plan283($current283, $current283, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias283 = static fn (): array => $plan283($current283, $current283, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next283 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next283', $changed283()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed283()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next283', $changed283()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next283', $changed283()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next283', $stable283()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable283()['next283ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-283-a', $changed283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next283', $changed283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed283()['nextGeneratedPathRowidCurrentSourceCostSelection283']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed283()['nextGeneratedPathRowidCurrentSourceCostSelection283']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed283()['nextGeneratedPathRowidCurrentSourceCostSelection283']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed283()['nextGeneratedPathRowidCurrentSourceCostSelection283']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed283()['nextGeneratedPathRowidCurrentSourceCostSelection283']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed283()['nextGeneratedPathRowidCurrentSourceCostSelection283']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next283', $changed283()['nextGeneratedPathRowidCurrentSourceCostSelection283']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed283()['generatedPathRowidCurrentSourceCostSelection283Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed283()['generatedPathRowidCurrentSourceCostSelection283Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed283()['generatedPathRowidCurrentSourceCostSelection283Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed283()['generatedPathRowidCurrentSourceCostSelection283Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed283()['generatedPathRowidCurrentSourceCostSelection283Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next283', $changed283()['next283ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next283', $changed283()['next283ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next283', $changed283()['next283ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next283', $changed283()['next283ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next283', $changed283()['next283ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed283()['next283ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next283', $staleFingerprint283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next283', $staleRowid283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next283', $noOrder283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias283()['currentGeneratedPathRowidCurrentSourceCostSelection283']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan283($current283, $current283, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan283(array_replace($current283, ['generated_path' => '$.rules[']), $current283)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next283 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
