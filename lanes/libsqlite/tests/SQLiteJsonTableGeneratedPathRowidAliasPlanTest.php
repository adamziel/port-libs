<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current174 = [
    'option_id' => 174,
    'option_name' => 'wp_plugin_generated_path_rowid_alias_plan',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
];
$next174 = [
    'option_id' => 174,
    'option_name' => 'wp_plugin_generated_path_rowid_alias_plan',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
];
$sameAlias174 = [
    ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ['column' => '_rowid_', 'operator' => '=', 'value' => '6'],
    ['column' => 'oid', 'operator' => '=', 'value' => 6],
];
$conflictingAlias174 = [
    ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ['column' => '_rowid_', 'operator' => '=', 'value' => 7],
    ['column' => 'oid', 'operator' => '=', 'value' => 6],
];

$plan174 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
): array => SQLiteJsonTablePlan::generatedPathRowidAliasPlan(
    'json_tree',
    $current ?? $current174,
    $next ?? $next174,
    'option_value',
    'generated_path',
    $constraints ?? $sameAlias174,
    'scan_root',
    [['column' => 'path'], ['column' => 'rowid']],
);
$stable174 = static fn (): array => $plan174($current174, $current174, $sameAlias174);
$conflict174 = static fn (): array => $plan174($current174, $current174, $conflictingAlias174);
$range174 = static fn (): array => $plan174(
    array_replace($current174, ['generated_path' => '$.rules']),
    array_replace($current174, ['generated_path' => '$.rules']),
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [4, 9]],
    ],
);
$unusable174 = static fn (): array => $plan174(
    $current174,
    $current174,
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6, 'usable' => false],
        ['column' => '_rowid_', 'operator' => '=', 'value' => 6],
    ],
);

