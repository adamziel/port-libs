<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$tests = [];

$rows = [
    ['rowid' => 1, 'blog' => 1, 'autoload' => null, 'option_name' => 'zeta', 'bytes' => 5, 'ok' => 1],
    ['rowid' => 2, 'blog' => 1, 'autoload' => 'yes', 'option_name' => null, 'bytes' => 7, 'ok' => 0],
    ['rowid' => 3, 'blog' => 1, 'autoload' => 'YES', 'option_name' => 'alpha', 'bytes' => null, 'ok' => null],
    ['rowid' => 4, 'blog' => 1, 'autoload' => 'yes', 'option_name' => 'Alpha ', 'bytes' => 11, 'ok' => '1'],
    ['rowid' => 5, 'blog' => 1, 'autoload' => 'yes', 'option_name' => 'beta', 'bytes' => 13, 'ok' => '0'],
    ['rowid' => 6, 'blog' => 1, 'autoload' => 'yes', 'option_name' => 'gamma', 'bytes' => 17, 'ok' => '2'],
    ['rowid' => 7, 'blog' => 2, 'autoload' => null, 'option_name' => 'network', 'bytes' => 19, 'ok' => 1],
    ['rowid' => 8, 'blog' => 2, 'autoload' => 'no', 'option_name' => null, 'bytes' => 23, 'ok' => '0'],
    ['rowid' => 9, 'blog' => 2, 'autoload' => 'no', 'option_name' => 'cache', 'bytes' => null, 'ok' => 1],
    ['rowid' => 10, 'blog' => 2, 'autoload' => 'yes', 'option_name' => 'Network', 'bytes' => 29, 'ok' => -1],
];

$cursorFor = static fn (int $preceding = 1, int $following = 1): SQLiteVdbeWindowAggregateCursor => new SQLiteVdbeWindowAggregateCursor(
    $rows,
    'bytes',
    ['blog'],
    ['autoload', 'option_name'],
    'ok',
    $preceding,
    $following,
    'D',
    [],
    'GG',
    ['NOCASE', 'RTRIM'],
    [false, false],
    ['FIRST', 'FIRST']
);

$at = static function (SQLiteVdbeWindowAggregateCursor $cursor, int $position): SQLiteVdbeWindowAggregateCursor {
    for ($i = 0; $i < $position; $i++) {
        $cursor->next();
    }

    return $cursor;
};

$rowidAt = static fn (int $position): int => $at($cursorFor(), $position)->currentRow()['rowid'];
$nextRowidAt = static fn (int $position): ?int => $at($cursorFor(), $position)->peekNextRow()['rowid'] ?? null;
$frameIdsAt = static fn (int $position): array => array_column($at($cursorFor(), $position)->currentFrameRows(false), 'rowid');
$filteredIdsAt = static fn (int $position): array => array_column($at($cursorFor(), $position)->currentFrameRows(true), 'rowid');
$filterAt = static fn (int $position): mixed => $at($cursorFor(), $position)->currentFilterValue();
$passedAt = static fn (int $position): bool => $at($cursorFor(), $position)->currentFilterPassed();
$orderKeyAt = static fn (int $position): array => $at($cursorFor(), $position)->currentOrderKey();
$nextOrderKeyAt = static fn (int $position): ?array => $at($cursorFor(), $position)->peekNextOrderKey();

