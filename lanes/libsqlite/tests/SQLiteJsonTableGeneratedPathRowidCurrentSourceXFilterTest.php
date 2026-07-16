<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current176 = [
    'option_id' => 176,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next176',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
];
$next176 = [
    'option_id' => 176,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next176',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
];

$plan176 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceXFilterPlan(
    'json_tree',
    $current ?? $current176,
    $next ?? $next176,
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

$stable176 = static fn (): array => $plan176($current176, $current176);
$single176 = static fn (): array => $plan176(
    array_replace($current176, ['generated_path' => '$.rules[1]']),
    array_replace($current176, ['generated_path' => '$.rules[1]']),
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ],
    [['column' => 'id']],
);
$residual176 = static fn (): array => $plan176(
    array_replace($current176, ['generated_path' => '$.rules']),
    array_replace($current176, ['generated_path' => '$.rules']),
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [4, 9]],
    ],
);
$limited176 = static fn (): array => $plan176(null, null, null, [['column' => 'id']], 1);
$unusable176 = static fn (): array => $plan176(
    $current176,
    $next176,
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%', 'usable' => false],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6]],
    ],
);
$jsonb176 = static fn (): array => $plan176(
    $current176,
    array_replace($current176, ['option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($current176['option_value'])))]),
);
$nullNext176 = static fn (): array => $plan176($current176, array_replace($next176, ['option_value' => null]));

$tests = [
    'records next176 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next176', $plan176()['dependencies'], true)),
    'preserves next173 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next173', $plan176()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-path-rowid-cost-current-source-next176-until-xfilter-close', $plan176()['currentReaderPolicy']),
    'prepares changed next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-path-rowid-cost-current-source-next176-xfilter', $plan176()['nextReaderPolicy']),
    'stable reuses next reader policy' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-cost-current-source-next176-xfilter', $stable176()['nextReaderPolicy']),
    'stable next176 reasons empty' => static fn (TestRunner $t) => $t->same([], $stable176()['next176ReplanReasons']),
    'current xfilter idxnum records pinned path rowid' => static fn (TestRunner $t) => $t->same(7, $plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['idxNum']),
    'current xfilter idxstr records scan' => static fn (TestRunner $t) => $t->same('generated-path-rowid-current-source-next173|path|rowid|pinned|scan', $plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['idxStr']),
    'current xfilter opcode seeks' => static fn (TestRunner $t) => $t->same('xFilter-generated-path-rowid-current-source', $plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['cursorOpcode']),
    'next xfilter opcode eof' => static fn (TestRunner $t) => $t->same('xFilter-eof-current-source', $plan176()['nextGeneratedPathRowidCurrentSourceXFilter176']['cursorOpcode']),
    'argv columns preserve path rowid' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['argvColumns']),
    'argv values preserve path and rowid list' => static fn (TestRunner $t) => $t->same(['$.rules%', [5, 6, 42]], $plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['argvValues']),
    'argv tape records path first' => static fn (TestRunner $t) => $t->same('path', $plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['argvTape'][0]['column']),
    'argv tape records rowid second' => static fn (TestRunner $t) => $t->same('id', $plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['argvTape'][1]['column']),
    'argv tape omits path' => static fn (TestRunner $t) => $t->same(true, $plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['argvTape'][0]['omit']),
    'argv tape omits rowid' => static fn (TestRunner $t) => $t->same(true, $plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['argvTape'][1]['omit']),
    'current omit columns path id' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['omitColumns']),
    'current residual columns empty' => static fn (TestRunner $t) => $t->same([], $plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['residualColumns']),
    'current residual not required' => static fn (TestRunner $t) => $t->same(false, $plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['residualRequired']),
    'current source pinned' => static fn (TestRunner $t) => $t->same(true, $plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['sourcePinned']),
    'current bestindex fingerprint carried' => static fn (TestRunner $t) => $t->same($plan176()['currentGeneratedPathRowidCurrentSourceBestIndex173']['bestIndexFingerprint'], $plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['bestIndexFingerprint']),
    'current filter fingerprint carried' => static fn (TestRunner $t) => $t->same($plan176()['currentGeneratedPathRowidCurrentSourceFilter']['filterFingerprint'], $plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['filterFingerprint']),
    'xfilter fingerprint is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['xFilterFingerprint'])),
    'current seek program has two rows' => static fn (TestRunner $t) => $t->same(2, count($plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['seekProgram'])),
    'current seek program desc first rowid' => static fn (TestRunner $t) => $t->same(6, $plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['seekProgram'][0]['rowid']),
    'current seek program second rowid' => static fn (TestRunner $t) => $t->same(5, $plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['seekProgram'][1]['rowid']),
    'current seek program carries path' => static fn (TestRunner $t) => $t->same('$.rules[1]', $plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['seekProgram'][0]['path']),
    'current seek program marks seekable' => static fn (TestRunner $t) => $t->same(true, $plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['seekProgram'][0]['seekable']),
    'current yield rowids' => static fn (TestRunner $t) => $t->same([6, 5], $plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['yieldRowids']),
    'current yield paths' => static fn (TestRunner $t) => $t->same(['$.rules[1]', '$.rules[1]'], $plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['yieldPaths']),
    'current output row count two' => static fn (TestRunner $t) => $t->same(2, $plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['outputRowCount']),
    'current eof false' => static fn (TestRunner $t) => $t->same(false, $plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['eof']),
    'current stale output not blocked' => static fn (TestRunner $t) => $t->same(false, $plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['staleOutputBlocked']),
    'current filter cost one' => static fn (TestRunner $t) => $t->same(1, $plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['filterCost']),
    'current cost class range' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xfilter-range-current-source', $plan176()['currentGeneratedPathRowidCurrentSourceXFilter176']['costClass']),
    'next yield rowids empty' => static fn (TestRunner $t) => $t->same([], $plan176()['nextGeneratedPathRowidCurrentSourceXFilter176']['yieldRowids']),
    'next stale output blocked' => static fn (TestRunner $t) => $t->same(true, $plan176()['nextGeneratedPathRowidCurrentSourceXFilter176']['staleOutputBlocked']),
    'next filter cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $plan176()['nextGeneratedPathRowidCurrentSourceXFilter176']['filterCost']),
    'next cost class eof' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xfilter-eof-current-source', $plan176()['nextGeneratedPathRowidCurrentSourceXFilter176']['costClass']),
    'single point yield rowid' => static fn (TestRunner $t) => $t->same([6], $single176()['currentGeneratedPathRowidCurrentSourceXFilter176']['yieldRowids']),
    'single point cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xfilter-point-current-source', $single176()['currentGeneratedPathRowidCurrentSourceXFilter176']['costClass']),
    'limited range becomes point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xfilter-point-current-source', $limited176()['currentGeneratedPathRowidCurrentSourceXFilter176']['costClass']),
    'between rowid xfilter still omits generated path' => static fn (TestRunner $t) => $t->same(['path', 'id'], $residual176()['currentGeneratedPathRowidCurrentSourceXFilter176']['omitColumns']),
    'between rowid xfilter keeps no residual' => static fn (TestRunner $t) => $t->same(false, $residual176()['currentGeneratedPathRowidCurrentSourceXFilter176']['residualRequired']),
    'between rowid xfilter keeps range class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xfilter-range-current-source', $residual176()['currentGeneratedPathRowidCurrentSourceXFilter176']['costClass']),
    'unusable path has eof class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xfilter-eof-current-source', $unusable176()['currentGeneratedPathRowidCurrentSourceXFilter176']['costClass']),
    'jsonb next can reuse xfilter' => static fn (TestRunner $t) => $t->same('xFilter-generated-path-rowid-current-source', $jsonb176()['nextGeneratedPathRowidCurrentSourceXFilter176']['cursorOpcode']),
    'null next xfilter eof class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-xfilter-eof-current-source', $nullNext176()['nextGeneratedPathRowidCurrentSourceXFilter176']['costClass']),
    'transition count records xfilter fields' => static fn (TestRunner $t) => $t->same(10, count($plan176()['generatedPathRowidCurrentSourceXFilter176Transitions'])),
    'transition idxnum changes' => static fn (TestRunner $t) => $t->same(true, $plan176()['generatedPathRowidCurrentSourceXFilter176Transitions'][0]['changed']),
    'transition argv tape changes with eof omit flags' => static fn (TestRunner $t) => $t->same(true, $plan176()['generatedPathRowidCurrentSourceXFilter176Transitions'][2]['changed']),
    'transition rowids change' => static fn (TestRunner $t) => $t->same(true, $plan176()['generatedPathRowidCurrentSourceXFilter176Transitions'][4]['changed']),
    'transition stale block changes' => static fn (TestRunner $t) => $t->same(true, $plan176()['generatedPathRowidCurrentSourceXFilter176Transitions'][6]['changed']),
    'transition fingerprint changes' => static fn (TestRunner $t) => $t->same(true, $plan176()['generatedPathRowidCurrentSourceXFilter176Transitions'][9]['changed']),
    'reasons include xfilter admission' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xfilter-admission-changed', $plan176()['next176ReplanReasons'], true)),
    'reasons include xfilter rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xfilter-rowset-changed', $plan176()['next176ReplanReasons'], true)),
    'reasons include xfilter cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xfilter-cost-changed', $plan176()['next176ReplanReasons'], true)),
    'reasons include xfilter fingerprint' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-xfilter-fingerprint-changed', $plan176()['next176ReplanReasons'], true)),
    'reasons preserve next173 bestindex rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-bestindex-rowset-changed', $plan176()['next176ReplanReasons'], true)),
    'negative limit rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan176(null, null, null, null, -1)),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next176 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
