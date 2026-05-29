<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current127 = [
    'option_id' => 127,
    'option_name' => 'wp_plugin_nested_rule_order',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]},{"name":"forms","rules":[{"slug":"forms","priority":4},{"slug":"lead","priority":6}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$next127 = [
    'option_id' => 127,
    'option_name' => 'wp_plugin_nested_rule_order',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":3},{"slug":"cache","priority":8},{"slug":"shop","priority":5}]},{"name":"forms","rules":[{"slug":"forms","priority":4},{"slug":"lead","priority":6},{"slug":"mail","priority":1}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[1].rules',
];
$constraints127 = [
    ['column' => 'key', 'operator' => '=', 'value' => 'priority'],
    ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
];
$order127 = [['column' => 'key'], ['column' => 'atom', 'direction' => 'DESC']];

$plan127 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
): array => SQLiteJsonTablePlan::currentSourceNestedConstraintOrder(
    'json_tree',
    $current ?? $current127,
    $next ?? $next127,
    'option_value',
    'base_root',
    'nested_path',
    $constraints ?? $constraints127,
    $orderBy ?? $order127,
);

$stable127 = static fn (): array => $plan127($current127, $current127);
$complete127 = static fn (): array => $plan127(
    $current127,
    $current127,
    $constraints127,
    [['column' => 'key'], ['column' => 'rowid']],
);
$bare127 = static fn (): array => $plan127(
    array_replace($current127, ['base_root' => '$.plugin.groups[0]', 'nested_path' => 'rules']),
    array_replace($current127, ['base_root' => '$.plugin.groups[0]', 'nested_path' => '.rules']),
    [['column' => 'type', 'operator' => '=', 'value' => 'object']],
    [['column' => 'id']],
);
$jsonb127 = static fn (): array => $plan127(
    $current127,
    array_replace($next127, ['option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($next127['option_value'])))]),
);
$unrunnable127 = static fn (): array => $plan127(
    $current127,
    array_replace($next127, ['option_value' => null]),
);

$tests = [
    'normalizes function' => static fn (TestRunner $t) => $t->same('json_tree', $plan127()['function']),
    'records next127 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-nested-constraint-order-current-source-next127', $plan127()['dependencies'], true)),
    'preserves next124 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-constraint-orderby-cost-current-source-next124', $plan127()['dependencies'], true)),
    'preserves next121 style root composition through output profile' => static fn (TestRunner $t) => $t->same('$.plugin.groups[0].rules', $plan127()['currentNestedConstraintOrder']['root']),
    'pins current nested constraint order reader' => static fn (TestRunner $t) => $t->same('pin-current-json-table-nested-constraint-order-source-until-cursor-reset', $plan127()['currentReaderPolicy']),
    'prepares changed nested constraint order reader' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-nested-constraint-order-source-plan', $plan127()['nextReaderPolicy']),
    'stable nested constraint order reader is reused' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-nested-constraint-order-source-plan', $stable127()['nextReaderPolicy']),
    'stable plan has no next127 reasons' => static fn (TestRunner $t) => $t->same([], $stable127()['next127ReplanReasons']),
    'current composed root appends array fragment' => static fn (TestRunner $t) => $t->same('$.plugin.groups[0].rules', $plan127()['currentNestedConstraintOrder']['root']),
    'next composed root appends array fragment' => static fn (TestRunner $t) => $t->same('$.plugin.groups[1].rules', $plan127()['nextNestedConstraintOrder']['root']),
    'current mode is array fragment' => static fn (TestRunner $t) => $t->same('array-fragment', $plan127()['currentNestedConstraintOrder']['mode']),
    'next mode is array fragment' => static fn (TestRunner $t) => $t->same('array-fragment', $plan127()['nextNestedConstraintOrder']['mode']),
    'base root transition remains stable' => static fn (TestRunner $t) => $t->same(false, $plan127()['nestedConstraintOrderTransitions']['baseRoot']['changed']),
    'nested path transition changes' => static fn (TestRunner $t) => $t->same(true, $plan127()['nestedConstraintOrderTransitions']['nestedPath']['changed']),
    'composed root transition changes' => static fn (TestRunner $t) => $t->same(true, $plan127()['nestedConstraintOrderTransitions']['composedRoot']['changed']),
    'consumed prefix transition remains stable' => static fn (TestRunner $t) => $t->same(false, $plan127()['nestedConstraintOrderTransitions']['consumedPrefixColumns']['changed']),
    'suffix transition remains stable' => static fn (TestRunner $t) => $t->same(false, $plan127()['nestedConstraintOrderTransitions']['suffixColumns']['changed']),
    'current hidden root argument is composed' => static fn (TestRunner $t) => $t->same('$.plugin.groups[0].rules', $plan127()['current']['filterArguments'][1]),
    'next hidden root argument is composed' => static fn (TestRunner $t) => $t->same('$.plugin.groups[1].rules', $plan127()['next']['filterArguments'][1]),
    'current visible key prefix consumed' => static fn (TestRunner $t) => $t->same(['key'], $plan127()['currentNestedConstraintOrder']['consumedPrefixColumns']),
    'next visible key prefix consumed' => static fn (TestRunner $t) => $t->same(['key'], $plan127()['nextNestedConstraintOrder']['consumedPrefixColumns']),
    'current suffix is atom' => static fn (TestRunner $t) => $t->same(['atom'], $plan127()['currentNestedConstraintOrder']['suffixColumns']),
    'next suffix is atom' => static fn (TestRunner $t) => $t->same(['atom'], $plan127()['nextNestedConstraintOrder']['suffixColumns']),
    'current prefix consumed count is one' => static fn (TestRunner $t) => $t->same(1, $plan127()['currentNestedConstraintOrder']['prefixConsumedCount']),
    'next prefix consumed count is one' => static fn (TestRunner $t) => $t->same(1, $plan127()['nextNestedConstraintOrder']['prefixConsumedCount']),
    'current block sort required' => static fn (TestRunner $t) => $t->same(true, $plan127()['currentNestedConstraintOrder']['blockSortRequired']),
    'next block sort required' => static fn (TestRunner $t) => $t->same(true, $plan127()['nextNestedConstraintOrder']['blockSortRequired']),
    'current cost class records partial block sort' => static fn (TestRunner $t) => $t->same('json-table-partial-order-block-sort', $plan127()['currentNestedConstraintOrder']['costClass']),
    'next cost class records partial block sort' => static fn (TestRunner $t) => $t->same('json-table-partial-order-block-sort', $plan127()['nextNestedConstraintOrder']['costClass']),
    'current effective cost charges suffix sort only' => static fn (TestRunner $t) => $t->same(8, $plan127()['currentNestedConstraintOrder']['effectiveEstimatedCost']),
    'next effective cost charges suffix sort only' => static fn (TestRunner $t) => $t->same(8, $plan127()['nextNestedConstraintOrder']['effectiveEstimatedCost']),
    'current priorities ordered by suffix desc' => static fn (TestRunner $t) => $t->same([7, 4, 2], array_column($plan127()['currentRows'], 'atom')),
    'next priorities ordered by suffix desc' => static fn (TestRunner $t) => $t->same([6, 4, 1], array_column($plan127()['nextRows'], 'atom')),
    'current row order records sorted rowids' => static fn (TestRunner $t) => $t->same([6, 9, 3], $plan127()['currentNestedConstraintOrder']['rowOrder']),
    'next row order records nested sorted rowids' => static fn (TestRunner $t) => $t->same([6, 3, 9], $plan127()['nextNestedConstraintOrder']['rowOrder']),
    'current root order key couples root and first rowid' => static fn (TestRunner $t) => $t->same(['$.plugin.groups[0].rules', 6], $plan127()['currentNestedConstraintOrder']['rootOrderKey']),
    'next root order key couples root and first rowid' => static fn (TestRunner $t) => $t->same(['$.plugin.groups[1].rules', 6], $plan127()['nextNestedConstraintOrder']['rootOrderKey']),
    'current first suffix key is highest priority' => static fn (TestRunner $t) => $t->same([7], $plan127()['currentNestedConstraintOrder']['firstSuffixKey']),
    'next first suffix key is highest forms priority' => static fn (TestRunner $t) => $t->same([6], $plan127()['nextNestedConstraintOrder']['firstSuffixKey']),
    'current last suffix key is lowest priority' => static fn (TestRunner $t) => $t->same([2], $plan127()['currentNestedConstraintOrder']['lastSuffixKey']),
    'next last suffix key is lowest priority' => static fn (TestRunner $t) => $t->same([1], $plan127()['nextNestedConstraintOrder']['lastSuffixKey']),
    'coverage records constant key operator' => static fn (TestRunner $t) => $t->same('=', $plan127()['currentNestedConstraintOrder']['coverage'][0]['constraintOperator']),
    'coverage records constant key value' => static fn (TestRunner $t) => $t->same('priority', $plan127()['currentNestedConstraintOrder']['coverage'][0]['constraintValue']),
    'next127 reasons include nested path change' => static fn (TestRunner $t) => $t->true(in_array('json-table-nested-constraint-order-path-changed', $plan127()['next127ReplanReasons'], true)),
    'next127 reasons include nested root change' => static fn (TestRunner $t) => $t->true(in_array('json-table-nested-constraint-order-root-changed', $plan127()['next127ReplanReasons'], true)),
    'next127 reasons preserve partial output change' => static fn (TestRunner $t) => $t->true(in_array('json-table-partial-order-output-changed', $plan127()['next127ReplanReasons'], true)),
    'next127 reasons preserve source json change' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $plan127()['next127ReplanReasons'], true)),
    'complete order consumes key and id' => static fn (TestRunner $t) => $t->same(['key', 'id'], $complete127()['currentNestedConstraintOrder']['consumedPrefixColumns']),
    'complete order has empty suffix' => static fn (TestRunner $t) => $t->same([], $complete127()['currentNestedConstraintOrder']['suffixColumns']),
    'complete order avoids block sorter' => static fn (TestRunner $t) => $t->same(false, $complete127()['currentNestedConstraintOrder']['blockSortRequired']),
    'complete order cost class is consumed' => static fn (TestRunner $t) => $t->same('json-table-complete-order-consumed', $complete127()['currentNestedConstraintOrder']['costClass']),
    'bare nested label composes through dot' => static fn (TestRunner $t) => $t->same('$.plugin.groups[0].rules', $bare127()['currentNestedConstraintOrder']['root']),
    'dot nested label composes to same root' => static fn (TestRunner $t) => $t->same('$.plugin.groups[0].rules', $bare127()['nextNestedConstraintOrder']['root']),
    'bare nested label mode is recorded' => static fn (TestRunner $t) => $t->same('bare-label-fragment', $bare127()['currentNestedConstraintOrder']['mode']),
    'dot nested label mode is recorded' => static fn (TestRunner $t) => $t->same('object-fragment', $bare127()['nextNestedConstraintOrder']['mode']),
    'jsonb next changes source kind' => static fn (TestRunner $t) => $t->true(in_array('source-json-kind-changed', $jsonb127()['next127ReplanReasons'], true)),
    'jsonb next remains runnable' => static fn (TestRunner $t) => $t->same(true, $jsonb127()['next']['runnable']),
    'jsonb next input kind is jsonb' => static fn (TestRunner $t) => $t->same('jsonb', $jsonb127()['next']['jsonInputKind']),
    'unrunnable next has sentinel cost class' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable127()['nextNestedConstraintOrder']['costClass']),
    'unrunnable next has sentinel cost' => static fn (TestRunner $t) => $t->same(1000000, $unrunnable127()['nextNestedConstraintOrder']['effectiveEstimatedCost']),
    'missing base root is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan127(['option_value' => '{}', 'nested_path' => 'rules'], $next127)),
    'missing nested path is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan127($current127, ['option_value' => '{}', 'base_root' => '$'])),
    'bad nested path is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan127(array_replace($current127, ['nested_path' => '[#-]']), $next127)),
    'bad order direction is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan127($current127, $next127, $constraints127, [['column' => 'key', 'direction' => 'BAD']])) ,
];

foreach ($tests as $name => $case) {
    $tests['json table nested constraint order current source next127 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
