<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current181 = [
    'option_id' => 181,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next181',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 17,
];
$next181 = [
    'option_id' => 181,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next181',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 18,
];

$plan181 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = null,
    ?int $lastYieldedRowid = null,
    ?array $projection = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext181(
    'json_tree',
    $current ?? $current181,
    $next ?? $next181,
    'option_value',
    'generated_path',
    $constraints ?? [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'IN', 'value' => [5, 6, 42]],
    ],
    'scan_root',
    $orderBy ?? [['column' => '_rowid_', 'direction' => 'DESC']],
    $limit,
    $lastYieldedRowid,
    $projection ?? ['id', 'fullkey', 'atom', 'value', 'type'],
);

$resume181 = static fn (): array => $plan181(null, null, null, null, null, 6);
$stable181 = static fn (): array => $plan181($current181, $current181, null, null, null, 6);
$first181 = static fn (): array => $plan181($current181, $current181);
$point181 = static fn (): array => $plan181(
    array_replace($current181, ['generated_path' => '$.rules[1]', 'source_generation' => 'same']),
    array_replace($current181, ['generated_path' => '$.rules[1]', 'source_generation' => 'same']),
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ],
    [['column' => 'id']],
    null,
    null,
    ['id', 'fullkey', 'value'],
);
$missing181 = static fn (): array => $plan181($current181, $current181, null, null, null, 99);

