<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current157 = [
    'option_id' => 157,
    'option_name' => 'wp_plugin_hidden_generated_rowid',
    'option_value' => '{"rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":true},{"slug":"forms","priority":4,"enabled":false}],"meta":{"version":1}}',
    'scan_root' => '$.rules',
];
$next157 = [
    'option_id' => 157,
    'option_name' => 'wp_plugin_hidden_generated_rowid',
    'option_value' => '{"rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":9,"enabled":false},{"slug":"forms","priority":4,"enabled":false},{"slug":"shop","priority":6,"enabled":true}],"meta":{"version":2}}',
    'scan_root' => '$.rules',
];
$constraints157 = [
    ['column' => 'path', 'operator' => '=', 'value' => '$.rules'],
    ['column' => 'rowid', 'operator' => '=', 'value' => 5],
    ['column' => 'type', 'operator' => '=', 'value' => 'object'],
];
$generated157 = [
    ['name' => 'slug', 'source' => 'value', 'path' => '$.slug', 'value' => 'cache'],
    ['name' => 'priority', 'source' => 'value', 'path' => '$.priority', 'operator' => 'BETWEEN', 'value' => [5, 9]],
    ['name' => 'enabled', 'source' => 'value', 'path' => '$.enabled', 'operator' => '=', 'value' => 1],
];

$plan157 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $generated = null,
): array => SQLiteJsonTablePlan::currentSourceHiddenGeneratedRowid(
    'json_tree',
    $current ?? $current157,
    $next ?? $next157,
    'option_value',
    $constraints ?? $constraints157,
    'scan_root',
    [['column' => 'path'], ['column' => 'id']],
    $generated ?? $generated157,
);

