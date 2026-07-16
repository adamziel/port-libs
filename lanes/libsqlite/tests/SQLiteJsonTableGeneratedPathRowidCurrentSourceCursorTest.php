<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current171 = [
    'setting_id' => 171,
    'key_name' => 'app_plugin_generated_path_rowid_cost_current_source_next171',
    'key_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"load_policy":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
];
$next171 = [
    'setting_id' => 171,
    'key_name' => 'app_plugin_generated_path_rowid_cost_current_source_next171',
    'key_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"load_policy":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
];

$plan171 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceCursorPlan(
    'json_tree',
    $current ?? $current171,
    $next ?? $next171,
    'key_value',
    'generated_path',
    $constraints ?? [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'IN', 'value' => [5, 6, 42]],
    ],
    'scan_root',
    $orderBy ?? [['column' => '_rowid_', 'direction' => 'DESC']],
    $limit,
);

$stable171 = static fn (): array => $plan171($current171, $current171);
$limited171 = static fn (): array => $plan171(null, null, null, [['column' => 'id', 'direction' => 'ASC']], 1);
$single171 = static fn (): array => $plan171(
    array_replace($current171, ['generated_path' => '$.rules[1]']),
    array_replace($current171, ['generated_path' => '$.rules[1]']),
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'oid', 'operator' => '=', 'value' => 6],
    ],
    [['column' => 'path', 'direction' => 'DESC']],
);
$sorter171 = static fn (): array => $plan171(null, null, null, [['column' => 'path', 'direction' => 'ASC']]);
$unusable171 = static fn (): array => $plan171(
    $current171,
    $next171,
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%', 'usable' => false],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6]],
    ],
    [['column' => 'id', 'direction' => 'DESC']],
);
$jsonb171 = static fn (): array => $plan171(
    $current171,
    array_replace($current171, ['key_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($current171['key_value'])))]),
);
$nullNext171 = static fn (): array => $plan171($current171, array_replace($next171, ['key_value' => null]));

$tests = [
    'records next171 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next171', $plan171()['dependencies'], true)),
    'preserves next167 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next167', $plan171()['dependencies'], true)),
    'pins current reader policy until xnext eof' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-path-rowid-cost-current-source-next171-until-xnext-eof', $plan171()['currentReaderPolicy']),
    'prepares changed next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-path-rowid-cost-current-source-next171-cursor', $plan171()['nextReaderPolicy']),
    'stable reuses next reader policy' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-cost-current-source-next171-cursor', $stable171()['nextReaderPolicy']),
    'stable has no next171 reasons' => static fn (TestRunner $t) => $t->same([], $stable171()['next171ReplanReasons']),
    'current cursor opcode pinned' => static fn (TestRunner $t) => $t->same('xNext-generated-path-rowid-current-source', $plan171()['currentGeneratedPathRowidCurrentSourceCursor']['cursorOpcode']),
    'next cursor opcode reparses' => static fn (TestRunner $t) => $t->same('xNext-generated-path-rowid-current-source-reprepare', $plan171()['nextGeneratedPathRowidCurrentSourceCursor']['cursorOpcode']),
    'source setting id retained' => static fn (TestRunner $t) => $t->same(171, $plan171()['currentGeneratedPathRowidCurrentSourceCursor']['sourceSettingId']),
    'source setting name retained' => static fn (TestRunner $t) => $t->same('app_plugin_generated_path_rowid_cost_current_source_next171', $plan171()['currentGeneratedPathRowidCurrentSourceCursor']['sourceKeyName']),
    'generated path retained' => static fn (TestRunner $t) => $t->same('$.rules', $plan171()['currentGeneratedPathRowidCurrentSourceCursor']['generatedPath']),
    'rowid alias is normalized into argv column' => static fn (TestRunner $t) => $t->same(null, $plan171()['currentGeneratedPathRowidCurrentSourceCursor']['rowidAlias']),
    'argv columns keep xfilter shape' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan171()['currentGeneratedPathRowidCurrentSourceCursor']['argvColumns']),
    'seek rowids preserve requested ints' => static fn (TestRunner $t) => $t->same([5, 6, 42], $plan171()['currentGeneratedPathRowidCurrentSourceCursor']['seekRowids']),
    'yield rowids follow filter order' => static fn (TestRunner $t) => $t->same([6, 5], $plan171()['currentGeneratedPathRowidCurrentSourceCursor']['yieldRowids']),
    'missing seek rowids retained' => static fn (TestRunner $t) => $t->same([42], $plan171()['currentGeneratedPathRowidCurrentSourceCursor']['missingSeekRowids']),
    'skipped rowids empty for full range' => static fn (TestRunner $t) => $t->same([], $plan171()['currentGeneratedPathRowidCurrentSourceCursor']['skippedRowids']),
    'yield count two' => static fn (TestRunner $t) => $t->same(2, $plan171()['currentGeneratedPathRowidCurrentSourceCursor']['yieldCount']),
    'first yield rowid desc' => static fn (TestRunner $t) => $t->same(6, $plan171()['currentGeneratedPathRowidCurrentSourceCursor']['firstYieldRowid']),
    'last yield rowid desc' => static fn (TestRunner $t) => $t->same(5, $plan171()['currentGeneratedPathRowidCurrentSourceCursor']['lastYieldRowid']),
    'eof after yield true' => static fn (TestRunner $t) => $t->same(true, $plan171()['currentGeneratedPathRowidCurrentSourceCursor']['eofAfterYield']),
    'current source pinned true' => static fn (TestRunner $t) => $t->same(true, $plan171()['currentGeneratedPathRowidCurrentSourceCursor']['currentSourcePinned']),
    'requires sorter false' => static fn (TestRunner $t) => $t->same(false, $plan171()['currentGeneratedPathRowidCurrentSourceCursor']['requiresSorter']),
    'estimated rows kept' => static fn (TestRunner $t) => $t->same(2, $plan171()['currentGeneratedPathRowidCurrentSourceCursor']['estimatedRows']),
    'estimated cost kept' => static fn (TestRunner $t) => $t->same(2, $plan171()['currentGeneratedPathRowidCurrentSourceCursor']['estimatedCost']),
    'cost class partial because requested rowid missing' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cursor-partial', $plan171()['currentGeneratedPathRowidCurrentSourceCursor']['costClass']),
    'cursor fingerprint is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan171()['currentGeneratedPathRowidCurrentSourceCursor']['cursorFingerprint'])),
    'program begins with xcolumn row' => static fn (TestRunner $t) => $t->same('xColumn-current-source-rowid-path', $plan171()['currentGeneratedPathRowidCurrentSourceCursor']['yieldProgram'][0]['opcode']),
    'program first rowid desc' => static fn (TestRunner $t) => $t->same(6, $plan171()['currentGeneratedPathRowidCurrentSourceCursor']['yieldProgram'][0]['rowid']),
    'program second uses xnext' => static fn (TestRunner $t) => $t->same('xNext-current-source-rowid-path', $plan171()['currentGeneratedPathRowidCurrentSourceCursor']['yieldProgram'][1]['opcode']),
    'program ends with eof' => static fn (TestRunner $t) => $t->same('xEof-current-source-rowid-path', $plan171()['currentGeneratedPathRowidCurrentSourceCursor']['yieldProgram'][2]['opcode']),
    'program eof flag set on final step' => static fn (TestRunner $t) => $t->same(true, $plan171()['currentGeneratedPathRowidCurrentSourceCursor']['yieldProgram'][2]['eof']),
    'next program reparses only' => static fn (TestRunner $t) => $t->same('xReprepare-json-table-cursor', $plan171()['nextGeneratedPathRowidCurrentSourceCursor']['yieldProgram'][0]['opcode']),
    'next yield rowids empty' => static fn (TestRunner $t) => $t->same([], $plan171()['nextGeneratedPathRowidCurrentSourceCursor']['yieldRowids']),
    'next current source pinned false' => static fn (TestRunner $t) => $t->same(false, $plan171()['nextGeneratedPathRowidCurrentSourceCursor']['currentSourcePinned']),
    'limited yield rowids capped' => static fn (TestRunner $t) => $t->same([5], $limited171()['currentGeneratedPathRowidCurrentSourceCursor']['yieldRowids']),
    'limited skipped rowids records post-limit row' => static fn (TestRunner $t) => $t->same([6], $limited171()['currentGeneratedPathRowidCurrentSourceCursor']['skippedRowids']),
    'limited class partial' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cursor-partial', $limited171()['currentGeneratedPathRowidCurrentSourceCursor']['costClass']),
    'single point class point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cursor-point', $single171()['currentGeneratedPathRowidCurrentSourceCursor']['costClass']),
    'single point yield rowid' => static fn (TestRunner $t) => $t->same([6], $single171()['currentGeneratedPathRowidCurrentSourceCursor']['yieldRowids']),
    'sorter cursor opcode records sorter' => static fn (TestRunner $t) => $t->same('xNext-generated-path-rowid-current-source-sorter', $sorter171()['currentGeneratedPathRowidCurrentSourceCursor']['cursorOpcode']),
    'sorter class records sorter' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-cursor-sorter', $sorter171()['currentGeneratedPathRowidCurrentSourceCursor']['costClass']),
    'unusable path reparses cursor' => static fn (TestRunner $t) => $t->same('xNext-generated-path-rowid-current-source-reprepare', $unusable171()['currentGeneratedPathRowidCurrentSourceCursor']['cursorOpcode']),
    'jsonb next remains pinned' => static fn (TestRunner $t) => $t->same(true, $jsonb171()['nextGeneratedPathRowidCurrentSourceCursor']['currentSourcePinned']),
    'jsonb next yields rows' => static fn (TestRunner $t) => $t->same([6, 5], $jsonb171()['nextGeneratedPathRowidCurrentSourceCursor']['yieldRowids']),
    'null next is unrunnable' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $nullNext171()['nextGeneratedPathRowidCurrentSourceCursor']['costClass']),
    'transition count records cursor state' => static fn (TestRunner $t) => $t->same(16, count($plan171()['generatedPathRowidCurrentSourceCursorTransitions'])),
    'transition opcode changes' => static fn (TestRunner $t) => $t->same(true, $plan171()['generatedPathRowidCurrentSourceCursorTransitions'][0]['changed']),
    'transition seek rowids stable' => static fn (TestRunner $t) => $t->same(false, $plan171()['generatedPathRowidCurrentSourceCursorTransitions'][4]['changed']),
    'transition yield program changes' => static fn (TestRunner $t) => $t->same(true, $plan171()['generatedPathRowidCurrentSourceCursorTransitions'][5]['changed']),
    'transition yield rowids changes' => static fn (TestRunner $t) => $t->same(true, $plan171()['generatedPathRowidCurrentSourceCursorTransitions'][6]['changed']),
    'transition missing rowids changes' => static fn (TestRunner $t) => $t->same(true, $plan171()['generatedPathRowidCurrentSourceCursorTransitions'][8]['changed']),
    'transition pinned changes' => static fn (TestRunner $t) => $t->same(true, $plan171()['generatedPathRowidCurrentSourceCursorTransitions'][10]['changed']),
    'transition cost class changes' => static fn (TestRunner $t) => $t->same(true, $plan171()['generatedPathRowidCurrentSourceCursorTransitions'][14]['changed']),
    'reasons include cursor admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-cursor-admission-changed', $plan171()['next171ReplanReasons'], true)),
    'reasons include cursor rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-cursor-rowset-changed', $plan171()['next171ReplanReasons'], true)),
    'reasons include cursor cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-cursor-cost-changed', $plan171()['next171ReplanReasons'], true)),
    'reasons preserve next167 filter rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-filter-rowset-changed', $plan171()['next171ReplanReasons'], true)),
    'negative limit rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan171(null, null, null, null, -1)),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next171 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
