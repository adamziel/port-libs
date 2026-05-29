<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current296 = [
    'option_id' => 296,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next296',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-296-a',
];
$next296 = [
    'option_id' => 296,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next296',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-296-b',
];

$plan296 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    296,
    $current ?? $current296,
    $next ?? $next296,
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

$changed296 = static fn (): array => $plan296();
$stable296 = static fn (): array => $plan296($current296, $current296);
$staleFingerprint296 = static function () use ($plan296, $current296): array {
    return $plan296($current296, $current296, null, null, str_repeat('0', 64), null);
};
$staleRowid296 = static fn (): array => $plan296($current296, $current296, null, null, null, 5);
$noOrder296 = static fn (): array => $plan296($current296, $current296, null, []);
$oidAlias296 = static fn (): array => $plan296($current296, $current296, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias296 = static fn (): array => $plan296($current296, $current296, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next296 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next296', $changed296()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed296()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next296', $changed296()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next296', $changed296()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next296', $stable296()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable296()['next296ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-296-a', $changed296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next296', $changed296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed296()['nextGeneratedPathRowidCurrentSourceCostSelection296']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed296()['nextGeneratedPathRowidCurrentSourceCostSelection296']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed296()['nextGeneratedPathRowidCurrentSourceCostSelection296']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed296()['nextGeneratedPathRowidCurrentSourceCostSelection296']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed296()['nextGeneratedPathRowidCurrentSourceCostSelection296']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed296()['nextGeneratedPathRowidCurrentSourceCostSelection296']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next296', $changed296()['nextGeneratedPathRowidCurrentSourceCostSelection296']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed296()['generatedPathRowidCurrentSourceCostSelection296Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed296()['generatedPathRowidCurrentSourceCostSelection296Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed296()['generatedPathRowidCurrentSourceCostSelection296Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed296()['generatedPathRowidCurrentSourceCostSelection296Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed296()['generatedPathRowidCurrentSourceCostSelection296Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next296', $changed296()['next296ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next296', $changed296()['next296ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next296', $changed296()['next296ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next296', $changed296()['next296ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next296', $changed296()['next296ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed296()['next296ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next296', $staleFingerprint296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next296', $staleRowid296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next296', $noOrder296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias296()['currentGeneratedPathRowidCurrentSourceCostSelection296']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan296($current296, $current296, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan296(array_replace($current296, ['generated_path' => '$.rules[']), $current296)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next296 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
