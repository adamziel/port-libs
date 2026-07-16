<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current310 = [
    'option_id' => 310,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next310',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-310-a',
];
$next310 = [
    'option_id' => 310,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next310',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-310-b',
];

$plan310 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    310,
    $current ?? $current310,
    $next ?? $next310,
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

$changed310 = static fn (): array => $plan310();
$stable310 = static fn (): array => $plan310($current310, $current310);
$staleFingerprint310 = static function () use ($plan310, $current310): array {
    return $plan310($current310, $current310, null, null, str_repeat('0', 64), null);
};
$staleRowid310 = static fn (): array => $plan310($current310, $current310, null, null, null, 5);
$noOrder310 = static fn (): array => $plan310($current310, $current310, null, []);
$oidAlias310 = static fn (): array => $plan310($current310, $current310, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias310 = static fn (): array => $plan310($current310, $current310, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next310 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next310', $changed310()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed310()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next310', $changed310()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next310', $changed310()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next310', $stable310()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable310()['next310ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-310-a', $changed310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next310', $changed310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed310()['nextGeneratedPathRowidCurrentSourceCostSelection310']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed310()['nextGeneratedPathRowidCurrentSourceCostSelection310']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed310()['nextGeneratedPathRowidCurrentSourceCostSelection310']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed310()['nextGeneratedPathRowidCurrentSourceCostSelection310']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed310()['nextGeneratedPathRowidCurrentSourceCostSelection310']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed310()['nextGeneratedPathRowidCurrentSourceCostSelection310']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next310', $changed310()['nextGeneratedPathRowidCurrentSourceCostSelection310']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed310()['generatedPathRowidCurrentSourceCostSelection310Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed310()['generatedPathRowidCurrentSourceCostSelection310Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed310()['generatedPathRowidCurrentSourceCostSelection310Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed310()['generatedPathRowidCurrentSourceCostSelection310Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed310()['generatedPathRowidCurrentSourceCostSelection310Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next310', $changed310()['next310ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next310', $changed310()['next310ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next310', $changed310()['next310ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next310', $changed310()['next310ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next310', $changed310()['next310ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed310()['next310ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next310', $staleFingerprint310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next310', $staleRowid310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next310', $noOrder310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias310()['currentGeneratedPathRowidCurrentSourceCostSelection310']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan310($current310, $current310, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan310(array_replace($current310, ['generated_path' => '$.rules[']), $current310)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next310 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
