<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current149 = [
    'option_id' => 149,
    'option_name' => 'wp_plugin_rowid_hidden_generated',
    'option_value' => '{"plugin":{"groups":[{"rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false},{"slug":"forms","priority":4,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$next149 = [
    'option_id' => 149,
    'option_name' => 'wp_plugin_rowid_hidden_generated',
    'option_value' => '{"plugin":{"groups":[{"rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false},{"slug":"forms","priority":6,"enabled":true},{"slug":"shop","priority":5,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$constraints149 = [
    ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[0].rules[%]'],
    ['column' => 'rowid', 'operator' => '=', 'value' => 9],
];
$generated149 = [
    ['name' => 'generated_priority', 'source' => 'value', 'path' => '$.priority', 'operator' => 'BETWEEN', 'value' => [3, 6]],
    ['name' => 'generated_enabled', 'source' => 'value', 'path' => '$.enabled', 'operator' => 'IS', 'value' => 1],
    ['name' => 'generated_slug', 'source' => 'value', 'path' => '$.slug', 'operator' => 'IN', 'value' => ['forms', 'shop']],
];

$plan149 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $generated = null,
    ?array $outputs = null,
): array => SQLiteJsonTablePlan::currentSourceRowidHiddenGeneratedNext149(
    'json_tree',
    $current ?? $current149,
    $next ?? $next149,
    'option_value',
    'base_root',
    'nested_path',
    $constraints ?? $constraints149,
    [['column' => 'id']],
    $generated ?? $generated149,
    $outputs ?? ['generated_slug', 'generated_priority', 'generated_enabled'],
);