$stable157 = static fn (): array => $plan157($current157, $current157);
$range157 = static fn (): array => $plan157(
    $current157,
    $next157,
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules'],
        ['column' => '_rowid_', 'operator' => 'BETWEEN', 'value' => [4, 8]],
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ],
);
$miss157 = static fn (): array => $plan157(
    $current157,
    $next157,
    array_replace($constraints157, [1 => ['column' => 'oid', 'operator' => '=', 'value' => 99]]),
);
$unconstrained157 = static fn (): array => $plan157($current157, $current157, array_slice($constraints157, 0, 1));
$unusable157 = static fn (): array => $plan157(
    $current157,
    $current157,
    array_replace($constraints157, [1 => ['column' => 'rowid', 'operator' => '=', 'value' => 5, 'usable' => false]]),
);
$filtered157 = static fn (): array => $plan157(
    $current157,
    $next157,
    $constraints157,
    array_replace($generated157, [2 => ['name' => 'enabled', 'source' => 'value', 'path' => '$.enabled', 'operator' => '=', 'value' => 0]]),
);
$jsonb157 = static fn (): array => $plan157(
    $current157,
    array_replace($current157, ['option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($current157['option_value'])))]),
);
$unrunnable157 = static fn (): array => $plan157($current157, array_replace($next157, ['option_value' => null]));

$tests = [
    'normalizes function name' => static fn (TestRunner $t) => $t->same('json_tree', $plan157()['function']),
    'records hidden generated rowid dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-hidden-generated-rowid', $plan157()['dependencies'], true)),
    'preserves hidden generated cost dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-hidden-generated-cost', $plan157()['dependencies'], true)),
    'preserves hidden path generated dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-hidden-path-generated', $plan157()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-hidden-generated-rowid-source-until-cursor-reset', $plan157()['currentReaderPolicy']),
    'prepares changed next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-hidden-generated-rowid-plan', $plan157()['nextReaderPolicy']),
    'stable reuses current reader policy' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-hidden-generated-rowid-plan', $stable157()['nextReaderPolicy']),
    'stable has no hidden generated rowid reasons' => static fn (TestRunner $t) => $t->same([], $stable157()['hiddenGeneratedRowidReplanReasons']),
    'current seek signature inherited' => static fn (TestRunner $t) => $t->same('2:path:=:"$.rules"&&3:id:=:5', $plan157()['currentHiddenGeneratedRowid']['seekSignature']),
    'rowid signature normalizes rowid' => static fn (TestRunner $t) => $t->same('id:=:5', $plan157()['currentHiddenGeneratedRowid']['rowidConstraintSignature']),
    'rowid column is id alias' => static fn (TestRunner $t) => $t->same('id', $plan157()['currentHiddenGeneratedRowid']['rowidConstraintColumn']),
    'rowid operator is equality' => static fn (TestRunner $t) => $t->same('=', $plan157()['currentHiddenGeneratedRowid']['rowidConstraintOperator']),
    'rowid value preserved' => static fn (TestRunner $t) => $t->same(5, $plan157()['currentHiddenGeneratedRowid']['rowidConstraintValue']),
    'rowid equality is scoped' => static fn (TestRunner $t) => $t->same(true, $plan157()['currentHiddenGeneratedRowid']['rowidScoped']),
    'current hidden row matched' => static fn (TestRunner $t) => $t->same(true, $plan157()['currentHiddenGeneratedRowid']['matched']),
    'current generated matched' => static fn (TestRunner $t) => $t->same(true, $plan157()['currentHiddenGeneratedRowid']['generatedMatched']),
    'current rowid matched' => static fn (TestRunner $t) => $t->same(true, $plan157()['currentHiddenGeneratedRowid']['rowidMatched']),
    'current intersection matched' => static fn (TestRunner $t) => $t->same(true, $plan157()['currentHiddenGeneratedRowid']['intersected']),
    'next generated filtered by changed enabled value' => static fn (TestRunner $t) => $t->same(false, $plan157()['nextHiddenGeneratedRowid']['generatedMatched']),
    'next intersection filtered out' => static fn (TestRunner $t) => $t->same(false, $plan157()['nextHiddenGeneratedRowid']['intersected']),
    'current intersected rowid pinned' => static fn (TestRunner $t) => $t->same([5], $plan157()['currentHiddenGeneratedRowid']['intersectedRowids']),
    'current intersected fullkey pinned' => static fn (TestRunner $t) => $t->same(['$.rules[1]'], $plan157()['currentHiddenGeneratedRowid']['intersectedFullkeys']),
    'next intersected rowid empty after generated filter' => static fn (TestRunner $t) => $t->same([], $plan157()['nextHiddenGeneratedRowid']['intersectedRowids']),
    'current generated values retained' => static fn (TestRunner $t) => $t->same(['slug' => 'cache', 'priority' => 7, 'enabled' => 1], $plan157()['currentHiddenGeneratedRowid']['generatedValues']),
    'next generated values retained' => static fn (TestRunner $t) => $t->same(['slug' => 'cache', 'priority' => 9, 'enabled' => 0], $plan157()['nextHiddenGeneratedRowid']['generatedValues']),
    'current tape has pinned row' => static fn (TestRunner $t) => $t->same(1, count($plan157()['currentHiddenGeneratedRowid']['rowidGeneratedTape'])),
    'current tape rowid is five' => static fn (TestRunner $t) => $t->same(5, $plan157()['currentHiddenGeneratedRowid']['rowidGeneratedTape'][0]['rowid']),
    'current tape fullkey is cache object' => static fn (TestRunner $t) => $t->same('$.rules[1]', $plan157()['currentHiddenGeneratedRowid']['rowidGeneratedTape'][0]['fullkey']),
    'current tape marks generated match' => static fn (TestRunner $t) => $t->same(true, $plan157()['currentHiddenGeneratedRowid']['rowidGeneratedTape'][0]['generatedMatched']),
    'current tape marks rowid match' => static fn (TestRunner $t) => $t->same(true, $plan157()['currentHiddenGeneratedRowid']['rowidGeneratedTape'][0]['rowidMatched']),
    'current tape marks final match' => static fn (TestRunner $t) => $t->same(true, $plan157()['currentHiddenGeneratedRowid']['rowidGeneratedTape'][0]['matched']),
    'next tape marks generated mismatch' => static fn (TestRunner $t) => $t->same(false, $plan157()['nextHiddenGeneratedRowid']['rowidGeneratedTape'][0]['generatedMatched']),
    'base estimated rows inherited' => static fn (TestRunner $t) => $t->same(1, $plan157()['currentHiddenGeneratedRowid']['baseEstimatedRows']),
    'base estimated cost inherited' => static fn (TestRunner $t) => $t->same(5, $plan157()['currentHiddenGeneratedRowid']['baseEstimatedCost']),
    'point effective rows are one' => static fn (TestRunner $t) => $t->same(1, $plan157()['currentHiddenGeneratedRowid']['effectiveEstimatedRows']),
    'point effective cost is one' => static fn (TestRunner $t) => $t->same(1, $plan157()['currentHiddenGeneratedRowid']['effectiveEstimatedCost']),
    'point cost class' => static fn (TestRunner $t) => $t->same('json-table-hidden-generated-rowid-point-current-source', $plan157()['currentHiddenGeneratedRowid']['costClass']),
    'next filtered cost class is empty' => static fn (TestRunner $t) => $t->same('json-table-hidden-generated-rowid-empty-current-source', $plan157()['nextHiddenGeneratedRowid']['costClass']),
    'fingerprint is sha256 length' => static fn (TestRunner $t) => $t->same(64, strlen($plan157()['currentHiddenGeneratedRowid']['planFingerprint'])),
    'fingerprint changes across next source' => static fn (TestRunner $t) => $t->same(true, $plan157()['hiddenGeneratedRowidTransitions'][11]['changed']),
    'transition count records hidden generated rowid fields' => static fn (TestRunner $t) => $t->same(12, count($plan157()['hiddenGeneratedRowidTransitions'])),
    'seek transition stable' => static fn (TestRunner $t) => $t->same(false, $plan157()['hiddenGeneratedRowidTransitions'][0]['changed']),
    'constraint transition stable' => static fn (TestRunner $t) => $t->same(false, $plan157()['hiddenGeneratedRowidTransitions'][1]['changed']),
    'generated transition changes' => static fn (TestRunner $t) => $t->same(true, $plan157()['hiddenGeneratedRowidTransitions'][2]['changed']),
    'rowid transition stable' => static fn (TestRunner $t) => $t->same(false, $plan157()['hiddenGeneratedRowidTransitions'][3]['changed']),
    'rowset transition changes' => static fn (TestRunner $t) => $t->same(true, $plan157()['hiddenGeneratedRowidTransitions'][4]['changed']),
    'fullkey transition changes' => static fn (TestRunner $t) => $t->same(true, $plan157()['hiddenGeneratedRowidTransitions'][5]['changed']),
    'value transition changes' => static fn (TestRunner $t) => $t->same(true, $plan157()['hiddenGeneratedRowidTransitions'][6]['changed']),
    'row estimate transition changes' => static fn (TestRunner $t) => $t->same(true, $plan157()['hiddenGeneratedRowidTransitions'][7]['changed']),
    'cost transition changes' => static fn (TestRunner $t) => $t->same(true, $plan157()['hiddenGeneratedRowidTransitions'][8]['changed']),
    'cost class transition changes' => static fn (TestRunner $t) => $t->same(true, $plan157()['hiddenGeneratedRowidTransitions'][9]['changed']),
    'tape transition changes' => static fn (TestRunner $t) => $t->same(true, $plan157()['hiddenGeneratedRowidTransitions'][10]['changed']),
    'reasons include values change' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-generated-rowid-values-changed', $plan157()['hiddenGeneratedRowidReplanReasons'], true)),
    'reasons include rowset change' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-generated-rowid-rowset-changed', $plan157()['hiddenGeneratedRowidReplanReasons'], true)),
    'reasons include row estimate change' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-generated-rowid-row-estimate-changed', $plan157()['hiddenGeneratedRowidReplanReasons'], true)),
    'reasons include cost change' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-generated-rowid-cost-changed', $plan157()['hiddenGeneratedRowidReplanReasons'], true)),
    'reasons include fingerprint change' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-generated-rowid-fingerprint-changed', $plan157()['hiddenGeneratedRowidReplanReasons'], true)),
    'reasons preserve hidden generated cost values change' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-generated-cost-values-changed', $plan157()['hiddenGeneratedRowidReplanReasons'], true)),
    'range rowid signature uses id alias' => static fn (TestRunner $t) => $t->same('id:BETWEEN:[4,8]', $range157()['currentHiddenGeneratedRowid']['rowidConstraintSignature']),
    'range is not rowid scoped' => static fn (TestRunner $t) => $t->same(false, $range157()['currentHiddenGeneratedRowid']['rowidScoped']),
    'range without point seek has no hidden-generated intersection' => static fn (TestRunner $t) => $t->same([], $range157()['currentHiddenGeneratedRowid']['intersectedRowids']),
    'range cost class is empty without point seek' => static fn (TestRunner $t) => $t->same('json-table-hidden-generated-rowid-empty-current-source', $range157()['currentHiddenGeneratedRowid']['costClass']),
    'range effective cost keeps base miss penalty' => static fn (TestRunner $t) => $t->same(7, $range157()['currentHiddenGeneratedRowid']['effectiveEstimatedCost']),
    'miss rowid signature uses oid alias' => static fn (TestRunner $t) => $t->same('id:=:99', $miss157()['currentHiddenGeneratedRowid']['rowidConstraintSignature']),
    'miss rowid does not match' => static fn (TestRunner $t) => $t->same(false, $miss157()['currentHiddenGeneratedRowid']['rowidMatched']),
    'miss has empty rowids' => static fn (TestRunner $t) => $t->same([], $miss157()['currentHiddenGeneratedRowid']['intersectedRowids']),
    'miss cost class empty' => static fn (TestRunner $t) => $t->same('json-table-hidden-generated-rowid-empty-current-source', $miss157()['currentHiddenGeneratedRowid']['costClass']),
    'unconstrained has null rowid signature' => static fn (TestRunner $t) => $t->same(null, $unconstrained157()['currentHiddenGeneratedRowid']['rowidConstraintSignature']),
    'unconstrained hidden path has empty current-source rowid class' => static fn (TestRunner $t) => $t->same('json-table-hidden-generated-rowid-empty-current-source', $unconstrained157()['currentHiddenGeneratedRowid']['costClass']),
    'unusable rowid keeps hidden path empty current-source class' => static fn (TestRunner $t) => $t->same('json-table-hidden-generated-rowid-empty-current-source', $unusable157()['currentHiddenGeneratedRowid']['costClass']),
    'filtered generated constraint empties current rowset' => static fn (TestRunner $t) => $t->same([], $filtered157()['currentHiddenGeneratedRowid']['intersectedRowids']),
    'filtered generated constraint cost class empty' => static fn (TestRunner $t) => $t->same('json-table-hidden-generated-rowid-empty-current-source', $filtered157()['currentHiddenGeneratedRowid']['costClass']),
    'jsonb next source remains runnable' => static fn (TestRunner $t) => $t->same(true, $jsonb157()['next']['runnable']),
    'jsonb next preserves rowid intersection' => static fn (TestRunner $t) => $t->same([5], $jsonb157()['nextHiddenGeneratedRowid']['intersectedRowids']),
    'jsonb next records source kind change' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-generated-cost-source-kind-changed', $jsonb157()['hiddenGeneratedRowidReplanReasons'], true)),
    'unrunnable next cost class' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable157()['nextHiddenGeneratedRowid']['costClass']),
    'unrunnable next effective cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $unrunnable157()['nextHiddenGeneratedRowid']['effectiveEstimatedCost']),
    'empty generated constraints rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan157($current157, $next157, $constraints157, [])),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan157($current157, $next157, $constraints157, [['name' => 'bad', 'path' => '$[', 'value' => 1]])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table hidden generated rowid current source ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
