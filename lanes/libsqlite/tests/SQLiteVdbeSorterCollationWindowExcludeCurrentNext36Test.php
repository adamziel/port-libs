<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$rows = [
    ['rowid' => 1, 'site' => 1, 'locale' => 'en_US', 'name' => 'Alpha', 'bytes' => 10, 'include' => 1],
    ['rowid' => 2, 'site' => 1, 'locale' => 'en_US', 'name' => 'alpha', 'bytes' => 20, 'include' => 1],
    ['rowid' => 3, 'site' => 1, 'locale' => 'en_US', 'name' => 'Beta', 'bytes' => 30, 'include' => 0],
    ['rowid' => 4, 'site' => 1, 'locale' => 'fr_FR', 'name' => 'alpha', 'bytes' => 40, 'include' => 1],
    ['rowid' => 5, 'site' => 2, 'locale' => 'en_US', 'name' => 'Alpha', 'bytes' => 50, 'include' => 1],
    ['rowid' => 6, 'site' => 2, 'locale' => 'en_US', 'name' => 'gamma', 'bytes' => 60, 'include' => 1],
    ['rowid' => 7, 'site' => 2, 'locale' => 'en_US', 'name' => 'Gamma', 'bytes' => null, 'include' => 1],
];

$cursorFor = static fn (int $preceding = 1, int $following = 1): SQLiteVdbeWindowAggregateCursor => new SQLiteVdbeWindowAggregateCursor(
    $rows,
    'bytes',
    ['site', 'locale'],
    ['name'],
    'include',
    $preceding,
    $following,
    ['INTEGER', 'TEXT'],
    ['BINARY', 'BINARY'],
    ['TEXT'],
    ['NOCASE'],
    [],
    [],
    'ROWS',
    'CURRENT ROW'
);

$tests = [];

$tests['vdbe sorter window exclude current starts in collation order'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $t->same(1, $cursor->currentRow()['rowid']);
};

$tests['vdbe sorter window exclude current preserves nocase peer stable order'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->next();
    $t->same(2, $cursor->currentRow()['rowid']);
};

$tests['vdbe sorter window exclude current removes first current row from frame'] = static function (TestRunner $t) use ($cursorFor): void {
    $t->same([2], array_column($cursorFor()->currentFrameRows(false), 'rowid'));
};

$tests['vdbe sorter window exclude current removes middle current row from frame'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->next();
    $t->same([1, 3], array_column($cursor->currentFrameRows(false), 'rowid'));
};

$tests['vdbe sorter window exclude current removes last current row from frame'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->next();
    $cursor->next();
    $t->same([2], array_column($cursor->currentFrameRows(false), 'rowid'));
};

$tests['vdbe sorter window exclude current never crosses partition'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    for ($i = 0; $i < 3; $i++) {
        $cursor->next();
    }
    $t->same([], array_column($cursor->currentFrameRows(false), 'rowid'));
};

$tests['vdbe sorter window exclude current supports empty frame'] = static function (TestRunner $t) use ($cursorFor): void {
    $t->same([], array_column($cursorFor(0, 0)->currentFrameRows(false), 'rowid'));
};

$tests['vdbe sorter window exclude current empty frame count all is zero'] = static function (TestRunner $t) use ($cursorFor): void {
    $t->same(0, $cursorFor(0, 0)->countAll());
};

$tests['vdbe sorter window exclude current empty frame sum is null'] = static function (TestRunner $t) use ($cursorFor): void {
    $t->same(null, $cursorFor(0, 0)->sum());
};

$tests['vdbe sorter window exclude current empty frame total is zero'] = static function (TestRunner $t) use ($cursorFor): void {
    $t->same(0.0, $cursorFor(0, 0)->total());
};

$tests['vdbe sorter window exclude current empty frame group concat is null'] = static function (TestRunner $t) use ($cursorFor): void {
    $t->same(null, $cursorFor(0, 0)->groupConcat('|'));
};

$tests['vdbe sorter window exclude current summary reports null empty bounds'] = static function (TestRunner $t) use ($cursorFor): void {
    $summary = $cursorFor(0, 0)->currentSummary();
    $t->same(null, $summary['frameStart']);
    $t->same(null, $summary['frameEnd']);
};

