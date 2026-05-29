<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current352 = [
    'option_id' => 352,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next352',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-352-a',
];
$next352 = [
    'option_id' => 352,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next352',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-352-b',
];

$plan352 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext352(
    'json_tree',
    $current ?? $current352,
    $next ?? $next352,
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

$changed352 = static fn (): array => $plan352();
$stable352 = static fn (): array => $plan352($current352, $current352);
$staleFingerprint352 = static function () use ($plan352, $current352): array {
    return $plan352($current352, $current352, null, null, str_repeat('0', 64), null);
};
$staleRowid352 = static fn (): array => $plan352($current352, $current352, null, null, null, 5);
$noOrder352 = static fn (): array => $plan352($current352, $current352, null, []);
$oidAlias352 = static fn (): array => $plan352($current352, $current352, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias352 = static fn (): array => $plan352($current352, $current352, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next352 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next352', $changed352()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed352()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next352', $changed352()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next352', $changed352()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next352', $stable352()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable352()['next352ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-352-a', $changed352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next352', $changed352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed352()['nextGeneratedPathRowidCurrentSourceCostSelection352']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed352()['nextGeneratedPathRowidCurrentSourceCostSelection352']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed352()['nextGeneratedPathRowidCurrentSourceCostSelection352']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed352()['nextGeneratedPathRowidCurrentSourceCostSelection352']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed352()['nextGeneratedPathRowidCurrentSourceCostSelection352']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed352()['nextGeneratedPathRowidCurrentSourceCostSelection352']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next352', $changed352()['nextGeneratedPathRowidCurrentSourceCostSelection352']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed352()['generatedPathRowidCurrentSourceCostSelection352Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed352()['generatedPathRowidCurrentSourceCostSelection352Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed352()['generatedPathRowidCurrentSourceCostSelection352Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed352()['generatedPathRowidCurrentSourceCostSelection352Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed352()['generatedPathRowidCurrentSourceCostSelection352Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next352', $changed352()['next352ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next352', $changed352()['next352ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next352', $changed352()['next352ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next352', $changed352()['next352ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next352', $changed352()['next352ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed352()['next352ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next352', $staleFingerprint352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next352', $staleRowid352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next352', $noOrder352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias352()['currentGeneratedPathRowidCurrentSourceCostSelection352']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan352($current352, $current352, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan352(array_replace($current352, ['generated_path' => '$.rules[']), $current352)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next352 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
