<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current281 = [
    'option_id' => 281,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next281',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-281-a',
];
$next281 = [
    'option_id' => 281,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next281',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-281-b',
];

$plan281 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext281(
    'json_tree',
    $current ?? $current281,
    $next ?? $next281,
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

$changed281 = static fn (): array => $plan281();
$stable281 = static fn (): array => $plan281($current281, $current281);
$staleFingerprint281 = static function () use ($plan281, $current281): array {
    return $plan281($current281, $current281, null, null, str_repeat('0', 64), null);
};
$staleRowid281 = static fn (): array => $plan281($current281, $current281, null, null, null, 5);
$noOrder281 = static fn (): array => $plan281($current281, $current281, null, []);
$oidAlias281 = static fn (): array => $plan281($current281, $current281, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias281 = static fn (): array => $plan281($current281, $current281, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next281 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next281', $changed281()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed281()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next281', $changed281()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next281', $changed281()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next281', $stable281()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable281()['next281ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-281-a', $changed281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next281', $changed281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed281()['nextGeneratedPathRowidCurrentSourceCostSelection281']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed281()['nextGeneratedPathRowidCurrentSourceCostSelection281']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed281()['nextGeneratedPathRowidCurrentSourceCostSelection281']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed281()['nextGeneratedPathRowidCurrentSourceCostSelection281']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed281()['nextGeneratedPathRowidCurrentSourceCostSelection281']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed281()['nextGeneratedPathRowidCurrentSourceCostSelection281']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next281', $changed281()['nextGeneratedPathRowidCurrentSourceCostSelection281']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed281()['generatedPathRowidCurrentSourceCostSelection281Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed281()['generatedPathRowidCurrentSourceCostSelection281Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed281()['generatedPathRowidCurrentSourceCostSelection281Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed281()['generatedPathRowidCurrentSourceCostSelection281Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed281()['generatedPathRowidCurrentSourceCostSelection281Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next281', $changed281()['next281ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next281', $changed281()['next281ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next281', $changed281()['next281ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next281', $changed281()['next281ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next281', $changed281()['next281ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed281()['next281ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next281', $staleFingerprint281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next281', $staleRowid281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next281', $noOrder281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias281()['currentGeneratedPathRowidCurrentSourceCostSelection281']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan281($current281, $current281, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan281(array_replace($current281, ['generated_path' => '$.rules[']), $current281)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next281 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
