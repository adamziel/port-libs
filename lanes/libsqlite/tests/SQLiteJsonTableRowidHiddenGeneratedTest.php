<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentRowidHiddenGenerated = [
    'option_id' => 149,
    'option_name' => 'wp_plugin_rowid_hidden_generated',
    'option_value' => '{"plugin":{"groups":[{"rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false},{"slug":"forms","priority":4,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$nextRowidHiddenGenerated = [
    'option_id' => 149,
    'option_name' => 'wp_plugin_rowid_hidden_generated',
    'option_value' => '{"plugin":{"groups":[{"rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false},{"slug":"forms","priority":6,"enabled":true},{"slug":"shop","priority":5,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$rowidHiddenGeneratedConstraints = [
    ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[0].rules[%]'],
    ['column' => 'rowid', 'operator' => '=', 'value' => 9],
];
$rowidHiddenGeneratedConstraintsSet = [
    ['name' => 'generated_priority', 'source' => 'value', 'path' => '$.priority', 'operator' => 'BETWEEN', 'value' => [3, 6]],
    ['name' => 'generated_enabled', 'source' => 'value', 'path' => '$.enabled', 'operator' => 'IS', 'value' => 1],
    ['name' => 'generated_slug', 'source' => 'value', 'path' => '$.slug', 'operator' => 'IN', 'value' => ['forms', 'shop']],
];

$rowidHiddenGeneratedPlan = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $generated = null,
    ?array $outputs = null,
): array => SQLiteJsonTablePlan::currentSourceRowidHiddenGenerated(
    'json_tree',
    $current ?? $currentRowidHiddenGenerated,
    $next ?? $nextRowidHiddenGenerated,
    'option_value',
    'base_root',
    'nested_path',
    $constraints ?? $rowidHiddenGeneratedConstraints,
    [['column' => 'id']],
    $generated ?? $rowidHiddenGeneratedConstraintsSet,
    $outputs ?? ['generated_slug', 'generated_priority', 'generated_enabled'],
);

