<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeSorterDistinctGroupCursor;

$rows = [
    ['rowid' => 1, 'autoload' => 'yes', 'kind' => 'core', 'option_name' => 'siteurl', 'bytes' => '24', 'enabled' => 1],
    ['rowid' => 2, 'autoload' => 'yes', 'kind' => 'core', 'option_name' => 'home', 'bytes' => 24, 'enabled' => 1],
    ['rowid' => 3, 'autoload' => 'yes', 'kind' => 'plugin', 'option_name' => 'Plugin_Cache', 'bytes' => '12', 'enabled' => 1],
    ['rowid' => 4, 'autoload' => 'yes', 'kind' => 'plugin', 'option_name' => 'plugin_cache', 'bytes' => 12.0, 'enabled' => 1],
    ['rowid' => 5, 'autoload' => 'yes', 'kind' => 'plugin', 'option_name' => 'plugin_cache_debug', 'bytes' => 12, 'enabled' => 0],
    ['rowid' => 6, 'autoload' => 'no', 'kind' => 'transient', 'option_name' => 'transient_feed', 'bytes' => 30, 'enabled' => 1],
    ['rowid' => 7, 'autoload' => 'no', 'kind' => 'transient', 'option_name' => 'transient_feed_v2', 'bytes' => '30', 'enabled' => 1],
    ['rowid' => 8, 'autoload' => null, 'kind' => 'network', 'option_name' => 'network_settings', 'bytes' => null, 'enabled' => 1],
];

$tests = [];

$tests['vdbe sorter distinct group starts at first sorted group key'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeSorterDistinctGroupCursor($rows, ['autoload'], 'bytes', 'bytes', 'enabled', 'G', ['NOCASE'], [], ['LAST'], 'C');
    $t->same(['no'], $cursor->currentGroupKey());
};

$tests['vdbe sorter distinct group exposes current group rows'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeSorterDistinctGroupCursor($rows, ['autoload'], 'bytes', 'bytes', 'enabled', 'G', ['NOCASE'], [], ['LAST'], 'C');
    $t->same([6, 7], array_column($cursor->currentRows(), 'rowid'));
};

$tests['vdbe sorter distinct group de-duplicates numeric values inside current group'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeSorterDistinctGroupCursor($rows, ['autoload'], 'bytes', 'bytes', 'enabled', 'G', ['NOCASE'], [], ['LAST'], 'C');
    $t->same([30], $cursor->currentDistinctValues());
};

$tests['vdbe sorter distinct group next advances to next group boundary'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeSorterDistinctGroupCursor($rows, ['autoload'], 'bytes', 'bytes', 'enabled', 'G', ['NOCASE'], [], ['LAST'], 'C');
    $cursor->next();
    $t->same(['yes'], $cursor->currentGroupKey());
};

$tests['vdbe sorter distinct group resets distinct cursor per group'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeSorterDistinctGroupCursor($rows, ['autoload'], 'bytes', 'bytes', 'enabled', 'G', ['NOCASE'], [], ['LAST'], 'C');
    $cursor->next();
    $t->same(['12', '24'], $cursor->currentDistinctValues());
};

$tests['vdbe sorter distinct group filter is scoped before distinct selection'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeSorterDistinctGroupCursor($rows, ['kind'], 'bytes', 'rowid', 'enabled', 'G', ['BINARY'], [], [], 'C');
    $cursor->next();
    $cursor->next();
    $t->same([3], $cursor->currentDistinctValues());
};

$tests['vdbe sorter distinct group preserves first duplicate row after sorted scan'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeSorterDistinctGroupCursor($rows, ['kind'], 'bytes', 'option_name', 'enabled', 'G', ['BINARY'], [], [], 'C');
    $cursor->next();
    $cursor->next();
    $t->same(['Plugin_Cache'], $cursor->currentDistinctValues());
};

$tests['vdbe sorter distinct group null group can be sorted last'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeSorterDistinctGroupCursor($rows, ['autoload'], 'bytes', 'bytes', 'enabled', 'G', ['NOCASE'], [], ['LAST'], 'C');
    $cursor->next();
    $cursor->next();
    $t->same([null], $cursor->currentGroupKey());
};