$cases = [
    'null autoload sorts first in first partition' => [static fn (): mixed => $rowidAt(0), 1],
    'next row after null autoload keeps current cursor unadvanced' => [static fn (): mixed => [$rowidAt(0), $nextRowidAt(0), $rowidAt(0)], [1, 3, 1]],
    'null filter row sorts before null option name inside yes autoload group' => [static fn (): mixed => $rowidAt(1), 3],
    'null filter current row is false but still yielded' => [static fn (): mixed => [$rowidAt(1), $filterAt(1), $passedAt(1)], [3, null, false]],
    'null filter row frame includes unfiltered false current row' => [static fn (): mixed => $frameIdsAt(1), [1, 3, 2]],
    'null filter row filtered frame skips false current row' => [static fn (): mixed => $filteredIdsAt(1), [1]],
    'null option name count all includes false current row' => [static fn (): mixed => $at($cursorFor(), 1)->countAll(), 3],
    'null filter row count value skips filtered rows' => [static fn (): mixed => $at($cursorFor(), 1)->countValue(), 1],
    'null filter row sum skips false current row' => [static fn (): mixed => $at($cursorFor(), 1)->sum(), 5],
    'null filter row group concat skips false current row' => [static fn (): mixed => $at($cursorFor(), 1)->groupConcat('|'), '5'],
    'null option name then rtrim peer follow null filter row' => [static fn (): mixed => [$rowidAt(2), $rowidAt(3)], [2, 4]],
    'null option name current row remains yielded with false filter' => [static fn (): mixed => [$filterAt(2), $passedAt(2), $at($cursorFor(), 2)->sum()], [0, false, 11]],
    'truthy string current row is yielded and contributes' => [static fn (): mixed => [$rowidAt(3), $filterAt(3), $passedAt(3)], [4, '1', true]],
    'truthy string current row keeps next key visible' => [static fn (): mixed => [$orderKeyAt(3), $nextOrderKeyAt(3)], [['yes', 'Alpha '], ['yes', 'beta']]],
    'string zero current filter is false' => [static fn (): mixed => [$rowidAt(4), $filterAt(4), $passedAt(4)], [5, '0', false]],
    'string zero current row remains in raw frame' => [static fn (): mixed => $frameIdsAt(4), [4, 5, 6]],
    'string zero current row is omitted from filtered frame' => [static fn (): mixed => $filteredIdsAt(4), [4, 6]],
    'string zero current row total sees neighboring truthy rows' => [static fn (): mixed => $at($cursorFor(), 4)->total(), 28.0],
    'string numeric current filter is true' => [static fn (): mixed => [$rowidAt(5), $filterAt(5), $passedAt(5)], [6, '2', true]],
    'string numeric final row in first partition clamps frame end' => [static fn (): mixed => $frameIdsAt(5), [5, 6]],
    'partition boundary moves to null autoload in second partition' => [static fn (): mixed => [$rowidAt(6), $at($cursorFor(), 6)->currentPartitionKey()], [7, [2]]],
    'peek next partition does not cross after last first partition row' => [static fn (): mixed => $at($cursorFor(), 5)->peekNextPartitionKey(), [2]],
    'second partition null autoload frame starts at partition start' => [static fn (): mixed => $frameIdsAt(6), [7, 8]],
    'second partition null autoload filtered frame skips next false row' => [static fn (): mixed => $filteredIdsAt(6), [7]],
    'second partition null option current filter false still yields row' => [static fn (): mixed => [$rowidAt(7), $filterAt(7), $passedAt(7)], [8, '0', false]],
    'second partition null option frame includes nullable payload row' => [static fn (): mixed => $frameIdsAt(7), [7, 8, 9]],
    'second partition null option filtered frame keeps truthy neighbors' => [static fn (): mixed => $filteredIdsAt(7), [7, 9]],
    'second partition null option sum skips false current row' => [static fn (): mixed => $at($cursorFor(), 7)->sum(), 19],
    'null payload truthy filter counts all but not value' => [static fn (): mixed => [$rowidAt(8), $at($cursorFor(), 8)->countAll(), $at($cursorFor(), 8)->countValue()], [9, 3, 1]],
    'negative filter is true for final network row' => [static fn (): mixed => [$rowidAt(9), $filterAt(9), $passedAt(9)], [10, -1, true]],
    'final row peek next is null' => [static fn (): mixed => [$nextRowidAt(9), $nextOrderKeyAt(9), $at($cursorFor(), 9)->peekNextPartitionKey()], [null, null, null]],
    'final row filtered aggregate keeps current value' => [static fn (): mixed => [$filteredIdsAt(9), $at($cursorFor(), 9)->sum()], [[9, 10], 29]],
    'drain summaries report all current rows including filtered out rows' => [static fn (): mixed => array_column($cursorFor()->drainSummaries('|'), 'value'), [5, null, 7, 11, 13, 17, 19, 23, null, 29]],
    'drain summaries preserve null sorter current next order' => [static fn (): mixed => array_map(static fn (array $summary): array => $summary['orderKey'], $cursorFor()->drainSummaries('|')), [[null, 'zeta'], ['YES', 'alpha'], ['yes', null], ['yes', 'Alpha '], ['yes', 'beta'], ['yes', 'gamma'], [null, 'network'], ['no', null], ['no', 'cache'], ['yes', 'Network']]],
    'drain summaries expose current filter pass bits' => [static fn (): mixed => array_column($cursorFor()->drainSummaries('|'), 'currentFilterPassed'), [true, false, false, true, false, true, true, false, true, true]],
    'drain summaries expose next order keys' => [static fn (): mixed => array_column($cursorFor()->drainSummaries('|'), 'nextOrderKey'), [['YES', 'alpha'], ['yes', null], ['yes', 'Alpha '], ['yes', 'beta'], ['yes', 'gamma'], [null, 'network'], ['no', null], ['no', 'cache'], ['yes', 'Network'], null]],
    'drain summaries expose next partition keys' => [static fn (): mixed => array_column($cursorFor()->drainSummaries('|'), 'nextPartitionKey'), [[1], [1], [1], [1], [1], [2], [2], [2], [2], null]],
    'drain summaries totals omit filtered false current rows' => [static fn (): mixed => array_column($cursorFor()->drainSummaries('|'), 'total'), [5.0, 5.0, 11.0, 11.0, 28.0, 17.0, 19.0, 19.0, 29.0, 29.0]],
    'drain summaries group concat omits filtered false current rows' => [static fn (): mixed => array_column($cursorFor()->drainSummaries('|'), 'groupConcat'), ['5', '5', '11', '11', '11|17', '17', '19', '19', '29', '29']],
    'summary for false current filter still reports current order key' => [static fn (): mixed => $at($cursorFor(), 1)->currentSummary()['orderKey'], ['YES', 'alpha']],
    'summary for false current filter reports true filtered row count' => [static fn (): mixed => $at($cursorFor(), 1)->currentSummary()['filteredRows'], 1],
    'summary for truthy current filter reports pass bit' => [static fn (): mixed => $at($cursorFor(), 3)->currentSummary()['currentFilterPassed'], true],
    'summary for truthy string filter reports true pass bit' => [static fn (): mixed => $at($cursorFor(), 5)->currentSummary()['currentFilterPassed'], true],
    'rewind restores null sorter first row after drain' => [static fn (): mixed => (static function () use ($cursorFor): int {
        $cursor = $cursorFor();
        $cursor->drainSummaries('|');
        $cursor->rewind();

        return $cursor->currentRow()['rowid'];
    })(), 1],
    'peek next after rewind returns null filter alpha row' => [static fn (): mixed => (static function () use ($cursorFor): ?int {
        $cursor = $cursorFor();
        $cursor->drainSummaries('|');
        $cursor->rewind();

        return $cursor->peekNextRow()['rowid'] ?? null;
    })(), 3],
    'cursor without filter treats current as contributing' => [static fn (): mixed => (new SQLiteVdbeWindowAggregateCursor([['ord' => 1, 'v' => 3]], 'v', [], ['ord']))->currentFilterPassed(), true],
    'cursor without filter exposes null filter value' => [static fn (): mixed => (new SQLiteVdbeWindowAggregateCursor([['ord' => 1, 'v' => 3]], 'v', [], ['ord']))->currentFilterValue(), null],
    'cursor without filter sum includes current row' => [static fn (): mixed => (new SQLiteVdbeWindowAggregateCursor([['ord' => 1, 'v' => 3]], 'v', [], ['ord']))->sum(), 3],
    'filter blob value is rejected before cursor scan' => [static fn (): mixed => (static function (): string {
        try {
            new SQLiteVdbeWindowAggregateCursor([['ord' => 1, 'v' => 3, 'ok' => new SQLiteBlobValue('1')]], 'v', [], ['ord'], 'ok');
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    })(), 'SQLite VDBE window aggregate FILTER values must be scalar or NULL'],
    'filter array value is rejected before cursor scan' => [static fn (): mixed => (static function (): string {
        try {
            new SQLiteVdbeWindowAggregateCursor([['ord' => 1, 'v' => 3, 'ok' => []]], 'v', [], ['ord'], 'ok');
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    })(), 'SQLite VDBE window aggregate FILTER values must be scalar or NULL'],
    'current filter at eof throws like current row access' => [static fn (): mixed => (static function () use ($cursorFor): string {
        try {
            $cursor = $cursorFor();
            $cursor->drainSummaries('|');
            $cursor->currentFilterPassed();
        } catch (OutOfBoundsException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    })(), 'SQLite VDBE window aggregate cursor is at EOF'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['vdbe window filter sorter null current next38 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
