<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteVdbeIndexCursor;

$tests = [];

$entries = [
    ['key' => ['autoload', '10', 'Plugin_Z'], 'rowid' => 10, 'payload' => ['option_name' => 'Plugin_Z']],
    ['key' => ['autoload', 2, 'plugin_a'], 'rowid' => 2, 'payload' => ['option_name' => 'plugin_a']],
    ['key' => ['autoload', '02', 'Plugin_A'], 'rowid' => 1, 'payload' => ['option_name' => 'Plugin_A']],
    ['key' => ['autoload', new SQLiteBlobValue('4'), 'plugin_blob'], 'rowid' => 4, 'payload' => ['option_name' => 'plugin_blob']],
    ['key' => ['cache', null, 'cache_a'], 'rowid' => 8, 'payload' => ['option_name' => 'cache_a']],
    ['key' => ['cache', '1', 'cache_b'], 'rowid' => 7, 'payload' => ['option_name' => 'cache_b']],
    ['key' => ['network', '9', 'Site_Option'], 'rowid' => 12, 'payload' => ['option_name' => 'Site_Option']],
];

$tests['vdbe index cursor sorts current entries with slot affinity'] = static function (TestRunner $t) use ($entries): void {
    $cursor = new SQLiteVdbeIndexCursor($entries, 'GCG', ['BINARY', 'BINARY', 'NOCASE']);
    $t->same(['autoload', 'cache', 'network'], array_values(array_unique(array_map(static fn (array $entry): string => $entry['key'][0], $cursor->remaining()))));
};

$tests['vdbe index cursor current exposes first sorted key'] = static function (TestRunner $t) use ($entries): void {
    $cursor = new SQLiteVdbeIndexCursor($entries, 'GCG', ['BINARY', 'BINARY', 'NOCASE']);
    $t->same(['autoload', '02', 'Plugin_A'], $cursor->currentKey());
};

$tests['vdbe index cursor current rowid uses rowid tiebreak after affinity equality'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeIndexCursor([
        ['key' => ['autoload', '2'], 'rowid' => 5],
        ['key' => ['autoload', 2], 'rowid' => 3],
    ], 'GC');
    $t->same(3, $cursor->currentRowid());
};

$tests['vdbe index cursor next advances over equal affinity keys'] = static function (TestRunner $t) use ($entries): void {
    $cursor = new SQLiteVdbeIndexCursor($entries, 'GCG', ['BINARY', 'BINARY', 'NOCASE']);
    $cursor->next();
    $t->same(2, $cursor->currentRowid());
};

$tests['vdbe index cursor reads current key column'] = static function (TestRunner $t) use ($entries): void {
    $cursor = new SQLiteVdbeIndexCursor($entries, 'GCG', ['BINARY', 'BINARY', 'NOCASE']);
    $t->same('Plugin_A', $cursor->currentColumn(2));
};

$tests['vdbe index cursor seek equal finds numeric text using affinity'] = static function (TestRunner $t) use ($entries): void {
    $cursor = new SQLiteVdbeIndexCursor($entries, 'GCG', ['BINARY', 'BINARY', 'NOCASE']);
    $matched = $cursor->yieldEqual(['autoload', 2]);
    $t->same([1, 2], array_column($matched, 'rowid'));
};

$tests['vdbe index cursor seek greater or equal positions after NULL cache key'] = static function (TestRunner $t) use ($entries): void {
    $cursor = new SQLiteVdbeIndexCursor($entries, 'GCG', ['BINARY', 'BINARY', 'NOCASE']);
    $t->true($cursor->seekGreaterOrEqual(['cache', 0]));
    $t->same(7, $cursor->currentRowid());
};

$tests['vdbe index cursor NULL key compares before numeric cache key'] = static function (TestRunner $t) use ($entries): void {
    $cursor = new SQLiteVdbeIndexCursor($entries, 'GCG', ['BINARY', 'BINARY', 'NOCASE']);
    $t->true($cursor->seekGreaterOrEqual(['cache']));
    $t->same(8, $cursor->currentRowid());
};

$tests['vdbe index cursor blob priority stays after numeric priorities'] = static function (TestRunner $t) use ($entries): void {
    $cursor = new SQLiteVdbeIndexCursor($entries, 'GCG', ['BINARY', 'BINARY', 'NOCASE']);
    $matched = $cursor->yieldEqual(['autoload']);
    $t->same([1, 2, 4, 10], array_column($matched, 'rowid'));
};

$tests['vdbe index cursor nocase suffix seek matches case variants'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeIndexCursor([
        ['key' => ['autoload', 'Plugin_A'], 'rowid' => 1],
        ['key' => ['autoload', 'plugin_a'], 'rowid' => 2],
        ['key' => ['autoload', 'plugin_b'], 'rowid' => 3],
    ], 'GG', ['BINARY', 'NOCASE']);
    $matched = $cursor->yieldEqual(['autoload', 'plugin_a']);
    $t->same([1, 2], array_column($matched, 'rowid'));
};