$tests['vdbe sorter distinct group null group distinct count ignores null aggregate value'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeSorterDistinctGroupCursor($rows, ['autoload'], 'bytes', 'bytes', 'enabled', 'G', ['NOCASE'], [], ['LAST'], 'C');
    $cursor->next();
    $cursor->next();
    $t->same(0, $cursor->currentDistinct()->countValue());
};

$tests['vdbe sorter distinct group reaches eof after final next'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeSorterDistinctGroupCursor($rows, ['autoload'], 'bytes', 'bytes', 'enabled', 'G', ['NOCASE'], [], ['LAST'], 'C');
    $cursor->next();
    $cursor->next();
    $cursor->next();
    $t->true($cursor->eof());
};

$tests['vdbe sorter distinct group next at eof remains eof'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeSorterDistinctGroupCursor([], ['autoload'], 'bytes', 'bytes');
    $cursor->next();
    $t->true($cursor->eof());
};

$tests['vdbe sorter distinct group current key at eof throws'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeSorterDistinctGroupCursor([], ['autoload'], 'bytes', 'bytes');
    $t->throws(OutOfBoundsException::class, static fn () => $cursor->currentGroupKey());
};

$tests['vdbe sorter distinct group current rows at eof throws'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeSorterDistinctGroupCursor([], ['autoload'], 'bytes', 'bytes');
    $t->throws(OutOfBoundsException::class, static fn () => $cursor->currentRows());
};

$tests['vdbe sorter distinct group current distinct at eof throws'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeSorterDistinctGroupCursor([], ['autoload'], 'bytes', 'bytes');
    $t->throws(OutOfBoundsException::class, static fn () => $cursor->currentDistinct());
};

$tests['vdbe sorter distinct group summary reports current group metadata'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeSorterDistinctGroupCursor($rows, ['kind'], 'bytes', 'bytes', 'enabled', 'G', ['BINARY'], [], [], 'C');
    $t->same(['groupKey' => ['core'], 'rowCount' => 2, 'distinctRows' => 1, 'filtered' => true, 'eof' => false], $cursor->currentSummary());
};

$tests['vdbe sorter distinct group summary distinct eof follows consumed aggregate cursor'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeSorterDistinctGroupCursor($rows, ['kind'], 'bytes', 'bytes', 'enabled', 'G', ['BINARY'], [], [], 'C');
    $cursor->currentDistinct()->remaining();
    $t->true($cursor->currentSummary()['eof']);
};

$tests['vdbe sorter distinct group drains summaries in sorted group order'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeSorterDistinctGroupCursor($rows, ['kind'], 'bytes', 'bytes', 'enabled', 'G', ['BINARY'], [], [], 'C');
    $t->same([['core'], ['network'], ['plugin'], ['transient']], array_column($cursor->drainSummaries(), 'key'));
};

$tests['vdbe sorter distinct group drain includes row counts'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeSorterDistinctGroupCursor($rows, ['kind'], 'bytes', 'bytes', 'enabled', 'G', ['BINARY'], [], [], 'C');
    $t->same([2, 1, 3, 2], array_column($cursor->drainSummaries(), 'rowCount'));
};

$tests['vdbe sorter distinct group drain includes distinct values per group'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeSorterDistinctGroupCursor($rows, ['kind'], 'bytes', 'bytes', 'enabled', 'G', ['BINARY'], [], [], 'C');
    $t->same([['24'], [null], ['12'], [30]], array_column($cursor->drainSummaries(), 'distinctValues'));
};

$tests['vdbe sorter distinct group composite group keys use collation equality'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeSorterDistinctGroupCursor([
        ['autoload' => 'YES', 'kind' => 'plugin', 'v' => 1],
        ['autoload' => 'yes', 'kind' => 'plugin', 'v' => 1],
        ['autoload' => 'yes', 'kind' => 'core', 'v' => 2],
    ], ['autoload', 'kind'], 'v', 'v', null, 'GG', ['NOCASE', 'BINARY'], [], [], 'C');
    $t->same([['yes', 'core'], ['YES', 'plugin']], array_column($cursor->drainSummaries(), 'key'));
};

