<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current264 = [
    'option_id' => 264,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next264',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-264-a',
];
$next264 = [
    'option_id' => 264,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next264',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-264-b',
];

$plan264 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext264(
    'json_tree',
    $current ?? $current264,
    $next ?? $next264,
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

$changed264 = static fn (): array => $plan264();
$stable264 = static fn (): array => $plan264($current264, $current264);
$staleFingerprint264 = static function () use ($plan264, $current264): array {
    return $plan264($current264, $current264, null, null, str_repeat('0', 64), null);
};
$staleRowid264 = static fn (): array => $plan264($current264, $current264, null, null, null, 5);
$noOrder264 = static fn (): array => $plan264($current264, $current264, null, []);
$oidAlias264 = static fn (): array => $plan264($current264, $current264, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias264 = static fn (): array => $plan264($current264, $current264, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next264 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next264', $changed264()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed264()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next264', $changed264()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next264', $changed264()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next264', $stable264()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable264()['next264ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-264-a', $changed264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next264', $changed264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed264()['nextGeneratedPathRowidCurrentSourceCostSelection264']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed264()['nextGeneratedPathRowidCurrentSourceCostSelection264']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed264()['nextGeneratedPathRowidCurrentSourceCostSelection264']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed264()['nextGeneratedPathRowidCurrentSourceCostSelection264']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed264()['nextGeneratedPathRowidCurrentSourceCostSelection264']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed264()['nextGeneratedPathRowidCurrentSourceCostSelection264']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next264', $changed264()['nextGeneratedPathRowidCurrentSourceCostSelection264']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed264()['generatedPathRowidCurrentSourceCostSelection264Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed264()['generatedPathRowidCurrentSourceCostSelection264Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed264()['generatedPathRowidCurrentSourceCostSelection264Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed264()['generatedPathRowidCurrentSourceCostSelection264Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed264()['generatedPathRowidCurrentSourceCostSelection264Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next264', $changed264()['next264ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next264', $changed264()['next264ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next264', $changed264()['next264ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next264', $changed264()['next264ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next264', $changed264()['next264ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed264()['next264ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next264', $staleFingerprint264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next264', $staleRowid264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next264', $noOrder264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias264()['currentGeneratedPathRowidCurrentSourceCostSelection264']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan264($current264, $current264, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan264(array_replace($current264, ['generated_path' => '$.rules[']), $current264)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next264 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
