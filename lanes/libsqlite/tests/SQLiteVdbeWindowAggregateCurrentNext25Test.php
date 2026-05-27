<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$rows = [
    ['rowid' => 1, 'site' => 1, 'autoload' => 'yes', 'name' => 'siteurl', 'bytes' => 24, 'ok' => 1],
    ['rowid' => 2, 'site' => 1, 'autoload' => 'yes', 'name' => 'home', 'bytes' => '24', 'ok' => '1'],
    ['rowid' => 3, 'site' => 1, 'autoload' => 'yes', 'name' => 'blogname', 'bytes' => 9, 'ok' => 0],
    ['rowid' => 4, 'site' => 1, 'autoload' => 'no', 'name' => '_transient_feed', 'bytes' => 30, 'ok' => 1],
    ['rowid' => 5, 'site' => 2, 'autoload' => 'yes', 'name' => 'network_home', 'bytes' => 40, 'ok' => 1],
    ['rowid' => 6, 'site' => 2, 'autoload' => 'yes', 'name' => 'plugin_cache', 'bytes' => null, 'ok' => 1],
    ['rowid' => 7, 'site' => 2, 'autoload' => 'no', 'name' => 'orphan', 'bytes' => 5, 'ok' => '0'],
];

$cursorFor = static fn (int $preceding = 1, int $following = 0): SQLiteVdbeWindowAggregateCursor => new SQLiteVdbeWindowAggregateCursor(
    $rows,
    'bytes',
    ['site'],
    ['autoload', 'name'],
    'ok',
    $preceding,
    $following,
    'D',
    [],
    'GG',
    ['NOCASE', 'BINARY']
);

$tests = [];

$tests['vdbe window aggregate starts at first partition and order key'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $t->same(4, $cursor->currentRow()['rowid']);
    $t->same([1], $cursor->currentPartitionKey());
    $t->same(['no', '_transient_feed'], $cursor->currentOrderKey());
};

$tests['vdbe window aggregate current frame clips at partition start'] = static function (TestRunner $t) use ($cursorFor): void {
    $t->same([4], array_column($cursorFor()->currentFrameRows(), 'rowid'));
};

$tests['vdbe window aggregate next advances through sorted rows'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->next();
    $t->same(3, $cursor->currentRow()['rowid']);
    $t->same(['yes', 'blogname'], $cursor->currentOrderKey());
};

$tests['vdbe window aggregate frame includes preceding row in same partition'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->next();
    $t->same([4, 3], array_column($cursor->currentFrameRows(), 'rowid'));
};

$tests['vdbe window aggregate frame never crosses partition boundary'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    for ($i = 0; $i < 4; $i++) {
        $cursor->next();
    }
    $t->same(2, $cursor->currentRow()['site']);
    $t->same([7], array_column($cursor->currentFrameRows(), 'rowid'));
};

$tests['vdbe window aggregate following bound includes next row'] = static function (TestRunner $t) use ($cursorFor): void {
    $t->same([4, 3], array_column($cursorFor(0, 1)->currentFrameRows(), 'rowid'));
};

$tests['vdbe window aggregate preceding and following frame is centered'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor(1, 1);
    $cursor->next();
    $t->same([4, 3, 2], array_column($cursor->currentFrameRows(), 'rowid'));
};

$tests['vdbe window aggregate count all includes null values'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor(1, 0);
    for ($i = 0; $i < 5; $i++) {
        $cursor->next();
    }
    $t->same(2, $cursor->countAll());
};

$tests['vdbe window aggregate count value skips null values'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor(1, 0);
    for ($i = 0; $i < 5; $i++) {
        $cursor->next();
    }
    $t->same(1, $cursor->countValue());
};

$tests['vdbe window aggregate sum applies filter before values'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->next();
    $t->same(30, $cursor->sum());
};

$tests['vdbe window aggregate current values can include unfiltered frame rows'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->next();
    $t->same([30, 9], $cursor->currentValues(false));
};

$tests['vdbe window aggregate filtered current values skip false SQL filters'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->next();
    $t->same([30], $cursor->currentValues());
};

$tests['vdbe window aggregate total returns floating frame total'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor(2, 0);
    $cursor->next();
    $cursor->next();
    $t->same(54.0, $cursor->total());
};

$tests['vdbe window aggregate avg uses non null filtered values'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor(2, 0);
    $cursor->next();
    $cursor->next();
    $t->same(27.0, $cursor->avg());
};

