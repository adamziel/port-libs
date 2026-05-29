<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current170 = [
    'option_id' => 170,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next170',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
];
$next170 = [
    'option_id' => 170,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next170',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
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
    'option_value',
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
    array_replace($current170, ['option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($current170['option_value'])))]),
);
$unrunnable170 = static fn (): array => $plan170($current170, array_replace($next170, ['option_value' => null]));

$tests = [
    'records next170 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next170', $plan170()['dependencies'], true)),
    'preserves next166 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next166', $plan170()['dependencies'], true)),
    'pins current next170 policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-path-rowid-cost-current-source-next170-until-xfilter-reset', $plan170()['currentReaderPolicy']),
    'prepares changed next170 policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-path-rowid-current-source-next170-plan', $plan170()['nextReaderPolicy']),
    'stable next170 policy reuses current' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-current-source-next170-plan', $stable170()['nextReaderPolicy']),
    'stable next170 reasons empty' => static fn (TestRunner $t) => $t->same([], $stable170()['next170ReplanReasons']),
    'current option id recorded' => static fn (TestRunner $t) => $t->same(170, $plan170()['currentGeneratedPathRowidCurrentSourceNext170']['sourceOptionId']),
    'current option name recorded' => static fn (TestRunner $t) => $t->same('wp_plugin_generated_path_rowid_cost_current_source_next170', $plan170()['currentGeneratedPathRowidCurrentSourceNext170']['sourceOptionName']),
    'current root recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan170()['currentGeneratedPathRowidCurrentSourceNext170']['sourceRoot']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules[1]', $plan170()['currentGeneratedPathRowidCurrentSourceNext170']['generatedPath']),
    'current idx num records path rowid order covering' => static fn (TestRunner $t) => $t->same(15, $plan170()['currentGeneratedPathRowidCurrentSourceNext170']['idxNum']),
    'current idx str records rowid alias' => static fn (TestRunner $t) => $t->same('path|rowid:_rowid_|order|covering|json-table-generated-path-rowid-point', $plan170()['currentGeneratedPathRowidCurrentSourceNext170']['idxStr']),
    'current argv values remain empty for omitted virtual constraints' => static fn (TestRunner $t) => $t->same([], $plan170()['currentGeneratedPathRowidCurrentSourceNext170']['argvValues']),
    'current argv columns remain empty for omitted virtual constraints' => static fn (TestRunner $t) => $t->same([], $plan170()['currentGeneratedPathRowidCurrentSourceNext170']['argvColumns']),
    'current omit columns are path and id' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan170()['currentGeneratedPathRowidCurrentSourceNext170']['omitColumns']),
    'current residual columns empty' => static fn (TestRunner $t) => $t->same([], $plan170()['currentGeneratedPathRowidCurrentSourceNext170']['residualColumns']),
    'current order by consumed' => static fn (TestRunner $t) => $t->same(true, $plan170()['currentGeneratedPathRowidCurrentSourceNext170']['orderByConsumed']),
    'current source pinned' => static fn (TestRunner $t) => $t->same(true, $plan170()['currentGeneratedPathRowidCurrentSourceNext170']['currentSourcePinned']),
    'current cursor mode is point' => static fn (TestRunner $t) => $t->same('pinned-current-source-point', $plan170()['currentGeneratedPathRowidCurrentSourceNext170']['cursorMode']),
    'current cursor row count is one' => static fn (TestRunner $t) => $t->same(1, $plan170()['currentGeneratedPathRowidCurrentSourceNext170']['cursorRowCount']),
    'current rowid tape pins one row' => static fn (TestRunner $t) => $t->same([6], $plan170()['currentGeneratedPathRowidCurrentSourceNext170']['rowidTape']),
    'current path tape pins one path' => static fn (TestRunner $t) => $t->same(['$.rules[1]'], $plan170()['currentGeneratedPathRowidCurrentSourceNext170']['pathTape']),
    'current first cursor row records rowid' => static fn (TestRunner $t) => $t->same(6, $plan170()['currentGeneratedPathRowidCurrentSourceNext170']['firstCursorRow']['rowid']),
    'current first cursor row records path' => static fn (TestRunner $t) => $t->same('$.rules[1]', $plan170()['currentGeneratedPathRowidCurrentSourceNext170']['firstCursorRow']['path']),
    'current first cursor row records pinned source' => static fn (TestRunner $t) => $t->same(true, $plan170()['currentGeneratedPathRowidCurrentSourceNext170']['firstCursorRow']['currentSourcePinned']),
    'current last cursor row equals first for point' => static fn (TestRunner $t) => $t->same($plan170()['currentGeneratedPathRowidCurrentSourceNext170']['firstCursorRow'], $plan170()['currentGeneratedPathRowidCurrentSourceNext170']['lastCursorRow']),
    'current estimated rows is point' => static fn (TestRunner $t) => $t->same(1, $plan170()['currentGeneratedPathRowidCurrentSourceNext170']['estimatedRows']),
    'current estimated cost is point' => static fn (TestRunner $t) => $t->same(1, $plan170()['currentGeneratedPathRowidCurrentSourceNext170']['estimatedCost']),
    'current cost class is point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-point', $plan170()['currentGeneratedPathRowidCurrentSourceNext170']['costClass']),
    'current plan fingerprint is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan170()['currentGeneratedPathRowidCurrentSourceNext170']['planFingerprint'])),
    'next changed generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules[2]', $plan170()['nextGeneratedPathRowidCurrentSourceNext170']['generatedPath']),
    'next changed source uses fresh xfilter' => static fn (TestRunner $t) => $t->same('fresh-json-table-xfilter', $plan170()['nextGeneratedPathRowidCurrentSourceNext170']['cursorMode']),
    'next changed source has no pinned rows' => static fn (TestRunner $t) => $t->same([], $plan170()['nextGeneratedPathRowidCurrentSourceNext170']['cursorRows']),
    'next changed source cost is fresh' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-fresh', $plan170()['nextGeneratedPathRowidCurrentSourceNext170']['costClass']),
    'transition count records tracked fields' => static fn (TestRunner $t) => $t->same(15, count($plan170()['generatedPathRowidCurrentSourceNext170Transitions'])),
    'source transition changes' => static fn (TestRunner $t) => $t->same(true, $plan170()['generatedPathRowidCurrentSourceNext170Transitions'][2]['changed']),
    'idx str transition changes' => static fn (TestRunner $t) => $t->same(true, $plan170()['generatedPathRowidCurrentSourceNext170Transitions'][4]['changed']),
    'cursor mode transition changes' => static fn (TestRunner $t) => $t->same(true, $plan170()['generatedPathRowidCurrentSourceNext170Transitions'][7]['changed']),
    'rowid tape transition changes' => static fn (TestRunner $t) => $t->same(true, $plan170()['generatedPathRowidCurrentSourceNext170Transitions'][9]['changed']),
    'cost class transition changes' => static fn (TestRunner $t) => $t->same(true, $plan170()['generatedPathRowidCurrentSourceNext170Transitions'][13]['changed']),
    'next170 reasons include source change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-next170-source-changed', $plan170()['next170ReplanReasons'], true)),
    'next170 reasons include xfilter change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-next170-xfilter-changed', $plan170()['next170ReplanReasons'], true)),
    'next170 reasons include admission change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-next170-admission-changed', $plan170()['next170ReplanReasons'], true)),
    'next170 reasons include rowset change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-next170-rowset-changed', $plan170()['next170ReplanReasons'], true)),
    'next170 reasons include cost change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-next170-cost-changed', $plan170()['next170ReplanReasons'], true)),
    'range cursor keeps rowids in virtual table order' => static fn (TestRunner $t) => $t->same([4, 6, 7, 9], $range170()['currentGeneratedPathRowidCurrentSourceNext170']['rowidTape']),
    'range cursor keeps path tape' => static fn (TestRunner $t) => $t->same(['$.rules', '$.rules[1]', '$.rules', '$.rules[2]'], $range170()['currentGeneratedPathRowidCurrentSourceNext170']['pathTape']),
    'range cursor has residual type and path filters' => static fn (TestRunner $t) => $t->same(['path', 'id', 'type'], $range170()['currentGeneratedPathRowidCurrentSourceNext170']['residualColumns']),
    'range cursor remains fresh because LIKE path is residual' => static fn (TestRunner $t) => $t->same('fresh-json-table-xfilter', $range170()['currentGeneratedPathRowidCurrentSourceNext170']['cursorMode']),
    'range cursor row count is four' => static fn (TestRunner $t) => $t->same(4, $range170()['currentGeneratedPathRowidCurrentSourceNext170']['cursorRowCount']),
    'jsonb next remains pinned point' => static fn (TestRunner $t) => $t->same('pinned-current-source-point', $jsonb170()['nextGeneratedPathRowidCurrentSourceNext170']['cursorMode']),
    'jsonb next remains point cost' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-point', $jsonb170()['nextGeneratedPathRowidCurrentSourceNext170']['costClass']),
    'unrunnable next cost class sentinel' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable170()['nextGeneratedPathRowidCurrentSourceNext170']['costClass']),
    'unrunnable next mode is fresh' => static fn (TestRunner $t) => $t->same('fresh-json-table-xfilter', $unrunnable170()['nextGeneratedPathRowidCurrentSourceNext170']['cursorMode']),
    'bad json source column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceCost('json_tree', $current170, $next170, '', 'generated_path', $constraints170)),
    'bad generated path column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceCost('json_tree', $current170, $next170, 'option_value', '', $constraints170)),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid current source cost ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