$tests['vdbe sorter window exclude current summary reports empty row count'] = static function (TestRunner $t) use ($cursorFor): void {
    $t->same(0, $cursorFor(0, 0)->currentSummary()['frameRows']);
};

$tests['vdbe sorter window exclude current summary reports filtered empty count'] = static function (TestRunner $t) use ($cursorFor): void {
    $t->same(0, $cursorFor(0, 0)->currentSummary()['filteredRows']);
};

$tests['vdbe sorter window exclude current filtered rows still apply filter after exclusion'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->next();
    $t->same([1], array_column($cursor->currentFrameRows(true), 'rowid'));
};

$tests['vdbe sorter window exclude current sum skips current and filtered false rows'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->next();
    $t->same(10, $cursor->sum());
};

$tests['vdbe sorter window exclude current count value skips null peer after exclusion'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    for ($i = 0; $i < 5; $i++) {
        $cursor->next();
    }
    $t->same(1, $cursor->countValue());
};

$tests['vdbe sorter window exclude current count all keeps null peer rows'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    for ($i = 0; $i < 5; $i++) {
        $cursor->next();
    }
    $t->same(2, $cursor->countAll());
};

$tests['vdbe sorter window exclude current group concat excludes current value'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->next();
    $t->same('10', $cursor->groupConcat('|'));
};

$tests['vdbe sorter window exclude current drain totals reflect excluded current rows'] = static function (TestRunner $t) use ($cursorFor): void {
    $t->same([20.0, 10.0, 20.0, 0.0, 60.0, 50.0, 60.0], array_column($cursorFor()->drainSummaries('|'), 'total'));
};

$tests['vdbe sorter window exclude current drain concat reflects excluded current rows'] = static function (TestRunner $t) use ($cursorFor): void {
    $t->same(['20', '10', '20', null, '60', '50', '60'], array_column($cursorFor()->drainSummaries('|'), 'groupConcat'));
};

$tests['vdbe sorter window exclude current current values can ignore filter'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->next();
    $t->same([10, 30], $cursor->currentValues(false));
};

$tests['vdbe sorter window exclude current filtered current values skip false neighbor'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->next();
    $t->same([10], $cursor->currentValues(true));
};

$tests['vdbe sorter window exclude current min uses excluded frame'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->next();
    $t->same(10, $cursor->min());
};

$tests['vdbe sorter window exclude current max uses excluded frame'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->next();
    $t->same(10, $cursor->max());
};

$tests['vdbe sorter window exclude current average uses excluded filtered frame'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->next();
    $t->same(10.0, $cursor->avg());
};

$tests['vdbe sorter window exclude current descending order removes physical current'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([
        ['ord' => 1, 'v' => 'low'],
        ['ord' => 2, 'v' => 'high'],
    ], 'v', [], ['ord'], null, 1, 0, [], [], [], [], [true], [], 'ROWS', 'CURRENT ROW');
    $t->same([], $cursor->currentValues(false));
};

$tests['vdbe sorter window exclude current range peers keep noncurrent peers'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([
        ['ord' => 1, 'v' => 'a'],
        ['ord' => 1, 'v' => 'b'],
        ['ord' => 2, 'v' => 'c'],
    ], 'v', [], ['ord'], null, 0, 0, [], [], [], [], [], [], 'RANGE', 'CURRENT ROW');
    $t->same(['b'], $cursor->currentValues(false));
};

$tests['vdbe sorter window exclude current range numeric following excludes current only'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([
        ['ord' => 1, 'v' => 10],
        ['ord' => 2, 'v' => 20],
        ['ord' => 3, 'v' => 30],
    ], 'v', [], ['ord'], null, 0, 1, [], [], [], [], [], [], 'RANGE', 'CURRENT ROW');
    $t->same([20], $cursor->currentValues(false));
};

$tests['vdbe sorter window exclude current range descending preceding excludes current only'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([
        ['ord' => 3, 'v' => 30],
        ['ord' => 2, 'v' => 20],
        ['ord' => 1, 'v' => 10],
    ], 'v', [], ['ord'], null, 1, 0, [], [], [], [], [true], [], 'RANGE', 'CURRENT ROW');
    $t->same([], $cursor->currentValues(false));
};