$tests['vdbe window aggregate min returns SQLite minimum for frame'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor(2, 0);
    $cursor->next();
    $cursor->next();
    $t->same(30, $cursor->min());
};

$tests['vdbe window aggregate max returns SQLite maximum for frame'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor(2, 0);
    $cursor->next();
    $cursor->next();
    $t->same('24', $cursor->max());
};

$tests['vdbe window aggregate group concat uses current filtered frame order'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor(2, 0);
    $cursor->next();
    $cursor->next();
    $t->same('30|24', $cursor->groupConcat('|'));
};

$tests['vdbe window aggregate group concat null separator returns null'] = static function (TestRunner $t) use ($cursorFor): void {
    $t->same(null, $cursorFor()->groupConcat(null));
};

$tests['vdbe window aggregate group concat casts blob separator'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor(2, 0);
    $cursor->next();
    $cursor->next();
    $t->same('30::24', $cursor->groupConcat(new SQLiteBlobValue('::')));
};

$tests['vdbe window aggregate summary reports frame bounds'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->next();
    $t->same(0, $cursor->currentSummary()['frameStart']);
    $t->same(1, $cursor->currentSummary()['frameEnd']);
    $t->same(2, $cursor->currentSummary()['frameRows']);
};

$tests['vdbe window aggregate summary reports filtered row count'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->next();
    $t->same(1, $cursor->currentSummary()['filteredRows']);
};

$tests['vdbe window aggregate drain summaries uses current next loop'] = static function (TestRunner $t) use ($cursorFor): void {
    $t->same([30, 9, 24, 24, 5, 40, 0], array_map(static fn (array $summary): int => $summary['value'] === null ? 0 : (int) $summary['value'], $cursorFor()->drainSummaries('|')));
};

$tests['vdbe window aggregate drain summaries reports totals per row'] = static function (TestRunner $t) use ($cursorFor): void {
    $t->same([30.0, 30.0, 24.0, 48.0, 0.0, 40.0, 40.0], array_column($cursorFor()->drainSummaries('|'), 'total'));
};

$tests['vdbe window aggregate drain summaries reports group concat per row'] = static function (TestRunner $t) use ($cursorFor): void {
    $t->same(['30', '30', '24', '24|24', null, '40', '40'], array_column($cursorFor()->drainSummaries('|'), 'groupConcat'));
};

$tests['vdbe window aggregate reaches eof after draining'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->drainSummaries();
    $t->true($cursor->eof());
};

$tests['vdbe window aggregate next at eof remains eof'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->drainSummaries();
    $cursor->next();
    $t->true($cursor->eof());
};

$tests['vdbe window aggregate rewind restores first row'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->drainSummaries();
    $cursor->rewind();
    $t->same(4, $cursor->currentRow()['rowid']);
};

$tests['vdbe window aggregate current row at eof is null'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([], 'v', [], ['ord']);
    $t->same(null, $cursor->currentRow());
};

$tests['vdbe window aggregate current frame at eof throws'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([], 'v', [], ['ord']);
    $t->throws(OutOfBoundsException::class, static fn () => $cursor->currentFrameRows());
};

$tests['vdbe window aggregate current partition at eof throws'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([], 'v', [], ['ord']);
    $t->throws(OutOfBoundsException::class, static fn () => $cursor->currentPartitionKey());
};

$tests['vdbe window aggregate empty partition columns make one partition'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([
        ['ord' => 2, 'v' => 2],
        ['ord' => 1, 'v' => 1],
    ], 'v', [], ['ord'], null, 1, 0);
    $cursor->next();
    $t->same([1, 2], $cursor->currentValues(false));
};

$tests['vdbe window aggregate descending order reverses scan within partition'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([
        ['g' => 'a', 'ord' => 1, 'v' => 'one'],
        ['g' => 'a', 'ord' => 2, 'v' => 'two'],
    ], 'v', ['g'], ['ord'], null, 0, 0, [], [], [], [], [true]);
    $t->same('two', $cursor->currentRow()['v']);
};

$tests['vdbe window aggregate nulls last places null order key at end'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([
        ['g' => 'a', 'ord' => null, 'v' => 'null'],
        ['g' => 'a', 'ord' => 1, 'v' => 'one'],
    ], 'v', ['g'], ['ord'], null, 0, 0, [], [], [], [], [], ['LAST']);
    $t->same('one', $cursor->currentRow()['v']);
};