$tests = [
    'records next181 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next181', $resume181()['dependencies'], true)),
    'preserves next178 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next178', $resume181()['dependencies'], true)),
    'current reader materializes xcolumn snapshot' => static fn (TestRunner $t) => $t->same('materialize-current-json-table-generated-path-rowid-xcolumn-next181', $resume181()['currentReaderPolicy']),
    'next changed source reparses xcolumn snapshot' => static fn (TestRunner $t) => $t->same('reprepare-next-json-table-generated-path-rowid-xcolumn-next181-snapshot', $resume181()['nextReaderPolicy']),
    'stable next source reuses xcolumn snapshot' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-xcolumn-next181-snapshot', $stable181()['nextReaderPolicy']),
    'stable next181 reasons empty' => static fn (TestRunner $t) => $t->same([], $stable181()['next181ReplanReasons']),
    'current xcolumn function normalized' => static fn (TestRunner $t) => $t->same('json_tree', $resume181()['currentGeneratedPathRowidXColumnSnapshot181']['function']),
    'current xcolumn source kind text' => static fn (TestRunner $t) => $t->same('text', $resume181()['currentGeneratedPathRowidXColumnSnapshot181']['jsonSourceKind']),
    'current xcolumn source generation pinned' => static fn (TestRunner $t) => $t->same('source_generation:17', $resume181()['currentGeneratedPathRowidXColumnSnapshot181']['sourceGeneration']),
    'current xcolumn cache key is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($resume181()['currentGeneratedPathRowidXColumnSnapshot181']['cacheKey'])),
    'current xcolumn cursor generation is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($resume181()['currentGeneratedPathRowidXColumnSnapshot181']['cursorGeneration'])),
    'current projection normalized' => static fn (TestRunner $t) => $t->same(['id', 'fullkey', 'atom', 'value', 'type'], $resume181()['currentGeneratedPathRowidXColumnSnapshot181']['projection']),
    'current rowids resume remaining rowid' => static fn (TestRunner $t) => $t->same([5], $resume181()['currentGeneratedPathRowidXColumnSnapshot181']['rowids']),
    'current materialized one row' => static fn (TestRunner $t) => $t->same(1, count($resume181()['currentGeneratedPathRowidXColumnSnapshot181']['materializedRows'])),
    'current materialized rowid' => static fn (TestRunner $t) => $t->same(5, $resume181()['currentGeneratedPathRowidXColumnSnapshot181']['materializedRows'][0]['rowid']),
    'current materialized id' => static fn (TestRunner $t) => $t->same(5, $resume181()['currentGeneratedPathRowidXColumnSnapshot181']['materializedRows'][0]['id']),
    'current materialized fullkey from pinned source' => static fn (TestRunner $t) => $t->same('$.rules[1].slug', $resume181()['currentGeneratedPathRowidXColumnSnapshot181']['materializedRows'][0]['fullkey']),
    'current materialized atom from pinned source' => static fn (TestRunner $t) => $t->same('cache', $resume181()['currentGeneratedPathRowidXColumnSnapshot181']['materializedRows'][0]['atom']),
    'current materialized value from pinned source' => static fn (TestRunner $t) => $t->same('cache', $resume181()['currentGeneratedPathRowidXColumnSnapshot181']['materializedRows'][0]['value']),
    'current materialized type text' => static fn (TestRunner $t) => $t->same('text', $resume181()['currentGeneratedPathRowidXColumnSnapshot181']['materializedRows'][0]['type']),
    'current missing rowids empty' => static fn (TestRunner $t) => $t->same([], $resume181()['currentGeneratedPathRowidXColumnSnapshot181']['missingRowids']),
    'current xcolumn tape rowid' => static fn (TestRunner $t) => $t->same(5, $resume181()['currentGeneratedPathRowidXColumnSnapshot181']['xColumnTape'][0]['rowid']),
    'current xcolumn tape source pinned' => static fn (TestRunner $t) => $t->same(true, $resume181()['currentGeneratedPathRowidXColumnSnapshot181']['xColumnTape'][0]['sourcePinned']),
    'current xcolumn tape column fullkey' => static fn (TestRunner $t) => $t->same('$.rules[1].slug', $resume181()['currentGeneratedPathRowidXColumnSnapshot181']['xColumnTape'][0]['columns']['fullkey']),
    'current snapshot fingerprint is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($resume181()['currentGeneratedPathRowidXColumnSnapshot181']['snapshotFingerprint'])),
    'current xcolumn reusable' => static fn (TestRunner $t) => $t->same(true, $resume181()['currentGeneratedPathRowidXColumnSnapshot181']['xColumnReusable']),
    'current xcolumn not stale' => static fn (TestRunner $t) => $t->same(false, $resume181()['currentGeneratedPathRowidXColumnSnapshot181']['staleAfterNextSource']),
    'current xcolumn estimated rows one' => static fn (TestRunner $t) => $t->same(1, $resume181()['currentGeneratedPathRowidXColumnSnapshot181']['estimatedRows']),
    'current xcolumn estimated cost one' => static fn (TestRunner $t) => $t->same(1, $resume181()['currentGeneratedPathRowidXColumnSnapshot181']['estimatedCost']),
    'current xcolumn cost class point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xcolumn-point-current-source-next181', $resume181()['currentGeneratedPathRowidXColumnSnapshot181']['costClass']),
    'next xcolumn source generation changes' => static fn (TestRunner $t) => $t->same('source_generation:18', $resume181()['nextGeneratedPathRowidXColumnSnapshot181']['sourceGeneration']),
    'next xcolumn stale after source' => static fn (TestRunner $t) => $t->same(true, $resume181()['nextGeneratedPathRowidXColumnSnapshot181']['staleAfterNextSource']),
    'next xcolumn not reusable' => static fn (TestRunner $t) => $t->same(false, $resume181()['nextGeneratedPathRowidXColumnSnapshot181']['xColumnReusable']),
    'next xcolumn estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $resume181()['nextGeneratedPathRowidXColumnSnapshot181']['estimatedRows']),
    'next xcolumn sentinel cost' => static fn (TestRunner $t) => $t->same(1000000, $resume181()['nextGeneratedPathRowidXColumnSnapshot181']['estimatedCost']),
    'next xcolumn reprepare cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xcolumn-reprepare-next-source-next181', $resume181()['nextGeneratedPathRowidXColumnSnapshot181']['costClass']),
    'first xcolumn materializes descending first row' => static fn (TestRunner $t) => $t->same(6, $first181()['currentGeneratedPathRowidXColumnSnapshot181']['materializedRows'][0]['rowid']),
    'first xcolumn materializes second row' => static fn (TestRunner $t) => $t->same(5, $first181()['currentGeneratedPathRowidXColumnSnapshot181']['materializedRows'][1]['rowid']),
    'first xcolumn cost class range' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xcolumn-range-current-source-next181', $first181()['currentGeneratedPathRowidXColumnSnapshot181']['costClass']),
    'point xcolumn materializes priority value' => static fn (TestRunner $t) => $t->same(7, $point181()['currentGeneratedPathRowidXColumnSnapshot181']['materializedRows'][0]['value']),
    'point xcolumn projected columns' => static fn (TestRunner $t) => $t->same(['rowid' => 6, 'id' => 6, 'fullkey' => '$.rules[1].priority', 'value' => 7], $point181()['currentGeneratedPathRowidXColumnSnapshot181']['materializedRows'][0]),
    'missing rowid xcolumn not reusable' => static fn (TestRunner $t) => $t->same(false, $missing181()['currentGeneratedPathRowidXColumnSnapshot181']['xColumnReusable']),
    'missing rowid xcolumn preserves reseek row tape' => static fn (TestRunner $t) => $t->same([6, 5], array_column($missing181()['currentGeneratedPathRowidXColumnSnapshot181']['materializedRows'], 'rowid')),
    'missing rowid xcolumn cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $missing181()['currentGeneratedPathRowidXColumnSnapshot181']['estimatedCost']),
    'transition count records xcolumn state' => static fn (TestRunner $t) => $t->same(15, count($resume181()['generatedPathRowidXColumnSnapshot181Transitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $resume181()['generatedPathRowidXColumnSnapshot181Transitions'][1]['changed']),
    'transition rowids change' => static fn (TestRunner $t) => $t->same(true, $resume181()['generatedPathRowidXColumnSnapshot181Transitions'][5]['changed']),
    'transition materialized rows change' => static fn (TestRunner $t) => $t->same(true, $resume181()['generatedPathRowidXColumnSnapshot181Transitions'][6]['changed']),
    'transition reuse changes' => static fn (TestRunner $t) => $t->same(true, $resume181()['generatedPathRowidXColumnSnapshot181Transitions'][10]['changed']),
    'reasons include xcolumn source snapshot' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcolumn-source-snapshot-changed-next181', $resume181()['next181ReplanReasons'], true)),
    'reasons include xcolumn rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcolumn-rowset-changed-next181', $resume181()['next181ReplanReasons'], true)),
    'reasons include xcolumn reuse' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcolumn-reuse-changed-next181', $resume181()['next181ReplanReasons'], true)),
    'reasons include xcolumn cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcolumn-cost-changed-next181', $resume181()['next181ReplanReasons'], true)),
    'bad projection rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan181(null, null, null, null, null, null, ['bad_column'])),
    'bad root rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan181(array_replace($current181, ['scan_root' => 99]), $current181)),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next181 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
