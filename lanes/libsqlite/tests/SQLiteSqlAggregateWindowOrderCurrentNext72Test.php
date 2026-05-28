<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$rows = [
    ['rowid' => 1, 'site' => 1, 'window_key' => 10, 'name' => 'siteurl', 'priority' => 30, 'label' => 'url', 'bytes' => 24, 'ok' => 1],
    ['rowid' => 2, 'site' => 1, 'window_key' => 20, 'name' => 'home', 'priority' => 10, 'label' => 'home', 'bytes' => 18, 'ok' => 1],
    ['rowid' => 3, 'site' => 1, 'window_key' => 30, 'name' => 'BlogName', 'priority' => 20, 'label' => 'title', 'bytes' => 9, 'ok' => 0],
    ['rowid' => 4, 'site' => 1, 'window_key' => 40, 'name' => 'plugin_cache', 'priority' => 20, 'label' => 'cache', 'bytes' => 12, 'ok' => '1'],
    ['rowid' => 5, 'site' => 1, 'window_key' => 50, 'name' => 'theme_mods', 'priority' => null, 'label' => 'theme', 'bytes' => null, 'ok' => 1],
    ['rowid' => 6, 'site' => 2, 'window_key' => 15, 'name' => 'network_home', 'priority' => 40, 'label' => 'network', 'bytes' => 40, 'ok' => 1],
    ['rowid' => 7, 'site' => 2, 'window_key' => 25, 'name' => 'network_cache', 'priority' => 15, 'label' => 'cache', 'bytes' => 16, 'ok' => 1],
    ['rowid' => 8, 'site' => 2, 'window_key' => 35, 'name' => 'network_theme', 'priority' => 15, 'label' => 'theme', 'bytes' => new SQLiteBlobValue('B'), 'ok' => null],
    ['rowid' => 9, 'site' => 2, 'window_key' => 45, 'name' => 'network_cron', 'priority' => 60, 'label' => 'cron', 'bytes' => 6, 'ok' => '1'],
];

$cursorFor = static fn (): SQLiteVdbeWindowAggregateCursor => new SQLiteVdbeWindowAggregateCursor(
    $rows,
    'label',
    ['site'],
    ['window_key'],
    'ok',
    2,
    1,
    ['INTEGER'],
    [],
    ['NUMERIC']
);

$summaryAt = static function (int $position, array $orderColumns = ['priority', 'name'], mixed $separator = '|', array|string $affinities = ['NUMERIC', 'TEXT'], array $collations = ['BINARY', 'NOCASE'], array $descending = [], array $nulls = ['LAST', null]) use ($cursorFor): array {
    $cursor = $cursorFor();
    for ($i = 0; $i < $position; $i++) {
        $cursor->next();
    }

    return $cursor->currentNextOrderedAggregateSummary($orderColumns, 'rowid', $separator, $affinities, $collations, $descending, $nulls);
};

