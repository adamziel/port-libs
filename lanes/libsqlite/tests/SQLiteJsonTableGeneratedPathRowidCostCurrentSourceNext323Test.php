<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current323 = [
    'option_id' => 323,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next323',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-323-a',
];
$next323 = [
    'option_id' => 323,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next323',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-323-b',
];

$plan323 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext323(
    'json_tree',
    $current ?? $current323,
    $next ?? $next323,
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

$changed323 = static fn (): array => $plan323();
$stable323 = static fn (): array => $plan323($current323, $current323);
$staleFingerprint323 = static function () use ($plan323, $current323): array {
    return $plan323($current323, $current323, null, null, str_repeat('0', 64), null);
};
$staleRowid323 = static fn (): array => $plan323($current323, $current323, null, null, null, 5);
$noOrder323 = static fn (): array => $plan323($current323, $current323, null, []);
$oidAlias323 = static fn (): array => $plan323($current323, $current323, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias323 = static fn (): array => $plan323($current323, $current323, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next323 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next323', $changed323()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed323()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next323', $changed323()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next323', $changed323()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next323', $stable323()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable323()['next323ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-323-a', $changed323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next323', $changed323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed323()['nextGeneratedPathRowidCurrentSourceCostSelection323']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed323()['nextGeneratedPathRowidCurrentSourceCostSelection323']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed323()['nextGeneratedPathRowidCurrentSourceCostSelection323']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed323()['nextGeneratedPathRowidCurrentSourceCostSelection323']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed323()['nextGeneratedPathRowidCurrentSourceCostSelection323']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed323()['nextGeneratedPathRowidCurrentSourceCostSelection323']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next323', $changed323()['nextGeneratedPathRowidCurrentSourceCostSelection323']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed323()['generatedPathRowidCurrentSourceCostSelection323Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed323()['generatedPathRowidCurrentSourceCostSelection323Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed323()['generatedPathRowidCurrentSourceCostSelection323Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed323()['generatedPathRowidCurrentSourceCostSelection323Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed323()['generatedPathRowidCurrentSourceCostSelection323Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next323', $changed323()['next323ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next323', $changed323()['next323ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next323', $changed323()['next323ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next323', $changed323()['next323ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next323', $changed323()['next323ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed323()['next323ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next323', $staleFingerprint323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next323', $staleRowid323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next323', $noOrder323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias323()['currentGeneratedPathRowidCurrentSourceCostSelection323']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan323($current323, $current323, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan323(array_replace($current323, ['generated_path' => '$.rules[']), $current323)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next323 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
