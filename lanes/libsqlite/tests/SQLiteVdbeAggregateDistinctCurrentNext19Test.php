<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteVdbeAggregateDistinctCursor;

$rows = [
    ['rowid' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => '24', 'enabled' => 1],
    ['rowid' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24, 'enabled' => 1],
    ['rowid' => 3, 'option_name' => 'BlogName', 'autoload' => 'yes', 'bytes' => 9, 'enabled' => '1'],
    ['rowid' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 9, 'enabled' => 1],
    ['rowid' => 5, 'option_name' => 'transient_feed', 'autoload' => 'no', 'bytes' => 12, 'enabled' => 0],
    ['rowid' => 6, 'option_name' => 'plugin_cache ', 'autoload' => 'yes', 'bytes' => '12', 'enabled' => '0'],
    ['rowid' => 7, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'bytes' => 12.0, 'enabled' => 1],
    ['rowid' => 8, 'option_name' => null, 'autoload' => 'yes', 'bytes' => null, 'enabled' => 1],
];

$tests = [];

$tests['vdbe aggregate distinct cursor current exposes first distinct numeric key'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor($rows, 'bytes', 'bytes', 'enabled', 'C');
    $t->same([null], $cursor->currentKey());
};

$tests['vdbe aggregate distinct cursor current value uses first row in sorted distinct group'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor($rows, 'bytes', 'option_name', 'enabled', 'C');
    $t->same(null, $cursor->currentValue());
};

$tests['vdbe aggregate distinct cursor next advances through distinct aggregate keys'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor($rows, 'bytes', 'bytes', 'enabled', 'C');
    $cursor->next();
    $t->same([9], $cursor->currentKey());
};

$tests['vdbe aggregate distinct cursor remaining yields only post current rows'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor($rows, 'bytes', 'bytes', 'enabled', 'C');
    $cursor->next();
    $t->same([9, 12.0, '24'], array_map(static fn (array $entry): mixed => $entry['value'], $cursor->remaining()));
};

$tests['vdbe aggregate distinct cursor rewind restores first distinct entry'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor($rows, 'bytes', 'bytes', 'enabled', 'C');
    $cursor->remaining();
    $cursor->rewind();
    $t->same([null], $cursor->currentKey());
};

$tests['vdbe aggregate distinct cursor eof after consuming all rows'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor($rows, 'bytes', 'bytes', 'enabled', 'C');
    $cursor->remaining();
    $t->true($cursor->eof());
};

$tests['vdbe aggregate distinct cursor next at eof stays eof'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([], 'bytes', 'bytes');
    $cursor->next();
    $t->true($cursor->eof());
};

$tests['vdbe aggregate distinct cursor current at eof is null'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([], 'bytes', 'bytes');
    $t->same(null, $cursor->current());
};

$tests['vdbe aggregate distinct cursor current key at eof throws'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([], 'bytes', 'bytes');
    $t->throws(OutOfBoundsException::class, static fn () => $cursor->currentKey());
};

$tests['vdbe aggregate distinct cursor current value at eof throws'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([], 'bytes', 'bytes');
    $t->throws(OutOfBoundsException::class, static fn () => $cursor->currentValue());
};

$tests['vdbe aggregate distinct cursor current row exposes source row'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor($rows, 'bytes', 'bytes', 'enabled', 'C');
    $t->same(8, $cursor->currentRow()['rowid']);
};

$tests['vdbe aggregate distinct cursor values preserve sorted distinct order'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor($rows, 'bytes', 'bytes', 'enabled', 'C');
    $t->same([null, 9, 12.0, '24'], $cursor->values());
};

$tests['vdbe aggregate distinct cursor count value ignores null distinct argument'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([
        ['v' => null],
        ['v' => 1],
        ['v' => 1],
        ['v' => 2],
    ], 'v', 'v', null, 'C');
    $t->same(2, $cursor->countValue());
};

$tests['vdbe aggregate distinct cursor sum applies numeric affinity distinctness'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([
        ['v' => '2'],
        ['v' => 2],
        ['v' => '3.5'],
    ], 'v', 'v', null, 'C');
    $t->same(5.5, $cursor->sum());
};

$tests['vdbe aggregate distinct cursor total returns zero for empty input'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([], 'v', 'v');
    $t->same(0.0, $cursor->total());
};

