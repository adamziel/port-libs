<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current143 = [
    'option_id' => 143,
    'option_name' => 'wp_plugin_hidden_path_generated',
    'option_value' => '{"rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":true},{"slug":"forms","priority":4,"enabled":false}],"meta":{"version":1}}',
    'scan_root' => '$.rules',
];
$next143 = [
    'option_id' => 143,
    'option_name' => 'wp_plugin_hidden_path_generated',
    'option_value' => '{"rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":8,"enabled":false},{"slug":"forms","priority":4,"enabled":false},{"slug":"shop","priority":8,"enabled":true}],"meta":{"version":2}}',
    'scan_root' => '$.rules',
];

$constraints143 = [
    ['column' => 'path', 'operator' => '=', 'value' => '$.rules'],
    ['column' => 'rowid', 'operator' => '=', 'value' => 5],
    ['column' => 'type', 'operator' => '=', 'value' => 'object'],
];
$generated143 = [
    ['name' => 'slug', 'path' => '$.slug', 'value' => 'cache'],
    ['name' => 'priority', 'path' => '$.priority', 'operator' => 'BETWEEN', 'value' => [5, 9]],
    ['name' => 'enabled', 'path' => '$.enabled', 'operator' => '=', 'value' => 1],
];

$plan143 = static fn (?array $current = null, ?array $next = null, ?array $generated = null): array => SQLiteJsonTablePlan::currentSourceHiddenPathGenerated(
    'json_tree',
    $current ?? $current143,
    $next ?? $next143,
    'option_value',
    $constraints143,
    'scan_root',
    [['column' => 'path'], ['column' => 'rowid']],
    $generated ?? $generated143,
);

