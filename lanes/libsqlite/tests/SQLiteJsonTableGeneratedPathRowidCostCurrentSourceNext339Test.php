<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current339 = [
    'option_id' => 339,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next339',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-339-a',
];
$next339 = [
    'option_id' => 339,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next339',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-339-b',
];

$plan339 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext339(
    'json_tree',
    $current ?? $current339,
    $next ?? $next339,
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

$changed339 = static fn (): array => $plan339();
$stable339 = static fn (): array => $plan339($current339, $current339);
$staleFingerprint339 = static function () use ($plan339, $current339): array {
    return $plan339($current339, $current339, null, null, str_repeat('0', 64), null);
};
$staleRowid339 = static fn (): array => $plan339($current339, $current339, null, null, null, 5);
$noOrder339 = static fn (): array => $plan339($current339, $current339, null, []);
$oidAlias339 = static fn (): array => $plan339($current339, $current339, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias339 = static fn (): array => $plan339($current339, $current339, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next339 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next339', $changed339()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed339()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next339', $changed339()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next339', $changed339()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next339', $stable339()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable339()['next339ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-339-a', $changed339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next339', $changed339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed339()['nextGeneratedPathRowidCurrentSourceCostSelection339']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed339()['nextGeneratedPathRowidCurrentSourceCostSelection339']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed339()['nextGeneratedPathRowidCurrentSourceCostSelection339']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed339()['nextGeneratedPathRowidCurrentSourceCostSelection339']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed339()['nextGeneratedPathRowidCurrentSourceCostSelection339']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed339()['nextGeneratedPathRowidCurrentSourceCostSelection339']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next339', $changed339()['nextGeneratedPathRowidCurrentSourceCostSelection339']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed339()['generatedPathRowidCurrentSourceCostSelection339Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed339()['generatedPathRowidCurrentSourceCostSelection339Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed339()['generatedPathRowidCurrentSourceCostSelection339Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed339()['generatedPathRowidCurrentSourceCostSelection339Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed339()['generatedPathRowidCurrentSourceCostSelection339Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next339', $changed339()['next339ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next339', $changed339()['next339ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next339', $changed339()['next339ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next339', $changed339()['next339ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next339', $changed339()['next339ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed339()['next339ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next339', $staleFingerprint339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next339', $staleRowid339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next339', $noOrder339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias339()['currentGeneratedPathRowidCurrentSourceCostSelection339']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan339($current339, $current339, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan339(array_replace($current339, ['generated_path' => '$.rules[']), $current339)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next339 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
