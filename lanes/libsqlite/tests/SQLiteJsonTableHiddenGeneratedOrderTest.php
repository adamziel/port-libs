<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current132 = [
    'option_id' => 132,
    'option_name' => 'wp_plugin_generated_order',
    'option_value' => '{"rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false},{"slug":"forms","priority":4,"enabled":true}]}',
    'scan_root' => '$.rules',
];
$next132 = [
    'option_id' => 132,
    'option_name' => 'wp_plugin_generated_order',
    'option_value' => '{"rules":[{"slug":"seo","priority":6,"enabled":true},{"slug":"cache","priority":1,"enabled":false},{"slug":"forms","priority":4,"enabled":true},{"slug":"shop","priority":5,"enabled":true}]}',
    'scan_root' => '$.rules',
];
$single132 = array_replace($current132, ['scan_root' => '$.rules[0]']);
$constraints132 = [
    ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.rules[%]'],
];
$generatedOrder132 = [
    ['name' => 'generated_priority', 'source' => 'value', 'path' => '$.priority', 'direction' => 'ASC'],
    ['name' => 'generated_slug', 'source' => 'value', 'path' => '$.slug', 'direction' => 'DESC'],
];

$plan132 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?array $generated = null,
): array => SQLiteJsonTablePlan::currentSourceHiddenGeneratedOrder(
    'json_tree',
    $current ?? $current132,
    $next ?? $next132,
    'option_value',
    $constraints ?? $constraints132,
    'scan_root',
    $orderBy ?? [['column' => 'json'], ['column' => 'id']],
    $generated ?? $generatedOrder132,
);

$stable132 = static fn (): array => $plan132($current132, $current132);
$single132Plan = static fn (): array => $plan132($single132, $single132);
$desc132 = static fn (): array => $plan132(
    $current132,
    $next132,
    $constraints132,
    [['column' => 'root'], ['column' => 'id']],
    [['name' => 'generated_priority', 'source' => 'value', 'path' => '$.priority', 'direction' => 'DESC']],
);
$jsonSource132 = static fn (): array => $plan132(
    $current132,
    $next132,
    [['column' => 'type', 'operator' => '=', 'value' => 'object']],
    [['column' => 'json']],
    [['name' => 'rule_count', 'source' => 'json', 'path' => '$.rules', 'direction' => 'ASC']],
);
$unrunnable132 = static fn (): array => $plan132(
    $current132,
    array_replace($next132, ['option_value' => null]),
);