$tests['vdbe aggregate distinct cursor avg uses de-duplicated numeric rows'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([
        ['v' => '2'],
        ['v' => 2],
        ['v' => '8'],
    ], 'v', 'v', null, 'C');
    $t->same(5.0, $cursor->avg());
};

$tests['vdbe aggregate distinct cursor avg returns null for all null rows'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([['v' => null], ['v' => null]], 'v', 'v');
    $t->same(null, $cursor->avg());
};

$tests['vdbe aggregate distinct cursor group concat skips null values'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([['v' => null], ['v' => 'a'], ['v' => 'a'], ['v' => 'b']], 'v', 'v');
    $t->same('a|b', $cursor->groupConcat('|'));
};

$tests['vdbe aggregate distinct cursor group concat null separator returns null'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([['v' => 'a'], ['v' => 'b']], 'v', 'v');
    $t->same(null, $cursor->groupConcat(null));
};

$tests['vdbe aggregate distinct cursor group concat casts blob separator'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([['v' => 'a'], ['v' => 'b']], 'v', 'v');
    $t->same('a::b', $cursor->groupConcat(new SQLiteBlobValue('::')));
};

$tests['vdbe aggregate distinct cursor nocase collation collapses case variants'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([
        ['name' => 'BlogName'],
        ['name' => 'blogname'],
        ['name' => 'siteurl'],
    ], 'name', 'name', null, 'G', ['NOCASE']);
    $t->same(['BlogName', 'siteurl'], $cursor->values());
};

$tests['vdbe aggregate distinct cursor binary collation keeps case variants'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([
        ['name' => 'BlogName'],
        ['name' => 'blogname'],
    ], 'name', 'name', null, 'G', ['BINARY']);
    $t->same(['BlogName', 'blogname'], $cursor->values());
};

$tests['vdbe aggregate distinct cursor rtrim collation collapses padded text'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([
        ['name' => 'cache  '],
        ['name' => 'cache'],
        ['name' => 'cache '],
    ], 'name', 'name', null, 'G', ['RTRIM']);
    $t->same(['cache  '], $cursor->values());
};

$tests['vdbe aggregate distinct cursor text affinity keeps integer text duplicate'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([['v' => 7], ['v' => '7']], 'v', 'v', null, 'G');
    $t->same([7], $cursor->values());
};

$tests['vdbe aggregate distinct cursor none affinity keeps storage classes distinct'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([['v' => 7], ['v' => '7']], 'v', 'v', null, 'A');
    $t->same([7, '7'], $cursor->values());
};

$tests['vdbe aggregate distinct cursor integer affinity collapses signed numeric text'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([['v' => '+7'], ['v' => 7], ['v' => 8]], 'v', 'v', null, 'D');
    $t->same(['+7', 8], $cursor->values());
};

$tests['vdbe aggregate distinct cursor real affinity collapses decimal text'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([['v' => '7.5'], ['v' => 7.5], ['v' => 8.5]], 'v', 'v', null, 'E');
    $t->same(['7.5', 8.5], $cursor->values());
};

$tests['vdbe aggregate distinct cursor blob values deduplicate by blob bytes'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([
        ['v' => new SQLiteBlobValue('AB')],
        ['v' => new SQLiteBlobValue('AB')],
        ['v' => new SQLiteBlobValue('AC')],
    ], 'v', 'v');
    $t->same(['AB', 'AC'], array_map(static fn (SQLiteBlobValue $value): string => $value->bytes, $cursor->values()));
};

$tests['vdbe aggregate distinct cursor blob and text remain distinct'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([
        ['v' => new SQLiteBlobValue('AB')],
        ['v' => 'AB'],
    ], 'v', 'v');
    $t->same(['AB', 'AB'], array_map(static fn (mixed $value): string => $value instanceof SQLiteBlobValue ? $value->bytes : $value, $cursor->values()));
};

$tests['vdbe aggregate distinct cursor composite keys collapse duplicates'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([
        ['autoload' => 'yes', 'name' => 'Plugin', 'v' => 1],
        ['autoload' => 'yes', 'name' => 'plugin', 'v' => 2],
        ['autoload' => 'no', 'name' => 'plugin', 'v' => 3],
    ], ['autoload', 'name'], 'v', null, 'GG', ['BINARY', 'NOCASE']);
    $t->same([3, 1], $cursor->values());
};