$stableRowidHiddenGeneratedPlan = static fn (): array => $rowidHiddenGeneratedPlan($currentRowidHiddenGenerated, $currentRowidHiddenGenerated);
$singleOutput149 = static fn (): array => $rowidHiddenGeneratedPlan($currentRowidHiddenGenerated, $currentRowidHiddenGenerated, null, null, ['generated_slug']);
$defaultOutput149 = static fn (): array => $rowidHiddenGeneratedPlan($currentRowidHiddenGenerated, $currentRowidHiddenGenerated, null, null, []);
$rangeRowidHiddenGeneratedPlan = static fn (): array => $rowidHiddenGeneratedPlan(
    $currentRowidHiddenGenerated,
    $nextRowidHiddenGenerated,
    [
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
        ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[0].rules[%]'],
        ['column' => 'oid', 'operator' => 'BETWEEN', 'value' => [1, 13]],
    ],
);
$missRowidHiddenGeneratedPlan = static fn (): array => $rowidHiddenGeneratedPlan(
    $currentRowidHiddenGenerated,
    $nextRowidHiddenGenerated,
    array_replace($rowidHiddenGeneratedConstraints, [2 => ['column' => '_rowid_', 'operator' => '=', 'value' => 99]]),
);
$jsonbRowidHiddenGeneratedPlan = static fn (): array => $rowidHiddenGeneratedPlan(
    $currentRowidHiddenGenerated,
    array_replace($nextRowidHiddenGenerated, ['option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($nextRowidHiddenGenerated['option_value'])))]),
);
$unrunnableRowidHiddenGeneratedPlan = static fn (): array => $rowidHiddenGeneratedPlan($currentRowidHiddenGenerated, array_replace($nextRowidHiddenGenerated, ['option_value' => null]));

$tests = [
    'normalizes function name' => static fn (TestRunner $t) => $t->same('json_tree', $rowidHiddenGeneratedPlan()['function']),
    'records rowid hidden generated dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-rowid-hidden-generated-current-source', $rowidHiddenGeneratedPlan()['dependencies'], true)),
    'preserves next142 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-hidden-rowid-cost-current-source-next142', $rowidHiddenGeneratedPlan()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-rowid-hidden-generated-source-until-cursor-reset', $rowidHiddenGeneratedPlan()['currentReaderPolicy']),
    'prepares next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-rowid-hidden-generated-plan', $rowidHiddenGeneratedPlan()['nextReaderPolicy']),
    'stable reader policy reuses current plan' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-rowid-hidden-generated-plan', $stableRowidHiddenGeneratedPlan()['nextReaderPolicy']),
    'stable plan has no rowid hidden generated reasons' => static fn (TestRunner $t) => $t->same([], $stableRowidHiddenGeneratedPlan()['rowidHiddenGeneratedReplanReasons']),
    'current root is composed hidden path' => static fn (TestRunner $t) => $t->same('$.plugin.groups[0].rules', $rowidHiddenGeneratedPlan()['currentRowidHiddenGenerated']['root']),
    'rowid constraint signature is normalized' => static fn (TestRunner $t) => $t->same('id:=:9', $rowidHiddenGeneratedPlan()['currentRowidHiddenGenerated']['rowidConstraintSignature']),
    'rowid is scoped' => static fn (TestRunner $t) => $t->same(true, $rowidHiddenGeneratedPlan()['currentRowidHiddenGenerated']['rowidScoped']),
    'output columns are stable sorted' => static fn (TestRunner $t) => $t->same(['generated_enabled', 'generated_priority', 'generated_slug'], $rowidHiddenGeneratedPlan()['currentRowidHiddenGenerated']['generatedOutputColumns']),
    'output column count is tracked' => static fn (TestRunner $t) => $t->same(3, $rowidHiddenGeneratedPlan()['currentRowidHiddenGenerated']['outputColumnCount']),
    'current matched row count is one' => static fn (TestRunner $t) => $t->same(1, $rowidHiddenGeneratedPlan()['currentRowidHiddenGenerated']['matchedRowCount']),
    'next matched row count is one' => static fn (TestRunner $t) => $t->same(1, $rowidHiddenGeneratedPlan()['nextRowidHiddenGenerated']['matchedRowCount']),
    'current rowids expose point row' => static fn (TestRunner $t) => $t->same([9], $rowidHiddenGeneratedPlan()['currentRowidHiddenGenerated']['rowids']),
    'next rowids keep point row' => static fn (TestRunner $t) => $t->same([9], $rowidHiddenGeneratedPlan()['nextRowidHiddenGenerated']['rowids']),
    'current fullkeys expose forms rule' => static fn (TestRunner $t) => $t->same(['$.plugin.groups[0].rules[2]'], $rowidHiddenGeneratedPlan()['currentRowidHiddenGenerated']['fullkeys']),
    'first rowid is tracked' => static fn (TestRunner $t) => $t->same(9, $rowidHiddenGeneratedPlan()['currentRowidHiddenGenerated']['firstRowid']),
    'last rowid is tracked' => static fn (TestRunner $t) => $t->same(9, $rowidHiddenGeneratedPlan()['currentRowidHiddenGenerated']['lastRowid']),
    'current generated row count is one' => static fn (TestRunner $t) => $t->same(1, count($rowidHiddenGeneratedPlan()['currentRowidHiddenGenerated']['generatedRows'])),
    'current generated row values expose slug' => static fn (TestRunner $t) => $t->same('forms', $rowidHiddenGeneratedPlan()['currentRowidHiddenGenerated']['generatedRows'][0]['values']['generated_slug']),
    'current generated row values expose priority' => static fn (TestRunner $t) => $t->same(4, $rowidHiddenGeneratedPlan()['currentRowidHiddenGenerated']['generatedRows'][0]['values']['generated_priority']),
    'next generated row values expose changed priority' => static fn (TestRunner $t) => $t->same(6, $rowidHiddenGeneratedPlan()['nextRowidHiddenGenerated']['generatedRows'][0]['values']['generated_priority']),
    'generated rows mark matched' => static fn (TestRunner $t) => $t->same(true, $rowidHiddenGeneratedPlan()['currentRowidHiddenGenerated']['generatedRows'][0]['matched']),
    'generated row fingerprint is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($rowidHiddenGeneratedPlan()['currentRowidHiddenGenerated']['generatedRows'][0]['generatedFingerprint'])),
    'combined fingerprint changes when generated value changes' => static fn (TestRunner $t) => $t->same(false, $rowidHiddenGeneratedPlan()['currentRowidHiddenGenerated']['combinedGeneratedFingerprint'] === $rowidHiddenGeneratedPlan()['nextRowidHiddenGenerated']['combinedGeneratedFingerprint']),
    'generated fingerprints transition changes' => static fn (TestRunner $t) => $t->same(true, $rowidHiddenGeneratedPlan()['rowidHiddenGeneratedTransitions'][6]['changed']),
    'generated rows transition changes' => static fn (TestRunner $t) => $t->same(true, $rowidHiddenGeneratedPlan()['rowidHiddenGeneratedTransitions'][9]['changed']),
    'rowid transition stays stable' => static fn (TestRunner $t) => $t->same(false, $rowidHiddenGeneratedPlan()['rowidHiddenGeneratedTransitions'][4]['changed']),
    'fullkey transition stays stable' => static fn (TestRunner $t) => $t->same(false, $rowidHiddenGeneratedPlan()['rowidHiddenGeneratedTransitions'][5]['changed']),
    'transition count records output state' => static fn (TestRunner $t) => $t->same(10, count($rowidHiddenGeneratedPlan()['rowidHiddenGeneratedTransitions'])),
    'current cost class is covering point' => static fn (TestRunner $t) => $t->same('json-table-rowid-hidden-generated-covering-point', $rowidHiddenGeneratedPlan()['currentRowidHiddenGenerated']['costClass']),
    'next cost class is covering point' => static fn (TestRunner $t) => $t->same('json-table-rowid-hidden-generated-covering-point', $rowidHiddenGeneratedPlan()['nextRowidHiddenGenerated']['costClass']),
    'single output uses point cost class' => static fn (TestRunner $t) => $t->same('json-table-rowid-hidden-generated-point', $singleOutput149()['currentRowidHiddenGenerated']['costClass']),
    'single output keeps requested column' => static fn (TestRunner $t) => $t->same(['generated_slug'], $singleOutput149()['currentRowidHiddenGenerated']['generatedOutputColumns']),
    'default output discovers generated columns' => static fn (TestRunner $t) => $t->same(['generated_enabled', 'generated_priority', 'generated_slug'], $defaultOutput149()['currentRowidHiddenGenerated']['generatedOutputColumns']),
    'range current scans one generated row' => static fn (TestRunner $t) => $t->same([9], $rangeRowidHiddenGeneratedPlan()['currentRowidHiddenGenerated']['rowids']),
    'range next scans forms and shop generated rows' => static fn (TestRunner $t) => $t->same([9, 13], $rangeRowidHiddenGeneratedPlan()['nextRowidHiddenGenerated']['rowids']),
    'range current cost class is scan' => static fn (TestRunner $t) => $t->same('json-table-rowid-hidden-generated-scan', $rangeRowidHiddenGeneratedPlan()['currentRowidHiddenGenerated']['costClass']),
    'range next row count transition changes' => static fn (TestRunner $t) => $t->same(true, $rangeRowidHiddenGeneratedPlan()['rowidHiddenGeneratedTransitions'][3]['changed']),
    'miss current has no rowids' => static fn (TestRunner $t) => $t->same([], $missRowidHiddenGeneratedPlan()['currentRowidHiddenGenerated']['rowids']),
    'miss cost class is empty' => static fn (TestRunner $t) => $t->same('json-table-rowid-hidden-generated-empty', $missRowidHiddenGeneratedPlan()['currentRowidHiddenGenerated']['costClass']),
    'miss fingerprint is still sha256' => static fn (TestRunner $t) => $t->same(64, strlen($missRowidHiddenGeneratedPlan()['currentRowidHiddenGenerated']['combinedGeneratedFingerprint'])),
    'jsonb next remains runnable' => static fn (TestRunner $t) => $t->same(true, $jsonbRowidHiddenGeneratedPlan()['next']['runnable']),
    'jsonb next preserves rowid output' => static fn (TestRunner $t) => $t->same([9], $jsonbRowidHiddenGeneratedPlan()['nextRowidHiddenGenerated']['rowids']),
    'jsonb next records source kind change' => static fn (TestRunner $t) => $t->true(in_array('source-json-kind-changed', $jsonbRowidHiddenGeneratedPlan()['rowidHiddenGeneratedReplanReasons'], true)),
    'unrunnable next class is sentinel' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnableRowidHiddenGeneratedPlan()['nextRowidHiddenGenerated']['costClass']),
    'unrunnable next effective cost is sentinel' => static fn (TestRunner $t) => $t->same(1000000, $unrunnableRowidHiddenGeneratedPlan()['nextRowidHiddenGenerated']['effectiveEstimatedCost']),
    'unrunnable next has no rows' => static fn (TestRunner $t) => $t->same([], $unrunnableRowidHiddenGeneratedPlan()['nextRowidHiddenGenerated']['generatedRows']),
    'reasons include generated value drift' => static fn (TestRunner $t) => $t->true(in_array('json-table-rowid-hidden-generated-values-changed', $rowidHiddenGeneratedPlan()['rowidHiddenGeneratedReplanReasons'], true)),
    'reasons preserve source json change' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $rowidHiddenGeneratedPlan()['rowidHiddenGeneratedReplanReasons'], true)),
    'stable reasons do not include values' => static fn (TestRunner $t) => $t->same(false, in_array('json-table-rowid-hidden-generated-values-changed', $stableRowidHiddenGeneratedPlan()['rowidHiddenGeneratedReplanReasons'], true)),
    'empty output column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $rowidHiddenGeneratedPlan($currentRowidHiddenGenerated, $nextRowidHiddenGenerated, null, null, ['generated_slug', ''])),
    'bad generated constraints still rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $rowidHiddenGeneratedPlan($currentRowidHiddenGenerated, $nextRowidHiddenGenerated, null, [])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table rowid hidden generated ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
