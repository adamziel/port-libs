<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteTextAggregate;

$rows = [
    ['siteurl', 50, 1],
    ['siteurl', 10, 1],
    ['home', 30, 1],
    ['blogname', 20, 0],
    ['cron', 40, '1'],
    ['theme', 5, null],
    [null, 1, 1],
];

$blobA = new SQLiteBlobValue('AB');
$blobB = new SQLiteBlobValue('AC');

return [
    'aggregate filter order distinct keeps first duplicate order key' => static function (TestRunner $t) use ($rows): void {
        $t->same('home|cron|siteurl', SQLiteTextAggregate::groupConcatDistinctOrderByFilter($rows, '|'));
    },
    'aggregate filter order distinct changes when first duplicate order changes' => static function (TestRunner $t): void {
        $t->same('a,b', SQLiteTextAggregate::groupConcatDistinctOrderByFilter([['a', 2, 1], ['a', 1, 1], ['b', 3, 1]]));
    },
    'aggregate filter order distinct sorts first selected duplicate after later lower duplicate is ignored' => static function (TestRunner $t): void {
        $t->same('b,a', SQLiteTextAggregate::groupConcatDistinctOrderByFilter([['a', 5, 1], ['a', 1, 1], ['b', 3, 1]]));
    },
    'aggregate filter order distinct ignores duplicate before order sort for text copies' => static function (TestRunner $t): void {
        $t->same('cache,autoload', SQLiteTextAggregate::groupConcatDistinctOrderByFilter([['autoload', 9, 1], ['autoload', 1, 1], ['cache', 3, 1]]));
    },
    'aggregate filter order distinct accepts false then true duplicate with true order key' => static function (TestRunner $t): void {
        $t->same('home,siteurl', SQLiteTextAggregate::groupConcatDistinctOrderByFilter([['siteurl', 1, 0], ['siteurl', 9, 1], ['home', 5, 1]]));
    },
    'aggregate filter order distinct ignores true then false duplicate after distinct selection' => static function (TestRunner $t): void {
        $t->same('siteurl,home', SQLiteTextAggregate::groupConcatDistinctOrderByFilter([['siteurl', 1, 1], ['siteurl', 9, 0], ['home', 5, 1]]));
    },
    'aggregate filter order distinct drops null aggregate values after filter' => static function (TestRunner $t): void {
        $t->same('home', SQLiteTextAggregate::groupConcatDistinctOrderByFilter([[null, 1, 1], ['home', 2, 1], [null, 0, 1]]));
    },
    'aggregate filter order distinct returns null when every selected value is null' => static function (TestRunner $t): void {
        $t->same(null, SQLiteTextAggregate::groupConcatDistinctOrderByFilter([[null, 1, 1], [null, 2, '1']]));
    },
    'aggregate filter order distinct returns null when every row is filtered out' => static function (TestRunner $t): void {
        $t->same(null, SQLiteTextAggregate::groupConcatDistinctOrderByFilter([['siteurl', 1, 0], ['home', 2, null], ['blogname', 3, '0']]));
    },
    'aggregate filter order distinct returns null when separator is null' => static function (TestRunner $t) use ($rows): void {
        $t->same(null, SQLiteTextAggregate::groupConcatDistinctOrderByFilter($rows, null));
    },
    'aggregate filter order distinct casts integer separator' => static function (TestRunner $t): void {
        $t->same('home7siteurl', SQLiteTextAggregate::groupConcatDistinctOrderByFilter([['siteurl', 2, 1], ['home', 1, 1]], 7));
    },
    'aggregate filter order distinct casts blob separator bytes' => static function (TestRunner $t): void {
        $t->same('homeABsiteurl', SQLiteTextAggregate::groupConcatDistinctOrderByFilter([['siteurl', 2, 1], ['home', 1, 1]], new SQLiteBlobValue('AB')));
    },
    'aggregate filter order distinct keeps integer and text distinct' => static function (TestRunner $t): void {
        $t->same('7,7', SQLiteTextAggregate::groupConcatDistinctOrderByFilter([[7, 1, 1], ['7', 2, 1]]));
    },
    'aggregate filter order distinct keeps integer and boolean duplicates aligned with integer key' => static function (TestRunner $t): void {
        $t->same('1', SQLiteTextAggregate::groupConcatDistinctOrderByFilter([[true, 3, 1], [1, 1, 1]]));
    },
    'aggregate filter order distinct keeps real and integer storage classes distinct' => static function (TestRunner $t): void {
        $t->same('1,1', SQLiteTextAggregate::groupConcatDistinctOrderByFilter([[1.0, 1, 1], [1, 2, 1]]));
    },
    'aggregate filter order distinct keeps blob and text storage classes distinct' => static function (TestRunner $t) use ($blobA): void {
        $t->same('AB,AB', SQLiteTextAggregate::groupConcatDistinctOrderByFilter([[$blobA, 1, 1], ['AB', 2, 1]]));
    },
    'aggregate filter order distinct deduplicates blob bytes' => static function (TestRunner $t) use ($blobA): void {
        $t->same('AB,home', SQLiteTextAggregate::groupConcatDistinctOrderByFilter([[$blobA, 1, 1], [new SQLiteBlobValue('AB'), 0, 1], ['home', 2, 1]]));
    },
    'aggregate filter order distinct orders blob keys after text keys' => static function (TestRunner $t) use ($blobA, $blobB): void {
        $t->same('home,AB,AC', SQLiteTextAggregate::groupConcatDistinctOrderByFilter([[$blobB, $blobB, 1], ['home', 'home', 1], [$blobA, $blobA, 1]]));
    },
    'aggregate filter order distinct treats null order key before numeric order key' => static function (TestRunner $t): void {
        $t->same('siteurl,home', SQLiteTextAggregate::groupConcatDistinctOrderByFilter([['home', 2, 1], ['siteurl', null, 1]]));
    },
    'aggregate filter order distinct stable sorts equal order keys by first surviving sequence' => static function (TestRunner $t): void {
        $t->same('first,second,third', SQLiteTextAggregate::groupConcatDistinctOrderByFilter([['first', 1, 1], ['second', 1, 1], ['third', 1, 1]]));
    },
    'aggregate filter order distinct stable sort skips filtered rows for sequence ties' => static function (TestRunner $t): void {
        $t->same('second,third', SQLiteTextAggregate::groupConcatDistinctOrderByFilter([['first', 1, 0], ['second', 1, 1], ['third', 1, 1]]));
    },
    'aggregate filter order distinct string numeric filter zero is false' => static function (TestRunner $t): void {
        $t->same('home', SQLiteTextAggregate::groupConcatDistinctOrderByFilter([['siteurl', 1, '0'], ['home', 2, '2']]));
    },
    'aggregate filter order distinct nonnumeric text filter is false' => static function (TestRunner $t): void {
        $t->same('home', SQLiteTextAggregate::groupConcatDistinctOrderByFilter([['siteurl', 1, 'yes'], ['home', 2, '1']]));
    },
    'aggregate filter order distinct negative numeric filter is true' => static function (TestRunner $t): void {
        $t->same('siteurl,home', SQLiteTextAggregate::groupConcatDistinctOrderByFilter([['siteurl', 1, -1], ['home', 2, 1]]));
    },
    'aggregate filter order distinct float zero filter is false' => static function (TestRunner $t): void {
        $t->same('home', SQLiteTextAggregate::groupConcatDistinctOrderByFilter([['siteurl', 1, 0.0], ['home', 2, 0.5]]));
    },
    'aggregate distinct order by existing helper now keeps first duplicate order key' => static function (TestRunner $t): void {
        $t->same('b,a', SQLiteTextAggregate::groupConcatDistinctOrderBy([['a', 5], ['a', 1], ['b', 3]]));
    },
    'aggregate distinct order by existing helper skips null values before sorting' => static function (TestRunner $t): void {
        $t->same('b,a', SQLiteTextAggregate::groupConcatDistinctOrderBy([[null, 0], ['a', 5], ['a', 1], ['b', 3]]));
    },
    'aggregate filter order distinct rejects missing filter column' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTextAggregate::groupConcatDistinctOrderByFilter([['siteurl', 1]]));
    },
    'aggregate distinct order by rejects missing order column' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTextAggregate::groupConcatDistinctOrderBy([['siteurl']]));
    },
    'aggregate filter order distinct rejects invalid aggregate value type' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTextAggregate::groupConcatDistinctOrderByFilter([[(object) ['bad' => true], 1, 1]]));
    },
    'aggregate filter order distinct rejects invalid order value type' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTextAggregate::groupConcatDistinctOrderByFilter([['siteurl', ['bad'], 1], ['home', 1, 1]]));
    },
];
