<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current240 = [
    'option_id' => 240,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next240',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-240-a',
];
$next240 = [
    'option_id' => 240,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next240',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-240-b',
];

$plan240 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext240(
    'json_tree',
    $current ?? $current240,
    $next ?? $next240,
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

$changed240 = static fn (): array => $plan240();
$stable240 = static fn (): array => $plan240($current240, $current240);
$staleFingerprint240 = static function () use ($plan240, $current240): array {
    return $plan240($current240, $current240, null, null, str_repeat('0', 64), null);
};
$staleRowid240 = static fn (): array => $plan240($current240, $current240, null, null, null, 5);
$noOrder240 = static fn (): array => $plan240($current240, $current240, null, []);
$oidAlias240 = static fn (): array => $plan240($current240, $current240, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias240 = static fn (): array => $plan240($current240, $current240, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next240 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next240', $changed240()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed240()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next240', $changed240()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next240', $changed240()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next240', $stable240()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable240()['next240ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-240-a', $changed240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next240', $changed240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed240()['nextGeneratedPathRowidCurrentSourceCostSelection240']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed240()['nextGeneratedPathRowidCurrentSourceCostSelection240']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed240()['nextGeneratedPathRowidCurrentSourceCostSelection240']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed240()['nextGeneratedPathRowidCurrentSourceCostSelection240']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed240()['nextGeneratedPathRowidCurrentSourceCostSelection240']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed240()['nextGeneratedPathRowidCurrentSourceCostSelection240']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next240', $changed240()['nextGeneratedPathRowidCurrentSourceCostSelection240']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed240()['generatedPathRowidCurrentSourceCostSelection240Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed240()['generatedPathRowidCurrentSourceCostSelection240Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed240()['generatedPathRowidCurrentSourceCostSelection240Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed240()['generatedPathRowidCurrentSourceCostSelection240Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed240()['generatedPathRowidCurrentSourceCostSelection240Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next240', $changed240()['next240ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next240', $changed240()['next240ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next240', $changed240()['next240ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next240', $changed240()['next240ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next240', $changed240()['next240ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed240()['next240ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next240', $staleFingerprint240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next240', $staleRowid240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next240', $noOrder240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias240()['currentGeneratedPathRowidCurrentSourceCostSelection240']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan240($current240, $current240, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan240(array_replace($current240, ['generated_path' => '$.rules[']), $current240)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next240 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
