<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteVdbeAggregateOrderCursor;

$rows = [
    ['rowid' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'priority' => 20, 'bytes' => '24', 'ok' => 1],
    ['rowid' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'priority' => 10, 'bytes' => 18, 'ok' => '1'],
    ['rowid' => 3, 'option_name' => 'BlogName', 'autoload' => 'yes', 'priority' => 30, 'bytes' => 9, 'ok' => 0],
    ['rowid' => 4, 'option_name' => 'blogname ', 'autoload' => 'yes', 'priority' => 30, 'bytes' => 11, 'ok' => 1],
    ['rowid' => 5, 'option_name' => 'plugin_cache', 'autoload' => 'no', 'priority' => null, 'bytes' => 12, 'ok' => null],
    ['rowid' => 6, 'option_name' => 'Plugin_Cache', 'autoload' => 'no', 'priority' => 15, 'bytes' => new SQLiteBlobValue('blob-bytes'), 'ok' => -1],
];

$tests = [];

$tests['vdbe aggregate order cursor starts at first ordered value'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor($rows, 'option_name', ['priority'], 'ok');
    $t->same('home', $cursor->currentValue());
};

$tests['vdbe aggregate order cursor next advances current value'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor($rows, 'option_name', ['priority'], 'ok');
    $cursor->next();
    $t->same('Plugin_Cache', $cursor->currentValue());
};

$tests['vdbe aggregate order cursor current row exposes source row'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor($rows, 'option_name', ['priority'], 'ok');
    $t->same(2, $cursor->currentRow()['rowid']);
};

$tests['vdbe aggregate order cursor remaining rows reflect current suffix'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor($rows, 'option_name', ['priority'], 'ok');
    $cursor->next();
    $t->same([6, 1, 4], array_column($cursor->remainingRows(), 'rowid'));
};

$tests['vdbe aggregate order cursor values follow aggregate order by'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor($rows, 'option_name', ['priority'], 'ok');
    $t->same(['home', 'Plugin_Cache', 'siteurl', 'blogname '], $cursor->values());
};

$tests['vdbe aggregate order cursor group concat uses sorted input order'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor($rows, 'option_name', ['priority'], 'ok');
    $t->same('home|Plugin_Cache|siteurl|blogname ', $cursor->groupConcat('|'));
};

$tests['vdbe aggregate order cursor count value skips null values'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => null, 'k' => 1], ['v' => 2, 'k' => 2]], 'v', ['k']);
    $t->same(1, $cursor->countValue());
};

$tests['vdbe aggregate order cursor sum ignores null values'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => null, 'k' => 1], ['v' => '2.5', 'k' => 2], ['v' => 3, 'k' => 3]], 'v', ['k']);
    $t->same(5.5, $cursor->sum());
};

$tests['vdbe aggregate order cursor total is zero for no ordered values'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => 7, 'k' => 1, 'ok' => 0]], 'v', ['k'], 'ok');
    $t->same(0.0, $cursor->total());
};

$tests['vdbe aggregate order cursor avg follows numeric aggregate rules'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => '2', 'k' => 2], ['v' => 8, 'k' => 1]], 'v', ['k']);
    $t->same(5.0, $cursor->avg());
};

$tests['vdbe aggregate order cursor null separator returns null concat'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor($rows, 'option_name', ['priority'], 'ok');
    $t->same(null, $cursor->groupConcat(null));
};

$tests['vdbe aggregate order cursor blob separator is cast to bytes'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor($rows, 'option_name', ['priority'], 'ok');
    $t->same('home::Plugin_Cache::siteurl::blogname ', $cursor->groupConcat(new SQLiteBlobValue('::')));
};

$tests['vdbe aggregate order cursor blob values concatenate as bytes'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => new SQLiteBlobValue('B'), 'k' => 2], ['v' => new SQLiteBlobValue('A'), 'k' => 1]], 'v', ['k']);
    $t->same('A|B', $cursor->groupConcat('|'));
};

$tests['vdbe aggregate order cursor stable tie keeps input order'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => 'a', 'k' => 1], ['v' => 'b', 'k' => 1], ['v' => 'c', 'k' => 1]], 'v', ['k']);
    $t->same(['a', 'b', 'c'], $cursor->values());
};

$tests['vdbe aggregate order cursor desc reverses numeric sort'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => 'low', 'k' => 1], ['v' => 'high', 'k' => 9]], 'v', ['k'], null, [], [], [true]);
    $t->same(['high', 'low'], $cursor->values());
};

$tests['vdbe aggregate order cursor nulls last places null sort key last'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => 'null', 'k' => null], ['v' => 'one', 'k' => 1]], 'v', ['k'], null, [], [], [], ['LAST']);
    $t->same(['one', 'null'], $cursor->values());
};