$stable143 = static fn (): array => $plan143($current143, $current143);
$miss143 = static fn (): array => SQLiteJsonTablePlan::currentSourceHiddenPathGenerated(
    'json_tree',
    $current143,
    $next143,
    'option_value',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[9]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 99],
    ],
    'scan_root',
    [],
    $generated143,
);
$jsonSource143 = static fn (): array => $plan143(
    $current143,
    $current143,
    [['name' => 'siteVersion', 'source' => 'json', 'path' => '$.meta.version', 'value' => 1]],
);
$jsonb143 = static fn (): array => $plan143(
    $current143,
    array_replace($current143, ['option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($current143['option_value'])))]),
);
$unrunnable143 = static fn (): array => $plan143(
    $current143,
    array_replace($next143, ['option_value' => null]),
);
$disabled143 = static fn (): array => $plan143(
    $current143,
    $next143,
    [
        ['name' => 'slug', 'path' => '$.slug', 'value' => 'cache'],
        ['name' => 'enabled', 'path' => '$.enabled', 'operator' => '=', 'value' => 0],
    ],
);

$tests = [
    'normalizes function' => static fn (TestRunner $t) => $t->same('json_tree', $plan143()['function']),
    'records hidden path generated dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-hidden-path-generated', $plan143()['dependencies'], true)),
    'preserves hidden path rowid dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-hidden-path-rowid', $plan143()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-hidden-path-generated-source-until-cursor-reset', $plan143()['currentReaderPolicy']),
    'prepares next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-hidden-path-generated-source', $plan143()['nextReaderPolicy']),
    'stable reuses reader policy' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-hidden-path-generated-source', $stable143()['nextReaderPolicy']),
    'stable has no hidden path generated replan reasons' => static fn (TestRunner $t) => $t->same([], $stable143()['hiddenPathGeneratedReplanReasons']),
    'point seek signature inherited' => static fn (TestRunner $t) => $t->same('2:path:=:"$.rules"&&3:id:=:5', $plan143()['currentHiddenPathGeneratedSource']['seekSignature']),
    'current source kind is text' => static fn (TestRunner $t) => $t->same('text', $plan143()['currentHiddenPathGeneratedSource']['sourceKind']),
    'current matched rowid' => static fn (TestRunner $t) => $t->same(5, $plan143()['currentHiddenPathGeneratedSource']['matchedRowid']),
    'current matched path' => static fn (TestRunner $t) => $t->same('$.rules', $plan143()['currentHiddenPathGeneratedSource']['matchedPath']),
    'current matched fullkey' => static fn (TestRunner $t) => $t->same('$.rules[1]', $plan143()['currentHiddenPathGeneratedSource']['matchedFullkey']),
    'current row match is true' => static fn (TestRunner $t) => $t->same(true, $plan143()['currentHiddenPathGeneratedSource']['matched']),
    'current generated match is true' => static fn (TestRunner $t) => $t->same(true, $plan143()['currentHiddenPathGeneratedSource']['generatedMatched']),
    'next row still matches path rowid' => static fn (TestRunner $t) => $t->same(true, $plan143()['nextHiddenPathGeneratedSource']['matched']),
    'next generated match becomes false' => static fn (TestRunner $t) => $t->same(false, $plan143()['nextHiddenPathGeneratedSource']['generatedMatched']),
    'current generated slug' => static fn (TestRunner $t) => $t->same('cache', $plan143()['currentHiddenPathGeneratedSource']['generatedValues']['slug']),
    'current generated priority' => static fn (TestRunner $t) => $t->same(7, $plan143()['currentHiddenPathGeneratedSource']['generatedValues']['priority']),
    'current generated enabled sqlite boolean' => static fn (TestRunner $t) => $t->same(1, $plan143()['currentHiddenPathGeneratedSource']['generatedValues']['enabled']),
    'next generated priority changes' => static fn (TestRunner $t) => $t->same(8, $plan143()['nextHiddenPathGeneratedSource']['generatedValues']['priority']),
    'next generated enabled changes' => static fn (TestRunner $t) => $t->same(0, $plan143()['nextHiddenPathGeneratedSource']['generatedValues']['enabled']),
    'generated constraints normalized' => static fn (TestRunner $t) => $t->same(['slug:value:$.slug:=:"cache"', 'priority:value:$.priority:BETWEEN:[5,9]', 'enabled:value:$.enabled:=:1'], $plan143()['currentHiddenPathGeneratedSource']['generatedConstraintSignatures']),
    'current generated tape names' => static fn (TestRunner $t) => $t->same(['slug', 'priority', 'enabled'], array_column($plan143()['currentHiddenPathGeneratedSource']['generatedTape'], 'name')),
    'current generated tape all matches' => static fn (TestRunner $t) => $t->same([true, true, true], array_column($plan143()['currentHiddenPathGeneratedSource']['generatedTape'], 'matched')),
    'next generated tape enabled fails' => static fn (TestRunner $t) => $t->same([true, true, false], array_column($plan143()['nextHiddenPathGeneratedSource']['generatedTape'], 'matched')),
    'current generated tape expected values' => static fn (TestRunner $t) => $t->same(['cache', [5, 9], 1], array_column($plan143()['currentHiddenPathGeneratedSource']['generatedTape'], 'expected')),
    'current effective cost includes generated filters' => static fn (TestRunner $t) => $t->same(4, $plan143()['currentHiddenPathGeneratedSource']['effectiveEstimatedCost']),
    'next effective cost records filtered point' => static fn (TestRunner $t) => $t->same(5, $plan143()['nextHiddenPathGeneratedSource']['effectiveEstimatedCost']),
    'current cost class is point' => static fn (TestRunner $t) => $t->same('json-table-hidden-path-generated-current-source-point', $plan143()['currentHiddenPathGeneratedSource']['costClass']),
    'next cost class is filtered' => static fn (TestRunner $t) => $t->same('json-table-hidden-path-generated-current-source-filtered', $plan143()['nextHiddenPathGeneratedSource']['costClass']),
    'transition count records generated source state' => static fn (TestRunner $t) => $t->same(8, count($plan143()['hiddenPathGeneratedSourceTransitions'])),
    'seek transition stable' => static fn (TestRunner $t) => $t->same(false, $plan143()['hiddenPathGeneratedSourceTransitions'][0]['changed']),
    'source kind transition stable for text' => static fn (TestRunner $t) => $t->same(false, $plan143()['hiddenPathGeneratedSourceTransitions'][1]['changed']),
    'constraint transition stable' => static fn (TestRunner $t) => $t->same(false, $plan143()['hiddenPathGeneratedSourceTransitions'][2]['changed']),
    'generated match transition changes' => static fn (TestRunner $t) => $t->same(true, $plan143()['hiddenPathGeneratedSourceTransitions'][3]['changed']),
    'generated values transition changes' => static fn (TestRunner $t) => $t->same(true, $plan143()['hiddenPathGeneratedSourceTransitions'][4]['changed']),
    'matched value fingerprint transition changes' => static fn (TestRunner $t) => $t->same(true, $plan143()['hiddenPathGeneratedSourceTransitions'][5]['changed']),
    'cost transition changes' => static fn (TestRunner $t) => $t->same(true, $plan143()['hiddenPathGeneratedSourceTransitions'][6]['changed']),
    'cost class transition changes' => static fn (TestRunner $t) => $t->same(true, $plan143()['hiddenPathGeneratedSourceTransitions'][7]['changed']),
    'reasons include generated match' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-path-generated-match-changed', $plan143()['hiddenPathGeneratedReplanReasons'], true)),
    'reasons include generated value' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-path-generated-value-changed', $plan143()['hiddenPathGeneratedReplanReasons'], true)),
    'reasons include generated cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-path-generated-cost-changed', $plan143()['hiddenPathGeneratedReplanReasons'], true)),
    'reasons preserve source json changed' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $plan143()['hiddenPathGeneratedReplanReasons'], true)),
    'disabled constraint matches next' => static fn (TestRunner $t) => $t->same(true, $disabled143()['nextHiddenPathGeneratedSource']['generatedMatched']),
    'disabled constraint filters current' => static fn (TestRunner $t) => $t->same(false, $disabled143()['currentHiddenPathGeneratedSource']['generatedMatched']),
    'json source generated value reads document meta' => static fn (TestRunner $t) => $t->same(1, $jsonSource143()['currentHiddenPathGeneratedSource']['generatedValues']['siteVersion']),
    'json source generated match is true' => static fn (TestRunner $t) => $t->same(true, $jsonSource143()['currentHiddenPathGeneratedSource']['generatedMatched']),
    'jsonb next source kind changes' => static fn (TestRunner $t) => $t->same('jsonb', $jsonb143()['nextHiddenPathGeneratedSource']['sourceKind']),
    'jsonb next generated values remain readable' => static fn (TestRunner $t) => $t->same('cache', $jsonb143()['nextHiddenPathGeneratedSource']['generatedValues']['slug']),
    'jsonb reasons include source kind' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-path-generated-source-kind-changed', $jsonb143()['hiddenPathGeneratedReplanReasons'], true)),
    'miss has no row match' => static fn (TestRunner $t) => $t->same(false, $miss143()['currentHiddenPathGeneratedSource']['matched']),
    'miss has no generated match' => static fn (TestRunner $t) => $t->same(false, $miss143()['currentHiddenPathGeneratedSource']['generatedMatched']),
    'miss cost class' => static fn (TestRunner $t) => $t->same('json-table-hidden-path-generated-current-source-miss', $miss143()['currentHiddenPathGeneratedSource']['costClass']),
    'unrunnable next source kind sql null' => static fn (TestRunner $t) => $t->same('sql-null', $unrunnable143()['nextHiddenPathGeneratedSource']['sourceKind']),
    'unrunnable next cost class' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable143()['nextHiddenPathGeneratedSource']['costClass']),
    'unrunnable next generated values are null' => static fn (TestRunner $t) => $t->same(['slug' => null, 'priority' => null, 'enabled' => null], $unrunnable143()['nextHiddenPathGeneratedSource']['generatedValues']),
    'missing generated constraints rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceHiddenPathGenerated('json_tree', $current143, $next143, 'option_value', $constraints143, 'scan_root', [], [])),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan143($current143, $next143, [['name' => 'bad', 'path' => '$[', 'value' => 1]])),
    'bad generated source rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan143($current143, $next143, [['name' => 'bad', 'source' => 'missing', 'path' => '$.x', 'value' => 1]])),
    'bad function rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceHiddenPathGenerated('json_bad', $current143, $next143, 'option_value', $constraints143, 'scan_root', [], $generated143)),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table hidden path generated current source ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
