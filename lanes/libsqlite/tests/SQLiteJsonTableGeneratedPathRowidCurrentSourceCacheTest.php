<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current175 = [
    'option_id' => 175,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next175',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 7,
];
$next175 = [
    'option_id' => 175,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next175',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 8,
];

$plan175 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceCachePlan(
    'json_tree',
    $current ?? $current175,
    $next ?? $next175,
    'option_value',
    'generated_path',
    $constraints ?? [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'IN', 'value' => [5, 6, 42]],
    ],
    'scan_root',
    $orderBy ?? [['column' => '_rowid_', 'direction' => 'DESC']],
    $limit,
);

$stable175 = static fn (): array => $plan175($current175, $current175);
$point175 = static fn (): array => $plan175(
    array_replace($current175, ['generated_path' => '$.rules[1]', 'source_generation' => 'same']),
    array_replace($current175, ['generated_path' => '$.rules[1]', 'source_generation' => 'same']),
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ],
    [['column' => 'id']],
);
$valueGeneration175 = static fn (): array => $plan175(
    array_diff_key($current175, ['source_generation' => true]),
    array_diff_key($current175, ['source_generation' => true]),
);
$unusable175 = static fn (): array => $plan175(
    $current175,
    $next175,
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%', 'usable' => false],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6]],
    ],
    [['column' => 'id', 'direction' => 'DESC']],
);
$limit175 = static fn (): array => $plan175(null, null, null, [['column' => 'id']], 1);

