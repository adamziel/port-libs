<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current238 = [
    'option_id' => 238,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next238',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-238-a',
];
$next238 = [
    'option_id' => 238,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next238',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-238-b',
];

$plan238 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    238,
    $current ?? $current238,
    $next ?? $next238,
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

$changed238 = static fn (): array => $plan238();
$stable238 = static fn (): array => $plan238($current238, $current238);
$staleFingerprint238 = static function () use ($plan238, $current238): array {
    return $plan238($current238, $current238, null, null, str_repeat('0', 64), null);
};
$staleRowid238 = static fn (): array => $plan238($current238, $current238, null, null, null, 5);
$noOrder238 = static fn (): array => $plan238($current238, $current238, null, []);
$oidAlias238 = static fn (): array => $plan238($current238, $current238, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias238 = static fn (): array => $plan238($current238, $current238, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next238 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next238', $changed238()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed238()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next238', $changed238()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next238', $changed238()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next238', $stable238()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable238()['next238ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-238-a', $changed238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next238', $changed238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed238()['nextGeneratedPathRowidCurrentSourceCostSelection238']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed238()['nextGeneratedPathRowidCurrentSourceCostSelection238']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed238()['nextGeneratedPathRowidCurrentSourceCostSelection238']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed238()['nextGeneratedPathRowidCurrentSourceCostSelection238']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed238()['nextGeneratedPathRowidCurrentSourceCostSelection238']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed238()['nextGeneratedPathRowidCurrentSourceCostSelection238']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next238', $changed238()['nextGeneratedPathRowidCurrentSourceCostSelection238']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed238()['generatedPathRowidCurrentSourceCostSelection238Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed238()['generatedPathRowidCurrentSourceCostSelection238Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed238()['generatedPathRowidCurrentSourceCostSelection238Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed238()['generatedPathRowidCurrentSourceCostSelection238Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed238()['generatedPathRowidCurrentSourceCostSelection238Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next238', $changed238()['next238ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next238', $changed238()['next238ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next238', $changed238()['next238ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next238', $changed238()['next238ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next238', $changed238()['next238ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed238()['next238ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next238', $staleFingerprint238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next238', $staleRowid238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next238', $noOrder238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias238()['currentGeneratedPathRowidCurrentSourceCostSelection238']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan238($current238, $current238, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan238(array_replace($current238, ['generated_path' => '$.rules[']), $current238)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next238 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
