<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$json = '{"rules":[{"name":"seo","priority":2,"enabled":true},{"name":"cache","priority":7,"enabled":false},{"name":"forms","priority":4,"enabled":true}],"flags":["network","beta"],"meta":{"version":2}}';
$base = [
    ['column' => 'json', 'operator' => '=', 'value' => $json],
    ['column' => 'root', 'operator' => '=', 'value' => '$.rules'],
];
$alternatives = [
    [
        ['column' => 'key', 'operator' => '=', 'value' => 'priority'],
        ['column' => 'atom', 'operator' => '>=', 'value' => 4],
    ],
    [
        ['column' => 'key', 'operator' => '=', 'value' => 'name'],
        ['column' => 'atom', 'operator' => 'LIKE', 'value' => 'c%'],
    ],
    [
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ],
];
$orderBy = [['column' => 'id', 'direction' => 'ASC']];

$plan = static fn (): array => SQLiteJsonTablePlan::rankedAlternativePlan('json_tree', $base, $alternatives, $orderBy);
$branches = static fn (): array => $plan()['branches'];
$rows = static fn (): array => $plan()['rows'];

$tests = [
    'function is normalized' => static fn (TestRunner $t) => $t->same('json_tree', SQLiteJsonTablePlan::rankedAlternativePlan('JSON_TREE', $base, $alternatives)['function']),
    'plan is runnable with json base' => static fn (TestRunner $t) => $t->same(true, $plan()['runnable']),
    'cheapest branch is selected first' => static fn (TestRunner $t) => $t->same(1, $plan()['chosenBranch']),
    'branch order is deterministic by cost rows index' => static fn (TestRunner $t) => $t->same([1, 0, 2], $plan()['branchOrder']),
    'branch one sorts first by lower cost' => static fn (TestRunner $t) => $t->same(1, $branches()[0]['branch']),
    'branch zero sorts second by range cost' => static fn (TestRunner $t) => $t->same(0, $branches()[1]['branch']),
    'branch two remains branch two after sorting' => static fn (TestRunner $t) => $t->same(2, $branches()[2]['branch']),
    'branch zero has json hidden argument first' => static fn (TestRunner $t) => $t->same($json, $branches()[0]['filterArguments'][0]),
    'branch zero has root hidden argument second' => static fn (TestRunner $t) => $t->same('$.rules', $branches()[0]['filterArguments'][1]),
    'first sorted branch records name key argument' => static fn (TestRunner $t) => $t->same('name', $branches()[0]['filterArguments'][2]),
    'first sorted branch records like argument' => static fn (TestRunner $t) => $t->same('c%', $branches()[0]['filterArguments'][3]),
    'second sorted branch records priority key argument' => static fn (TestRunner $t) => $t->same('priority', $branches()[1]['filterArguments'][2]),
    'second sorted branch records range argument' => static fn (TestRunner $t) => $t->same(4, $branches()[1]['filterArguments'][3]),
    'branch two records object type argument' => static fn (TestRunner $t) => $t->same('object', $branches()[2]['filterArguments'][2]),
    'branch zero usage includes hidden json' => static fn (TestRunner $t) => $t->same('json', $branches()[0]['constraintUsage'][0]['column']),
    'branch zero usage includes hidden root' => static fn (TestRunner $t) => $t->same('root', $branches()[0]['constraintUsage'][1]['column']),
    'branch zero usage includes visible key' => static fn (TestRunner $t) => $t->same('key', $branches()[0]['constraintUsage'][2]['column']),
    'branch zero usage includes visible atom' => static fn (TestRunner $t) => $t->same('atom', $branches()[0]['constraintUsage'][3]['column']),
    'first sorted branch row count includes cache name' => static fn (TestRunner $t) => $t->same(1, $branches()[0]['rowCount']),
    'second sorted branch row count includes matching priorities' => static fn (TestRunner $t) => $t->same(2, $branches()[1]['rowCount']),
    'branch two row count includes rule objects' => static fn (TestRunner $t) => $t->same(3, $branches()[2]['rowCount']),
    'merged rows deduplicate object rows from alternatives' => static fn (TestRunner $t) => $t->same(6, count($rows())),
    'merged rows are sorted by id when alternatives cannot globally consume order' => static fn (TestRunner $t) => $t->same([1, 5, 6, 7, 9, 11], array_column($rows(), 'id')),
    'first row is first object under rules' => static fn (TestRunner $t) => $t->same('$.rules[0]', $rows()[0]['fullkey']),
    'second row is cache object' => static fn (TestRunner $t) => $t->same('$.rules[1]', $rows()[1]['fullkey']),
    'third row is cache name' => static fn (TestRunner $t) => $t->same('$.rules[1].name', $rows()[2]['fullkey']),
    'fourth row is cache priority' => static fn (TestRunner $t) => $t->same(7, $rows()[3]['atom']),
    'fifth row is forms object' => static fn (TestRunner $t) => $t->same('$.rules[2]', $rows()[4]['fullkey']),
    'sixth row is forms priority' => static fn (TestRunner $t) => $t->same(4, $rows()[5]['atom']),
    'estimated cost sums runnable branches' => static fn (TestRunner $t) => $t->same(10, $plan()['estimatedCost']),
    'estimated rows sums branch estimates' => static fn (TestRunner $t) => $t->same(6, $plan()['estimatedRows']),
    'order by consumed is false for id with visible branch filters' => static fn (TestRunner $t) => $t->same(false, $plan()['orderByConsumed']),
    'reader policy records ranked planning' => static fn (TestRunner $t) => $t->same('rank-json-table-alternative-branches-before-xfilter', $plan()['currentReaderPolicy']),
    'next policy records reuse boundary' => static fn (TestRunner $t) => $t->same('reuse-ranked-json-table-alternative-plan-until-constraint-change', $plan()['nextReaderPolicy']),
    'dependency marker is stable and unsuffixed' => static fn (TestRunner $t) => $t->same(['sqlite-json-table-ranked-alternative-planner-current'], $plan()['dependencies']),
    'json_each branch ranking works' => static fn (TestRunner $t) => $t->same([0, 1], SQLiteJsonTablePlan::rankedAlternativePlan('json_each', [['column' => 'json', 'operator' => '=', 'value' => '{"a":1,"b":2}']], [[['column' => 'key', 'operator' => '=', 'value' => 'a']], [['column' => 'atom', 'operator' => '>', 'value' => 1]]])['branchOrder']),
    'json_each rows merge branch outputs' => static fn (TestRunner $t) => $t->same(['a', 'b'], array_column(SQLiteJsonTablePlan::rankedAlternativePlan('json_each', [['column' => 'json', 'operator' => '=', 'value' => '{"a":1,"b":2}']], [[['column' => 'key', 'operator' => '=', 'value' => 'a']], [['column' => 'atom', 'operator' => '>', 'value' => 1]]])['rows'], 'key')),
    'duplicate alternatives deduplicate rows' => static fn (TestRunner $t) => $t->same(1, count(SQLiteJsonTablePlan::rankedAlternativePlan('json_each', [['column' => 'json', 'operator' => '=', 'value' => '{"a":1}']], [[['column' => 'key', 'operator' => '=', 'value' => 'a']], [['column' => 'atom', 'operator' => '=', 'value' => 1]]])['rows'])),
    'unrunnable without json chooses null' => static fn (TestRunner $t) => $t->same(null, SQLiteJsonTablePlan::rankedAlternativePlan('json_each', [], [[['column' => 'key', 'operator' => '=', 'value' => 'a']]])['chosenBranch']),
    'unrunnable without json has no rows' => static fn (TestRunner $t) => $t->same([], SQLiteJsonTablePlan::rankedAlternativePlan('json_each', [], [[['column' => 'key', 'operator' => '=', 'value' => 'a']]])['rows']),
    'unrunnable without json has million cost' => static fn (TestRunner $t) => $t->same(1000000, SQLiteJsonTablePlan::rankedAlternativePlan('json_each', [], [[['column' => 'key', 'operator' => '=', 'value' => 'a']]])['estimatedCost']),
    'invalid empty alternatives rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::rankedAlternativePlan('json_each', $base, [])),
    'invalid empty branch rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::rankedAlternativePlan('json_each', $base, [[]])),
    'invalid function rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::rankedAlternativePlan('json_group_array', $base, $alternatives)),
    'invalid root still rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::rankedAlternativePlan('json_each', [['column' => 'json', 'operator' => '=', 'value' => '{}'], ['column' => 'root', 'operator' => '=', 'value' => 'bad']], [[['column' => 'key', 'operator' => '=', 'value' => 'a']]])),
    'order by desc sorts merged rows descending when not consumed' => static fn (TestRunner $t) => $t->same([11, 9, 7, 6, 5, 1], array_column(SQLiteJsonTablePlan::rankedAlternativePlan('json_tree', $base, $alternatives, [['column' => 'id', 'direction' => 'DESC']])['rows'], 'id')),
    'limit in base caps each branch before merge' => static fn (TestRunner $t) => $t->same(3, count(SQLiteJsonTablePlan::rankedAlternativePlan('json_tree', array_merge($base, [['column' => 'limit', 'operator' => '=', 'value' => 1]]), $alternatives, $orderBy)['rows'])),
    'offset in base skips first match per branch' => static fn (TestRunner $t) => $t->same([11], array_column(SQLiteJsonTablePlan::rankedAlternativePlan('json_tree', array_merge($base, [['column' => 'offset', 'operator' => '=', 'value' => 1]]), [$alternatives[0]], $orderBy)['rows'], 'id')),
    'unusable visible branch is residual and still filters rows' => static fn (TestRunner $t) => $t->same(1, count(SQLiteJsonTablePlan::rankedAlternativePlan('json_each', [['column' => 'json', 'operator' => '=', 'value' => '{"a":1,"b":2}']], [[['column' => 'key', 'operator' => '=', 'value' => 'a', 'usable' => false]]])['rows'])),
    'rowid alias branch normalizes to id usage' => static fn (TestRunner $t) => $t->same('id', SQLiteJsonTablePlan::rankedAlternativePlan('json_each', [['column' => 'json', 'operator' => '=', 'value' => '{"a":1}']], [[['column' => 'rowid', 'operator' => '=', 'value' => 1]]])['branches'][0]['constraintUsage'][1]['column']),
    'oid alias branch normalizes to id usage' => static fn (TestRunner $t) => $t->same('id', SQLiteJsonTablePlan::rankedAlternativePlan('json_each', [['column' => 'json', 'operator' => '=', 'value' => '{"a":1}']], [[['column' => 'oid', 'operator' => '=', 'value' => 1]]])['branches'][0]['constraintUsage'][1]['column']),
    'fullkey like branch returns nested priority rows' => static fn (TestRunner $t) => $t->same(['$.rules[0].priority', '$.rules[1].priority', '$.rules[2].priority'], array_column(SQLiteJsonTablePlan::rankedAlternativePlan('json_tree', $base, [[['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.rules[%].priority']]], $orderBy)['rows'], 'fullkey')),
];

foreach ($tests as $name => $case) {
    $tests['json table ranked alternative planner current ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
