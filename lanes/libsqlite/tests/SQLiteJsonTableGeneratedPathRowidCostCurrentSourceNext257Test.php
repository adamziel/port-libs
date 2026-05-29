<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current257 = [
    'option_id' => 257,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next257',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-257-a',
];
$next257 = [
    'option_id' => 257,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next257',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-257-b',
];

$plan257 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext257(
    'json_tree',
    $current ?? $current257,
    $next ?? $next257,
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

$changed257 = static fn (): array => $plan257();
$stable257 = static fn (): array => $plan257($current257, $current257);
$staleFingerprint257 = static function () use ($plan257, $current257): array {
    return $plan257($current257, $current257, null, null, str_repeat('0', 64), null);
};
$staleRowid257 = static fn (): array => $plan257($current257, $current257, null, null, null, 5);
$noOrder257 = static fn (): array => $plan257($current257, $current257, null, []);
$oidAlias257 = static fn (): array => $plan257($current257, $current257, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias257 = static fn (): array => $plan257($current257, $current257, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next257 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next257', $changed257()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed257()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next257', $changed257()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next257', $changed257()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next257', $stable257()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable257()['next257ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-257-a', $changed257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next257', $changed257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed257()['nextGeneratedPathRowidCurrentSourceCostSelection257']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed257()['nextGeneratedPathRowidCurrentSourceCostSelection257']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed257()['nextGeneratedPathRowidCurrentSourceCostSelection257']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed257()['nextGeneratedPathRowidCurrentSourceCostSelection257']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed257()['nextGeneratedPathRowidCurrentSourceCostSelection257']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed257()['nextGeneratedPathRowidCurrentSourceCostSelection257']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next257', $changed257()['nextGeneratedPathRowidCurrentSourceCostSelection257']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed257()['generatedPathRowidCurrentSourceCostSelection257Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed257()['generatedPathRowidCurrentSourceCostSelection257Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed257()['generatedPathRowidCurrentSourceCostSelection257Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed257()['generatedPathRowidCurrentSourceCostSelection257Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed257()['generatedPathRowidCurrentSourceCostSelection257Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next257', $changed257()['next257ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next257', $changed257()['next257ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next257', $changed257()['next257ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next257', $changed257()['next257ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next257', $changed257()['next257ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed257()['next257ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next257', $staleFingerprint257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next257', $staleRowid257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next257', $noOrder257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias257()['currentGeneratedPathRowidCurrentSourceCostSelection257']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan257($current257, $current257, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan257(array_replace($current257, ['generated_path' => '$.rules[']), $current257)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next257 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
