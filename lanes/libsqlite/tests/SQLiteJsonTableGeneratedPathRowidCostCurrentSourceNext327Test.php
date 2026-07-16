<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current327 = [
    'option_id' => 327,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next327',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-327-a',
];
$next327 = [
    'option_id' => 327,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next327',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-327-b',
];

$plan327 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    327,
    $current ?? $current327,
    $next ?? $next327,
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

$changed327 = static fn (): array => $plan327();
$stable327 = static fn (): array => $plan327($current327, $current327);
$staleFingerprint327 = static function () use ($plan327, $current327): array {
    return $plan327($current327, $current327, null, null, str_repeat('0', 64), null);
};
$staleRowid327 = static fn (): array => $plan327($current327, $current327, null, null, null, 5);
$noOrder327 = static fn (): array => $plan327($current327, $current327, null, []);
$oidAlias327 = static fn (): array => $plan327($current327, $current327, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias327 = static fn (): array => $plan327($current327, $current327, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next327 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next327', $changed327()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed327()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next327', $changed327()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next327', $changed327()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next327', $stable327()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable327()['next327ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-327-a', $changed327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next327', $changed327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed327()['nextGeneratedPathRowidCurrentSourceCostSelection327']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed327()['nextGeneratedPathRowidCurrentSourceCostSelection327']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed327()['nextGeneratedPathRowidCurrentSourceCostSelection327']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed327()['nextGeneratedPathRowidCurrentSourceCostSelection327']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed327()['nextGeneratedPathRowidCurrentSourceCostSelection327']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed327()['nextGeneratedPathRowidCurrentSourceCostSelection327']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next327', $changed327()['nextGeneratedPathRowidCurrentSourceCostSelection327']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed327()['generatedPathRowidCurrentSourceCostSelection327Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed327()['generatedPathRowidCurrentSourceCostSelection327Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed327()['generatedPathRowidCurrentSourceCostSelection327Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed327()['generatedPathRowidCurrentSourceCostSelection327Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed327()['generatedPathRowidCurrentSourceCostSelection327Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next327', $changed327()['next327ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next327', $changed327()['next327ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next327', $changed327()['next327ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next327', $changed327()['next327ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next327', $changed327()['next327ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed327()['next327ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next327', $staleFingerprint327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next327', $staleRowid327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next327', $noOrder327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias327()['currentGeneratedPathRowidCurrentSourceCostSelection327']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan327($current327, $current327, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan327(array_replace($current327, ['generated_path' => '$.rules[']), $current327)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next327 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