$tests['vdbe index cursor rtrim collation groups padded keys'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeIndexCursor([
        ['key' => ['transient '], 'rowid' => 4],
        ['key' => ['transient'], 'rowid' => 2],
        ['key' => ['transient  '], 'rowid' => 6],
    ], 'G', ['RTRIM']);
    $matched = $cursor->yieldEqual(['transient']);
    $t->same([2, 4, 6], array_column($matched, 'rowid'));
};

$tests['vdbe index cursor descending key reverses scan order'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeIndexCursor([
        ['key' => ['autoload', '1'], 'rowid' => 1],
        ['key' => ['autoload', '10'], 'rowid' => 10],
        ['key' => ['autoload', '2'], 'rowid' => 2],
    ], 'GC', ['BINARY', 'BINARY'], [false, true]);
    $t->same([10, 2, 1], array_column($cursor->remaining(), 'rowid'));
};

$tests['vdbe index cursor seek greater or equal respects descending comparator'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeIndexCursor([
        ['key' => ['autoload', '1'], 'rowid' => 1],
        ['key' => ['autoload', '10'], 'rowid' => 10],
        ['key' => ['autoload', '2'], 'rowid' => 2],
    ], 'GC', ['BINARY', 'BINARY'], [false, true]);
    $t->true($cursor->seekGreaterOrEqual(['autoload', 2]));
    $t->same(2, $cursor->currentRowid());
};

$tests['vdbe index cursor partial prefix scan yields all matching leading keys'] = static function (TestRunner $t) use ($entries): void {
    $cursor = new SQLiteVdbeIndexCursor($entries, 'GCG', ['BINARY', 'BINARY', 'NOCASE']);
    $matched = $cursor->yieldEqual(['autoload']);
    $t->same(['Plugin_A', 'plugin_a', 'plugin_blob', 'Plugin_Z'], array_map(static fn (array $entry): string => $entry['payload']['option_name'], $matched));
};

$tests['vdbe index cursor remaining continues after prefix scan'] = static function (TestRunner $t) use ($entries): void {
    $cursor = new SQLiteVdbeIndexCursor($entries, 'GCG', ['BINARY', 'BINARY', 'NOCASE']);
    $cursor->yieldEqual(['autoload']);
    $t->same([8, 7, 12], array_column($cursor->remaining(), 'rowid'));
};

$tests['vdbe index cursor seek past final key moves to eof'] = static function (TestRunner $t) use ($entries): void {
    $cursor = new SQLiteVdbeIndexCursor($entries, 'GCG', ['BINARY', 'BINARY', 'NOCASE']);
    $t->same(false, $cursor->seekGreaterOrEqual(['zz']));
    $t->true($cursor->eof());
};

$tests['vdbe index cursor next at eof remains eof'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeIndexCursor([]);
    $cursor->next();
    $t->true($cursor->eof());
};

$tests['vdbe index cursor rewind restores first entry'] = static function (TestRunner $t) use ($entries): void {
    $cursor = new SQLiteVdbeIndexCursor($entries, 'GCG', ['BINARY', 'BINARY', 'NOCASE']);
    $cursor->seekGreaterOrEqual(['network']);
    $cursor->rewind();
    $t->same(1, $cursor->currentRowid());
};

$tests['vdbe index cursor current at eof returns null'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeIndexCursor([]);
    $t->same(null, $cursor->current());
};

$tests['vdbe index cursor current key at eof throws'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeIndexCursor([]);
    $t->throws(OutOfBoundsException::class, static fn () => $cursor->currentKey());
};

$tests['vdbe index cursor current rowid at eof throws'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeIndexCursor([]);
    $t->throws(OutOfBoundsException::class, static fn () => $cursor->currentRowid());
};

$tests['vdbe index cursor rejects missing current column'] = static function (TestRunner $t) use ($entries): void {
    $cursor = new SQLiteVdbeIndexCursor($entries, 'GCG', ['BINARY', 'BINARY', 'NOCASE']);
    $t->throws(OutOfBoundsException::class, static fn () => $cursor->currentColumn(4));
};

$tests['vdbe index cursor rejects negative current column'] = static function (TestRunner $t) use ($entries): void {
    $cursor = new SQLiteVdbeIndexCursor($entries, 'GCG', ['BINARY', 'BINARY', 'NOCASE']);
    $t->throws(InvalidArgumentException::class, static fn () => $cursor->currentColumn(-1));
};

$tests['vdbe index cursor rejects associative entries'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeIndexCursor(['x' => ['key' => [1]]]));
};

$tests['vdbe index cursor rejects missing key'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeIndexCursor([['rowid' => 1]]));
};

$tests['vdbe index cursor rejects non-list key'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeIndexCursor([['key' => ['a' => 1]]]));
};

$tests['vdbe index cursor rejects associative probe'] = static function (TestRunner $t) use ($entries): void {
    $cursor = new SQLiteVdbeIndexCursor($entries, 'GCG');
    $t->throws(InvalidArgumentException::class, static fn () => $cursor->seekGreaterOrEqual(['name' => 'autoload']));
};