$cases = [
    'first current frame follows window order before aggregate order' => [static fn (): mixed => $summaryAt(0)['current']['frameRowids'], [1, 2]],
    'first current aggregate order uses priority not window order' => [static fn (): mixed => $summaryAt(0)['current']['orderedFrameRowids'], [2, 1]],
    'first current group concat follows aggregate order' => [static fn (): mixed => $summaryAt(0)['current']['groupConcat'], 'home|url'],
    'first next frame includes filtered next peer after current' => [static fn (): mixed => $summaryAt(0)['next']['frameRowids'], [1, 2]],
    'first next aggregate order sorts filtered frame' => [static fn (): mixed => $summaryAt(0)['next']['orderedFrameRowids'], [2, 1]],
    'first next group concat skips false filter row' => [static fn (): mixed => $summaryAt(0)['next']['groupConcat'], 'home|url'],
    'middle current frame skips false row but keeps frame order evidence' => [static fn (): mixed => $summaryAt(2)['current']['frameRowids'], [1, 2, 4]],
    'middle current aggregate order keeps filtered rows sorted' => [static fn (): mixed => $summaryAt(2)['current']['orderedFrameRowids'], [2, 4, 1]],
    'middle current ordered values expose labels' => [static fn (): mixed => $summaryAt(2)['current']['orderedValues'], ['home', 'cache', 'url']],
    'middle current count value counts ordered labels' => [static fn (): mixed => $summaryAt(2)['current']['countValue'], 3],
    'middle current min follows aggregate value comparison' => [static fn (): mixed => $summaryAt(2)['current']['min'], 'cache'],
    'middle current max follows aggregate value comparison' => [static fn (): mixed => $summaryAt(2)['current']['max'], 'url'],
    'theme current null priority sorts last with nulls last' => [static fn (): mixed => $summaryAt(4)['current']['orderedFrameRowids'], [4, 5]],
    'theme current group concat includes null value label at end' => [static fn (): mixed => $summaryAt(4)['current']['groupConcat'], 'cache|theme'],
    'theme current next crosses partition without merging frames' => [static fn (): mixed => $summaryAt(4)['next']['frameRowids'], [6, 7]],
    'theme current next partition ordered rowids reset' => [static fn (): mixed => $summaryAt(4)['next']['orderedFrameRowids'], [7, 6]],
    'second partition first current aggregate order uses priority' => [static fn (): mixed => $summaryAt(5)['current']['orderedFrameRowids'], [7, 6]],
    'second partition first current group concat is network cache first' => [static fn (): mixed => $summaryAt(5)['current']['groupConcat'], 'cache|network'],
    'second partition next null filter excludes blob label row' => [static fn (): mixed => $summaryAt(6)['next']['frameRowids'], [6, 7, 9]],
    'second partition next aggregate order ignores filtered blob row' => [static fn (): mixed => $summaryAt(6)['next']['orderedFrameRowids'], [7, 6, 9]],
    'second partition next group concat omits null filter row' => [static fn (): mixed => $summaryAt(6)['next']['groupConcat'], 'cache|network|cron'],
    'last current has no next peek' => [static fn (): mixed => $summaryAt(8)['next'], null],
    'last current frame retains partition suffix' => [static fn (): mixed => $summaryAt(8)['current']['frameRowids'], [7, 9]],
    'last current aggregate order sorts suffix' => [static fn (): mixed => $summaryAt(8)['current']['orderedFrameRowids'], [7, 9]],
    'last current group concat returns suffix labels' => [static fn (): mixed => $summaryAt(8)['current']['groupConcat'], 'cache|cron'],
    'current next summary does not advance cursor' => [static fn (): mixed => (static function () use ($cursorFor): array {
        $cursor = $cursorFor();
        $cursor->next();
        $before = $cursor->currentRow()['rowid'];
        $pair = $cursor->currentNextOrderedAggregateSummary(['priority', 'name']);

        return [$before, $cursor->currentRow()['rowid'], $pair['advanced']];
    })(), [2, 2, false]],
    'aggregate order column metadata is preserved' => [static fn (): mixed => $summaryAt(1)['aggregateOrderColumns'], ['priority', 'name']],
    'descending aggregate order reverses priorities' => [static fn (): mixed => $summaryAt(2, ['priority'], '|', ['NUMERIC'], [], [true], ['LAST'])['current']['orderedFrameRowids'], [1, 4, 2]],
    'nulls first aggregate order exposes null priority first' => [static fn (): mixed => $summaryAt(4, ['priority'], '|', ['NUMERIC'], [], [], ['FIRST'])['current']['orderedFrameRowids'], [5, 4]],
    'nocase aggregate name tiebreaker orders BlogName before cache when unfiltered' => [static fn (): mixed => (static function () use ($rows): array {
        $cursor = new SQLiteVdbeWindowAggregateCursor($rows, 'label', ['site'], ['window_key'], null, 2, 1, ['INTEGER'], [], ['NUMERIC']);
        $cursor->next();
        $cursor->next();

        return $cursor->currentNextOrderedAggregateSummary(['priority', 'name'], 'rowid', '|', ['NUMERIC', 'TEXT'], ['BINARY', 'NOCASE'], [], ['LAST', null])['current']['orderedFrameRowids'];
    })(), [2, 3, 4, 1]],
    'unfiltered aggregate order includes false filter row value' => [static fn (): mixed => (static function () use ($rows): ?string {
        $cursor = new SQLiteVdbeWindowAggregateCursor($rows, 'label', ['site'], ['window_key'], null, 2, 1, ['INTEGER'], [], ['NUMERIC']);
        $cursor->next();
        $cursor->next();

        return $cursor->currentNextOrderedAggregateSummary(['priority', 'name'], 'rowid', '|', ['NUMERIC', 'TEXT'], ['BINARY', 'NOCASE'], [], ['LAST', null])['current']['groupConcat'];
    })(), 'home|title|cache|url'],
    'blob value can participate in ordered numeric aggregate total as zero' => [static fn (): mixed => (static function () use ($rows): float {
        $cursor = new SQLiteVdbeWindowAggregateCursor($rows, 'bytes', ['site'], ['window_key'], null, 2, 1, ['INTEGER'], [], ['NUMERIC']);
        for ($i = 0; $i < 7; $i++) {
            $cursor->next();
        }

        return $cursor->currentNextOrderedAggregateSummary(['priority'], 'rowid', '|', ['NUMERIC'])['current']['total'];
    })(), 62.0],
    'blob value group concat casts bytes in aggregate order' => [static fn (): mixed => (static function () use ($rows): ?string {
        $cursor = new SQLiteVdbeWindowAggregateCursor($rows, 'bytes', ['site'], ['window_key'], null, 2, 1, ['INTEGER'], [], ['NUMERIC']);
        for ($i = 0; $i < 7; $i++) {
            $cursor->next();
        }

        return $cursor->currentNextOrderedAggregateSummary(['priority'], 'rowid', ':', ['NUMERIC'])['current']['groupConcat'];
    })(), '16:B:40:6'],
    'numeric values ordered by label text produce stable sum' => [static fn (): mixed => (static function () use ($rows): int|float|null {
        $cursor = new SQLiteVdbeWindowAggregateCursor($rows, 'bytes', ['site'], ['window_key'], 'ok', 2, 1, ['INTEGER'], [], ['NUMERIC']);
        $cursor->next();

        return $cursor->currentNextOrderedAggregateSummary(['label'], 'rowid', '|', ['TEXT'])['current']['sum'];
    })(), 42],
    'numeric values ordered by label text produce stable average' => [static fn (): mixed => (static function () use ($rows): ?float {
        $cursor = new SQLiteVdbeWindowAggregateCursor($rows, 'bytes', ['site'], ['window_key'], 'ok', 2, 1, ['INTEGER'], [], ['NUMERIC']);
        $cursor->next();

        return $cursor->currentNextOrderedAggregateSummary(['label'], 'rowid', '|', ['TEXT'])['current']['avg'];
    })(), 21.0],
    'empty filtered ordered frame returns null group concat' => [static fn (): mixed => (static function () use ($rows): ?string {
        $cursor = new SQLiteVdbeWindowAggregateCursor($rows, 'label', ['site'], ['window_key'], 'ok', 0, 0, ['INTEGER'], [], ['NUMERIC']);
        $cursor->next();
        $cursor->next();

        return $cursor->currentNextOrderedAggregateSummary(['priority'], 'rowid', '|', ['NUMERIC'])['current']['groupConcat'];
    })(), null],
    'empty filtered ordered frame has zero total' => [static fn (): mixed => (static function () use ($rows): float {
        $cursor = new SQLiteVdbeWindowAggregateCursor($rows, 'bytes', ['site'], ['window_key'], 'ok', 0, 0, ['INTEGER'], [], ['NUMERIC']);
        $cursor->next();
        $cursor->next();

        return $cursor->currentNextOrderedAggregateSummary(['priority'], 'rowid', '|', ['NUMERIC'])['current']['total'];
    })(), 0.0],
    'separator null keeps ordered group concat null' => [static fn (): mixed => $summaryAt(2, ['priority'], null, ['NUMERIC'])['current']['groupConcat'], null],
    'blob separator is cast for ordered group concat' => [static fn (): mixed => $summaryAt(2, ['priority'], new SQLiteBlobValue('::'), ['NUMERIC'])['current']['groupConcat'], 'home::cache::url'],
    'missing aggregate order column throws' => [static fn (): mixed => (static function () use ($summaryAt): string {
        try {
            $summaryAt(0, ['missing']);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    })(), 'SQLite VDBE window aggregate row is missing aggregate order column missing'],
    'empty aggregate order column list throws' => [static fn (): mixed => (static function () use ($summaryAt): string {
        try {
            $summaryAt(0, []);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    })(), 'SQLite VDBE window aggregate aggregate order columns must be non-empty'],
    'blank aggregate order column throws' => [static fn (): mixed => (static function () use ($summaryAt): string {
        try {
            $summaryAt(0, ['']);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    })(), 'SQLite VDBE window aggregate aggregate order columns must be non-empty strings'],
    'bad aggregate order scalar throws' => [static fn (): mixed => (static function (): string {
        try {
            $cursor = new SQLiteVdbeWindowAggregateCursor([
                ['rowid' => 1, 'g' => 1, 'k' => 1, 'v' => 'x', 'bad' => ['array']],
            ], 'v', ['g'], ['k']);
            $cursor->currentNextOrderedAggregateSummary(['bad']);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    })(), 'SQLite VDBE window aggregate sort values must be scalar, BLOB, or NULL'],
    'current row remains readable after missing aggregate order exception' => [static fn (): mixed => (static function () use ($cursorFor): int {
        $cursor = $cursorFor();
        try {
            $cursor->currentNextOrderedAggregateSummary(['missing']);
        } catch (InvalidArgumentException) {
        }

        return $cursor->currentRow()['rowid'];
    })(), 1],
    'current row remains readable after ordered aggregate peek' => [static fn (): mixed => (static function () use ($cursorFor): int {
        $cursor = $cursorFor();
        $cursor->next();
        $cursor->currentNextOrderedAggregateSummary(['priority']);

        return $cursor->currentRow()['rowid'];
    })(), 2],
    'manual next after ordered peek reaches following window row' => [static fn (): mixed => (static function () use ($cursorFor): int {
        $cursor = $cursorFor();
        $cursor->currentNextOrderedAggregateSummary(['priority']);
        $cursor->next();

        return $cursor->currentRow()['rowid'];
    })(), 2],
    'ordered values at second partition edge do not include prior partition' => [static fn (): mixed => $summaryAt(5)['current']['orderedValues'], ['cache', 'network']],
    'ordered frame rowids at partition edge do not include prior partition' => [static fn (): mixed => $summaryAt(5)['current']['frameRowids'], [6, 7]],
    'next ordered values after partition edge include only second site' => [static fn (): mixed => $summaryAt(5)['next']['orderedValues'], ['cache', 'network']],
    'last ordered average skips null and blob labels when bytes cursor filters' => [static fn (): mixed => (static function () use ($rows): ?float {
        $cursor = new SQLiteVdbeWindowAggregateCursor($rows, 'bytes', ['site'], ['window_key'], 'ok', 2, 1, ['INTEGER'], [], ['NUMERIC']);
        for ($i = 0; $i < 8; $i++) {
            $cursor->next();
        }

        return $cursor->currentNextOrderedAggregateSummary(['priority'], 'rowid', '|', ['NUMERIC'])['current']['avg'];
    })(), 11.0],
    'last ordered sum skips null and blob labels when bytes cursor filters' => [static fn (): mixed => (static function () use ($rows): int|float|null {
        $cursor = new SQLiteVdbeWindowAggregateCursor($rows, 'bytes', ['site'], ['window_key'], 'ok', 2, 1, ['INTEGER'], [], ['NUMERIC']);
        for ($i = 0; $i < 8; $i++) {
            $cursor->next();
        }

        return $cursor->currentNextOrderedAggregateSummary(['priority'], 'rowid', '|', ['NUMERIC'])['current']['sum'];
    })(), 22],
    'last ordered count skips null byte values' => [static fn (): mixed => (static function () use ($rows): int {
        $cursor = new SQLiteVdbeWindowAggregateCursor($rows, 'bytes', ['site'], ['window_key'], 'ok', 2, 1, ['INTEGER'], [], ['NUMERIC']);
        for ($i = 0; $i < 8; $i++) {
            $cursor->next();
        }

        return $cursor->currentNextOrderedAggregateSummary(['priority'], 'rowid', '|', ['NUMERIC'])['current']['countValue'];
    })(), 2],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['sql aggregate window order current next72 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
