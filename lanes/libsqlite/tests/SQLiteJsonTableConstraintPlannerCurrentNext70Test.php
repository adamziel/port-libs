<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$settings70 = json_encode([
    'plugins' => [
        ['slug' => 'akismet', 'enabled' => true, 'autoload' => 'yes', 'priority' => 4],
        ['slug' => 'seo-pack', 'enabled' => true, 'autoload' => 'yes', 'priority' => 2],
        ['slug' => 'cache-pro', 'enabled' => true, 'autoload' => 'no', 'priority' => 7],
        ['slug' => 'forms-lite', 'enabled' => false, 'autoload' => 'yes', 'priority' => 5],
    ],
    'network' => ['enabled' => true, 'blog_id' => 1],
], JSON_THROW_ON_ERROR);

$constraints70 = [
    ['column' => 'json', 'operator' => '=', 'value' => $settings70],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugins'],
    ['column' => 'key', 'operator' => '=', 'value' => 'slug'],
    ['column' => 'type', 'operator' => '=', 'value' => 'text'],
    ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugins[%].slug'],
    ['column' => 'atom', 'operator' => 'GLOB', 'value' => '*-*'],
    ['column' => 'id', 'operator' => '>=', 'value' => 4],
];

$plan70 = static fn (): array => SQLiteJsonTablePlan::adjacentConstraintPlan(
    'json_tree',
    $constraints70,
    [['column' => 'id']],
);
$pairs70 = static fn (): array => $plan70()['rowCurrentNext'];
$first70 = static fn (): array => $pairs70()[0];
$second70 = static fn (): array => $pairs70()[1];

$limited70 = static fn (): array => SQLiteJsonTablePlan::adjacentConstraintPlan(
    'json_tree',
    array_merge($constraints70, [
        ['column' => 'limit', 'operator' => '=', 'value' => 1],
        ['column' => 'offset', 'operator' => '=', 'value' => 1],
    ]),
    [['column' => 'id']],
);

$unordered70 = static fn (): array => SQLiteJsonTablePlan::adjacentConstraintPlan(
    'json_tree',
    $constraints70,
    [['column' => 'atom', 'direction' => 'DESC']],
);