$tests['vdbe sorter distinct group composite values reset across equal distinct keys in different groups'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeSorterDistinctGroupCursor([
        ['autoload' => 'yes', 'kind' => 'plugin', 'v' => 1],
        ['autoload' => 'no', 'kind' => 'plugin', 'v' => 1],
        ['autoload' => 'no', 'kind' => 'plugin', 'v' => 1],
    ], ['autoload'], 'v', 'v', null, 'G', ['BINARY'], [], [], 'C');
    $t->same([[1], [1]], array_column($cursor->drainSummaries(), 'distinctValues'));
};

$tests['vdbe sorter distinct group descending order reverses group scan'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeSorterDistinctGroupCursor($rows, ['kind'], 'bytes', 'bytes', 'enabled', 'G', ['BINARY'], [true], [], 'C');
    $t->same([['transient'], ['plugin'], ['network'], ['core']], array_column($cursor->drainSummaries(), 'key'));
};

$tests['vdbe sorter distinct group nulls first places null group before text groups'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeSorterDistinctGroupCursor($rows, ['autoload'], 'bytes', 'bytes', 'enabled', 'G', ['NOCASE'], [], ['FIRST'], 'C');
    $t->same([null], $cursor->currentGroupKey());
};

$tests['vdbe sorter distinct group none affinity keeps text numeric distinct'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeSorterDistinctGroupCursor([
        ['g' => 'a', 'v' => '7'],
        ['g' => 'a', 'v' => 7],
    ], ['g'], 'v', 'v', null, 'G', [], [], [], 'A');
    $t->same([7, '7'], $cursor->currentDistinctValues());
};

$tests['vdbe sorter distinct group text affinity collapses numeric text'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeSorterDistinctGroupCursor([
        ['g' => 'a', 'v' => '7'],
        ['g' => 'a', 'v' => 7],
    ], ['g'], 'v', 'v', null, 'G', [], [], [], 'G');
    $t->same(['7'], $cursor->currentDistinctValues());
};

$tests['vdbe sorter distinct group nocase distinct collation collapses plugin names'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeSorterDistinctGroupCursor([
        ['g' => 'a', 'name' => 'Plugin'],
        ['g' => 'a', 'name' => 'plugin'],
    ], ['g'], 'name', 'name', null, 'G', [], [], [], 'G', ['NOCASE']);
    $t->same(['Plugin'], $cursor->currentDistinctValues());
};

$tests['vdbe sorter distinct group rejects empty group columns'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeSorterDistinctGroupCursor([], [], 'v', 'v'));
};

$tests['vdbe sorter distinct group rejects associative group columns'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeSorterDistinctGroupCursor([], ['x' => 'g'], 'v', 'v'));
};

$tests['vdbe sorter distinct group rejects empty group column name'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeSorterDistinctGroupCursor([], [''], 'v', 'v'));
};

$tests['vdbe sorter distinct group rejects empty value column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeSorterDistinctGroupCursor([], ['g'], 'v', ''));
};

$tests['vdbe sorter distinct group rejects missing group column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeSorterDistinctGroupCursor([['v' => 1]], ['g'], 'v', 'v'));
};

$tests['vdbe sorter distinct group rejects missing distinct column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeSorterDistinctGroupCursor([['g' => 'a', 'v' => 1]], ['g'], 'missing', 'v'));
};

$tests['vdbe sorter distinct group rejects missing value column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeSorterDistinctGroupCursor([['g' => 'a', 'k' => 1]], ['g'], 'k', 'v'));
};

$tests['vdbe sorter distinct group rejects missing filter column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeSorterDistinctGroupCursor([['g' => 'a', 'v' => 1]], ['g'], 'v', 'v', 'ok'));
};

$tests['vdbe sorter distinct group rejects invalid group null placement'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeSorterDistinctGroupCursor([['g' => null, 'v' => 1], ['g' => 'a', 'v' => 2]], ['g'], 'v', 'v', null, 'G', [], [], ['MIDDLE']));
};

$tests['vdbe sorter distinct group rejects invalid distinct affinity'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeSorterDistinctGroupCursor([['g' => 'a', 'v' => 1], ['g' => 'a', 'v' => 2]], ['g'], 'v', 'v', null, 'G', [], [], [], 'Z'));
};

return $tests;
