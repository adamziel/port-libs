<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentOption = [
    'option_id' => 181,
    'option_name' => 'wp_plugin_generated_path_rowid_xcolumn_snapshot',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 17,
];
$nextOption = [
    'option_id' => 181,
    'option_name' => 'wp_plugin_generated_path_rowid_xcolumn_snapshot',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 18,
];

$plan = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = null,
    ?int $lastYieldedRowid = null,
    ?array $projection = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidXColumnSnapshotPlan(
    'json_tree',
    $current ?? $currentOption,
    $next ?? $nextOption,
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

$resume = static fn (): array => $plan(null, null, null, null, null, 6);
$stable = static fn (): array => $plan($currentOption, $currentOption, null, null, null, 6);
$first = static fn (): array => $plan($currentOption, $currentOption);
$point = static fn (): array => $plan(
    array_replace($currentOption, ['generated_path' => '$.rules[1]', 'source_generation' => 'same']),
    array_replace($currentOption, ['generated_path' => '$.rules[1]', 'source_generation' => 'same']),
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ],
    [['column' => 'id']],
    null,
    null,
    ['id', 'fullkey', 'value'],
);
$missing = static fn (): array => $plan($currentOption, $currentOption, null, null, null, 99);

$tests = [
    'records xcolumn snapshot dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-xcolumn-snapshot', $resume()['dependencies'], true)),
    'preserves next178 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next178', $resume()['dependencies'], true)),
    'current reader materializes xcolumn snapshot' => static fn (TestRunner $t) => $t->same('materialize-current-json-table-generated-path-rowid-xcolumn-snapshot', $resume()['currentReaderPolicy']),
    'next changed source reparses xcolumn snapshot' => static fn (TestRunner $t) => $t->same('reprepare-next-json-table-generated-path-rowid-xcolumn-snapshot', $resume()['nextReaderPolicy']),
    'stable next source reuses xcolumn snapshot' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-xcolumn-snapshot', $stable()['nextReaderPolicy']),
    'stable xcolumn snapshot reasons empty' => static fn (TestRunner $t) => $t->same([], $stable()['xColumnSnapshotReplanReasons']),
    'current xcolumn function normalized' => static fn (TestRunner $t) => $t->same('json_tree', $resume()['currentGeneratedPathRowidXColumnSnapshot']['function']),
    'current xcolumn source kind text' => static fn (TestRunner $t) => $t->same('text', $resume()['currentGeneratedPathRowidXColumnSnapshot']['jsonSourceKind']),
    'current xcolumn source generation pinned' => static fn (TestRunner $t) => $t->same('source_generation:17', $resume()['currentGeneratedPathRowidXColumnSnapshot']['sourceGeneration']),
    'current xcolumn cache key is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($resume()['currentGeneratedPathRowidXColumnSnapshot']['cacheKey'])),
    'current xcolumn cursor generation is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($resume()['currentGeneratedPathRowidXColumnSnapshot']['cursorGeneration'])),
    'current projection normalized' => static fn (TestRunner $t) => $t->same(['id', 'fullkey', 'atom', 'value', 'type'], $resume()['currentGeneratedPathRowidXColumnSnapshot']['projection']),
    'current rowids resume remaining rowid' => static fn (TestRunner $t) => $t->same([5], $resume()['currentGeneratedPathRowidXColumnSnapshot']['rowids']),
    'current materialized one row' => static fn (TestRunner $t) => $t->same(1, count($resume()['currentGeneratedPathRowidXColumnSnapshot']['materializedRows'])),
    'current materialized rowid' => static fn (TestRunner $t) => $t->same(5, $resume()['currentGeneratedPathRowidXColumnSnapshot']['materializedRows'][0]['rowid']),
    'current materialized id' => static fn (TestRunner $t) => $t->same(5, $resume()['currentGeneratedPathRowidXColumnSnapshot']['materializedRows'][0]['id']),
    'current materialized fullkey from pinned source' => static fn (TestRunner $t) => $t->same('$.rules[1].slug', $resume()['currentGeneratedPathRowidXColumnSnapshot']['materializedRows'][0]['fullkey']),
    'current materialized atom from pinned source' => static fn (TestRunner $t) => $t->same('cache', $resume()['currentGeneratedPathRowidXColumnSnapshot']['materializedRows'][0]['atom']),
    'current materialized value from pinned source' => static fn (TestRunner $t) => $t->same('cache', $resume()['currentGeneratedPathRowidXColumnSnapshot']['materializedRows'][0]['value']),
    'current materialized type text' => static fn (TestRunner $t) => $t->same('text', $resume()['currentGeneratedPathRowidXColumnSnapshot']['materializedRows'][0]['type']),
    'current missing rowids empty' => static fn (TestRunner $t) => $t->same([], $resume()['currentGeneratedPathRowidXColumnSnapshot']['missingRowids']),
    'current xcolumn tape rowid' => static fn (TestRunner $t) => $t->same(5, $resume()['currentGeneratedPathRowidXColumnSnapshot']['xColumnTape'][0]['rowid']),
    'current xcolumn tape source pinned' => static fn (TestRunner $t) => $t->same(true, $resume()['currentGeneratedPathRowidXColumnSnapshot']['xColumnTape'][0]['sourcePinned']),
    'current xcolumn tape column fullkey' => static fn (TestRunner $t) => $t->same('$.rules[1].slug', $resume()['currentGeneratedPathRowidXColumnSnapshot']['xColumnTape'][0]['columns']['fullkey']),
    'current snapshot fingerprint is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($resume()['currentGeneratedPathRowidXColumnSnapshot']['snapshotFingerprint'])),
    'current xcolumn reusable' => static fn (TestRunner $t) => $t->same(true, $resume()['currentGeneratedPathRowidXColumnSnapshot']['xColumnReusable']),
    'current xcolumn not stale' => static fn (TestRunner $t) => $t->same(false, $resume()['currentGeneratedPathRowidXColumnSnapshot']['staleAfterNextSource']),
    'current xcolumn estimated rows one' => static fn (TestRunner $t) => $t->same(1, $resume()['currentGeneratedPathRowidXColumnSnapshot']['estimatedRows']),
    'current xcolumn estimated cost one' => static fn (TestRunner $t) => $t->same(1, $resume()['currentGeneratedPathRowidXColumnSnapshot']['estimatedCost']),
    'current xcolumn cost class point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xcolumn-point-current-source', $resume()['currentGeneratedPathRowidXColumnSnapshot']['costClass']),
    'next xcolumn source generation changes' => static fn (TestRunner $t) => $t->same('source_generation:18', $resume()['nextGeneratedPathRowidXColumnSnapshot']['sourceGeneration']),
    'next xcolumn stale after source' => static fn (TestRunner $t) => $t->same(true, $resume()['nextGeneratedPathRowidXColumnSnapshot']['staleAfterNextSource']),
    'next xcolumn not reusable' => static fn (TestRunner $t) => $t->same(false, $resume()['nextGeneratedPathRowidXColumnSnapshot']['xColumnReusable']),
    'next xcolumn estimated rows zero' => static fn (TestRunner $t) => $t->same(0, $resume()['nextGeneratedPathRowidXColumnSnapshot']['estimatedRows']),
    'next xcolumn sentinel cost' => static fn (TestRunner $t) => $t->same(1000000, $resume()['nextGeneratedPathRowidXColumnSnapshot']['estimatedCost']),
    'next xcolumn reprepare cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xcolumn-reprepare-next-source', $resume()['nextGeneratedPathRowidXColumnSnapshot']['costClass']),
    'first xcolumn materializes descending first row' => static fn (TestRunner $t) => $t->same(6, $first()['currentGeneratedPathRowidXColumnSnapshot']['materializedRows'][0]['rowid']),
    'first xcolumn materializes second row' => static fn (TestRunner $t) => $t->same(5, $first()['currentGeneratedPathRowidXColumnSnapshot']['materializedRows'][1]['rowid']),
    'first xcolumn cost class range' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xcolumn-range-current-source', $first()['currentGeneratedPathRowidXColumnSnapshot']['costClass']),
    'point xcolumn materializes priority value' => static fn (TestRunner $t) => $t->same(7, $point()['currentGeneratedPathRowidXColumnSnapshot']['materializedRows'][0]['value']),
    'point xcolumn projected columns' => static fn (TestRunner $t) => $t->same(['rowid' => 6, 'id' => 6, 'fullkey' => '$.rules[1].priority', 'value' => 7], $point()['currentGeneratedPathRowidXColumnSnapshot']['materializedRows'][0]),
    'missing rowid xcolumn not reusable' => static fn (TestRunner $t) => $t->same(false, $missing()['currentGeneratedPathRowidXColumnSnapshot']['xColumnReusable']),
    'missing rowid xcolumn preserves reseek row tape' => static fn (TestRunner $t) => $t->same([6, 5], array_column($missing()['currentGeneratedPathRowidXColumnSnapshot']['materializedRows'], 'rowid')),
    'missing rowid xcolumn cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $missing()['currentGeneratedPathRowidXColumnSnapshot']['estimatedCost']),
    'transition count records xcolumn state' => static fn (TestRunner $t) => $t->same(15, count($resume()['generatedPathRowidXColumnSnapshotTransitions'])),
    'transition source generation changes' => static fn (TestRunner $t) => $t->same(true, $resume()['generatedPathRowidXColumnSnapshotTransitions'][1]['changed']),
    'transition rowids change' => static fn (TestRunner $t) => $t->same(true, $resume()['generatedPathRowidXColumnSnapshotTransitions'][5]['changed']),
    'transition materialized rows change' => static fn (TestRunner $t) => $t->same(true, $resume()['generatedPathRowidXColumnSnapshotTransitions'][6]['changed']),
    'transition reuse changes' => static fn (TestRunner $t) => $t->same(true, $resume()['generatedPathRowidXColumnSnapshotTransitions'][10]['changed']),
    'reasons include xcolumn source snapshot' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcolumn-source-snapshot-changed', $resume()['xColumnSnapshotReplanReasons'], true)),
    'reasons include xcolumn rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcolumn-rowset-changed', $resume()['xColumnSnapshotReplanReasons'], true)),
    'reasons include xcolumn reuse' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcolumn-reuse-changed', $resume()['xColumnSnapshotReplanReasons'], true)),
    'reasons include xcolumn cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xcolumn-cost-changed', $resume()['xColumnSnapshotReplanReasons'], true)),
    'bad projection rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan(null, null, null, null, null, null, ['bad_column'])),
    'bad root rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan(array_replace($currentOption, ['scan_root' => 99]), $currentOption)),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid xcolumn snapshot ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