$tests['vdbe window aggregate nocase partition collation groups case variants'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([
        ['g' => 'YES', 'ord' => 1, 'v' => 1],
        ['g' => 'yes', 'ord' => 2, 'v' => 2],
        ['g' => 'no', 'ord' => 3, 'v' => 10],
    ], 'v', ['g'], ['ord'], null, 1, 0, 'G', ['NOCASE']);
    $cursor->next();
    $cursor->next();
    $t->same([1, 2], $cursor->currentValues(false));
};

$tests['vdbe window aggregate binary partition collation separates case variants'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([
        ['g' => 'YES', 'ord' => 1, 'v' => 1],
        ['g' => 'yes', 'ord' => 2, 'v' => 2],
    ], 'v', ['g'], ['ord'], null, 1, 0, 'G', ['BINARY']);
    $cursor->next();
    $t->same([2], $cursor->currentValues(false));
};

$tests['vdbe window aggregate text order affinity sorts integer text lexically'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([
        ['g' => 'a', 'ord' => 10, 'v' => 'ten'],
        ['g' => 'a', 'ord' => 2, 'v' => 'two'],
    ], 'v', ['g'], ['ord'], null, 0, 0, [], [], ['TEXT']);
    $t->same('ten', $cursor->currentRow()['v']);
};

$tests['vdbe window aggregate numeric order affinity sorts numeric text numerically'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([
        ['g' => 'a', 'ord' => '10', 'v' => 'ten'],
        ['g' => 'a', 'ord' => '2', 'v' => 'two'],
    ], 'v', ['g'], ['ord'], null, 0, 0, [], [], ['NUMERIC']);
    $t->same('two', $cursor->currentRow()['v']);
};

$tests['vdbe window aggregate string zero filter is false'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([['ord' => 1, 'v' => 7, 'ok' => '0']], 'v', [], ['ord'], 'ok');
    $t->same(null, $cursor->sum());
};

$tests['vdbe window aggregate negative filter is true'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([['ord' => 1, 'v' => 7, 'ok' => -1]], 'v', [], ['ord'], 'ok');
    $t->same(7, $cursor->sum());
};

$tests['vdbe window aggregate nonnumeric filter is false'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([['ord' => 1, 'v' => 7, 'ok' => 'yes']], 'v', [], ['ord'], 'ok');
    $t->same(null, $cursor->sum());
};

$tests['vdbe window aggregate rejects associative rows'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeWindowAggregateCursor(['x' => ['ord' => 1, 'v' => 1]], 'v', [], ['ord']));
};

$tests['vdbe window aggregate rejects empty value column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeWindowAggregateCursor([], '', [], ['ord']));
};

$tests['vdbe window aggregate rejects negative preceding bound'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeWindowAggregateCursor([], 'v', [], ['ord'], null, -1));
};

$tests['vdbe window aggregate rejects negative following bound'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeWindowAggregateCursor([], 'v', [], ['ord'], null, 0, -1));
};

$tests['vdbe window aggregate rejects associative partition column list'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeWindowAggregateCursor([], 'v', ['x' => 'g'], ['ord']));
};

$tests['vdbe window aggregate rejects empty partition column name'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeWindowAggregateCursor([], 'v', [''], ['ord']));
};

$tests['vdbe window aggregate rejects empty order columns'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeWindowAggregateCursor([], 'v', [], []));
};

$tests['vdbe window aggregate rejects associative order columns'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeWindowAggregateCursor([], 'v', [], ['x' => 'ord']));
};

$tests['vdbe window aggregate rejects missing value column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeWindowAggregateCursor([['ord' => 1]], 'v', [], ['ord']));
};

$tests['vdbe window aggregate rejects missing partition column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeWindowAggregateCursor([['ord' => 1, 'v' => 1]], 'v', ['g'], ['ord']));
};

$tests['vdbe window aggregate rejects missing order column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeWindowAggregateCursor([['v' => 1]], 'v', [], ['ord']));
};

$tests['vdbe window aggregate rejects missing filter column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeWindowAggregateCursor([['ord' => 1, 'v' => 1]], 'v', [], ['ord'], 'ok'));
};

$tests['vdbe window aggregate rejects non scalar sort value'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeWindowAggregateCursor([['ord' => [], 'v' => 1]], 'v', [], ['ord']));
};

$tests['vdbe window aggregate rejects invalid collation through comparator'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeWindowAggregateCursor([['ord' => 'a', 'v' => 1], ['ord' => 'b', 'v' => 2]], 'v', [], ['ord'], null, 0, 0, [], [], [], ['BOGUS']));
};

return $tests;
