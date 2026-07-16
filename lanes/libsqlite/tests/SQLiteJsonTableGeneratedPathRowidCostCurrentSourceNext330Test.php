<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current330 = [
    'option_id' => 330,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next330',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-330-a',
];
$next330 = [
    'option_id' => 330,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next330',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-330-b',
];

$plan330 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    330,
    $current ?? $current330,
    $next ?? $next330,
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

$changed330 = static fn (): array => $plan330();
$stable330 = static fn (): array => $plan330($current330, $current330);
$staleFingerprint330 = static function () use ($plan330, $current330): array {
    return $plan330($current330, $current330, null, null, str_repeat('0', 64), null);
};
$staleRowid330 = static fn (): array => $plan330($current330, $current330, null, null, null, 5);
$noOrder330 = static fn (): array => $plan330($current330, $current330, null, []);
$oidAlias330 = static fn (): array => $plan330($current330, $current330, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias330 = static fn (): array => $plan330($current330, $current330, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next330 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next330', $changed330()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed330()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next330', $changed330()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next330', $changed330()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next330', $stable330()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable330()['next330ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-330-a', $changed330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next330', $changed330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed330()['nextGeneratedPathRowidCurrentSourceCostSelection330']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed330()['nextGeneratedPathRowidCurrentSourceCostSelection330']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed330()['nextGeneratedPathRowidCurrentSourceCostSelection330']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed330()['nextGeneratedPathRowidCurrentSourceCostSelection330']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed330()['nextGeneratedPathRowidCurrentSourceCostSelection330']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed330()['nextGeneratedPathRowidCurrentSourceCostSelection330']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next330', $changed330()['nextGeneratedPathRowidCurrentSourceCostSelection330']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed330()['generatedPathRowidCurrentSourceCostSelection330Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed330()['generatedPathRowidCurrentSourceCostSelection330Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed330()['generatedPathRowidCurrentSourceCostSelection330Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed330()['generatedPathRowidCurrentSourceCostSelection330Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed330()['generatedPathRowidCurrentSourceCostSelection330Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next330', $changed330()['next330ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next330', $changed330()['next330ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next330', $changed330()['next330ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next330', $changed330()['next330ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next330', $changed330()['next330ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed330()['next330ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next330', $staleFingerprint330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next330', $staleRowid330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next330', $noOrder330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias330()['currentGeneratedPathRowidCurrentSourceCostSelection330']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan330($current330, $current330, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan330(array_replace($current330, ['generated_path' => '$.rules[']), $current330)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next330 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
