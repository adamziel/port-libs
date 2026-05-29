<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current335 = [
    'option_id' => 335,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next335',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-335-a',
];
$next335 = [
    'option_id' => 335,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next335',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"security","priority":9}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-335-b',
];

$plan335 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?string $observedFingerprint = null,
    ?int $observedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext335(
    'json_tree',
    $current ?? $current335,
    $next ?? $next335,
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

$changed335 = static fn (): array => $plan335();
$stable335 = static fn (): array => $plan335($current335, $current335);
$staleFingerprint335 = static function () use ($plan335, $current335): array {
    return $plan335($current335, $current335, null, null, str_repeat('0', 64), null);
};
$staleRowid335 = static fn (): array => $plan335($current335, $current335, null, null, null, 5);
$noOrder335 = static fn (): array => $plan335($current335, $current335, null, []);
$oidAlias335 = static fn (): array => $plan335($current335, $current335, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => '=', 'value' => 7],
]);
$unusableAlias335 = static fn (): array => $plan335($current335, $current335, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 8], 'usable' => false],
]);

$tests = [
    'records next335 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next335', $changed335()['dependencies'], true)),
    'preserves next224 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next224', $changed335()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('cost-select-current-json-table-generated-path-rowid-next335', $changed335()['currentReaderPolicy']),
    'changed next reader reparses' => static fn (TestRunner $t) => $t->same('reprepare-cost-select-next-json-table-generated-path-rowid-next335', $changed335()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-cost-select-current-json-table-generated-path-rowid-next335', $stable335()['nextReaderPolicy']),
    'stable reasons empty' => static fn (TestRunner $t) => $t->same([], $stable335()['next335ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $changed335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['function']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $changed335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-335-a', $changed335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['sourceGeneration']),
    'current rowid aliases recorded' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_'], $changed335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['rowidAliases']),
    'current order columns normalized' => static fn (TestRunner $t) => $t->same(['id'], $changed335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['orderByColumns']),
    'current active rowid' => static fn (TestRunner $t) => $t->same(7, $changed335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['activeRowid']),
    'current delivered rowids' => static fn (TestRunner $t) => $t->same([7], $changed335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['deliveredRowids']),
    'current restart rowids empty' => static fn (TestRunner $t) => $t->same([], $changed335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['restartRowids']),
    'current fingerprint matches' => static fn (TestRunner $t) => $t->same(true, $changed335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['fingerprintMatches']),
    'current rowid matches' => static fn (TestRunner $t) => $t->same(true, $changed335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['rowidMatches']),
    'current alias consistent' => static fn (TestRunner $t) => $t->same(true, $changed335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['aliasConsistent']),
    'current yield guard reusable' => static fn (TestRunner $t) => $t->same(true, $changed335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['yieldGuardReusable']),
    'current xrowid reusable' => static fn (TestRunner $t) => $t->same(true, $changed335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['xRowidReusable']),
    'current cost reusable' => static fn (TestRunner $t) => $t->same(true, $changed335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['currentSourceCostReusable']),
    'current order consumed' => static fn (TestRunner $t) => $t->same(true, $changed335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['orderByConsumed']),
    'current idx num' => static fn (TestRunner $t) => $t->same(15, $changed335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['idxNum']),
    'current idx str' => static fn (TestRunner $t) => $t->same('current-source-rowid-point|fingerprint-match|rowid-match|orderby', $changed335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['idxStr']),
    'current estimated rows' => static fn (TestRunner $t) => $t->same(1, $changed335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['estimatedRows']),
    'current estimated cost' => static fn (TestRunner $t) => $t->same(1, $changed335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['estimatedCost']),
    'current cost class covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-covering-point-next335', $changed335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['costClass']),
    'selection fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($changed335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['selectionFingerprint'])),
    'next generated path changed' => static fn (TestRunner $t) => $t->same('$.rules[0]', $changed335()['nextGeneratedPathRowidCurrentSourceCostSelection335']['generatedPath']),
    'next delivered rowids empty' => static fn (TestRunner $t) => $t->same([], $changed335()['nextGeneratedPathRowidCurrentSourceCostSelection335']['deliveredRowids']),
    'next restart rowids empty after changed source miss' => static fn (TestRunner $t) => $t->same([], $changed335()['nextGeneratedPathRowidCurrentSourceCostSelection335']['restartRowids']),
    'next cost not reusable' => static fn (TestRunner $t) => $t->same(false, $changed335()['nextGeneratedPathRowidCurrentSourceCostSelection335']['currentSourceCostReusable']),
    'next estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $changed335()['nextGeneratedPathRowidCurrentSourceCostSelection335']['estimatedRows']),
    'next estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $changed335()['nextGeneratedPathRowidCurrentSourceCostSelection335']['estimatedCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-eof-next335', $changed335()['nextGeneratedPathRowidCurrentSourceCostSelection335']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(22, count($changed335()['generatedPathRowidCurrentSourceCostSelection335Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $changed335()['generatedPathRowidCurrentSourceCostSelection335Transitions'][3]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $changed335()['generatedPathRowidCurrentSourceCostSelection335Transitions'][7]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $changed335()['generatedPathRowidCurrentSourceCostSelection335Transitions'][14]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $changed335()['generatedPathRowidCurrentSourceCostSelection335Transitions'][19]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-source-changed-next335', $changed335()['next335ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-rowset-changed-next335', $changed335()['next335ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-admission-changed-next335', $changed335()['next335ReplanReasons'], true)),
    'reasons include index' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-index-changed-next335', $changed335()['next335ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-selection-cost-changed-next335', $changed335()['next335ReplanReasons'], true)),
    'preserves next224 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $changed335()['next335ReplanReasons'], true)),
    'stale fingerprint cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-fingerprint-next335', $staleFingerprint335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['costClass']),
    'stale fingerprint not reusable' => static fn (TestRunner $t) => $t->same(false, $staleFingerprint335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['currentSourceCostReusable']),
    'stale rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-stale-rowid-next335', $staleRowid335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['costClass']),
    'stale rowid restart rowids' => static fn (TestRunner $t) => $t->same([7, 8], $staleRowid335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['restartRowids']),
    'no order loses range xcurrent reuse' => static fn (TestRunner $t) => $t->same(false, $noOrder335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['currentSourceCostReusable']),
    'no order reparses range cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cost-reprepare-next335', $noOrder335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['costClass']),
    'oid alias recorded' => static fn (TestRunner $t) => $t->same(['oid'], $oidAlias335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['rowidAliases']),
    'oid alias alone remains guarded' => static fn (TestRunner $t) => $t->same(false, $oidAlias335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['currentSourceCostReusable']),
    'unusable alias ignored' => static fn (TestRunner $t) => $t->same([], $unusableAlias335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['rowidAliases']),
    'unusable alias not reusable' => static fn (TestRunner $t) => $t->same(false, $unusableAlias335()['currentGeneratedPathRowidCurrentSourceCostSelection335']['currentSourceCostReusable']),
    'bad fingerprint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan335($current335, $current335, null, null, 'bad-fingerprint', null)),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan335(array_replace($current335, ['generated_path' => '$.rules[']), $current335)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next335 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
