<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current217 = [
    'option_id' => 217,
    'option_name' => 'wp_plugin_generated_path_rowid_bestindex',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-217-a',
];
$generatedPathRowidBestIndex = [
    'option_id' => 217,
    'option_name' => 'wp_plugin_generated_path_rowid_bestindex',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-217-b',
];

$plan217 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = 5,
    ?int $lastYieldedRowid = null,
    ?int $yieldBatchSize = 3,
    ?array $projection = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidBestIndex(
    'json_tree',
    $current ?? $current217,
    $next ?? $generatedPathRowidBestIndex,
    'option_value',
    'generated_path',
    $constraints ?? [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
        ['column' => '_rowid_', 'operator' => 'BETWEEN', 'value' => [7, 8]],
    ],
    'scan_root',
    $orderBy ?? [['column' => 'rowid', 'direction' => 'ASC']],
    $limit,
    $lastYieldedRowid,
    $yieldBatchSize,
    $projection ?? ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey'],
);

$stable217 = static fn (): array => $plan217($current217, $current217);
$point217 = static fn (): array => $plan217($current217, $current217, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [8, 8]],
]);
$externalOrder217 = static fn (): array => $plan217($current217, $current217, null, [['column' => 'fullkey', 'direction' => 'ASC']]);
$residual217 = static fn (): array => $plan217($current217, $current217, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [7, 8]],
    ['column' => 'type', 'operator' => '=', 'value' => 'object'],
]);
$noRange217 = static fn (): array => $plan217($current217, $current217, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
]);
$empty217 = static fn (): array => $plan217($current217, $current217, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [20, 30]],
]);