$tests = [
    'records next174 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next174', $plan174()['dependencies'], true)),
    'preserves current-source dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-current-source-cost', $plan174()['dependencies'], true)),
    'pins current next174 policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-path-rowid-alias-next174-until-xfilter-reset', $plan174()['currentReaderPolicy']),
    'prepares changed next174 policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-path-rowid-alias-next174-plan', $plan174()['nextReaderPolicy']),
    'stable next174 policy reuses current' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-alias-next174-plan', $stable174()['nextReaderPolicy']),
    'stable next174 reasons empty' => static fn (TestRunner $t) => $t->same([], $stable174()['next174ReplanReasons']),
    'current source option recorded' => static fn (TestRunner $t) => $t->same(174, $plan174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['sourceOptionId']),
    'current root recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['sourceRoot']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules[1]', $plan174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['generatedPath']),
    'same aliases counted' => static fn (TestRunner $t) => $t->same(3, $plan174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['rowidAliasConstraintCount']),
    'same aliases preserve original columns' => static fn (TestRunner $t) => $t->same(['rowid', '_rowid_', 'oid'], $plan174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['usableRowidAliasColumns']),
    'same aliases canonicalize one rowid' => static fn (TestRunner $t) => $t->same(6, $plan174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['canonicalRowidConstraint']),
    'same aliases are not conflicting' => static fn (TestRunner $t) => $t->same(false, $plan174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['conflictingRowidAliases']),
    'same aliases point values dedupe' => static fn (TestRunner $t) => $t->same([6], $plan174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['aliasPointValues']),
    'same aliases bind one argv column' => static fn (TestRunner $t) => $t->same(['id'], $plan174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['dedupedArgvColumns']),
    'same aliases bind one argv value' => static fn (TestRunner $t) => $t->same([6], $plan174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['dedupedArgvValues']),
    'same aliases omit id and path' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['omitColumns']),
    'same aliases have no residuals' => static fn (TestRunner $t) => $t->same([], $plan174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['residualColumns']),
    'same aliases keep point cursor mode' => static fn (TestRunner $t) => $t->same('pinned-current-source-point', $plan174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['cursorMode']),
    'same aliases keep rowid tape' => static fn (TestRunner $t) => $t->same([6], $plan174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['rowidTape']),
    'same aliases keep path tape' => static fn (TestRunner $t) => $t->same(['$.rules[1]'], $plan174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['pathTape']),
    'same aliases keep one cursor row' => static fn (TestRunner $t) => $t->same(1, $plan174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['cursorRowCount']),
    'same aliases estimate one row' => static fn (TestRunner $t) => $t->same(1, $plan174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['estimatedRows']),
    'same aliases estimate one cost' => static fn (TestRunner $t) => $t->same(1, $plan174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['estimatedCost']),
    'same aliases cost class deduped' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-alias-point-deduped', $plan174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['costClass']),
    'same aliases fingerprint is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['planFingerprint'])),
    'first alias usage records rowid source' => static fn (TestRunner $t) => $t->same('rowid', $plan174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['aliasConstraintUsage'][0]['sourceColumn']),
    'second alias usage records hidden source' => static fn (TestRunner $t) => $t->same('_rowid_', $plan174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['aliasConstraintUsage'][1]['sourceColumn']),
    'third alias usage records oid source' => static fn (TestRunner $t) => $t->same('oid', $plan174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['aliasConstraintUsage'][2]['sourceColumn']),
    'alias usage canonicalizes to id' => static fn (TestRunner $t) => $t->same('id', $plan174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['aliasConstraintUsage'][1]['canonicalColumn']),
    'alias usage marks argv index' => static fn (TestRunner $t) => $t->same(1, $plan174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['aliasConstraintUsage'][2]['argvIndex']),
    'alias usage marks omit' => static fn (TestRunner $t) => $t->same(true, $plan174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['aliasConstraintUsage'][0]['omit']),
    'next changed path is fresh' => static fn (TestRunner $t) => $t->same('fresh-json-table-xfilter', $plan174()['nextGeneratedPathRowidAliasCurrentSourceNext174']['cursorMode']),
    'next changed path has no rows' => static fn (TestRunner $t) => $t->same([], $plan174()['nextGeneratedPathRowidAliasCurrentSourceNext174']['rowidTape']),
    'transition count' => static fn (TestRunner $t) => $t->same(17, count($plan174()['generatedPathRowidAliasCurrentSourceNext174Transitions'])),
    'generated path transition changes' => static fn (TestRunner $t) => $t->same(true, $plan174()['generatedPathRowidAliasCurrentSourceNext174Transitions'][2]['changed']),
    'rowid alias transition stable' => static fn (TestRunner $t) => $t->same(false, $plan174()['generatedPathRowidAliasCurrentSourceNext174Transitions'][3]['changed']),
    'rowset transition changes' => static fn (TestRunner $t) => $t->same(true, $plan174()['generatedPathRowidAliasCurrentSourceNext174Transitions'][11]['changed']),
    'cost transition remains stable after alias dedupe' => static fn (TestRunner $t) => $t->same(false, $plan174()['generatedPathRowidAliasCurrentSourceNext174Transitions'][15]['changed']),
    'reasons include source change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-alias-next174-source-changed', $plan174()['next174ReplanReasons'], true)),
    'reasons include rowset change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-alias-next174-rowset-changed', $plan174()['next174ReplanReasons'], true)),
    'reasons include cost change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-alias-next174-cost-changed', $plan174()['next174ReplanReasons'], true)),
    'conflict marks contradiction' => static fn (TestRunner $t) => $t->same(true, $conflict174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['conflictingRowidAliases']),
    'conflict records both point values' => static fn (TestRunner $t) => $t->same([6, 7], $conflict174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['aliasPointValues']),
    'conflict has no canonical rowid' => static fn (TestRunner $t) => $t->same(null, $conflict174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['canonicalRowidConstraint']),
    'conflict empties rowid tape' => static fn (TestRunner $t) => $t->same([], $conflict174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['rowidTape']),
    'conflict empties path tape' => static fn (TestRunner $t) => $t->same([], $conflict174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['pathTape']),
    'conflict estimates zero rows' => static fn (TestRunner $t) => $t->same(0, $conflict174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['estimatedRows']),
    'conflict estimates zero cost' => static fn (TestRunner $t) => $t->same(0, $conflict174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['estimatedCost']),
    'conflict cost class empty' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-alias-contradiction-empty', $conflict174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['costClass']),
    'range has no canonical rowid' => static fn (TestRunner $t) => $t->same(null, $range174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['canonicalRowidConstraint']),
    'range preserves inherited range rowid tape' => static fn (TestRunner $t) => $t->same([4, 7, 5, 6, 8, 9], $range174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['rowidTape']),
    'range keeps inherited residual columns' => static fn (TestRunner $t) => $t->same(['path', 'id'], $range174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['residualColumns']),
    'unusable rowid alias is not canonical' => static fn (TestRunner $t) => $t->same(6, $unusable174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['canonicalRowidConstraint']),
    'unusable rowid alias is recorded but not argv' => static fn (TestRunner $t) => $t->same(null, $unusable174()['currentGeneratedPathRowidAliasCurrentSourceNext174']['aliasConstraintUsage'][0]['argvIndex']),
    'bad json source column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::generatedPathRowidAliasPlan('json_tree', $current174, $next174, '', 'generated_path', $sameAlias174)),
    'bad generated path column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::generatedPathRowidAliasPlan('json_tree', $current174, $next174, 'option_value', '', $sameAlias174)),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid alias plan ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