$stable149 = static fn (): array => $plan149($current149, $current149);
$singleOutput149 = static fn (): array => $plan149($current149, $current149, null, null, ['generated_slug']);
$defaultOutput149 = static fn (): array => $plan149($current149, $current149, null, null, []);
$range149 = static fn (): array => $plan149(
    $current149,
    $next149,
    [
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
        ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[0].rules[%]'],
        ['column' => 'oid', 'operator' => 'BETWEEN', 'value' => [1, 13]],
    ],
);
$miss149 = static fn (): array => $plan149(
    $current149,
    $next149,
    array_replace($constraints149, [2 => ['column' => '_rowid_', 'operator' => '=', 'value' => 99]]),
);
$jsonb149 = static fn (): array => $plan149(
    $current149,
    array_replace($next149, ['option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($next149['option_value'])))]),
);
$unrunnable149 = static fn (): array => $plan149($current149, array_replace($next149, ['option_value' => null]));

$tests = [
    'normalizes function name' => static fn (TestRunner $t) => $t->same('json_tree', $plan149()['function']),
    'records next149 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-rowid-hidden-generated-current-source-next149', $plan149()['dependencies'], true)),
    'preserves next142 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-hidden-rowid-cost-current-source-next142', $plan149()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-rowid-hidden-generated-source-until-cursor-reset', $plan149()['currentReaderPolicy']),
    'prepares next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-rowid-hidden-generated-plan', $plan149()['nextReaderPolicy']),
    'stable reader policy reuses current plan' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-rowid-hidden-generated-plan', $stable149()['nextReaderPolicy']),
    'stable plan has no next149 reasons' => static fn (TestRunner $t) => $t->same([], $stable149()['next149ReplanReasons']),
    'current root is composed hidden path' => static fn (TestRunner $t) => $t->same('$.plugin.groups[0].rules', $plan149()['currentRowidHiddenGenerated']['root']),
    'rowid constraint signature is normalized' => static fn (TestRunner $t) => $t->same('id:=:9', $plan149()['currentRowidHiddenGenerated']['rowidConstraintSignature']),
    'rowid is scoped' => static fn (TestRunner $t) => $t->same(true, $plan149()['currentRowidHiddenGenerated']['rowidScoped']),
    'output columns are stable sorted' => static fn (TestRunner $t) => $t->same(['generated_enabled', 'generated_priority', 'generated_slug'], $plan149()['currentRowidHiddenGenerated']['generatedOutputColumns']),
    'output column count is tracked' => static fn (TestRunner $t) => $t->same(3, $plan149()['currentRowidHiddenGenerated']['outputColumnCount']),
    'current matched row count is one' => static fn (TestRunner $t) => $t->same(1, $plan149()['currentRowidHiddenGenerated']['matchedRowCount']),
    'next matched row count is one' => static fn (TestRunner $t) => $t->same(1, $plan149()['nextRowidHiddenGenerated']['matchedRowCount']),
    'current rowids expose point row' => static fn (TestRunner $t) => $t->same([9], $plan149()['currentRowidHiddenGenerated']['rowids']),
    'next rowids keep point row' => static fn (TestRunner $t) => $t->same([9], $plan149()['nextRowidHiddenGenerated']['rowids']),
    'current fullkeys expose forms rule' => static fn (TestRunner $t) => $t->same(['$.plugin.groups[0].rules[2]'], $plan149()['currentRowidHiddenGenerated']['fullkeys']),
    'first rowid is tracked' => static fn (TestRunner $t) => $t->same(9, $plan149()['currentRowidHiddenGenerated']['firstRowid']),
    'last rowid is tracked' => static fn (TestRunner $t) => $t->same(9, $plan149()['currentRowidHiddenGenerated']['lastRowid']),
    'current generated row count is one' => static fn (TestRunner $t) => $t->same(1, count($plan149()['currentRowidHiddenGenerated']['generatedRows'])),
    'current generated row values expose slug' => static fn (TestRunner $t) => $t->same('forms', $plan149()['currentRowidHiddenGenerated']['generatedRows'][0]['values']['generated_slug']),
    'current generated row values expose priority' => static fn (TestRunner $t) => $t->same(4, $plan149()['currentRowidHiddenGenerated']['generatedRows'][0]['values']['generated_priority']),
    'next generated row values expose changed priority' => static fn (TestRunner $t) => $t->same(6, $plan149()['nextRowidHiddenGenerated']['generatedRows'][0]['values']['generated_priority']),
    'generated rows mark matched' => static fn (TestRunner $t) => $t->same(true, $plan149()['currentRowidHiddenGenerated']['generatedRows'][0]['matched']),
    'generated row fingerprint is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan149()['currentRowidHiddenGenerated']['generatedRows'][0]['generatedFingerprint'])),
    'combined fingerprint changes when generated value changes' => static fn (TestRunner $t) => $t->same(false, $plan149()['currentRowidHiddenGenerated']['combinedGeneratedFingerprint'] === $plan149()['nextRowidHiddenGenerated']['combinedGeneratedFingerprint']),
    'generated fingerprints transition changes' => static fn (TestRunner $t) => $t->same(true, $plan149()['rowidHiddenGeneratedTransitions'][6]['changed']),
    'generated rows transition changes' => static fn (TestRunner $t) => $t->same(true, $plan149()['rowidHiddenGeneratedTransitions'][9]['changed']),
    'rowid transition stays stable' => static fn (TestRunner $t) => $t->same(false, $plan149()['rowidHiddenGeneratedTransitions'][4]['changed']),
    'fullkey transition stays stable' => static fn (TestRunner $t) => $t->same(false, $plan149()['rowidHiddenGeneratedTransitions'][5]['changed']),
    'transition count records output state' => static fn (TestRunner $t) => $t->same(10, count($plan149()['rowidHiddenGeneratedTransitions'])),
    'current cost class is covering point' => static fn (TestRunner $t) => $t->same('json-table-rowid-hidden-generated-covering-point', $plan149()['currentRowidHiddenGenerated']['costClass']),
    'next cost class is covering point' => static fn (TestRunner $t) => $t->same('json-table-rowid-hidden-generated-covering-point', $plan149()['nextRowidHiddenGenerated']['costClass']),
    'single output uses point cost class' => static fn (TestRunner $t) => $t->same('json-table-rowid-hidden-generated-point', $singleOutput149()['currentRowidHiddenGenerated']['costClass']),
    'single output keeps requested column' => static fn (TestRunner $t) => $t->same(['generated_slug'], $singleOutput149()['currentRowidHiddenGenerated']['generatedOutputColumns']),
    'default output discovers generated columns' => static fn (TestRunner $t) => $t->same(['generated_enabled', 'generated_priority', 'generated_slug'], $defaultOutput149()['currentRowidHiddenGenerated']['generatedOutputColumns']),
    'range current scans one generated row' => static fn (TestRunner $t) => $t->same([9], $range149()['currentRowidHiddenGenerated']['rowids']),
    'range next scans forms and shop generated rows' => static fn (TestRunner $t) => $t->same([9, 13], $range149()['nextRowidHiddenGenerated']['rowids']),
    'range current cost class is scan' => static fn (TestRunner $t) => $t->same('json-table-rowid-hidden-generated-scan', $range149()['currentRowidHiddenGenerated']['costClass']),
    'range next row count transition changes' => static fn (TestRunner $t) => $t->same(true, $range149()['rowidHiddenGeneratedTransitions'][3]['changed']),
    'miss current has no rowids' => static fn (TestRunner $t) => $t->same([], $miss149()['currentRowidHiddenGenerated']['rowids']),
    'miss cost class is empty' => static fn (TestRunner $t) => $t->same('json-table-rowid-hidden-generated-empty', $miss149()['currentRowidHiddenGenerated']['costClass']),
    'miss fingerprint is still sha256' => static fn (TestRunner $t) => $t->same(64, strlen($miss149()['currentRowidHiddenGenerated']['combinedGeneratedFingerprint'])),
    'jsonb next remains runnable' => static fn (TestRunner $t) => $t->same(true, $jsonb149()['next']['runnable']),
    'jsonb next preserves rowid output' => static fn (TestRunner $t) => $t->same([9], $jsonb149()['nextRowidHiddenGenerated']['rowids']),
    'jsonb next records source kind change' => static fn (TestRunner $t) => $t->true(in_array('source-json-kind-changed', $jsonb149()['next149ReplanReasons'], true)),
    'unrunnable next class is sentinel' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable149()['nextRowidHiddenGenerated']['costClass']),
    'unrunnable next effective cost is sentinel' => static fn (TestRunner $t) => $t->same(1000000, $unrunnable149()['nextRowidHiddenGenerated']['effectiveEstimatedCost']),
    'unrunnable next has no rows' => static fn (TestRunner $t) => $t->same([], $unrunnable149()['nextRowidHiddenGenerated']['generatedRows']),
    'reasons include generated value drift' => static fn (TestRunner $t) => $t->true(in_array('json-table-rowid-hidden-generated-values-changed', $plan149()['next149ReplanReasons'], true)),
    'reasons preserve source json change' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $plan149()['next149ReplanReasons'], true)),
    'stable reasons do not include values' => static fn (TestRunner $t) => $t->same(false, in_array('json-table-rowid-hidden-generated-values-changed', $stable149()['next149ReplanReasons'], true)),
    'empty output column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan149($current149, $next149, null, null, ['generated_slug', ''])),
    'bad generated constraints still rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan149($current149, $next149, null, [])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table rowid hidden generated current source next149 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