$tests = [
    'normalizes function name' => static fn (TestRunner $t) => $t->same('json_tree', $plan132()['function']),
    'records next132 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-hidden-generated-order-current-source-next132', $plan132()['dependencies'], true)),
    'preserves next122 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-indexed-hidden-order-current-source-next122', $plan132()['dependencies'], true)),
    'pins current reader' => static fn (TestRunner $t) => $t->same('pin-current-json-table-hidden-generated-order-until-cursor-reset', $plan132()['currentReaderPolicy']),
    'prepares changed next plan' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-hidden-generated-order-plan', $plan132()['nextReaderPolicy']),
    'stable plan reuses current plan' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-hidden-generated-order-plan', $stable132()['nextReaderPolicy']),
    'stable plan has no next132 reasons' => static fn (TestRunner $t) => $t->same([], $stable132()['next132ReplanReasons']),
    'generated order terms are normalized' => static fn (TestRunner $t) => $t->same($generatedOrder132, $plan132()['currentHiddenGeneratedOrder']['generatedOrderBy']),
    'generated order flag is set' => static fn (TestRunner $t) => $t->same(true, $plan132()['currentHiddenGeneratedOrder']['hasGeneratedOrder']),
    'current sorter is required for multiple rows' => static fn (TestRunner $t) => $t->same(true, $plan132()['currentHiddenGeneratedOrder']['requiresGeneratedSorter']),
    'next sorter is required for inserted rowset' => static fn (TestRunner $t) => $t->same(true, $plan132()['nextHiddenGeneratedOrder']['requiresGeneratedSorter']),
    'single row avoids generated sorter' => static fn (TestRunner $t) => $t->same(false, $single132Plan()['currentHiddenGeneratedOrder']['requiresGeneratedSorter']),
    'current row count tracks object rules' => static fn (TestRunner $t) => $t->same(3, $plan132()['currentHiddenGeneratedOrder']['rowCount']),
    'next row count tracks inserted rule' => static fn (TestRunner $t) => $t->same(4, $plan132()['nextHiddenGeneratedOrder']['rowCount']),
    'current generated keys sort by priority then slug' => static fn (TestRunner $t) => $t->same([[2, 'seo'], [4, 'forms'], [7, 'cache']], $plan132()['currentHiddenGeneratedOrder']['rowGeneratedKeys']),
    'next generated keys sort changed priorities' => static fn (TestRunner $t) => $t->same([[1, 'cache'], [4, 'forms'], [5, 'shop'], [6, 'seo']], $plan132()['nextHiddenGeneratedOrder']['rowGeneratedKeys']),
    'current ordered rowids follow generated priority order' => static fn (TestRunner $t) => $t->same([1, 9, 5], $plan132()['currentHiddenGeneratedOrder']['orderedRowids']),
    'next ordered rowids follow generated priority order' => static fn (TestRunner $t) => $t->same([5, 9, 13, 1], $plan132()['nextHiddenGeneratedOrder']['orderedRowids']),
    'current first generated key is lowest priority' => static fn (TestRunner $t) => $t->same([2, 'seo'], $plan132()['currentHiddenGeneratedOrder']['firstGeneratedKey']),
    'next first generated key is changed cache priority' => static fn (TestRunner $t) => $t->same([1, 'cache'], $plan132()['nextHiddenGeneratedOrder']['firstGeneratedKey']),
    'current last generated key is highest priority' => static fn (TestRunner $t) => $t->same([7, 'cache'], $plan132()['currentHiddenGeneratedOrder']['lastGeneratedKey']),
    'next last generated key is highest priority' => static fn (TestRunner $t) => $t->same([6, 'seo'], $plan132()['nextHiddenGeneratedOrder']['lastGeneratedKey']),
    'generated output tape preserves fullkeys' => static fn (TestRunner $t) => $t->same(['$.rules[0]', '$.rules[2]', '$.rules[1]'], array_column($plan132()['currentHiddenGeneratedOrder']['generatedOutputTape'], 'fullkey')),
    'next generated output tape preserves inserted fullkey' => static fn (TestRunner $t) => $t->same(['$.rules[1]', '$.rules[2]', '$.rules[3]', '$.rules[0]'], array_column($plan132()['nextHiddenGeneratedOrder']['generatedOutputTape'], 'fullkey')),
    'current hidden source key is option JSON' => static fn (TestRunner $t) => $t->same([$current132['option_value']], $plan132()['currentHiddenGeneratedOrder']['hiddenSourceKey']),
    'next hidden source key is next option JSON' => static fn (TestRunner $t) => $t->same([$next132['option_value']], $plan132()['nextHiddenGeneratedOrder']['hiddenSourceKey']),
    'current hidden effective cost is carried forward' => static fn (TestRunner $t) => $t->same($plan132()['currentIndexedHiddenOrder']['effectiveEstimatedCost'], $plan132()['currentHiddenGeneratedOrder']['hiddenEffectiveCost']),
    'generated sort penalty reflects two generated terms' => static fn (TestRunner $t) => $t->same(12, $plan132()['currentHiddenGeneratedOrder']['generatedSortPenalty']),
    'next generated sort penalty reflects four rows' => static fn (TestRunner $t) => $t->same(16, $plan132()['nextHiddenGeneratedOrder']['generatedSortPenalty']),
    'effective cost adds generated sort penalty' => static fn (TestRunner $t) => $t->same($plan132()['currentHiddenGeneratedOrder']['hiddenEffectiveCost'] + 12, $plan132()['currentHiddenGeneratedOrder']['effectiveEstimatedCost']),
    'cost class names generated sort' => static fn (TestRunner $t) => $t->same('json-table-hidden-generated-order-sort-required', $plan132()['currentHiddenGeneratedOrder']['costClass']),
    'single row cost class is narrow generated order' => static fn (TestRunner $t) => $t->same('json-table-hidden-generated-order-narrow', $single132Plan()['currentHiddenGeneratedOrder']['costClass']),
    'descending generated priority reverses current rowids' => static fn (TestRunner $t) => $t->same([5, 9, 1], $desc132()['currentHiddenGeneratedOrder']['orderedRowids']),
    'descending generated priority reverses next rowids' => static fn (TestRunner $t) => $t->same([1, 13, 9, 5], $desc132()['nextHiddenGeneratedOrder']['orderedRowids']),
    'root hidden source order is captured' => static fn (TestRunner $t) => $t->same(['$.rules'], $desc132()['currentHiddenGeneratedOrder']['hiddenSourceKey']),
    'json source generated term can read full document' => static fn (TestRunner $t) => $t->same(['[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false},{"slug":"forms","priority":4,"enabled":true}]'], $jsonSource132()['currentHiddenGeneratedOrder']['firstGeneratedKey']),
    'transition count records generated state' => static fn (TestRunner $t) => $t->same(7, count($plan132()['hiddenGeneratedOrderTransitions'])),
    'generated order transition is stable' => static fn (TestRunner $t) => $t->same(false, $plan132()['hiddenGeneratedOrderTransitions'][0]['changed']),
    'hidden source transition changes' => static fn (TestRunner $t) => $t->same(true, $plan132()['hiddenGeneratedOrderTransitions'][1]['changed']),
    'generated keys transition changes' => static fn (TestRunner $t) => $t->same(true, $plan132()['hiddenGeneratedOrderTransitions'][2]['changed']),
    'ordered rowids transition changes' => static fn (TestRunner $t) => $t->same(true, $plan132()['hiddenGeneratedOrderTransitions'][3]['changed']),
    'generated sorter transition is stable for multi-row plans' => static fn (TestRunner $t) => $t->same(false, $plan132()['hiddenGeneratedOrderTransitions'][4]['changed']),
    'effective cost transition changes with inserted row' => static fn (TestRunner $t) => $t->same(true, $plan132()['hiddenGeneratedOrderTransitions'][5]['changed']),
    'cost class transition remains stable for multi-row sort' => static fn (TestRunner $t) => $t->same(false, $plan132()['hiddenGeneratedOrderTransitions'][6]['changed']),
    'next132 reasons include source change' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-generated-source-changed', $plan132()['next132ReplanReasons'], true)),
    'next132 reasons include key change' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-generated-keys-changed', $plan132()['next132ReplanReasons'], true)),
    'next132 reasons include output order change' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-generated-output-order-changed', $plan132()['next132ReplanReasons'], true)),
    'next132 reasons include cost change' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-generated-cost-changed', $plan132()['next132ReplanReasons'], true)),
    'next132 preserves next122 source reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-order-source-changed', $plan132()['next132ReplanReasons'], true)),
    'unrunnable next has sentinel cost class' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable132()['nextHiddenGeneratedOrder']['costClass']),
    'unrunnable next has sentinel effective cost' => static fn (TestRunner $t) => $t->same(1000000, $unrunnable132()['nextHiddenGeneratedOrder']['effectiveEstimatedCost']),
    'unrunnable next has no ordered rowids' => static fn (TestRunner $t) => $t->same([], $unrunnable132()['nextHiddenGeneratedOrder']['orderedRowids']),
    'empty generated order is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan132($current132, $next132, $constraints132, [['column' => 'json']], [])),
    'bad generated order direction is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan132($current132, $next132, $constraints132, [['column' => 'json']], [['name' => 'priority', 'source' => 'value', 'path' => '$.priority', 'direction' => 'SIDEWAYS']])),
    'bad generated order source is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan132($current132, $next132, $constraints132, [['column' => 'json']], [['name' => 'priority', 'source' => 'missing', 'path' => '$.priority']])),
    'bad generated order path is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan132($current132, $next132, $constraints132, [['column' => 'json']], [['name' => 'priority', 'source' => 'value', 'path' => '$[#-]']])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table hidden generated order current source next132 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
