<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current217 = [
    'option_id' => 217,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next217',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-217-a',
];
$next217 = [
    'option_id' => 217,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next217',
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
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext217(
    'json_tree',
    $current ?? $current217,
    $next ?? $next217,
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
    'records next217 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next217', $plan217()['dependencies'], true)),
    'preserves next212 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next212', $plan217()['dependencies'], true)),
    'current reader policy' => static fn (TestRunner $t) => $t->same('bestindex-current-json-table-generated-path-rowid-next217', $plan217()['currentReaderPolicy']),
    'next reader policy reparses' => static fn (TestRunner $t) => $t->same('reprepare-bestindex-next-json-table-generated-path-rowid-next217', $plan217()['nextReaderPolicy']),
    'stable reader policy reuses' => static fn (TestRunner $t) => $t->same('reuse-bestindex-current-json-table-generated-path-rowid-next217', $stable217()['nextReaderPolicy']),
    'stable next217 reasons empty' => static fn (TestRunner $t) => $t->same([], $stable217()['next217ReplanReasons']),
    'current function recorded' => static fn (TestRunner $t) => $t->same('json_tree', $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['function']),
    'current root recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['root']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['generatedPath']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('current-217-a', $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['sourceGeneration']),
    'source fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['sourceFingerprint'])),
    'range fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['rangeFingerprint'])),
    'xcurrent fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['xCurrentFingerprint'])),
    'argv columns include generated rowid range' => static fn (TestRunner $t) => $t->same(['json', 'root', 'generated_path', 'rowid_range'], $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['argvColumns']),
    'json argv first' => static fn (TestRunner $t) => $t->same(['argvIndex' => 1, 'column' => 'json', 'operator' => '=', 'value' => 'current-source-json-column', 'omit' => true], $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['argv'][0]),
    'root argv second' => static fn (TestRunner $t) => $t->same('$.rules', $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['argv'][1]['value']),
    'generated path argv third' => static fn (TestRunner $t) => $t->same('$.rules', $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['argv'][2]['value']),
    'rowid range argv fourth' => static fn (TestRunner $t) => $t->same([7, 8], $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['argv'][3]['value']),
    'omitted constraints' => static fn (TestRunner $t) => $t->same(['json', 'root', 'generated_path', 'rowid_range'], $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['omittedConstraintColumns']),
    'residual constraints empty' => static fn (TestRunner $t) => $t->same([], $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['residualConstraintColumns']),
    'idx num includes current source' => static fn (TestRunner $t) => $t->same(63, $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['idxNum']),
    'idx str names all parts' => static fn (TestRunner $t) => $t->same('json|root|generated-path|rowid-range|order|current-source|json-table-generated-path-rowid-range-seek-next209', $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['idxStr']),
    'rowid lower bound' => static fn (TestRunner $t) => $t->same(7, $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['rowidLowerBound']),
    'rowid upper bound' => static fn (TestRunner $t) => $t->same(8, $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['rowidUpperBound']),
    'accepted rowids' => static fn (TestRunner $t) => $t->same([7, 8], $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['acceptedRangeRowids']),
    'active rowid' => static fn (TestRunner $t) => $t->same(7, $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['activeRowid']),
    'remaining rowids' => static fn (TestRunner $t) => $t->same([8], $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['remainingRowids']),
    'order by consumed' => static fn (TestRunner $t) => $t->same(true, $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['orderByConsumed']),
    'range reusable' => static fn (TestRunner $t) => $t->same(true, $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['rangeReusable']),
    'xcurrent reusable' => static fn (TestRunner $t) => $t->same(true, $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['xCurrentReusable']),
    'upstream replan false' => static fn (TestRunner $t) => $t->same(false, $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['upstreamReplanRequired']),
    'xbestindex reusable' => static fn (TestRunner $t) => $t->same(true, $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['xBestIndexReusable']),
    'covering cursor' => static fn (TestRunner $t) => $t->same(true, $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['coveringCursor']),
    'xbestindex opcode' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidBestIndexCurrentSourceNext217', $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['xBestIndexOpcode']),
    'estimated rows two' => static fn (TestRunner $t) => $t->same(2, $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['estimatedRows']),
    'estimated cost two' => static fn (TestRunner $t) => $t->same(2, $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['estimatedCost']),
    'cost class range covering' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-bestindex-covering-range-next217', $plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['costClass']),
    'bestindex fingerprint sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['bestIndexFingerprint'])),
    'next generated path changes' => static fn (TestRunner $t) => $t->same('$.rules[0]', $plan217()['nextGeneratedPathRowidCurrentSourceBestIndex217']['generatedPath']),
    'next idx num lacks current source bit' => static fn (TestRunner $t) => $t->same(31, $plan217()['nextGeneratedPathRowidCurrentSourceBestIndex217']['idxNum']),
    'next accepted rowids empty' => static fn (TestRunner $t) => $t->same([], $plan217()['nextGeneratedPathRowidCurrentSourceBestIndex217']['acceptedRangeRowids']),
    'next active rowid null' => static fn (TestRunner $t) => $t->same(null, $plan217()['nextGeneratedPathRowidCurrentSourceBestIndex217']['activeRowid']),
    'next xbestindex not reusable' => static fn (TestRunner $t) => $t->same(false, $plan217()['nextGeneratedPathRowidCurrentSourceBestIndex217']['xBestIndexReusable']),
    'next opcode reparses' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidBestIndexReprepareNext217', $plan217()['nextGeneratedPathRowidCurrentSourceBestIndex217']['xBestIndexOpcode']),
    'next cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $plan217()['nextGeneratedPathRowidCurrentSourceBestIndex217']['estimatedCost']),
    'next cost class reprepare' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-bestindex-reprepare-next217', $plan217()['nextGeneratedPathRowidCurrentSourceBestIndex217']['costClass']),
    'transition count' => static fn (TestRunner $t) => $t->same(29, count($plan217()['generatedPathRowidCurrentSourceBestIndex217Transitions'])),
    'transition source changes' => static fn (TestRunner $t) => $t->same(true, $plan217()['generatedPathRowidCurrentSourceBestIndex217Transitions'][4]['changed']),
    'transition argv changes' => static fn (TestRunner $t) => $t->same(true, $plan217()['generatedPathRowidCurrentSourceBestIndex217Transitions'][7]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $plan217()['generatedPathRowidCurrentSourceBestIndex217Transitions'][15]['changed']),
    'transition admission changes' => static fn (TestRunner $t) => $t->same(true, $plan217()['generatedPathRowidCurrentSourceBestIndex217Transitions'][21]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $plan217()['generatedPathRowidCurrentSourceBestIndex217Transitions'][26]['changed']),
    'reasons include source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-bestindex-source-changed-next217', $plan217()['next217ReplanReasons'], true)),
    'reasons include argv' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-bestindex-argv-changed-next217', $plan217()['next217ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-bestindex-rowset-changed-next217', $plan217()['next217ReplanReasons'], true)),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-bestindex-admission-changed-next217', $plan217()['next217ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-bestindex-cost-changed-next217', $plan217()['next217ReplanReasons'], true)),
    'point rowids' => static fn (TestRunner $t) => $t->same([8], $point217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['acceptedRangeRowids']),
    'point cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-bestindex-covering-point-next217', $point217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['costClass']),
    'point estimated rows' => static fn (TestRunner $t) => $t->same(1, $point217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['estimatedRows']),
    'external order not consumed' => static fn (TestRunner $t) => $t->same(false, $externalOrder217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['orderByConsumed']),
    'external order reseeks' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidBestIndexReseekRangeNext217', $externalOrder217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['xBestIndexOpcode']),
    'residual type column recorded' => static fn (TestRunner $t) => $t->same(['type'], $residual217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['residualConstraintColumns']),
    'residual argv column appended' => static fn (TestRunner $t) => $t->same('type', $residual217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['argvColumns'][4]),
    'residual covering false' => static fn (TestRunner $t) => $t->same(false, $residual217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['coveringCursor']),
    'no range reseeks' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidBestIndexReseekRangeNext217', $noRange217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['xBestIndexOpcode']),
    'no range residual rowid' => static fn (TestRunner $t) => $t->same(['rowid_range'], $noRange217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['residualConstraintColumns']),
    'empty range rowids empty' => static fn (TestRunner $t) => $t->same([], $empty217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['acceptedRangeRowids']),
    'empty range cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $empty217()['currentGeneratedPathRowidCurrentSourceBestIndex217']['estimatedCost']),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan217(array_replace($current217, ['generated_path' => '$.rules[']), $current217)),
    'dependency closure' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next217 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