$tests['vdbe sorter window exclude current rejects unsupported exclude mode'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeWindowAggregateCursor([], 'v', [], ['ord'], null, 0, 0, [], [], [], [], [], [], 'ROWS', 'SIDEWAYS'));
};

$tests['vdbe sorter window exclude current accepts lowercase exclude mode'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([
        ['ord' => 1, 'v' => 1],
        ['ord' => 2, 'v' => 2],
    ], 'v', [], ['ord'], null, 0, 1, [], [], [], [], [], [], 'ROWS', 'current row');
    $t->same([2], $cursor->currentValues(false));
};

$tests['vdbe sorter window no others keeps current row by default'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([
        ['ord' => 1, 'v' => 1],
    ], 'v', [], ['ord']);
    $t->same([1], $cursor->currentValues(false));
};

foreach ([
    'first row frame rowids' => [0, [2]],
    'second row frame rowids' => [1, [1, 3]],
    'third row frame rowids' => [2, [2]],
    'fourth row frame rowids' => [3, []],
    'fifth row frame rowids' => [4, [6]],
    'sixth row frame rowids' => [5, [5, 7]],
    'seventh row frame rowids' => [6, [6]],
] as $name => [$steps, $expected]) {
    $tests['vdbe sorter window exclude current loop ' . $name] = static function (TestRunner $t) use ($cursorFor, $steps, $expected): void {
        $cursor = $cursorFor();
        for ($i = 0; $i < $steps; $i++) {
            $cursor->next();
        }
        $t->same($expected, array_column($cursor->currentFrameRows(false), 'rowid'));
    };
}

foreach ([
    'first row filtered total' => [0, 20.0],
    'second row filtered total' => [1, 10.0],
    'third row filtered total' => [2, 20.0],
    'fourth row filtered total' => [3, 0.0],
    'fifth row filtered total' => [4, 60.0],
    'sixth row filtered total' => [5, 50.0],
    'seventh row filtered total' => [6, 60.0],
] as $name => [$steps, $expected]) {
    $tests['vdbe sorter window exclude current loop ' . $name] = static function (TestRunner $t) use ($cursorFor, $steps, $expected): void {
        $cursor = $cursorFor();
        for ($i = 0; $i < $steps; $i++) {
            $cursor->next();
        }
        $t->same($expected, $cursor->total());
    };
}

foreach ([
    'nocase first alpha peer' => [0, ['Alpha']],
    'nocase second alpha peer' => [1, ['alpha']],
    'nocase beta neighbor excluded by filter' => [2, ['Beta']],
    'site two first row neighbor' => [4, ['Alpha']],
    'site two gamma peer lower' => [5, ['gamma']],
    'site two gamma peer upper' => [6, ['Gamma']],
] as $name => [$steps, $expected]) {
    $tests['vdbe sorter window exclude current loop ' . $name] = static function (TestRunner $t) use ($cursorFor, $steps, $expected): void {
        $cursor = $cursorFor();
        for ($i = 0; $i < $steps; $i++) {
            $cursor->next();
        }
        $t->same($expected, [$cursor->currentRow()['name']]);
    };
}

foreach ([
    'summary first row bounds' => [0, 1, 1],
    'summary second row bounds' => [1, 0, 2],
    'summary third row bounds' => [2, 1, 1],
    'summary fourth row bounds' => [3, null, null],
    'summary fifth row bounds' => [4, 5, 5],
    'summary sixth row bounds' => [5, 4, 6],
    'summary seventh row bounds' => [6, 5, 5],
] as $name => [$steps, $start, $end]) {
    $tests['vdbe sorter window exclude current ' . $name] = static function (TestRunner $t) use ($cursorFor, $steps, $start, $end): void {
        $cursor = $cursorFor();
        for ($i = 0; $i < $steps; $i++) {
            $cursor->next();
        }
        $summary = $cursor->currentSummary();
        $t->same($start, $summary['frameStart']);
        $t->same($end, $summary['frameEnd']);
    };
}

return $tests;
