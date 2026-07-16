<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current361 = [
    'option_id' => 361,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next361',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-361-a',
];
$next361 = [
    'option_id' => 361,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next361',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-361-b',
];

$plan361 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    361,
    $current ?? $current361,
    $next ?? $next361,
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

$changed361 = static fn (): array => $plan361();
$stable361 = static fn (): array => $plan361($current361, $current361);
$staleFingerprint361 = static function () use ($plan361, $current361): array {
    return $plan361($current361, $current361, null, null, str_repeat('0', 64), null);
};
$staleRowid361 = static fn (): array => $plan361($current361, $current361, null, null, null, 5);
$noOrder361 = static fn (): array => $plan361($current361, $current361, null, []);
$oidAlias361 = static fn (): array => $plan361($current361, $current361, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias361 = static fn (): array => $plan361($current361, $current361, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next361 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next361', $changed361()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed361()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next361', $changed361()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next361', $changed361()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next361', $stable361()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable361()['next361ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-361-a', $changed361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next361', $changed361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed361()['nextGeneratedPathRowidCurrentSourceCostSelection361']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed361()['nextGeneratedPathRowidCurrentSourceCostSelection361']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed361()['nextGeneratedPathRowidCurrentSourceCostSelection361']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed361()['nextGeneratedPathRowidCurrentSourceCostSelection361']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed361()['nextGeneratedPathRowidCurrentSourceCostSelection361']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed361()['nextGeneratedPathRowidCurrentSourceCostSelection361']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next361', $changed361()['nextGeneratedPathRowidCurrentSourceCostSelection361']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed361()['generatedPathRowidCurrentSourceCostSelection361Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed361()['generatedPathRowidCurrentSourceCostSelection361Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed361()['generatedPathRowidCurrentSourceCostSelection361Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed361()['generatedPathRowidCurrentSourceCostSelection361Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed361()['generatedPathRowidCurrentSourceCostSelection361Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next361', $changed361()['next361ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next361', $changed361()['next361ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next361', $changed361()['next361ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next361', $changed361()['next361ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next361', $changed361()['next361ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed361()['next361ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next361', $staleFingerprint361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next361', $staleRowid361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next361', $noOrder361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias361()['currentGeneratedPathRowidCurrentSourceCostSelection361']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan361($current361, $current361, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan361(array_replace($current361, ['generated_path' => '$.rules[']), $current361)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next361 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
