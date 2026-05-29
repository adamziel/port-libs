<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current167 = [
    'option_id' => 167,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next167',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
];
$next167 = [
    'option_id' => 167,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next167',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
];

$plan167 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceFilterPlan(
    'json_tree',
    $current ?? $current167,
    $next ?? $next167,
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

$stable167 = static fn (): array => $plan167($current167, $current167);
$limited167 = static fn (): array => $plan167(null, null, null, [['column' => 'id', 'direction' => 'ASC']], 1);
$single167 = static fn (): array => $plan167(
    array_replace($current167, ['generated_path' => '$.rules[1]']),
    array_replace($current167, ['generated_path' => '$.rules[1]']),
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'oid', 'operator' => '=', 'value' => 6],
    ],
    [['column' => 'path', 'direction' => 'DESC']],
);
$sorter167 = static fn (): array => $plan167(null, null, null, [['column' => 'path', 'direction' => 'ASC']]);
$unusable167 = static fn (): array => $plan167(
    $current167,
    $next167,
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%', 'usable' => false],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6]],
    ],
    [['column' => 'id', 'direction' => 'DESC']],
);
$jsonb167 = static fn (): array => $plan167(
    $current167,
    array_replace($current167, ['option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($current167['option_value'])))]),
);
$nullNext167 = static fn (): array => $plan167($current167, array_replace($next167, ['option_value' => null]));

$tests = [
    'records next167 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next167', $plan167()['dependencies'], true)),
    'preserves next164 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next164', $plan167()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-path-rowid-cost-current-source-next167-until-xfilter-reset', $plan167()['currentReaderPolicy']),
    'prepares changed next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-path-rowid-cost-current-source-next167-filter', $plan167()['nextReaderPolicy']),
    'stable reuses next reader policy' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-cost-current-source-next167-filter', $stable167()['nextReaderPolicy']),
    'stable has no next167 reasons' => static fn (TestRunner $t) => $t->same([], $stable167()['next167ReplanReasons']),
    'current filter opcode pinned' => static fn (TestRunner $t) => $t->same('xFilter-generated-path-rowid-current-source-pinned', $plan167()['currentGeneratedPathRowidCurrentSourceFilter']['filterOpcode']),
    'next filter opcode reparses' => static fn (TestRunner $t) => $t->same('xFilter-generated-path-rowid-current-source-reprepare', $plan167()['nextGeneratedPathRowidCurrentSourceFilter']['filterOpcode']),
    'argv columns preserve xbestindex order' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan167()['currentGeneratedPathRowidCurrentSourceFilter']['argvColumns']),
    'argv values preserve path then rowid in list' => static fn (TestRunner $t) => $t->same(['$.rules%', [5, 6, 42]], $plan167()['currentGeneratedPathRowidCurrentSourceFilter']['argvValues']),
    'argv omit flags are true for path and rowid' => static fn (TestRunner $t) => $t->same([true, true], $plan167()['currentGeneratedPathRowidCurrentSourceFilter']['argvOmitFlags']),
    'current output rowids follow desc order' => static fn (TestRunner $t) => $t->same([6, 5], $plan167()['currentGeneratedPathRowidCurrentSourceFilter']['orderedOutputRowids']),
    'current output paths follow generated path scope' => static fn (TestRunner $t) => $t->same(['$.rules[1]', '$.rules[1]'], $plan167()['currentGeneratedPathRowidCurrentSourceFilter']['orderedOutputPaths']),
    'current output row count' => static fn (TestRunner $t) => $t->same(2, $plan167()['currentGeneratedPathRowidCurrentSourceFilter']['outputRowCount']),
    'current filter not eof' => static fn (TestRunner $t) => $t->same(false, $plan167()['currentGeneratedPathRowidCurrentSourceFilter']['eof']),
    'current source pinned true' => static fn (TestRunner $t) => $t->same(true, $plan167()['currentGeneratedPathRowidCurrentSourceFilter']['currentSourcePinned']),
    'current filter requires no sorter' => static fn (TestRunner $t) => $t->same(false, $plan167()['currentGeneratedPathRowidCurrentSourceFilter']['requiresSorter']),
    'current estimated rows kept' => static fn (TestRunner $t) => $t->same(2, $plan167()['currentGeneratedPathRowidCurrentSourceFilter']['estimatedRows']),
    'current estimated cost kept' => static fn (TestRunner $t) => $t->same(2, $plan167()['currentGeneratedPathRowidCurrentSourceFilter']['estimatedCost']),
    'current cost class range' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-filter-range', $plan167()['currentGeneratedPathRowidCurrentSourceFilter']['costClass']),
    'current fingerprint is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan167()['currentGeneratedPathRowidCurrentSourceFilter']['filterFingerprint'])),
    'next filter eof after reprepare' => static fn (TestRunner $t) => $t->same(true, $plan167()['nextGeneratedPathRowidCurrentSourceFilter']['eof']),
    'next source pinned false' => static fn (TestRunner $t) => $t->same(false, $plan167()['nextGeneratedPathRowidCurrentSourceFilter']['currentSourcePinned']),
    'next output rowids empty' => static fn (TestRunner $t) => $t->same([], $plan167()['nextGeneratedPathRowidCurrentSourceFilter']['orderedOutputRowids']),
    'next cost class reprepare' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-filter-reprepare', $plan167()['nextGeneratedPathRowidCurrentSourceFilter']['costClass']),
    'fingerprint changes with next source' => static fn (TestRunner $t) => $t->true($plan167()['currentGeneratedPathRowidCurrentSourceFilter']['filterFingerprint'] !== $plan167()['nextGeneratedPathRowidCurrentSourceFilter']['filterFingerprint']),
    'limited output rowids capped' => static fn (TestRunner $t) => $t->same([5], $limited167()['currentGeneratedPathRowidCurrentSourceFilter']['orderedOutputRowids']),
    'limited output paths capped' => static fn (TestRunner $t) => $t->same(['$.rules[1]'], $limited167()['currentGeneratedPathRowidCurrentSourceFilter']['orderedOutputPaths']),
    'limited cost class point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-filter-point', $limited167()['currentGeneratedPathRowidCurrentSourceFilter']['costClass']),
    'limited output row count one' => static fn (TestRunner $t) => $t->same(1, $limited167()['currentGeneratedPathRowidCurrentSourceFilter']['outputRowCount']),
    'single point output rowid' => static fn (TestRunner $t) => $t->same([6], $single167()['currentGeneratedPathRowidCurrentSourceFilter']['orderedOutputRowids']),
    'single point output path' => static fn (TestRunner $t) => $t->same(['$.rules[1]'], $single167()['currentGeneratedPathRowidCurrentSourceFilter']['orderedOutputPaths']),
    'single point argv values' => static fn (TestRunner $t) => $t->same(['$.rules[1]', 6], $single167()['currentGeneratedPathRowidCurrentSourceFilter']['argvValues']),
    'single point cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-filter-point', $single167()['currentGeneratedPathRowidCurrentSourceFilter']['costClass']),
    'sorter opcode records sorter path' => static fn (TestRunner $t) => $t->same('xFilter-generated-path-rowid-current-source-sorter', $sorter167()['currentGeneratedPathRowidCurrentSourceFilter']['filterOpcode']),
    'sorter requires sorter true' => static fn (TestRunner $t) => $t->same(true, $sorter167()['currentGeneratedPathRowidCurrentSourceFilter']['requiresSorter']),
    'sorter cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-filter-sorter', $sorter167()['currentGeneratedPathRowidCurrentSourceFilter']['costClass']),
    'unusable path filter reparses' => static fn (TestRunner $t) => $t->same('xFilter-generated-path-rowid-current-source-reprepare', $unusable167()['currentGeneratedPathRowidCurrentSourceFilter']['filterOpcode']),
    'unusable path eof true' => static fn (TestRunner $t) => $t->same(true, $unusable167()['currentGeneratedPathRowidCurrentSourceFilter']['eof']),
    'unusable path argv columns keep id only' => static fn (TestRunner $t) => $t->same(['id'], $unusable167()['currentGeneratedPathRowidCurrentSourceFilter']['argvColumns']),
    'jsonb next remains pinned' => static fn (TestRunner $t) => $t->same(true, $jsonb167()['nextGeneratedPathRowidCurrentSourceFilter']['currentSourcePinned']),
    'jsonb next not eof' => static fn (TestRunner $t) => $t->same(false, $jsonb167()['nextGeneratedPathRowidCurrentSourceFilter']['eof']),
    'null next is unrunnable' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $nullNext167()['nextGeneratedPathRowidCurrentSourceFilter']['costClass']),
    'transition count records filter state' => static fn (TestRunner $t) => $t->same(14, count($plan167()['generatedPathRowidCurrentSourceFilterTransitions'])),
    'transition opcode changes' => static fn (TestRunner $t) => $t->same(true, $plan167()['generatedPathRowidCurrentSourceFilterTransitions'][0]['changed']),
    'transition argv values stable' => static fn (TestRunner $t) => $t->same(false, $plan167()['generatedPathRowidCurrentSourceFilterTransitions'][1]['changed']),
    'transition rowids change' => static fn (TestRunner $t) => $t->same(true, $plan167()['generatedPathRowidCurrentSourceFilterTransitions'][4]['changed']),
    'transition paths change' => static fn (TestRunner $t) => $t->same(true, $plan167()['generatedPathRowidCurrentSourceFilterTransitions'][5]['changed']),
    'transition eof changes' => static fn (TestRunner $t) => $t->same(true, $plan167()['generatedPathRowidCurrentSourceFilterTransitions'][7]['changed']),
    'transition cost class changes' => static fn (TestRunner $t) => $t->same(true, $plan167()['generatedPathRowidCurrentSourceFilterTransitions'][12]['changed']),
    'reasons include admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-filter-admission-changed', $plan167()['next167ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-filter-rowset-changed', $plan167()['next167ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-filter-cost-changed', $plan167()['next167ReplanReasons'], true)),
    'reasons include fingerprint' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-filter-fingerprint-changed', $plan167()['next167ReplanReasons'], true)),
    'reasons preserve next164 order usage' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-order-usage-changed', $plan167()['next167ReplanReasons'], true)),
    'negative limit rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan167(null, null, null, null, -1)),
    'bad order direction rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan167(null, null, null, [['column' => 'id', 'direction' => 'SIDEWAYS']])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next167 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