$cases70 = [
    'reports json tree function' => static fn (TestRunner $t) => $t->same('json_tree', $plan70()['function']),
    'plan is runnable from hidden json constraint' => static fn (TestRunner $t) => $t->same(true, $plan70()['runnable']),
    'idx num records hidden and visible constraints' => static fn (TestRunner $t) => $t->same(7, count(explode('|', $plan70()['idxStr']))),
    'filter arguments preserve hidden json first' => static fn (TestRunner $t) => $t->same($settings70, $plan70()['filterArguments'][0]),
    'filter arguments preserve hidden root second' => static fn (TestRunner $t) => $t->same('$.plugins', $plan70()['filterArguments'][1]),
    'filter arguments preserve visible key third' => static fn (TestRunner $t) => $t->same('slug', $plan70()['filterArguments'][2]),
    'filter arguments preserve visible type fourth' => static fn (TestRunner $t) => $t->same('text', $plan70()['filterArguments'][3]),
    'filter arguments preserve visible fullkey fifth' => static fn (TestRunner $t) => $t->same('$.plugins[%].slug', $plan70()['filterArguments'][4]),
    'filter arguments preserve visible atom glob sixth' => static fn (TestRunner $t) => $t->same('*-*', $plan70()['filterArguments'][5]),
    'filter arguments preserve visible id range seventh' => static fn (TestRunner $t) => $t->same(4, $plan70()['filterArguments'][6]),
    'constraint usage count includes all pushed filters' => static fn (TestRunner $t) => $t->same(7, count($plan70()['constraintUsage'])),
    'hidden json usage is hidden kind' => static fn (TestRunner $t) => $t->same('hidden', $plan70()['constraintUsage'][0]['kind']),
    'hidden root usage is hidden kind' => static fn (TestRunner $t) => $t->same('hidden', $plan70()['constraintUsage'][1]['kind']),
    'visible key usage is visible kind' => static fn (TestRunner $t) => $t->same('visible', $plan70()['constraintUsage'][2]['kind']),
    'visible atom glob usage omits false' => static fn (TestRunner $t) => $t->same(false, $plan70()['constraintUsage'][5]['omit']),
    'visible id range usage has argv index seven' => static fn (TestRunner $t) => $t->same(7, $plan70()['constraintUsage'][6]['argvIndex']),
    'constraint tape starts at hidden json' => static fn (TestRunner $t) => $t->same('json', $plan70()['filterCurrentNext'][0]['current']['column']),
    'constraint tape json points to root' => static fn (TestRunner $t) => $t->same('root', $plan70()['filterCurrentNext'][0]['next']['column']),
    'constraint tape root points to key' => static fn (TestRunner $t) => $t->same('key', $plan70()['filterCurrentNext'][1]['next']['column']),
    'constraint tape key points to type' => static fn (TestRunner $t) => $t->same('type', $plan70()['filterCurrentNext'][2]['next']['column']),
    'constraint tape type points to fullkey' => static fn (TestRunner $t) => $t->same('fullkey', $plan70()['filterCurrentNext'][3]['next']['column']),
    'constraint tape fullkey points to atom glob' => static fn (TestRunner $t) => $t->same('atom', $plan70()['filterCurrentNext'][4]['next']['column']),
    'constraint tape atom points to id range' => static fn (TestRunner $t) => $t->same('id', $plan70()['filterCurrentNext'][5]['next']['column']),
    'constraint tape terminal id has no next' => static fn (TestRunner $t) => $t->same(null, $plan70()['filterCurrentNext'][6]['next']),
    'filtered row pair count keeps only dashed slugs' => static fn (TestRunner $t) => $t->same(3, count($pairs70())),
    'first current atom is seo pack' => static fn (TestRunner $t) => $t->same('seo-pack', $first70()['current']['atom']),
    'first next atom is cache pro' => static fn (TestRunner $t) => $t->same('cache-pro', $first70()['next']['atom']),
    'second current atom is cache pro' => static fn (TestRunner $t) => $t->same('cache-pro', $second70()['current']['atom']),
    'second next atom is forms lite' => static fn (TestRunner $t) => $t->same('forms-lite', $second70()['next']['atom']),
    'terminal current atom is forms lite' => static fn (TestRunner $t) => $t->same('forms-lite', $pairs70()[2]['current']['atom']),
    'terminal next is null' => static fn (TestRunner $t) => $t->same(null, $pairs70()[2]['next']),
    'first current index is zero' => static fn (TestRunner $t) => $t->same(0, $first70()['currentIndex']),
    'first next index is one' => static fn (TestRunner $t) => $t->same(1, $first70()['nextIndex']),
    'terminal next index is null' => static fn (TestRunner $t) => $t->same(null, $pairs70()[2]['nextIndex']),
    'first current id is from json tree row' => static fn (TestRunner $t) => $t->same($first70()['current']['id'], $first70()['currentId']),
    'first next id is from json tree row' => static fn (TestRunner $t) => $t->same($first70()['next']['id'], $first70()['nextId']),
    'terminal next id is null' => static fn (TestRunner $t) => $t->same(null, $pairs70()[2]['nextId']),
    'first pair stays in same parent object list' => static fn (TestRunner $t) => $t->same(false, $first70()['sameParent']),
    'first pair stays in same path shape' => static fn (TestRunner $t) => $t->same(false, $first70()['samePath']),
    'estimated rows include visible pushdown' => static fn (TestRunner $t) => $t->same(1, $plan70()['estimatedRows']),
    'estimated cost includes visible pushdown' => static fn (TestRunner $t) => $t->same(1, $plan70()['estimatedCost']),
    'limit offset returns one current next pair' => static fn (TestRunner $t) => $t->same(1, count($limited70()['rowCurrentNext'])),
    'limit offset current is second matched slug' => static fn (TestRunner $t) => $t->same('cache-pro', $limited70()['rowCurrentNext'][0]['current']['atom']),
    'limit offset terminal next is null' => static fn (TestRunner $t) => $t->same(null, $limited70()['rowCurrentNext'][0]['next']),
    'limit offset keeps constraint arguments before paging metadata' => static fn (TestRunner $t) => $t->same([5 => '*-*', 6 => 4], array_slice($limited70()['filterArguments'], 5, 2, true)),
    'descending atom order is applied when order by is not consumed' => static fn (TestRunner $t) => $t->same('seo-pack', $unordered70()['rowCurrentNext'][0]['current']['atom']),
    'descending atom next follows sorted order' => static fn (TestRunner $t) => $t->same('forms-lite', $unordered70()['rowCurrentNext'][0]['next']['atom']),
    'descending atom terminal is cache pro' => static fn (TestRunner $t) => $t->same('cache-pro', $unordered70()['rowCurrentNext'][2]['current']['atom']),
    'sql null hidden json has no row pairs' => static fn (TestRunner $t) => $t->same([], SQLiteJsonTablePlan::adjacentConstraintPlan('json_each', [['column' => 'json', 'operator' => '=', 'value' => null]])['rowCurrentNext']),
    'malformed jsonb hidden json has no row pairs' => static fn (TestRunner $t) => $t->same([], SQLiteJsonTablePlan::adjacentConstraintPlan('json_each', [['column' => 'json', 'operator' => '=', 'value' => new PortLibs\LibSqlite\SQLiteBlobValue(hex2bin("1c00"))]])['rowCurrentNext']),
    'unusable json is not runnable' => static fn (TestRunner $t) => $t->same(false, SQLiteJsonTablePlan::adjacentConstraintPlan('json_each', [['column' => 'json', 'operator' => '=', 'value' => $settings70, 'usable' => false]])['runnable']),
    'unusable json has no row pairs' => static fn (TestRunner $t) => $t->same([], SQLiteJsonTablePlan::adjacentConstraintPlan('json_each', [['column' => 'json', 'operator' => '=', 'value' => $settings70, 'usable' => false]])['rowCurrentNext']),
    'invalid root path is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::adjacentConstraintPlan('json_tree', [['column' => 'json', 'operator' => '=', 'value' => $settings70], ['column' => 'root', 'operator' => '=', 'value' => '$.[bad']])) ,
];

foreach ($cases70 as $name => $case) {
    $tests['json table constraint planner current next70 ' . $name] = $case;
}

return $tests;
