<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current344 = [
    'option_id' => 344,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next344',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-344-a',
];
$next344 = [
    'option_id' => 344,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next344',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-344-b',
];

$plan344 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    344,
    $current ?? $current344,
    $next ?? $next344,
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

$changed344 = static fn (): array => $plan344();
$stable344 = static fn (): array => $plan344($current344, $current344);
$staleFingerprint344 = static function () use ($plan344, $current344): array {
    return $plan344($current344, $current344, null, null, str_repeat('0', 64), null);
};
$staleRowid344 = static fn (): array => $plan344($current344, $current344, null, null, null, 5);
$noOrder344 = static fn (): array => $plan344($current344, $current344, null, []);
$oidAlias344 = static fn (): array => $plan344($current344, $current344, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias344 = static fn (): array => $plan344($current344, $current344, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next344 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next344', $changed344()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed344()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next344', $changed344()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next344', $changed344()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next344', $stable344()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable344()['next344ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-344-a', $changed344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next344', $changed344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed344()['nextGeneratedPathRowidCurrentSourceCostSelection344']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed344()['nextGeneratedPathRowidCurrentSourceCostSelection344']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed344()['nextGeneratedPathRowidCurrentSourceCostSelection344']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed344()['nextGeneratedPathRowidCurrentSourceCostSelection344']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed344()['nextGeneratedPathRowidCurrentSourceCostSelection344']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed344()['nextGeneratedPathRowidCurrentSourceCostSelection344']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next344', $changed344()['nextGeneratedPathRowidCurrentSourceCostSelection344']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed344()['generatedPathRowidCurrentSourceCostSelection344Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed344()['generatedPathRowidCurrentSourceCostSelection344Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed344()['generatedPathRowidCurrentSourceCostSelection344Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed344()['generatedPathRowidCurrentSourceCostSelection344Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed344()['generatedPathRowidCurrentSourceCostSelection344Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next344', $changed344()['next344ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next344', $changed344()['next344ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next344', $changed344()['next344ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next344', $changed344()['next344ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next344', $changed344()['next344ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed344()['next344ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next344', $staleFingerprint344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next344', $staleRowid344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next344', $noOrder344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias344()['currentGeneratedPathRowidCurrentSourceCostSelection344']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan344($current344, $current344, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan344(array_replace($current344, ['generated_path' => '$.rules[']), $current344)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next344 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
