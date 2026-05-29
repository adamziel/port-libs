<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current351 = [
    'option_id' => 351,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next351',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-351-a',
];
$next351 = [
    'option_id' => 351,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next351',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-351-b',
];

$plan351 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext351(
    'json_tree',
    $current ?? $current351,
    $next ?? $next351,
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

$changed351 = static fn (): array => $plan351();
$stable351 = static fn (): array => $plan351($current351, $current351);
$staleFingerprint351 = static function () use ($plan351, $current351): array {
    return $plan351($current351, $current351, null, null, str_repeat('0', 64), null);
};
$staleRowid351 = static fn (): array => $plan351($current351, $current351, null, null, null, 5);
$noOrder351 = static fn (): array => $plan351($current351, $current351, null, []);
$oidAlias351 = static fn (): array => $plan351($current351, $current351, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias351 = static fn (): array => $plan351($current351, $current351, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next351 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next351', $changed351()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed351()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next351', $changed351()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next351', $changed351()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next351', $stable351()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable351()['next351ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-351-a', $changed351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next351', $changed351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed351()['nextGeneratedPathRowidCurrentSourceCostSelection351']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed351()['nextGeneratedPathRowidCurrentSourceCostSelection351']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed351()['nextGeneratedPathRowidCurrentSourceCostSelection351']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed351()['nextGeneratedPathRowidCurrentSourceCostSelection351']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed351()['nextGeneratedPathRowidCurrentSourceCostSelection351']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed351()['nextGeneratedPathRowidCurrentSourceCostSelection351']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next351', $changed351()['nextGeneratedPathRowidCurrentSourceCostSelection351']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed351()['generatedPathRowidCurrentSourceCostSelection351Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed351()['generatedPathRowidCurrentSourceCostSelection351Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed351()['generatedPathRowidCurrentSourceCostSelection351Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed351()['generatedPathRowidCurrentSourceCostSelection351Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed351()['generatedPathRowidCurrentSourceCostSelection351Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next351', $changed351()['next351ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next351', $changed351()['next351ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next351', $changed351()['next351ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next351', $changed351()['next351ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next351', $changed351()['next351ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed351()['next351ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next351', $staleFingerprint351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next351', $staleRowid351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next351', $noOrder351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias351()['currentGeneratedPathRowidCurrentSourceCostSelection351']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan351($current351, $current351, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan351(array_replace($current351, ['generated_path' => '$.rules[']), $current351)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next351 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