$tests['vdbe aggregate distinct cursor composite key current key has both slots'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([
        ['autoload' => 'yes', 'name' => 'siteurl', 'v' => 1],
        ['autoload' => 'no', 'name' => 'cache', 'v' => 2],
    ], ['autoload', 'name'], 'v', null, 'GG');
    $t->same(['no', 'cache'], $cursor->currentKey());
};

$tests['vdbe aggregate distinct cursor filter removes rows before distinct selection'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([
        ['v' => 'siteurl', 'ok' => 0],
        ['v' => 'siteurl', 'ok' => 1],
        ['v' => 'home', 'ok' => 1],
    ], 'v', 'v', 'ok');
    $t->same(['home', 'siteurl'], $cursor->values());
};

$tests['vdbe aggregate distinct cursor numeric string zero filter is false'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([['v' => 'a', 'ok' => '0'], ['v' => 'b', 'ok' => '2']], 'v', 'v', 'ok');
    $t->same(['b'], $cursor->values());
};

$tests['vdbe aggregate distinct cursor nonnumeric text filter is false'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([['v' => 'a', 'ok' => 'yes'], ['v' => 'b', 'ok' => '1']], 'v', 'v', 'ok');
    $t->same(['b'], $cursor->values());
};

$tests['vdbe aggregate distinct cursor negative filter is true'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([['v' => 'a', 'ok' => -1], ['v' => 'b', 'ok' => null]], 'v', 'v', 'ok');
    $t->same(['a'], $cursor->values());
};

$tests['vdbe aggregate distinct cursor summary reports distinct rows and filter state'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor($rows, 'bytes', 'bytes', 'enabled', 'C');
    $t->same(['inputRows' => 8, 'distinctRows' => 4, 'filtered' => true, 'eof' => false], $cursor->summary(count($rows)));
};

$tests['vdbe aggregate distinct cursor summary records eof after scan'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([['v' => 1]], 'v', 'v');
    $cursor->remaining();
    $t->same(['inputRows' => 1, 'distinctRows' => 1, 'filtered' => false, 'eof' => true], $cursor->summary(1));
};

$tests['vdbe aggregate distinct cursor null keys sort before numeric keys'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([['v' => 2], ['v' => null], ['v' => 1]], 'v', 'v', null, 'C');
    $t->same([null, 1, 2], $cursor->values());
};

$tests['vdbe aggregate distinct cursor first duplicate wins after sorted scan'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([
        ['v' => '02', 'label' => 'first'],
        ['v' => 2, 'label' => 'second'],
    ], 'v', 'label', null, 'C');
    $t->same(['first'], $cursor->values());
};

$tests['vdbe aggregate distinct cursor rejects associative rows'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateDistinctCursor(['x' => ['v' => 1]], 'v', 'v'));
};

$tests['vdbe aggregate distinct cursor rejects empty value column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateDistinctCursor([], 'v', ''));
};

$tests['vdbe aggregate distinct cursor rejects empty string key column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateDistinctCursor([], '', 'v'));
};

$tests['vdbe aggregate distinct cursor rejects empty key column list'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateDistinctCursor([], [], 'v'));
};

$tests['vdbe aggregate distinct cursor rejects associative key column list'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateDistinctCursor([], ['x' => 'v'], 'v'));
};

$tests['vdbe aggregate distinct cursor rejects missing value column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateDistinctCursor([['k' => 1]], 'k', 'v'));
};

$tests['vdbe aggregate distinct cursor rejects missing key column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateDistinctCursor([['v' => 1]], 'k', 'v'));
};

$tests['vdbe aggregate distinct cursor rejects missing filter column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateDistinctCursor([['v' => 1]], 'v', 'v', 'ok'));
};

$tests['vdbe aggregate distinct cursor rejects unsupported affinity'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateDistinctCursor([['v' => 1], ['v' => 2]], 'v', 'v', null, 'Z'));
};

$tests['vdbe aggregate distinct cursor rejects invalid collation through comparator'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateDistinctCursor([['v' => 'a'], ['v' => 'b']], 'v', 'v', null, 'G', ['BOGUS']));
};

$tests['vdbe aggregate distinct cursor rejects negative summary input count'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateDistinctCursor([], 'v', 'v');
    $t->throws(InvalidArgumentException::class, static fn () => $cursor->summary(-1));
};

return $tests;