$tests = [
    'records bestindex dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-bestindex', $plan217()['dependencies'], true)),
    'preserves xcurrent dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-xcurrent', $plan217()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('bestindex-current-json-table-generated-path-rowid', $plan217()['currentReaderPolicy']),
    'next reader policy reparses' => static fn (TestRunner $t) => $t->same('reprepare-bestindex-next-json-table-generated-path-rowid', $plan217()['nextReaderPolicy']),
    'stable reader policy reuses' => static fn (TestRunner $t) => $t->same('reuse-bestindex-current-json-table-generated-path-rowid', $stable217()['nextReaderPolicy']),
    'stable bestindex reasons empty' => static fn (TestRunner $t) => $t->same([], $stable217()['generatedPathRowidBestIndexReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $plan217()['currentGeneratedPathRowidBestIndexProfile']['function']),
    'current root recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan217()['currentGeneratedPathRowidBestIndexProfile']['root']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan217()['currentGeneratedPathRowidBestIndexProfile']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-217-a', $plan217()['currentGeneratedPathRowidBestIndexProfile']['sourceGeneration']),
    'source fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan217()['currentGeneratedPathRowidBestIndexProfile']['sourceFingerprint'])),
    'range fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan217()['currentGeneratedPathRowidBestIndexProfile']['rangeFingerprint'])),
    'xcurrent fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan217()['currentGeneratedPathRowidBestIndexProfile']['xCurrentFingerprint'])),
    'argv columns include generated rowid range' => static fn (TestRunner $t) => $t->same(['json', 'root', 'generated_path', 'rowid_range'], $plan217()['currentGeneratedPathRowidBestIndexProfile']['argvColumns']),
    'json argv first' => static fn (TestRunner $t) => $t->same(['argvIndex' => 1, 'column' => 'json', 'operator' => '=', 'value' => 'current-source-json-column', 'omit' => true], $plan217()['currentGeneratedPathRowidBestIndexProfile']['argv'][0]),
    'root argv second' => static fn (TestRunner $t) => $t->same('$.rules', $plan217()['currentGeneratedPathRowidBestIndexProfile']['argv'][1]['value']),
    'generated path argv third' => static fn (TestRunner $t) => $t->same('$.rules', $plan217()['currentGeneratedPathRowidBestIndexProfile']['argv'][2]['value']),
    'rowid range argv fourth' => static fn (TestRunner $t) => $t->same([7, 8], $plan217()['currentGeneratedPathRowidBestIndexProfile']['argv'][3]['value']),
    'omitted constraints' => static fn (TestRunner $t) => $t->same(['json', 'root', 'generated_path', 'rowid_range'], $plan217()['currentGeneratedPathRowidBestIndexProfile']['omittedConstraintColumns']),
    'residual constraints empty' => static fn (TestRunner $t) => $t->same([], $plan217()['currentGeneratedPathRowidBestIndexProfile']['residualConstraintColumns']),
    'idx num includes current source' => static fn (TestRunner $t) => $t->same(63, $plan217()['currentGeneratedPathRowidBestIndexProfile']['idxNum']),
    'idx str names all parts' => static fn (TestRunner $t) => $t->same('json|root|generated-path|rowid-range|order|current-source|json-table-generated-path-rowid-range-seek-next209', $plan217()['currentGeneratedPathRowidBestIndexProfile']['idxStr']),
    'rowid lower bound' => static fn (TestRunner $t) => $t->same(7, $plan217()['currentGeneratedPathRowidBestIndexProfile']['rowidLowerBound']),
    'rowid upper bound' => static fn (TestRunner $t) => $t->same(8, $plan217()['currentGeneratedPathRowidBestIndexProfile']['rowidUpperBound']),
    'accepted rowids' => static fn (TestRunner $t) => $t->same([7, 8], $plan217()['currentGeneratedPathRowidBestIndexProfile']['acceptedRangeRowids']),
    'active rowid' => static fn (TestRunner $t) => $t->same(7, $plan217()['currentGeneratedPathRowidBestIndexProfile']['activeRowid']),
    'remaining rowids' => static fn (TestRunner $t) => $t->same([8], $plan217()['currentGeneratedPathRowidBestIndexProfile']['remainingRowids']),
    'order by consumed' => static fn (TestRunner $t) => $t->same(true, $plan217()['currentGeneratedPathRowidBestIndexProfile']['orderByConsumed']),
    'range reusable' => static fn (TestRunner $t) => $t->same(true, $plan217()['currentGeneratedPathRowidBestIndexProfile']['rangeReusable']),
    'xcurrent reusable' => static fn (TestRunner $t) => $t->same(true, $plan217()['currentGeneratedPathRowidBestIndexProfile']['xCurrentReusable']),
    'upstream replan false' => static fn (TestRunner $t) => $t->same(false, $plan217()['currentGeneratedPathRowidBestIndexProfile']['upstreamReplanRequired']),
    'xbestindex reusable' => static fn (TestRunner $t) => $t->same(true, $plan217()['currentGeneratedPathRowidBestIndexProfile']['xBestIndexReusable']),
    'covering cursor' => static fn (TestRunner $t) => $t->same(true, $plan217()['currentGeneratedPathRowidBestIndexProfile']['coveringCursor']),
    'xbestindex opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidBestIndexCurrentSource', $plan217()['currentGeneratedPathRowidBestIndexProfile']['xBestIndexOpcode']),
    'estimated rows two' => static fn (TestRunner $t) => $t->same(2, $plan217()['currentGeneratedPathRowidBestIndexProfile']['estimatedRows']),
    'estimated cost two' => static fn (TestRunner $t) => $t->same(2, $plan217()['currentGeneratedPathRowidBestIndexProfile']['estimatedCost']),
    'cost class range covering' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-bestindex-covering-range', $plan217()['currentGeneratedPathRowidBestIndexProfile']['costClass']),
    'bestindex fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan217()['currentGeneratedPathRowidBestIndexProfile']['bestIndexFingerprint'])),
    'next generated path changes' => static fn (TestRunner $t) => $t->same('$.rules[0]', $plan217()['nextGeneratedPathRowidBestIndexProfile']['generatedPath']),
    'next idx num lacks current source bit' => static fn (TestRunner $t) => $t->same(31, $plan217()['nextGeneratedPathRowidBestIndexProfile']['idxNum']),
    'next accepted rowids empty' => static fn (TestRunner $t) => $t->same([], $plan217()['nextGeneratedPathRowidBestIndexProfile']['acceptedRangeRowids']),
    'next active rowid null' => static fn (TestRunner $t) => $t->same(null, $plan217()['nextGeneratedPathRowidBestIndexProfile']['activeRowid']),
    'next xbestindex not reusable' => static fn (TestRunner $t) => $t->same(false, $plan217()['nextGeneratedPathRowidBestIndexProfile']['xBestIndexReusable']),
    'next opcode reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidBestIndexReprepare', $plan217()['nextGeneratedPathRowidBestIndexProfile']['xBestIndexOpcode']),
    'next cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $plan217()['nextGeneratedPathRowidBestIndexProfile']['estimatedCost']),
    'next cost class reprepare' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-bestindex-reprepare', $plan217()['nextGeneratedPathRowidBestIndexProfile']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(29, count($plan217()['generatedPathRowidBestIndexTransitions'])),
    'transition source changes' => static fn (TestRunner $t) => $t->same(true, $plan217()['generatedPathRowidBestIndexTransitions'][4]['changed']),
    'transition argv changes' => static fn (TestRunner $t) => $t->same(true, $plan217()['generatedPathRowidBestIndexTransitions'][7]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $plan217()['generatedPathRowidBestIndexTransitions'][15]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $plan217()['generatedPathRowidBestIndexTransitions'][21]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $plan217()['generatedPathRowidBestIndexTransitions'][26]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-bestindex-source-changed', $plan217()['generatedPathRowidBestIndexReplanReasons'], true)),
    'reasons include argv' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-bestindex-argv-changed', $plan217()['generatedPathRowidBestIndexReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-bestindex-rowset-changed', $plan217()['generatedPathRowidBestIndexReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-bestindex-admission-changed', $plan217()['generatedPathRowidBestIndexReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-bestindex-cost-changed', $plan217()['generatedPathRowidBestIndexReplanReasons'], true)),
    'point rowids' => static fn (TestRunner $t) => $t->same([8], $point217()['currentGeneratedPathRowidBestIndexProfile']['acceptedRangeRowids']),
    'point cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-bestindex-covering-point', $point217()['currentGeneratedPathRowidBestIndexProfile']['costClass']),
    'point estimated rows' => static fn (TestRunner $t) => $t->same(1, $point217()['currentGeneratedPathRowidBestIndexProfile']['estimatedRows']),
    'external order not consumed' => static fn (TestRunner $t) => $t->same(false, $externalOrder217()['currentGeneratedPathRowidBestIndexProfile']['orderByConsumed']),
    'external order reseeks' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidBestIndexReseekRange', $externalOrder217()['currentGeneratedPathRowidBestIndexProfile']['xBestIndexOpcode']),
    'residual type column recorded' => static fn (TestRunner $t) => $t->same(['type'], $residual217()['currentGeneratedPathRowidBestIndexProfile']['residualConstraintColumns']),
    'residual argv column appended' => static fn (TestRunner $t) => $t->same('type', $residual217()['currentGeneratedPathRowidBestIndexProfile']['argvColumns'][4]),
    'residual covering false' => static fn (TestRunner $t) => $t->same(false, $residual217()['currentGeneratedPathRowidBestIndexProfile']['coveringCursor']),
    'no range reseeks' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidBestIndexReseekRange', $noRange217()['currentGeneratedPathRowidBestIndexProfile']['xBestIndexOpcode']),
    'no range residual rowid' => static fn (TestRunner $t) => $t->same(['rowid_range'], $noRange217()['currentGeneratedPathRowidBestIndexProfile']['residualConstraintColumns']),
    'empty range rowids empty' => static fn (TestRunner $t) => $t->same([], $empty217()['currentGeneratedPathRowidBestIndexProfile']['acceptedRangeRowids']),
    'empty range cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $empty217()['currentGeneratedPathRowidBestIndexProfile']['estimatedCost']),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan217(array_replace($current217, ['generated_path' => '$.rules[']), $current217)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source bestindex ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