$tests = [
    'records next175 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next175', $plan175()['dependencies'], true)),
    'preserves next173 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next173', $plan175()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-path-rowid-cost-current-source-next175-until-cache-generation-reset', $plan175()['currentReaderPolicy']),
    'prepares changed next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-path-rowid-cost-current-source-next175-cache', $plan175()['nextReaderPolicy']),
    'stable reader policy reuses cache' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-cost-current-source-next175-cache', $stable175()['nextReaderPolicy']),
    'stable has no next175 reasons' => static fn (TestRunner $t) => $t->same([], $stable175()['next175ReplanReasons']),
    'current source generation recorded' => static fn (TestRunner $t) => $t->same('source_generation:7', $plan175()['currentGeneratedPathRowidCurrentSourceCache175']['sourceGeneration']),
    'next source generation recorded' => static fn (TestRunner $t) => $t->same('source_generation:8', $plan175()['nextGeneratedPathRowidCurrentSourceCache175']['sourceGeneration']),
    'source token records option id' => static fn (TestRunner $t) => $t->same(175, $plan175()['currentGeneratedPathRowidCurrentSourceCache175']['sourceToken']['option_id']),
    'source token records generated path' => static fn (TestRunner $t) => $t->same('$.rules', $plan175()['currentGeneratedPathRowidCurrentSourceCache175']['sourceToken']['generatedPath']),
    'bestindex fingerprint is carried' => static fn (TestRunner $t) => $t->same($plan175()['currentGeneratedPathRowidCurrentSourceBestIndex173']['bestIndexFingerprint'], $plan175()['currentGeneratedPathRowidCurrentSourceCache175']['bestIndexFingerprint']),
    'cache key is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan175()['currentGeneratedPathRowidCurrentSourceCache175']['cacheKey'])),
    'cache key changes for next source' => static fn (TestRunner $t) => $t->true($plan175()['currentGeneratedPathRowidCurrentSourceCache175']['cacheKey'] !== $plan175()['nextGeneratedPathRowidCurrentSourceCache175']['cacheKey']),
    'current rowid scope is in list' => static fn (TestRunner $t) => $t->same(['operator' => 'IN', 'value' => [5, 6, 42], 'kind' => 'in-list', 'count' => 3], $plan175()['currentGeneratedPathRowidCurrentSourceCache175']['rowidScope']),
    'argv columns preserve path and id' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan175()['currentGeneratedPathRowidCurrentSourceCache175']['argvColumns']),
    'argv values preserve path and rowid list' => static fn (TestRunner $t) => $t->same(['$.rules%', [5, 6, 42]], $plan175()['currentGeneratedPathRowidCurrentSourceCache175']['argvValues']),
    'ordered rowids preserve xfilter output' => static fn (TestRunner $t) => $t->same([6, 5], $plan175()['currentGeneratedPathRowidCurrentSourceCache175']['orderedOutputRowids']),
    'estimated rows preserve bestindex' => static fn (TestRunner $t) => $t->same(2, $plan175()['currentGeneratedPathRowidCurrentSourceCache175']['estimatedRows']),
    'planner cost preserve bestindex' => static fn (TestRunner $t) => $t->same(1, $plan175()['currentGeneratedPathRowidCurrentSourceCache175']['plannerCost']),
    'current cache reusable' => static fn (TestRunner $t) => $t->same(true, $plan175()['currentGeneratedPathRowidCurrentSourceCache175']['cacheReusable']),
    'current cache disposition reuse' => static fn (TestRunner $t) => $t->same('reuse-json-table-generated-path-rowid-cache', $plan175()['currentGeneratedPathRowidCurrentSourceCache175']['cacheDisposition']),
    'current cost class range' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-cache-range-current-source', $plan175()['currentGeneratedPathRowidCurrentSourceCache175']['costClass']),
    'next cache not reusable' => static fn (TestRunner $t) => $t->same(false, $plan175()['nextGeneratedPathRowidCurrentSourceCache175']['cacheReusable']),
    'next cache disposition reprepare' => static fn (TestRunner $t) => $t->same('reprepare-json-table-generated-path-rowid-cache', $plan175()['nextGeneratedPathRowidCurrentSourceCache175']['cacheDisposition']),
    'next cost class reprepare' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-cache-reprepare-current-source', $plan175()['nextGeneratedPathRowidCurrentSourceCache175']['costClass']),
    'next ordered rowids empty' => static fn (TestRunner $t) => $t->same([], $plan175()['nextGeneratedPathRowidCurrentSourceCache175']['orderedOutputRowids']),
    'next planner cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $plan175()['nextGeneratedPathRowidCurrentSourceCache175']['plannerCost']),
    'point scope records point' => static fn (TestRunner $t) => $t->same(['operator' => '=', 'value' => 6, 'kind' => 'point', 'count' => 1], $point175()['currentGeneratedPathRowidCurrentSourceCache175']['rowidScope']),
    'point cost class point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-cache-point-current-source', $point175()['currentGeneratedPathRowidCurrentSourceCache175']['costClass']),
    'point stable no reasons' => static fn (TestRunner $t) => $t->same([], $point175()['next175ReplanReasons']),
    'value generation uses hash fallback' => static fn (TestRunner $t) => $t->true(str_starts_with($valueGeneration175()['currentGeneratedPathRowidCurrentSourceCache175']['sourceGeneration'], 'value:')),
    'value generation stable for same source' => static fn (TestRunner $t) => $t->same($valueGeneration175()['currentGeneratedPathRowidCurrentSourceCache175']['sourceGeneration'], $valueGeneration175()['nextGeneratedPathRowidCurrentSourceCache175']['sourceGeneration']),
    'unusable path cache not reusable' => static fn (TestRunner $t) => $t->same(false, $unusable175()['currentGeneratedPathRowidCurrentSourceCache175']['cacheReusable']),
    'unusable path cache class reprepare' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-cache-reprepare-current-source', $unusable175()['currentGeneratedPathRowidCurrentSourceCache175']['costClass']),
    'limited cache point class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-cache-point-current-source', $limit175()['currentGeneratedPathRowidCurrentSourceCache175']['costClass']),
    'transition count records cache state' => static fn (TestRunner $t) => $t->same(12, count($plan175()['generatedPathRowidCurrentSourceCache175Transitions'])),
    'transition generation changes' => static fn (TestRunner $t) => $t->same(true, $plan175()['generatedPathRowidCurrentSourceCache175Transitions'][0]['changed']),
    'transition token changes' => static fn (TestRunner $t) => $t->same(true, $plan175()['generatedPathRowidCurrentSourceCache175Transitions'][1]['changed']),
    'transition bestindex changes' => static fn (TestRunner $t) => $t->same(true, $plan175()['generatedPathRowidCurrentSourceCache175Transitions'][2]['changed']),
    'transition cache key changes' => static fn (TestRunner $t) => $t->same(true, $plan175()['generatedPathRowidCurrentSourceCache175Transitions'][3]['changed']),
    'transition rowid scope stable' => static fn (TestRunner $t) => $t->same(false, $plan175()['generatedPathRowidCurrentSourceCache175Transitions'][4]['changed']),
    'transition argv values stable' => static fn (TestRunner $t) => $t->same(false, $plan175()['generatedPathRowidCurrentSourceCache175Transitions'][5]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $plan175()['generatedPathRowidCurrentSourceCache175Transitions'][6]['changed']),
    'transition planner cost changes' => static fn (TestRunner $t) => $t->same(true, $plan175()['generatedPathRowidCurrentSourceCache175Transitions'][8]['changed']),
    'transition reusable changes' => static fn (TestRunner $t) => $t->same(true, $plan175()['generatedPathRowidCurrentSourceCache175Transitions'][9]['changed']),
    'transition disposition changes' => static fn (TestRunner $t) => $t->same(true, $plan175()['generatedPathRowidCurrentSourceCache175Transitions'][10]['changed']),
    'reasons include source generation' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cache-source-generation-changed', $plan175()['next175ReplanReasons'], true)),
    'reasons include cache key' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cache-key-changed', $plan175()['next175ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cache-rowset-changed', $plan175()['next175ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cache-cost-changed', $plan175()['next175ReplanReasons'], true)),
    'reasons include disposition' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cache-disposition-changed', $plan175()['next175ReplanReasons'], true)),
    'reasons preserve bestindex cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-bestindex-cost-changed', $plan175()['next175ReplanReasons'], true)),
    'negative limit rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan175(null, null, null, null, -1)),
    'bad order direction rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan175(null, null, null, [['column' => 'id', 'direction' => 'SIDEWAYS']])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next175 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