$tests['vdbe aggregate order cursor nulls first places null sort key first'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => 'one', 'k' => 1], ['v' => 'null', 'k' => null]], 'v', ['k'], null, [], [], [], ['FIRST']);
    $t->same(['null', 'one'], $cursor->values());
};

$tests['vdbe aggregate order cursor nocase collation orders ascii case-insensitively'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => 1, 'name' => 'plugin_b'], ['v' => 2, 'name' => 'Plugin_A']], 'v', ['name'], null, 'G', ['NOCASE']);
    $t->same([2, 1], $cursor->values());
};

$tests['vdbe aggregate order cursor rtrim collation preserves stable padded ties'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => 'padded', 'name' => 'cache  '], ['v' => 'plain', 'name' => 'cache']], 'v', ['name'], null, 'G', ['RTRIM']);
    $t->same(['padded', 'plain'], $cursor->values());
};

$tests['vdbe aggregate order cursor text affinity sorts numeric text lexically'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => 'ten', 'k' => 10], ['v' => 'two', 'k' => 2]], 'v', ['k'], null, 'G');
    $t->same(['ten', 'two'], $cursor->values());
};

$tests['vdbe aggregate order cursor numeric affinity sorts numeric text numerically'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => 'ten', 'k' => '10'], ['v' => 'two', 'k' => 2]], 'v', ['k'], null, 'C');
    $t->same(['two', 'ten'], $cursor->values());
};

$tests['vdbe aggregate order cursor composite order uses later terms'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => 'b', 'a' => 1, 'b' => 2], ['v' => 'a', 'a' => 1, 'b' => 1]], 'v', ['a', 'b']);
    $t->same(['a', 'b'], $cursor->values());
};

$tests['vdbe aggregate order cursor composite order supports mixed directions'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => 'old', 'site' => 1, 'ts' => 1], ['v' => 'new', 'site' => 1, 'ts' => 3]], 'v', ['site', 'ts'], null, [], [], [false, true]);
    $t->same(['new', 'old'], $cursor->values());
};

$tests['vdbe aggregate order cursor filter skips integer zero'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => 'skip', 'k' => 1, 'ok' => 0], ['v' => 'keep', 'k' => 2, 'ok' => 1]], 'v', ['k'], 'ok');
    $t->same(['keep'], $cursor->values());
};

$tests['vdbe aggregate order cursor filter skips numeric string zero'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => 'skip', 'k' => 1, 'ok' => '0'], ['v' => 'keep', 'k' => 2, 'ok' => '2']], 'v', ['k'], 'ok');
    $t->same(['keep'], $cursor->values());
};

$tests['vdbe aggregate order cursor filter skips nonnumeric string'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => 'skip', 'k' => 1, 'ok' => 'yes'], ['v' => 'keep', 'k' => 2, 'ok' => '1']], 'v', ['k'], 'ok');
    $t->same(['keep'], $cursor->values());
};

$tests['vdbe aggregate order cursor filter keeps negative numeric'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => 'keep', 'k' => 1, 'ok' => -1], ['v' => 'skip', 'k' => 2, 'ok' => null]], 'v', ['k'], 'ok');
    $t->same(['keep'], $cursor->values());
};

$tests['vdbe aggregate order cursor empty filtered input is eof'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => 'skip', 'k' => 1, 'ok' => 0]], 'v', ['k'], 'ok');
    $t->true($cursor->eof());
};

$tests['vdbe aggregate order cursor current at eof throws'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([], 'v', ['k']);
    $t->throws(OutOfBoundsException::class, static fn () => $cursor->currentValue());
};

$tests['vdbe aggregate order cursor current row at eof throws'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([], 'v', ['k']);
    $t->throws(OutOfBoundsException::class, static fn () => $cursor->currentRow());
};

$tests['vdbe aggregate order cursor next at eof remains eof'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([], 'v', ['k']);
    $cursor->next();
    $t->true($cursor->eof());
};

$tests['vdbe aggregate order cursor summary reports filter counts'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor($rows, 'option_name', ['priority'], 'ok');
    $t->same(['inputRows' => 6, 'filteredRows' => 4, 'orderedRows' => 4, 'filter' => true, 'eof' => false], $cursor->summary());
};

$tests['vdbe aggregate order cursor summary eof follows cursor consumption'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => 1, 'k' => 1]], 'v', ['k']);
    $cursor->next();
    $t->true($cursor->summary()['eof']);
};

$tests['vdbe aggregate order cursor preserves null values for count aggregate'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => null, 'k' => 2], ['v' => 'x', 'k' => 1]], 'v', ['k']);
    $t->same(['x', null], $cursor->values());
};

