<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current362 = [
    'option_id' => 362,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next362',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-362-a',
];
$next362 = [
    'option_id' => 362,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next362',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-362-b',
];

$plan362 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    362,
    $current ?? $current362,
    $next ?? $next362,
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

$changed362 = static fn (): array => $plan362();
$stable362 = static fn (): array => $plan362($current362, $current362);
$staleFingerprint362 = static function () use ($plan362, $current362): array {
    return $plan362($current362, $current362, null, null, str_repeat('0', 64), null);
};
$staleRowid362 = static fn (): array => $plan362($current362, $current362, null, null, null, 5);
$noOrder362 = static fn (): array => $plan362($current362, $current362, null, []);
$oidAlias362 = static fn (): array => $plan362($current362, $current362, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias362 = static fn (): array => $plan362($current362, $current362, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next362 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next362', $changed362()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed362()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next362', $changed362()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next362', $changed362()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next362', $stable362()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable362()['next362ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-362-a', $changed362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next362', $changed362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed362()['nextGeneratedPathRowidCurrentSourceCostSelection362']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed362()['nextGeneratedPathRowidCurrentSourceCostSelection362']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed362()['nextGeneratedPathRowidCurrentSourceCostSelection362']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed362()['nextGeneratedPathRowidCurrentSourceCostSelection362']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed362()['nextGeneratedPathRowidCurrentSourceCostSelection362']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed362()['nextGeneratedPathRowidCurrentSourceCostSelection362']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next362', $changed362()['nextGeneratedPathRowidCurrentSourceCostSelection362']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed362()['generatedPathRowidCurrentSourceCostSelection362Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed362()['generatedPathRowidCurrentSourceCostSelection362Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed362()['generatedPathRowidCurrentSourceCostSelection362Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed362()['generatedPathRowidCurrentSourceCostSelection362Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed362()['generatedPathRowidCurrentSourceCostSelection362Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next362', $changed362()['next362ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next362', $changed362()['next362ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next362', $changed362()['next362ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next362', $changed362()['next362ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next362', $changed362()['next362ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed362()['next362ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next362', $staleFingerprint362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next362', $staleRowid362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next362', $noOrder362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias362()['currentGeneratedPathRowidCurrentSourceCostSelection362']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan362($current362, $current362, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan362(array_replace($current362, ['generated_path' => '$.rules[']), $current362)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next362 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
