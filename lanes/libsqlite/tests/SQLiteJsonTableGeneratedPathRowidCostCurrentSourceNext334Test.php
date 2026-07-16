<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current334 = [
    'option_id' => 334,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next334',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-334-a',
];
$next334 = [
    'option_id' => 334,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next334',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-334-b',
];

$plan334 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    334,
    $current ?? $current334,
    $next ?? $next334,
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

$changed334 = static fn (): array => $plan334();
$stable334 = static fn (): array => $plan334($current334, $current334);
$staleFingerprint334 = static function () use ($plan334, $current334): array {
    return $plan334($current334, $current334, null, null, str_repeat('0', 64), null);
};
$staleRowid334 = static fn (): array => $plan334($current334, $current334, null, null, null, 5);
$noOrder334 = static fn (): array => $plan334($current334, $current334, null, []);
$oidAlias334 = static fn (): array => $plan334($current334, $current334, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias334 = static fn (): array => $plan334($current334, $current334, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next334 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next334', $changed334()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed334()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next334', $changed334()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next334', $changed334()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next334', $stable334()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable334()['next334ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-334-a', $changed334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next334', $changed334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed334()['nextGeneratedPathRowidCurrentSourceCostSelection334']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed334()['nextGeneratedPathRowidCurrentSourceCostSelection334']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed334()['nextGeneratedPathRowidCurrentSourceCostSelection334']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed334()['nextGeneratedPathRowidCurrentSourceCostSelection334']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed334()['nextGeneratedPathRowidCurrentSourceCostSelection334']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed334()['nextGeneratedPathRowidCurrentSourceCostSelection334']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next334', $changed334()['nextGeneratedPathRowidCurrentSourceCostSelection334']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed334()['generatedPathRowidCurrentSourceCostSelection334Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed334()['generatedPathRowidCurrentSourceCostSelection334Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed334()['generatedPathRowidCurrentSourceCostSelection334Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed334()['generatedPathRowidCurrentSourceCostSelection334Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed334()['generatedPathRowidCurrentSourceCostSelection334Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next334', $changed334()['next334ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next334', $changed334()['next334ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next334', $changed334()['next334ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next334', $changed334()['next334ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next334', $changed334()['next334ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed334()['next334ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next334', $staleFingerprint334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next334', $staleRowid334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next334', $noOrder334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias334()['currentGeneratedPathRowidCurrentSourceCostSelection334']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan334($current334, $current334, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan334(array_replace($current334, ['generated_path' => '$.rules[']), $current334)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next334 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