$tests['vdbe aggregate order cursor group concat skips null ordered values'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => null, 'k' => 1], ['v' => 'x', 'k' => 2]], 'v', ['k']);
    $t->same('x', $cursor->groupConcat('|'));
};

$tests['vdbe aggregate order cursor bool value text is sqlite integer text'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => true, 'k' => 2], ['v' => false, 'k' => 1]], 'v', ['k']);
    $t->same('0|1', $cursor->groupConcat('|'));
};

$tests['vdbe aggregate order cursor rejects associative rows'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateOrderCursor(['x' => ['v' => 1, 'k' => 1]], 'v', ['k']));
};

$tests['vdbe aggregate order cursor rejects empty value column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateOrderCursor([], '', ['k']));
};

$tests['vdbe aggregate order cursor rejects empty order columns'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateOrderCursor([], 'v', []));
};

$tests['vdbe aggregate order cursor rejects associative order columns'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateOrderCursor([], 'v', ['x' => 'k']));
};

$tests['vdbe aggregate order cursor rejects empty order column name'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateOrderCursor([], 'v', ['']));
};

$tests['vdbe aggregate order cursor rejects missing value column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateOrderCursor([['k' => 1]], 'v', ['k']));
};

$tests['vdbe aggregate order cursor rejects missing order column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateOrderCursor([['v' => 1]], 'v', ['k']));
};

$tests['vdbe aggregate order cursor rejects missing filter column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateOrderCursor([['v' => 1, 'k' => 1]], 'v', ['k'], 'ok'));
};

$tests['vdbe aggregate order cursor rejects invalid affinity code'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateOrderCursor([['v' => 1, 'k' => 1]], 'v', ['k'], null, 'Z'));
};

$tests['vdbe aggregate order cursor rejects invalid collation'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateOrderCursor([['v' => 1, 'k' => 'a']], 'v', ['k'], null, 'G', ['UNICODE']));
};

$tests['vdbe aggregate order cursor rejects invalid null placement'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateOrderCursor([['v' => 1, 'k' => null], ['v' => 2, 'k' => 1]], 'v', ['k'], null, [], [], [], ['MIDDLE']));
};

$tests['vdbe aggregate order cursor rejects unsupported order value'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateOrderCursor([['v' => 1, 'k' => ['nested']]], 'v', ['k']));
};

$tests['vdbe aggregate order cursor rejects unsupported aggregate value for concat'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => ['nested'], 'k' => 1]], 'v', ['k']);
    $t->throws(InvalidArgumentException::class, static fn () => $cursor->groupConcat('|'));
};

$tests['vdbe aggregate order cursor accepts empty row set with valid columns'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([], 'v', ['k']);
    $t->same([], $cursor->values());
};

$tests['vdbe aggregate order cursor empty row set summary is zero'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([], 'v', ['k']);
    $t->same(['inputRows' => 0, 'filteredRows' => 0, 'orderedRows' => 0, 'filter' => false, 'eof' => true], $cursor->summary());
};

$tests['vdbe aggregate order cursor remaining rows empty after full scan'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => 1, 'k' => 1]], 'v', ['k']);
    $cursor->next();
    $t->same([], $cursor->remainingRows());
};

$tests['vdbe aggregate order cursor no filter summary marks filter false'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => 1, 'k' => 1]], 'v', ['k']);
    $t->same(false, $cursor->summary()['filter']);
};

$tests['vdbe aggregate order cursor order by blob keys uses blob bytes'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => 'b', 'k' => new SQLiteBlobValue('B')], ['v' => 'a', 'k' => new SQLiteBlobValue('A')]], 'v', ['k']);
    $t->same(['a', 'b'], $cursor->values());
};

$tests['vdbe aggregate order cursor text and blob order by follows sqlite storage class'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => 'blob', 'k' => new SQLiteBlobValue('A')], ['v' => 'text', 'k' => 'z']], 'v', ['k']);
    $t->same(['text', 'blob'], $cursor->values());
};

$tests['vdbe aggregate order cursor descending with nulls first keeps explicit null first before reverse'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => 'null', 'k' => null], ['v' => 'nine', 'k' => 9]], 'v', ['k'], null, [], [], [true], ['FIRST']);
    $t->same(['null', 'nine'], $cursor->values());
};

$tests['vdbe aggregate order cursor descending with nulls last keeps explicit null last before reverse'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor([['v' => 'null', 'k' => null], ['v' => 'nine', 'k' => 9]], 'v', ['k'], null, [], [], [true], ['LAST']);
    $t->same(['nine', 'null'], $cursor->values());
};

return $tests;
