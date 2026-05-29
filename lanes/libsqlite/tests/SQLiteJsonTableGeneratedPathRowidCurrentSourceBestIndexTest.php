<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current173 = [
    'option_id' => 173,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next173',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
];
$next173 = [
    'option_id' => 173,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next173',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
];

$plan173 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceBestIndexPlan(
    'json_tree',
    $current ?? $current173,
    $next ?? $next173,
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

$stable173 = static fn (): array => $plan173($current173, $current173);
$single173 = static fn (): array => $plan173(
    array_replace($current173, ['generated_path' => '$.rules[1]']),
    array_replace($current173, ['generated_path' => '$.rules[1]']),
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'oid', 'operator' => '=', 'value' => 6],
    ],
    [['column' => 'path', 'direction' => 'DESC']],
);
$limited173 = static fn (): array => $plan173(null, null, null, [['column' => 'id', 'direction' => 'ASC']], 1);
$sorter173 = static fn (): array => $plan173(null, null, null, [['column' => 'path', 'direction' => 'ASC']]);
$unusable173 = static fn (): array => $plan173(
    $current173,
    $next173,
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%', 'usable' => false],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6]],
    ],
    [['column' => 'id', 'direction' => 'DESC']],
);
$jsonb173 = static fn (): array => $plan173(
    $current173,
    array_replace($current173, ['option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($current173['option_value'])))]),
);
$nullNext173 = static fn (): array => $plan173($current173, array_replace($next173, ['option_value' => null]));

$tests = [
    'records next173 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next173', $plan173()['dependencies'], true)),
    'preserves next167 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next167', $plan173()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-path-rowid-cost-current-source-next173-until-xbestindex-reset', $plan173()['currentReaderPolicy']),
    'prepares changed next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-path-rowid-cost-current-source-next173-bestindex', $plan173()['nextReaderPolicy']),
    'stable reuses next reader policy' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-cost-current-source-next173-bestindex', $stable173()['nextReaderPolicy']),
    'stable has no next173 reasons' => static fn (TestRunner $t) => $t->same([], $stable173()['next173ReplanReasons']),
    'current source token records generated path' => static fn (TestRunner $t) => $t->same('$.rules', $plan173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['sourceToken']['generatedPath']),
    'next source token records changed generated path' => static fn (TestRunner $t) => $t->same('$.rules[2]', $plan173()['nextGeneratedPathRowidCurrentSourceBestIndex173']['sourceToken']['generatedPath']),
    'current generated path column recorded' => static fn (TestRunner $t) => $t->same('generated_path', $plan173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['generatedPathColumn']),
    'current generated path usable' => static fn (TestRunner $t) => $t->same(true, $plan173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['generatedPathUsable']),
    'current rowid usable' => static fn (TestRunner $t) => $t->same(true, $plan173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['rowidUsable']),
    'current source reusable' => static fn (TestRunner $t) => $t->same(true, $plan173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['sourceReusable']),
    'current source pinned' => static fn (TestRunner $t) => $t->same(true, $plan173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['currentSourcePinned']),
    'current eof false' => static fn (TestRunner $t) => $t->same(false, $plan173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['eof']),
    'current idxnum combines path rowid and pinned bits' => static fn (TestRunner $t) => $t->same(7, $plan173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['idxNum']),
    'next idxnum falls back to eof scan' => static fn (TestRunner $t) => $t->same(8, $plan173()['nextGeneratedPathRowidCurrentSourceBestIndex173']['idxNum']),
    'current idxstr records scan' => static fn (TestRunner $t) => $t->same('generated-path-rowid-current-source-next173|path|rowid|pinned|scan', $plan173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['idxStr']),
    'next idxstr records reprepare eof' => static fn (TestRunner $t) => $t->same('generated-path-rowid-current-source-next173|reprepare|eof', $plan173()['nextGeneratedPathRowidCurrentSourceBestIndex173']['idxStr']),
    'argv columns preserve path and id' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['argvColumns']),
    'argv values preserve path and rowid list' => static fn (TestRunner $t) => $t->same(['$.rules%', [5, 6, 42]], $plan173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['argvValues']),
    'omit columns preserve pushed constraints' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['omitColumns']),
    'rowid scope is in list' => static fn (TestRunner $t) => $t->same(['operator' => 'IN', 'value' => [5, 6, 42], 'kind' => 'in-list', 'count' => 3], $plan173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['rowidScope']),
    'current ordered rowids follow xfilter order' => static fn (TestRunner $t) => $t->same([6, 5], $plan173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['orderedOutputRowids']),
    'current ordered paths follow generated path scope' => static fn (TestRunner $t) => $t->same(['$.rules[1]', '$.rules[1]'], $plan173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['orderedOutputPaths']),
    'current output row count two' => static fn (TestRunner $t) => $t->same(2, $plan173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['outputRowCount']),
    'current estimated rows two' => static fn (TestRunner $t) => $t->same(2, $plan173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['estimatedRows']),
    'current estimated cost two' => static fn (TestRunner $t) => $t->same(2, $plan173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['estimatedCost']),
    'current planner cost tightens to one' => static fn (TestRunner $t) => $t->same(1, $plan173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['plannerCost']),
    'current cost class range' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-bestindex-range-current-source', $plan173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['costClass']),
    'fingerprint is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['bestIndexFingerprint'])),
    'next eof true' => static fn (TestRunner $t) => $t->same(true, $plan173()['nextGeneratedPathRowidCurrentSourceBestIndex173']['eof']),
    'next output rowids empty' => static fn (TestRunner $t) => $t->same([], $plan173()['nextGeneratedPathRowidCurrentSourceBestIndex173']['orderedOutputRowids']),
    'next planner cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $plan173()['nextGeneratedPathRowidCurrentSourceBestIndex173']['plannerCost']),
    'next cost class empty' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-bestindex-empty-current-source', $plan173()['nextGeneratedPathRowidCurrentSourceBestIndex173']['costClass']),
    'fingerprint changes with next source' => static fn (TestRunner $t) => $t->true($plan173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['bestIndexFingerprint'] !== $plan173()['nextGeneratedPathRowidCurrentSourceBestIndex173']['bestIndexFingerprint']),
    'single point rowid scope' => static fn (TestRunner $t) => $t->same(['operator' => '=', 'value' => 6, 'kind' => 'point', 'count' => 1], $single173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['rowidScope']),
    'single point cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-bestindex-point-current-source', $single173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['costClass']),
    'single point planner cost one' => static fn (TestRunner $t) => $t->same(1, $single173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['plannerCost']),
    'limited rowids capped' => static fn (TestRunner $t) => $t->same([5], $limited173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['orderedOutputRowids']),
    'limited cost class point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-bestindex-point-current-source', $limited173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['costClass']),
    'sorter still admits bestindex range' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-bestindex-range-current-source', $sorter173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['costClass']),
    'unusable path clears generated path usability' => static fn (TestRunner $t) => $t->same(false, $unusable173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['generatedPathUsable']),
    'unusable path clears rowid usability after reprepare' => static fn (TestRunner $t) => $t->same(false, $unusable173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['rowidUsable']),
    'unusable path idxnum eof only' => static fn (TestRunner $t) => $t->same(8, $unusable173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['idxNum']),
    'unusable path cost class empty' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-bestindex-empty-current-source', $unusable173()['currentGeneratedPathRowidCurrentSourceBestIndex173']['costClass']),
    'jsonb next remains pinned' => static fn (TestRunner $t) => $t->same(true, $jsonb173()['nextGeneratedPathRowidCurrentSourceBestIndex173']['currentSourcePinned']),
    'jsonb next idxnum pinned scan' => static fn (TestRunner $t) => $t->same(7, $jsonb173()['nextGeneratedPathRowidCurrentSourceBestIndex173']['idxNum']),
    'null next is empty cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-bestindex-empty-current-source', $nullNext173()['nextGeneratedPathRowidCurrentSourceBestIndex173']['costClass']),
    'transition count records bestindex state' => static fn (TestRunner $t) => $t->same(11, count($plan173()['generatedPathRowidCurrentSourceBestIndex173Transitions'])),
    'transition source token changes' => static fn (TestRunner $t) => $t->same(true, $plan173()['generatedPathRowidCurrentSourceBestIndex173Transitions'][0]['changed']),
    'transition idxnum changes' => static fn (TestRunner $t) => $t->same(true, $plan173()['generatedPathRowidCurrentSourceBestIndex173Transitions'][3]['changed']),
    'transition argv values stable' => static fn (TestRunner $t) => $t->same(false, $plan173()['generatedPathRowidCurrentSourceBestIndex173Transitions'][5]['changed']),
    'transition rowset changes' => static fn (TestRunner $t) => $t->same(true, $plan173()['generatedPathRowidCurrentSourceBestIndex173Transitions'][7]['changed']),
    'transition planner cost changes' => static fn (TestRunner $t) => $t->same(true, $plan173()['generatedPathRowidCurrentSourceBestIndex173Transitions'][8]['changed']),
    'reasons include bestindex source token' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-bestindex-source-token-changed', $plan173()['next173ReplanReasons'], true)),
    'reasons include bestindex admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-bestindex-admission-changed', $plan173()['next173ReplanReasons'], true)),
    'reasons include bestindex rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-bestindex-rowset-changed', $plan173()['next173ReplanReasons'], true)),
    'reasons include bestindex cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-bestindex-cost-changed', $plan173()['next173ReplanReasons'], true)),
    'reasons include prior filter rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-filter-rowset-changed', $plan173()['next173ReplanReasons'], true)),
    'negative limit rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan173(null, null, null, null, -1)),
    'bad order direction rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan173(null, null, null, [['column' => 'id', 'direction' => 'SIDEWAYS']])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next173 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
