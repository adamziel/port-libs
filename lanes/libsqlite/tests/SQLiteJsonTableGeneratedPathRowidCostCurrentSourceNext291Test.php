<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current291 = [
    'option_id' => 291,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next291',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-291-a',
];
$next291 = [
    'option_id' => 291,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next291',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-291-b',
];

$plan291 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionVariant(291,
    'json_tree',
    $current ?? $current291,
    $next ?? $next291,
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

$changed291 = static fn (): array => $plan291();
$stable291 = static fn (): array => $plan291($current291, $current291);
$staleFingerprint291 = static function () use ($plan291, $current291): array {
    return $plan291($current291, $current291, null, null, str_repeat('0', 64), null);
};
$staleRowid291 = static fn (): array => $plan291($current291, $current291, null, null, null, 5);
$noOrder291 = static fn (): array => $plan291($current291, $current291, null, []);
$oidAlias291 = static fn (): array => $plan291($current291, $current291, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias291 = static fn (): array => $plan291($current291, $current291, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next291 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next291', $changed291()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed291()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next291', $changed291()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next291', $changed291()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next291', $stable291()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable291()['next291ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-291-a', $changed291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next291', $changed291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed291()['nextGeneratedPathRowidCurrentSourceCostSelection291']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed291()['nextGeneratedPathRowidCurrentSourceCostSelection291']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed291()['nextGeneratedPathRowidCurrentSourceCostSelection291']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed291()['nextGeneratedPathRowidCurrentSourceCostSelection291']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed291()['nextGeneratedPathRowidCurrentSourceCostSelection291']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed291()['nextGeneratedPathRowidCurrentSourceCostSelection291']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next291', $changed291()['nextGeneratedPathRowidCurrentSourceCostSelection291']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed291()['generatedPathRowidCurrentSourceCostSelection291Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed291()['generatedPathRowidCurrentSourceCostSelection291Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed291()['generatedPathRowidCurrentSourceCostSelection291Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed291()['generatedPathRowidCurrentSourceCostSelection291Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed291()['generatedPathRowidCurrentSourceCostSelection291Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next291', $changed291()['next291ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next291', $changed291()['next291ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next291', $changed291()['next291ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next291', $changed291()['next291ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next291', $changed291()['next291ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed291()['next291ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next291', $staleFingerprint291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next291', $staleRowid291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next291', $noOrder291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias291()['currentGeneratedPathRowidCurrentSourceCostSelection291']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan291($current291, $current291, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan291(array_replace($current291, ['generated_path' => '$.rules[']), $current291)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next291 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