$tests['vdbe index cursor rejects wide probe'] = static function (TestRunner $t) use ($entries): void {
    $cursor = new SQLiteVdbeIndexCursor($entries, 'GCG');
    $t->throws(InvalidArgumentException::class, static fn () => $cursor->seekGreaterOrEqual(['autoload', 2, 'Plugin_A', 'extra']));
};

$tests['vdbe index cursor unsupported affinity bubbles from comparator'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeIndexCursor([
        ['key' => [1], 'rowid' => 1],
        ['key' => [2], 'rowid' => 2],
    ], 'Z'));
};

$tests['vdbe index cursor yields no rows for absent equality prefix'] = static function (TestRunner $t) use ($entries): void {
    $cursor = new SQLiteVdbeIndexCursor($entries, 'GCG', ['BINARY', 'BINARY', 'NOCASE']);
    $t->same([], $cursor->yieldEqual(['autoload', 3]));
};

$tests['vdbe index cursor preserves payload with current row'] = static function (TestRunner $t) use ($entries): void {
    $cursor = new SQLiteVdbeIndexCursor($entries, 'GCG', ['BINARY', 'BINARY', 'NOCASE']);
    $t->same('Plugin_A', $cursor->current()['payload']['option_name']);
};

$tests['vdbe index cursor default rowids are one based'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeIndexCursor([
        ['key' => ['b']],
        ['key' => ['a']],
    ], 'G');
    $t->same([2, 1], array_column($cursor->remaining(), 'rowid'));
};

$tests['vdbe index cursor integer affinity coerces plus signed text'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeIndexCursor([
        ['key' => ['+8'], 'rowid' => 8],
        ['key' => [9], 'rowid' => 9],
    ], 'D');
    $matched = $cursor->yieldEqual([8]);
    $t->same([8], array_column($matched, 'rowid'));
};

$tests['vdbe index cursor real affinity coerces decimal text'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeIndexCursor([
        ['key' => ['8.5'], 'rowid' => 85],
        ['key' => [9.5], 'rowid' => 95],
    ], 'E');
    $matched = $cursor->yieldEqual([8.5]);
    $t->same([85], array_column($matched, 'rowid'));
};

$tests['vdbe index cursor text affinity compares converted booleans'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeIndexCursor([
        ['key' => [true], 'rowid' => 1],
        ['key' => [false], 'rowid' => 0],
    ], 'G');
    $t->same([0, 1], array_column($cursor->remaining(), 'rowid'));
};

$tests['vdbe index cursor none affinity preserves storage class rank'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeIndexCursor([
        ['key' => ['2'], 'rowid' => 2],
        ['key' => [10], 'rowid' => 10],
    ], 'A');
    $t->same([10, 2], array_column($cursor->remaining(), 'rowid'));
};

$tests['vdbe index cursor binary collation keeps case order'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeIndexCursor([
        ['key' => ['plugin'], 'rowid' => 2],
        ['key' => ['Plugin'], 'rowid' => 1],
    ], 'G', ['BINARY']);
    $t->same([1, 2], array_column($cursor->remaining(), 'rowid'));
};

$tests['vdbe index cursor nocase collation falls back to rowid'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeIndexCursor([
        ['key' => ['plugin'], 'rowid' => 2],
        ['key' => ['Plugin'], 'rowid' => 1],
    ], 'G', ['NOCASE']);
    $t->same([1, 2], array_column($cursor->remaining(), 'rowid'));
};

$tests['vdbe index cursor partial numeric prefix ignores suffix while equal'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeIndexCursor([
        ['key' => ['autoload', '2', 'b'], 'rowid' => 2],
        ['key' => ['autoload', 2, 'a'], 'rowid' => 1],
        ['key' => ['autoload', 3, 'a'], 'rowid' => 3],
    ], 'GCG');
    $matched = $cursor->yieldEqual(['autoload', '02']);
    $t->same([1, 2], array_column($matched, 'rowid'));
};

$tests['vdbe index cursor remaining from seek includes positioned row'] = static function (TestRunner $t) use ($entries): void {
    $cursor = new SQLiteVdbeIndexCursor($entries, 'GCG', ['BINARY', 'BINARY', 'NOCASE']);
    $cursor->seekGreaterOrEqual(['cache']);
    $t->same([8, 7, 12], array_column($cursor->remaining(), 'rowid'));
};

$tests['vdbe index cursor duplicate rowid tie keeps deterministic order'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeIndexCursor([
        ['key' => ['a'], 'rowid' => 1, 'payload' => ['value' => 'first']],
        ['key' => ['a'], 'rowid' => 1, 'payload' => ['value' => 'second']],
    ], 'G');
    $t->same(['first', 'second'], array_map(static fn (array $entry): string => $entry['payload']['value'], $cursor->remaining()));
};

return $tests;
