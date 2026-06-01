<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current170 = [
    'setting_id' => 170,
    'key_name' => 'app_plugin_generated_path_rowid_current_source_cost',
    'key_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"load_policy":"yes"}}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
];
$next170 = [
    'setting_id' => 170,
    'key_name' => 'app_plugin_generated_path_rowid_current_source_cost',
    'key_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"load_policy":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
];
$constraints170 = [
    ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ['column' => '_rowid_', 'operator' => '=', 'value' => 6],
];
$order170 = [['column' => 'path'], ['column' => 'rowid']];

$plan170 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceCost(
    'json_tree',
    $current ?? $current170,
    $next ?? $next170,
    'key_value',
    'generated_path',
    $constraints ?? $constraints170,
    'scan_root',
    $orderBy ?? $order170,
);

$stable170 = static fn (): array => $plan170($current170, $current170);
$range170 = static fn (): array => $plan170(
    array_replace($current170, ['generated_path' => '$.rules']),
    array_replace($current170, ['generated_path' => '$.rules']),
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [4, 9]],
        ['column' => 'type', 'operator' => 'IN', 'value' => ['object', 'integer']],
    ],
    [],
);
$jsonb170 = static fn (): array => $plan170(
    $current170,
    array_replace($current170, ['key_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($current170['key_value'])))]),
);
$unrunnable170 = static fn (): array => $plan170($current170, array_replace($next170, ['key_value' => null]));

$tests = [
    'records current-source dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-current-source-cost', $plan170()['dependencies'], true)),
    'preserves next166 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next166', $plan170()['dependencies'], true)),
    'pins current current-source policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-path-rowid-current-source-cost-until-xfilter-reset', $plan170()['currentReaderPolicy']),
    'prepares changed current-source policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-path-rowid-current-source-cost-plan', $plan170()['nextReaderPolicy']),
    'stable current-source policy reuses current' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-current-source-cost-plan', $stable170()['nextReaderPolicy']),
    'stable current-source reasons empty' => static fn (TestRunner $t) => $t->same([], $stable170()['generatedPathRowidCurrentSourceReplanReasons']),
    'current setting id recorded' => static fn (TestRunner $t) => $t->same(170, $plan170()['currentGeneratedPathRowidCurrentSource']['sourceSettingId']),
    'current key name recorded' => static fn (TestRunner $t) => $t->same('app_plugin_generated_path_rowid_current_source_cost', $plan170()['currentGeneratedPathRowidCurrentSource']['sourceKeyName']),
    'current root recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan170()['currentGeneratedPathRowidCurrentSource']['sourceRoot']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules[1]', $plan170()['currentGeneratedPathRowidCurrentSource']['generatedPath']),
    'current idx num records path rowid order covering' => static fn (TestRunner $t) => $t->same(15, $plan170()['currentGeneratedPathRowidCurrentSource']['idxNum']),
    'current idx str records rowid alias' => static fn (TestRunner $t) => $t->same('path|rowid:_rowid_|order|covering|json-table-generated-path-rowid-point', $plan170()['currentGeneratedPathRowidCurrentSource']['idxStr']),
    'current argv values remain empty for omitted virtual constraints' => static fn (TestRunner $t) => $t->same([], $plan170()['currentGeneratedPathRowidCurrentSource']['argvValues']),
    'current argv columns remain empty for omitted virtual constraints' => static fn (TestRunner $t) => $t->same([], $plan170()['currentGeneratedPathRowidCurrentSource']['argvColumns']),
    'current omit columns are path and id' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan170()['currentGeneratedPathRowidCurrentSource']['omitColumns']),
    'current residual columns empty' => static fn (TestRunner $t) => $t->same([], $plan170()['currentGeneratedPathRowidCurrentSource']['residualColumns']),
    'current order by consumed' => static fn (TestRunner $t) => $t->same(true, $plan170()['currentGeneratedPathRowidCurrentSource']['orderByConsumed']),
    'current source pinned' => static fn (TestRunner $t) => $t->same(true, $plan170()['currentGeneratedPathRowidCurrentSource']['currentSourcePinned']),
    'current cursor mode is point' => static fn (TestRunner $t) => $t->same('pinned-current-source-point', $plan170()['currentGeneratedPathRowidCurrentSource']['cursorMode']),
    'current cursor row count is one' => static fn (TestRunner $t) => $t->same(1, $plan170()['currentGeneratedPathRowidCurrentSource']['cursorRowCount']),
    'current rowid tape pins one row' => static fn (TestRunner $t) => $t->same([6], $plan170()['currentGeneratedPathRowidCurrentSource']['rowidTape']),
    'current path tape pins one path' => static fn (TestRunner $t) => $t->same(['$.rules[1]'], $plan170()['currentGeneratedPathRowidCurrentSource']['pathTape']),
    'current first cursor row records rowid' => static fn (TestRunner $t) => $t->same(6, $plan170()['currentGeneratedPathRowidCurrentSource']['firstCursorRow']['rowid']),
    'current first cursor row records path' => static fn (TestRunner $t) => $t->same('$.rules[1]', $plan170()['currentGeneratedPathRowidCurrentSource']['firstCursorRow']['path']),
    'current first cursor row records pinned source' => static fn (TestRunner $t) => $t->same(true, $plan170()['currentGeneratedPathRowidCurrentSource']['firstCursorRow']['currentSourcePinned']),
    'current last cursor row equals first for point' => static fn (TestRunner $t) => $t->same($plan170()['currentGeneratedPathRowidCurrentSource']['firstCursorRow'], $plan170()['currentGeneratedPathRowidCurrentSource']['lastCursorRow']),
    'current estimated rows is point' => static fn (TestRunner $t) => $t->same(1, $plan170()['currentGeneratedPathRowidCurrentSource']['estimatedRows']),
    'current estimated cost is point' => static fn (TestRunner $t) => $t->same(1, $plan170()['currentGeneratedPathRowidCurrentSource']['estimatedCost']),
    'current cost class is point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-point', $plan170()['currentGeneratedPathRowidCurrentSource']['costClass']),
    'current plan fingerprint is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan170()['currentGeneratedPathRowidCurrentSource']['planFingerprint'])),
    'next changed generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules[2]', $plan170()['nextGeneratedPathRowidCurrentSource']['generatedPath']),
    'next changed source uses fresh xfilter' => static fn (TestRunner $t) => $t->same('fresh-json-table-xfilter', $plan170()['nextGeneratedPathRowidCurrentSource']['cursorMode']),
    'next changed source has no pinned rows' => static fn (TestRunner $t) => $t->same([], $plan170()['nextGeneratedPathRowidCurrentSource']['cursorRows']),
    'next changed source cost is fresh' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-fresh', $plan170()['nextGeneratedPathRowidCurrentSource']['costClass']),
    'transition count records tracked fields' => static fn (TestRunner $t) => $t->same(15, count($plan170()['generatedPathRowidCurrentSourceTransitions'])),
    'source transition changes' => static fn (TestRunner $t) => $t->same(true, $plan170()['generatedPathRowidCurrentSourceTransitions'][2]['changed']),
    'idx str transition changes' => static fn (TestRunner $t) => $t->same(true, $plan170()['generatedPathRowidCurrentSourceTransitions'][4]['changed']),
    'cursor mode transition changes' => static fn (TestRunner $t) => $t->same(true, $plan170()['generatedPathRowidCurrentSourceTransitions'][7]['changed']),
    'rowid tape transition changes' => static fn (TestRunner $t) => $t->same(true, $plan170()['generatedPathRowidCurrentSourceTransitions'][9]['changed']),
    'cost class transition changes' => static fn (TestRunner $t) => $t->same(true, $plan170()['generatedPathRowidCurrentSourceTransitions'][13]['changed']),
    'current-source reasons include source change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-source-changed', $plan170()['generatedPathRowidCurrentSourceReplanReasons'], true)),
    'current-source reasons include xfilter change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-xfilter-changed', $plan170()['generatedPathRowidCurrentSourceReplanReasons'], true)),
    'current-source reasons include admission change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-admission-changed', $plan170()['generatedPathRowidCurrentSourceReplanReasons'], true)),
    'current-source reasons include rowset change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-rowset-changed', $plan170()['generatedPathRowidCurrentSourceReplanReasons'], true)),
    'current-source reasons include cost change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-cost-changed', $plan170()['generatedPathRowidCurrentSourceReplanReasons'], true)),
    'range cursor keeps rowids in virtual table order' => static fn (TestRunner $t) => $t->same([4, 6, 7, 9], $range170()['currentGeneratedPathRowidCurrentSource']['rowidTape']),
    'range cursor keeps path tape' => static fn (TestRunner $t) => $t->same(['$.rules', '$.rules[1]', '$.rules', '$.rules[2]'], $range170()['currentGeneratedPathRowidCurrentSource']['pathTape']),
    'range cursor has residual type and path filters' => static fn (TestRunner $t) => $t->same(['path', 'id', 'type'], $range170()['currentGeneratedPathRowidCurrentSource']['residualColumns']),
    'range cursor remains fresh because LIKE path is residual' => static fn (TestRunner $t) => $t->same('fresh-json-table-xfilter', $range170()['currentGeneratedPathRowidCurrentSource']['cursorMode']),
    'range cursor row count is four' => static fn (TestRunner $t) => $t->same(4, $range170()['currentGeneratedPathRowidCurrentSource']['cursorRowCount']),
    'jsonb next remains pinned point' => static fn (TestRunner $t) => $t->same('pinned-current-source-point', $jsonb170()['nextGeneratedPathRowidCurrentSource']['cursorMode']),
    'jsonb next remains point cost' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-point', $jsonb170()['nextGeneratedPathRowidCurrentSource']['costClass']),
    'unrunnable next cost class sentinel' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable170()['nextGeneratedPathRowidCurrentSource']['costClass']),
    'unrunnable next mode is fresh' => static fn (TestRunner $t) => $t->same('fresh-json-table-xfilter', $unrunnable170()['nextGeneratedPathRowidCurrentSource']['cursorMode']),
    'bad json source column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceCost('json_tree', $current170, $next170, '', 'generated_path', $constraints170)),
    'bad generated path column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceCost('json_tree', $current170, $next170, 'key_value', '', $constraints170)),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid current source cost ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
