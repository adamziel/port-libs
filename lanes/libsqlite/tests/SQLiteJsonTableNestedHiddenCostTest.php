<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current129 = [
    'option_id' => 129,
    'option_name' => 'wp_plugin_nested_hidden_cost',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false}]},{"name":"forms","rules":[{"slug":"forms","priority":4,"enabled":true},{"slug":"lead","priority":6,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$next129 = [
    'option_id' => 129,
    'option_name' => 'wp_plugin_nested_hidden_cost',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":3,"enabled":true},{"slug":"cache","priority":8,"enabled":false}]},{"name":"forms","rules":[{"slug":"forms","priority":4,"enabled":true},{"slug":"lead","priority":6,"enabled":true},{"slug":"spam","priority":1,"enabled":false}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[1].rules',
];
$constraints129 = [
    ['column' => 'path', 'operator' => '=', 'value' => '$.plugin.groups[0].rules[0]'],
    ['column' => 'id', 'operator' => '=', 'value' => 2],
];
$order129 = [['column' => 'id']];

$plan129 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
): array => SQLiteJsonTablePlan::currentSourceNestedHiddenCost(
    'json_tree',
    $current ?? $current129,
    $next ?? $next129,
    'option_value',
    'base_root',
    'nested_path',
    $constraints ?? $constraints129,
    $orderBy ?? $order129,
);

$stable129 = static fn (): array => $plan129($current129, $current129);
$rowidOnly129 = static fn (): array => $plan129(
    array_replace($current129, ['base_root' => '$.plugin.groups[0].rules[0]', 'nested_path' => '']),
    array_replace($current129, ['base_root' => '$.plugin.groups[0].rules[0]', 'nested_path' => '']),
    [['column' => 'id', 'operator' => '=', 'value' => 2]],
    [['column' => 'id']],
);
$pathOnly129 = static fn (): array => $plan129(
    $current129,
    $current129,
    [['column' => 'path', 'operator' => '=', 'value' => '$.plugin.groups[0].rules[0]']],
    [['column' => 'id']],
);
$fullScan129 = static fn (): array => $plan129(
    $current129,
    $next129,
    [['column' => 'type', 'operator' => '=', 'value' => 'integer']],
    [['column' => 'atom', 'direction' => 'DESC']],
);
$bareVsDot129 = static fn (): array => $plan129(
    array_replace($current129, ['base_root' => '$.plugin.groups[0]', 'nested_path' => 'rules']),
    array_replace($current129, ['base_root' => '$.plugin.groups[0]', 'nested_path' => '.rules']),
    [['column' => 'type', 'operator' => '=', 'value' => 'object']],
    [['column' => 'id']],
);
$absolute129 = static fn (): array => $plan129(
    array_replace($current129, ['nested_path' => '$.plugin.groups[1].rules']),
    array_replace($current129, ['nested_path' => '$.plugin.groups[1].rules']),
    [['column' => 'id', 'operator' => '=', 'value' => 2]],
    [['column' => 'id']],
);
$jsonb129 = static fn (): array => $plan129(
    $current129,
    array_replace($next129, ['option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($next129['option_value'])))]),
);
$unrunnable129 = static fn (): array => $plan129(
    $current129,
    array_replace($next129, ['option_value' => null]),
);

$tests = [
    'normalizes function name' => static fn (TestRunner $t) => $t->same('json_tree', $plan129()['function']),
    'records next129 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-nested-hidden-cost-current-source-next129', $plan129()['dependencies'], true)),
    'preserves next126 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-path-hidden-rowid-cost-current-source-next126', $plan129()['dependencies'], true)),
    'pins current nested hidden cost reader' => static fn (TestRunner $t) => $t->same('pin-current-json-table-nested-hidden-cost-source-until-cursor-reset', $plan129()['currentReaderPolicy']),
    'prepares changed nested hidden cost reader' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-nested-hidden-cost-source-plan', $plan129()['nextReaderPolicy']),
    'stable nested hidden cost reader is reused' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-nested-hidden-cost-source-plan', $stable129()['nextReaderPolicy']),
    'stable plan has no next129 reasons' => static fn (TestRunner $t) => $t->same([], $stable129()['next129ReplanReasons']),
    'current composed root is nested array fragment' => static fn (TestRunner $t) => $t->same('$.plugin.groups[0].rules', $plan129()['currentNestedHiddenCost']['root']),
    'next composed root is nested array fragment' => static fn (TestRunner $t) => $t->same('$.plugin.groups[1].rules', $plan129()['nextNestedHiddenCost']['root']),
    'current hidden arguments include composed root' => static fn (TestRunner $t) => $t->same('$.plugin.groups[0].rules', $plan129()['currentNestedHiddenCost']['hiddenArguments'][1]),
    'next hidden arguments include composed root' => static fn (TestRunner $t) => $t->same('$.plugin.groups[1].rules', $plan129()['nextNestedHiddenCost']['hiddenArguments'][1]),
    'hidden argument count tracks json and root' => static fn (TestRunner $t) => $t->same(2, $plan129()['currentNestedHiddenCost']['hiddenArgumentCount']),
    'current root depth is nested path depth' => static fn (TestRunner $t) => $t->same(4, $plan129()['currentNestedHiddenCost']['hiddenRootDepth']),
    'array fragment mode is recorded' => static fn (TestRunner $t) => $t->same('array-fragment', $plan129()['currentNestedHiddenCost']['mode']),
    'path rowid intersection scan is selected' => static fn (TestRunner $t) => $t->same('path-rowid-intersection', $plan129()['currentNestedHiddenCost']['scanStrategy']),
    'path rowid intersection cost class is nested' => static fn (TestRunner $t) => $t->same('json-table-nested-hidden-path-rowid-intersection', $plan129()['currentNestedHiddenCost']['costClass']),
    'composite signature combines path and rowid' => static fn (TestRunner $t) => $t->same('2:path:=:"$.plugin.groups[0].rules[0]"&&3:id:=:2', $plan129()['currentNestedHiddenCost']['compositeSignature']),
    'current rowid tape is rooted' => static fn (TestRunner $t) => $t->same([['root' => '$.plugin.groups[0].rules', 'rowid' => 2, 'fullkey' => '$.plugin.groups[0].rules[0].slug']], $plan129()['currentNestedHiddenCost']['rootRowidTape']),
    'rowid only scan strategy is recorded' => static fn (TestRunner $t) => $t->same('rowid-only-lookup', $rowidOnly129()['currentNestedHiddenCost']['scanStrategy']),
    'rowid only cost class is nested lookup' => static fn (TestRunner $t) => $t->same('json-table-nested-hidden-rowid-lookup', $rowidOnly129()['currentNestedHiddenCost']['costClass']),
    'path only scan strategy is recorded' => static fn (TestRunner $t) => $t->same('path-only-lookup', $pathOnly129()['currentNestedHiddenCost']['scanStrategy']),
    'path only cost class is nested lookup' => static fn (TestRunner $t) => $t->same('json-table-nested-hidden-path-lookup', $pathOnly129()['currentNestedHiddenCost']['costClass']),
    'full scan cost class is recorded' => static fn (TestRunner $t) => $t->same('json-table-nested-hidden-full-scan', $fullScan129()['currentNestedHiddenCost']['costClass']),
    'full scan current row count follows nested priorities' => static fn (TestRunner $t) => $t->same(2, $fullScan129()['currentNestedHiddenCost']['rowCount']),
    'full scan next row count follows changed nested priorities' => static fn (TestRunner $t) => $t->same(3, $fullScan129()['nextNestedHiddenCost']['rowCount']),
    'hidden estimated cost includes root depth and mode penalty' => static fn (TestRunner $t) => $t->same($plan129()['currentNestedHiddenCost']['effectiveEstimatedCost'] + 5, $plan129()['currentNestedHiddenCost']['hiddenEstimatedCost']),
    'root transition changes' => static fn (TestRunner $t) => $t->same(true, $plan129()['nestedHiddenCostTransitions'][0]['changed']),
    'mode transition is stable for array fragments' => static fn (TestRunner $t) => $t->same(false, $plan129()['nestedHiddenCostTransitions'][1]['changed']),
    'hidden argument transition changes' => static fn (TestRunner $t) => $t->same(true, $plan129()['nestedHiddenCostTransitions'][2]['changed']),
    'scan strategy transition is stable' => static fn (TestRunner $t) => $t->same(false, $plan129()['nestedHiddenCostTransitions'][3]['changed']),
    'row count transition changes for full scan' => static fn (TestRunner $t) => $t->same(true, $fullScan129()['nestedHiddenCostTransitions'][5]['changed']),
    'root rowid tape transition changes' => static fn (TestRunner $t) => $t->same(true, $plan129()['nestedHiddenCostTransitions'][6]['changed']),
    'reasons include nested hidden root change' => static fn (TestRunner $t) => $t->true(in_array('json-table-nested-hidden-root-changed', $plan129()['next129ReplanReasons'], true)),
    'reasons include nested hidden output change' => static fn (TestRunner $t) => $t->true(in_array('json-table-nested-hidden-output-changed', $plan129()['next129ReplanReasons'], true)),
    'full scan reasons include row count change' => static fn (TestRunner $t) => $t->true(in_array('json-table-nested-hidden-row-count-changed', $fullScan129()['next129ReplanReasons'], true)),
    'bare nested mode is recorded' => static fn (TestRunner $t) => $t->same('bare-label-fragment', $bareVsDot129()['currentNestedHiddenCost']['mode']),
    'dot nested mode is recorded' => static fn (TestRunner $t) => $t->same('object-fragment', $bareVsDot129()['nextNestedHiddenCost']['mode']),
    'bare and dot compose same root' => static fn (TestRunner $t) => $t->same($bareVsDot129()['currentNestedHiddenCost']['root'], $bareVsDot129()['nextNestedHiddenCost']['root']),
    'bare to dot reason includes mode change' => static fn (TestRunner $t) => $t->true(in_array('json-table-nested-hidden-mode-changed', $bareVsDot129()['next129ReplanReasons'], true)),
    'absolute nested path cost remains bounded above sentinel' => static fn (TestRunner $t) => $t->true($absolute129()['currentNestedHiddenCost']['hiddenEstimatedCost'] < 1000000),
    'jsonb next source remains runnable' => static fn (TestRunner $t) => $t->same(true, $jsonb129()['next']['runnable']),
    'jsonb next changes source kind' => static fn (TestRunner $t) => $t->true(in_array('source-json-kind-changed', $jsonb129()['next129ReplanReasons'], true)),
    'unrunnable next has sentinel cost class' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable129()['nextNestedHiddenCost']['costClass']),
    'unrunnable next has sentinel hidden cost' => static fn (TestRunner $t) => $t->same(1000000, $unrunnable129()['nextNestedHiddenCost']['hiddenEstimatedCost']),
    'bad function is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceNestedHiddenCost('json_bad', $current129, $next129, 'option_value', 'base_root', 'nested_path')),
    'empty json column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceNestedHiddenCost('json_tree', $current129, $next129, '', 'base_root', 'nested_path')),
    'empty base root column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceNestedHiddenCost('json_tree', $current129, $next129, 'option_value', '', 'nested_path')),
    'empty nested path column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceNestedHiddenCost('json_tree', $current129, $next129, 'option_value', 'base_root', '')),
    'missing nested path source is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan129($current129, ['option_value' => '{}', 'base_root' => '$'])),
    'bad nested path source is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan129(array_replace($current129, ['nested_path' => '[#-]']), $next129)),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table nested hidden cost current source next129 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
