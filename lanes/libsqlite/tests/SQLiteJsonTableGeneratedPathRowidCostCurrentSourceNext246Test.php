<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current246 = [
    'option_id' => 246,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next246',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-246-a',
];
$next246 = [
    'option_id' => 246,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next246',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-246-b',
];

$plan246 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias(
    'json_tree',
    246,
    $current ?? $current246,
    $next ?? $next246,
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

$changed246 = static fn (): array => $plan246();
$stable246 = static fn (): array => $plan246($current246, $current246);
$staleFingerprint246 = static function () use ($plan246, $current246): array {
    return $plan246($current246, $current246, null, null, str_repeat('0', 64), null);
};
$staleRowid246 = static fn (): array => $plan246($current246, $current246, null, null, null, 5);
$noOrder246 = static fn (): array => $plan246($current246, $current246, null, []);
$oidAlias246 = static fn (): array => $plan246($current246, $current246, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias246 = static fn (): array => $plan246($current246, $current246, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next246 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next246', $changed246()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed246()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next246', $changed246()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next246', $changed246()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next246', $stable246()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable246()['next246ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-246-a', $changed246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next246', $changed246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed246()['nextGeneratedPathRowidCurrentSourceCostSelection246']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed246()['nextGeneratedPathRowidCurrentSourceCostSelection246']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed246()['nextGeneratedPathRowidCurrentSourceCostSelection246']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed246()['nextGeneratedPathRowidCurrentSourceCostSelection246']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed246()['nextGeneratedPathRowidCurrentSourceCostSelection246']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed246()['nextGeneratedPathRowidCurrentSourceCostSelection246']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next246', $changed246()['nextGeneratedPathRowidCurrentSourceCostSelection246']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed246()['generatedPathRowidCurrentSourceCostSelection246Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed246()['generatedPathRowidCurrentSourceCostSelection246Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed246()['generatedPathRowidCurrentSourceCostSelection246Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed246()['generatedPathRowidCurrentSourceCostSelection246Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed246()['generatedPathRowidCurrentSourceCostSelection246Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next246', $changed246()['next246ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next246', $changed246()['next246ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next246', $changed246()['next246ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next246', $changed246()['next246ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next246', $changed246()['next246ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed246()['next246ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next246', $staleFingerprint246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next246', $staleRowid246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next246', $noOrder246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias246()['currentGeneratedPathRowidCurrentSourceCostSelection246']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan246($current246, $current246, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan246(array_replace($current246, ['generated_path' => '$.rules[']), $current246)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next246 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
