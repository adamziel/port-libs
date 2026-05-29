<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current236 = [
    'option_id' => 236,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next236',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-236-a',
];
$next236 = [
    'option_id' => 236,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next236',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-236-b',
];

$plan236 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext236(
    'json_tree',
    $current ?? $current236,
    $next ?? $next236,
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

$changed236 = static fn (): array => $plan236();
$stable236 = static fn (): array => $plan236($current236, $current236);
$staleFingerprint236 = static function () use ($plan236, $current236): array {
    return $plan236($current236, $current236, null, null, str_repeat('0', 64), null);
};
$staleRowid236 = static fn (): array => $plan236($current236, $current236, null, null, null, 5);
$noOrder236 = static fn (): array => $plan236($current236, $current236, null, []);
$oidAlias236 = static fn (): array => $plan236($current236, $current236, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias236 = static fn (): array => $plan236($current236, $current236, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next236 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next236', $changed236()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed236()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next236', $changed236()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next236', $changed236()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next236', $stable236()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable236()['next236ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-236-a', $changed236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next236', $changed236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed236()['nextGeneratedPathRowidCurrentSourceCostSelection236']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed236()['nextGeneratedPathRowidCurrentSourceCostSelection236']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed236()['nextGeneratedPathRowidCurrentSourceCostSelection236']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed236()['nextGeneratedPathRowidCurrentSourceCostSelection236']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed236()['nextGeneratedPathRowidCurrentSourceCostSelection236']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed236()['nextGeneratedPathRowidCurrentSourceCostSelection236']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next236', $changed236()['nextGeneratedPathRowidCurrentSourceCostSelection236']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed236()['generatedPathRowidCurrentSourceCostSelection236Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed236()['generatedPathRowidCurrentSourceCostSelection236Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed236()['generatedPathRowidCurrentSourceCostSelection236Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed236()['generatedPathRowidCurrentSourceCostSelection236Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed236()['generatedPathRowidCurrentSourceCostSelection236Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next236', $changed236()['next236ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next236', $changed236()['next236ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next236', $changed236()['next236ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next236', $changed236()['next236ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next236', $changed236()['next236ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed236()['next236ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next236', $staleFingerprint236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next236', $staleRowid236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next236', $noOrder236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias236()['currentGeneratedPathRowidCurrentSourceCostSelection236']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan236($current236, $current236, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan236(array_replace($current236, ['generated_path' => '$.rules[']), $current236)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next236 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
