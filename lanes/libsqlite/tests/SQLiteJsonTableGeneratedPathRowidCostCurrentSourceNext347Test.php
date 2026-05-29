<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current347 = [
    'option_id' => 347,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next347',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-347-a',
];
$next347 = [
    'option_id' => 347,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next347',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-347-b',
];

$plan347 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext347(
    'json_tree',
    $current ?? $current347,
    $next ?? $next347,
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

$changed347 = static fn (): array => $plan347();
$stable347 = static fn (): array => $plan347($current347, $current347);
$staleFingerprint347 = static function () use ($plan347, $current347): array {
    return $plan347($current347, $current347, null, null, str_repeat('0', 64), null);
};
$staleRowid347 = static fn (): array => $plan347($current347, $current347, null, null, null, 5);
$noOrder347 = static fn (): array => $plan347($current347, $current347, null, []);
$oidAlias347 = static fn (): array => $plan347($current347, $current347, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias347 = static fn (): array => $plan347($current347, $current347, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next347 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next347', $changed347()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed347()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next347', $changed347()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next347', $changed347()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next347', $stable347()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable347()['next347ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-347-a', $changed347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next347', $changed347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed347()['nextGeneratedPathRowidCurrentSourceCostSelection347']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed347()['nextGeneratedPathRowidCurrentSourceCostSelection347']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed347()['nextGeneratedPathRowidCurrentSourceCostSelection347']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed347()['nextGeneratedPathRowidCurrentSourceCostSelection347']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed347()['nextGeneratedPathRowidCurrentSourceCostSelection347']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed347()['nextGeneratedPathRowidCurrentSourceCostSelection347']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next347', $changed347()['nextGeneratedPathRowidCurrentSourceCostSelection347']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed347()['generatedPathRowidCurrentSourceCostSelection347Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed347()['generatedPathRowidCurrentSourceCostSelection347Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed347()['generatedPathRowidCurrentSourceCostSelection347Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed347()['generatedPathRowidCurrentSourceCostSelection347Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed347()['generatedPathRowidCurrentSourceCostSelection347Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next347', $changed347()['next347ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next347', $changed347()['next347ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next347', $changed347()['next347ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next347', $changed347()['next347ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next347', $changed347()['next347ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed347()['next347ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next347', $staleFingerprint347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next347', $staleRowid347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next347', $noOrder347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias347()['currentGeneratedPathRowidCurrentSourceCostSelection347']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan347($current347, $current347, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan347(array_replace($current347, ['generated_path' => '$.rules[']), $current347)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next347 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
