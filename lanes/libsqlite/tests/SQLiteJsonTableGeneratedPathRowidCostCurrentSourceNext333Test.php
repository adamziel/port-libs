<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current333 = [
    'option_id' => 333,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next333',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-333-a',
];
$next333 = [
    'option_id' => 333,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next333',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-333-b',
];

$plan333 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    333,
    $current ?? $current333,
    $next ?? $next333,
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

$changed333 = static fn (): array => $plan333();
$stable333 = static fn (): array => $plan333($current333, $current333);
$staleFingerprint333 = static function () use ($plan333, $current333): array {
    return $plan333($current333, $current333, null, null, str_repeat('0', 64), null);
};
$staleRowid333 = static fn (): array => $plan333($current333, $current333, null, null, null, 5);
$noOrder333 = static fn (): array => $plan333($current333, $current333, null, []);
$oidAlias333 = static fn (): array => $plan333($current333, $current333, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias333 = static fn (): array => $plan333($current333, $current333, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next333 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next333', $changed333()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed333()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next333', $changed333()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next333', $changed333()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next333', $stable333()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable333()['next333ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-333-a', $changed333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next333', $changed333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed333()['nextGeneratedPathRowidCurrentSourceCostSelection333']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed333()['nextGeneratedPathRowidCurrentSourceCostSelection333']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed333()['nextGeneratedPathRowidCurrentSourceCostSelection333']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed333()['nextGeneratedPathRowidCurrentSourceCostSelection333']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed333()['nextGeneratedPathRowidCurrentSourceCostSelection333']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed333()['nextGeneratedPathRowidCurrentSourceCostSelection333']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next333', $changed333()['nextGeneratedPathRowidCurrentSourceCostSelection333']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed333()['generatedPathRowidCurrentSourceCostSelection333Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed333()['generatedPathRowidCurrentSourceCostSelection333Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed333()['generatedPathRowidCurrentSourceCostSelection333Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed333()['generatedPathRowidCurrentSourceCostSelection333Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed333()['generatedPathRowidCurrentSourceCostSelection333Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next333', $changed333()['next333ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next333', $changed333()['next333ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next333', $changed333()['next333ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next333', $changed333()['next333ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next333', $changed333()['next333ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed333()['next333ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next333', $staleFingerprint333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next333', $staleRowid333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next333', $noOrder333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias333()['currentGeneratedPathRowidCurrentSourceCostSelection333']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan333($current333, $current333, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan333(array_replace($current333, ['generated_path' => '$.rules[']), $current333)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next333 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
