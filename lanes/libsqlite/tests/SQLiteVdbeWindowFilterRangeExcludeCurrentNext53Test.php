<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$rows = [
    ['rowid' => 1, 'site' => 1, 'bucket' => 1.0, 'name' => 'siteurl', 'bytes' => 10, 'ok' => 1],
    ['rowid' => 2, 'site' => 1, 'bucket' => 1.0, 'name' => 'home', 'bytes' => 20, 'ok' => 0],
    ['rowid' => 3, 'site' => 1, 'bucket' => 1.2, 'name' => 'blogname', 'bytes' => 30, 'ok' => '1'],
    ['rowid' => 4, 'site' => 1, 'bucket' => 1.4, 'name' => 'rewrite_rules', 'bytes' => 40, 'ok' => '0.0'],
    ['rowid' => 5, 'site' => 1, 'bucket' => 1.6, 'name' => 'plugin_settings', 'bytes' => null, 'ok' => '2'],
    ['rowid' => 6, 'site' => 1, 'bucket' => 2.0, 'name' => '_transient_feed', 'bytes' => 60, 'ok' => true],
    ['rowid' => 7, 'site' => 2, 'bucket' => 1.0, 'name' => 'network_siteurl', 'bytes' => 70, 'ok' => 1],
    ['rowid' => 8, 'site' => 2, 'bucket' => 1.3, 'name' => 'network_home', 'bytes' => 80, 'ok' => null],
    ['rowid' => 9, 'site' => 2, 'bucket' => 1.3, 'name' => 'network_plugin', 'bytes' => 90, 'ok' => '0x'],
    ['rowid' => 10, 'site' => 2, 'bucket' => 1.7, 'name' => 'network_cache', 'bytes' => 100, 'ok' => '3'],
];

$cursorFor = static fn (float|int $following = 0.4, ?string $filterColumn = 'ok', string $exclude = 'CURRENT ROW'): SQLiteVdbeWindowAggregateCursor => new SQLiteVdbeWindowAggregateCursor(
    $rows,
    'bytes',
    ['site'],
    ['bucket'],
    $filterColumn,
    0.0,
    $following,
    'D',
    [],
    'D',
    [],
    [],
    [],
    'RANGE',
    $exclude,
);

$at = static function (SQLiteVdbeWindowAggregateCursor $cursor, int $zeroBased): SQLiteVdbeWindowAggregateCursor {
    for ($i = 0; $i < $zeroBased; $i++) {
        $cursor->next();
    }

    return $cursor;
};

$tests = [];

$cases = [
    'row1 frame excludes current but keeps peer and following band' => [static fn () => array_column($cursorFor()->currentFrameRows(false), 'rowid'), [2, 3, 4]],
    'row1 filter drops false peer and false boundary' => [static fn () => array_column($cursorFor()->currentFrameRows(true), 'rowid'), [3]],
    'row1 count all ignores filter but honors exclude current' => [static fn () => $cursorFor()->countAll(), 3],
    'row1 count filtered all models FILTER count star' => [static fn () => $cursorFor()->countFilteredAll(), 1],
    'row1 count value skips filtered false and nulls' => [static fn () => $cursorFor()->countValue(), 1],
    'row1 sum is following truthy blogname only' => [static fn () => $cursorFor()->sum(), 30],
    'row1 total is floating filtered step total' => [static fn () => $cursorFor()->total(), 30.0],
    'row1 avg is filtered step average' => [static fn () => $cursorFor()->avg(), 30.0],
    'row1 concat keeps filtered frame order' => [static fn () => $cursorFor()->groupConcat('|'), '30'],
    'row1 first value after filter is following row' => [static fn () => $cursorFor()->firstValue(true), 30],
    'row1 last value after filter is following row' => [static fn () => $cursorFor()->lastValue(true), 30],
    'row1 nth value missing returns null' => [static fn () => $cursorFor()->nthValue(2, true), null],
    'row2 peer current exclusion drops only row2' => [static fn () => array_column($at($cursorFor(), 1)->currentFrameRows(false), 'rowid'), [1, 3, 4]],
    'row2 filtered frame keeps current peer and following truthy row' => [static fn () => array_column($at($cursorFor(), 1)->currentFrameRows(true), 'rowid'), [1, 3]],
    'row2 filtered count star counts peer plus following' => [static fn () => $at($cursorFor(), 1)->countFilteredAll(), 2],
    'row2 filtered sum includes peer siteurl and blogname' => [static fn () => $at($cursorFor(), 1)->sum(), 40],
    'row2 filtered concat includes peer siteurl and blogname' => [static fn () => $at($cursorFor(), 1)->groupConcat('|'), '10|30'],
    'row3 current exclusion reaches next truthy null payload' => [static fn () => array_column($at($cursorFor(), 2)->currentFrameRows(false), 'rowid'), [4, 5]],
    'row3 filtered count star includes truthy null payload row' => [static fn () => $at($cursorFor(), 2)->countFilteredAll(), 1],
    'row3 count value skips filtered null payload' => [static fn () => $at($cursorFor(), 2)->countValue(), 0],
    'row3 sum is null after filtered null payload' => [static fn () => $at($cursorFor(), 2)->sum(), null],
    'row3 concat is null after filtered null payload' => [static fn () => $at($cursorFor(), 2)->groupConcat('|'), null],
    'row4 current exclusion keeps null payload and row6 boundary' => [static fn () => array_column($at($cursorFor(0.6), 3)->currentFrameRows(false), 'rowid'), [5, 6]],
    'row4 filtered all includes null and numeric truth rows' => [static fn () => $at($cursorFor(0.6), 3)->countFilteredAll(), 2],
    'row4 filtered value count skips null but keeps row6' => [static fn () => $at($cursorFor(0.6), 3)->countValue(), 1],
    'row4 filtered sum keeps row6 after null skip' => [static fn () => $at($cursorFor(0.6), 3)->sum(), 60],
    'row5 current exclusion can keep only following row6' => [static fn () => array_column($at($cursorFor(0.4), 4)->currentFrameRows(false), 'rowid'), [6]],
    'row5 filtered frame keeps row6' => [static fn () => array_column($at($cursorFor(0.4), 4)->currentFrameRows(true), 'rowid'), [6]],
    'row5 filtered sum keeps row6' => [static fn () => $at($cursorFor(0.4), 4)->sum(), 60],
    'row6 tail current exclusion empties range frame' => [static fn () => array_column($at($cursorFor(0.4), 5)->currentFrameRows(false), 'rowid'), []],
    'row6 filtered count star is zero' => [static fn () => $at($cursorFor(0.4), 5)->countFilteredAll(), 0],
    'row6 sum is null for empty filtered frame' => [static fn () => $at($cursorFor(0.4), 5)->sum(), null],
    'row7 partition does not cross from site two to site one' => [static fn () => array_column($at($cursorFor(), 6)->currentFrameRows(false), 'rowid'), [8, 9]],
    'row7 filtered frame drops null and zero string peers' => [static fn () => array_column($at($cursorFor(), 6)->currentFrameRows(true), 'rowid'), []],
    'row7 filtered count star is zero after filter' => [static fn () => $at($cursorFor(), 6)->countFilteredAll(), 0],
    'row8 duplicate peer excludes current only not peer' => [static fn () => array_column($at($cursorFor(), 7)->currentFrameRows(false), 'rowid'), [9, 10]],
    'row8 filter keeps following cache only' => [static fn () => array_column($at($cursorFor(), 7)->currentFrameRows(true), 'rowid'), [10]],
    'row8 filtered sum keeps network cache only' => [static fn () => $at($cursorFor(), 7)->sum(), 100],
    'row9 duplicate peer excludes row9 but keeps null peer unfiltered' => [static fn () => array_column($at($cursorFor(), 8)->currentFrameRows(false), 'rowid'), [8, 10]],
    'row9 filter keeps row10 only' => [static fn () => array_column($at($cursorFor(), 8)->currentFrameRows(true), 'rowid'), [10]],
    'row9 filtered concat keeps row10 bytes' => [static fn () => $at($cursorFor(), 8)->groupConcat('|'), '100'],
    'row10 partition tail excludes itself to empty' => [static fn () => array_column($at($cursorFor(), 9)->currentFrameRows(false), 'rowid'), []],
    'row10 filtered count star is zero' => [static fn () => $at($cursorFor(), 9)->countFilteredAll(), 0],
    'no filter current exclusion row1 count filtered all equals count all' => [static fn () => $cursorFor(0.4, null)->countFilteredAll(), 3],
    'exclude no others row1 filter includes current and following truthy rows' => [static fn () => array_column($cursorFor(0.4, 'ok', 'NO OTHERS')->currentFrameRows(true), 'rowid'), [1, 3]],
    'exclude group row1 removes both duplicate current peers' => [static fn () => array_column($cursorFor(0.4, 'ok', 'GROUP')->currentFrameRows(false), 'rowid'), [3, 4]],
    'exclude group row1 filtered frame keeps row3 only' => [static fn () => array_column($cursorFor(0.4, 'ok', 'GROUP')->currentFrameRows(true), 'rowid'), [3]],
    'exclude ties row1 keeps current but removes peer row2' => [static fn () => array_column($cursorFor(0.4, 'ok', 'TIES')->currentFrameRows(false), 'rowid'), [1, 3, 4]],
    'exclude ties row1 filtered frame keeps current and row3' => [static fn () => array_column($cursorFor(0.4, 'ok', 'TIES')->currentFrameRows(true), 'rowid'), [1, 3]],
    'summary row1 reports unfiltered frame start after current peer' => [static fn () => $cursorFor()->currentSummary()['frameStart'], 1],
    'summary row1 reports unfiltered frame end at boundary' => [static fn () => $cursorFor()->currentSummary()['frameEnd'], 3],
    'summary row1 reports filtered rows after exclude and filter' => [static fn () => $cursorFor()->currentSummary()['filteredRows'], 1],
    'summary row1 reports frame rows before filter' => [static fn () => $cursorFor()->currentSummary()['frameRows'], 3],
    'drain summaries count all preserves exclude current frames' => [static fn () => array_column($cursorFor()->drainSummaries('|'), 'countAll'), [3, 3, 2, 1, 1, 0, 2, 2, 2, 0]],
    'drain summaries count value applies filter to payload values' => [static fn () => array_column($cursorFor()->drainSummaries('|'), 'countValue'), [1, 2, 0, 0, 1, 0, 0, 1, 1, 0]],
    'drain summaries sums apply filter after exclusion' => [static fn () => array_column($cursorFor()->drainSummaries('|'), 'sum'), [30, 40, null, null, 60, null, null, 100, 100, null]],
    'drain summaries concat applies filter after exclusion' => [static fn () => array_column($cursorFor()->drainSummaries('|'), 'groupConcat'), ['30', '10|30', null, null, '60', null, null, '100', '100', null]],
    'rewind restores first range after drain' => [static function () use ($cursorFor): array {
        $cursor = $cursorFor();
        $cursor->drainSummaries('|');
        $cursor->rewind();
        return array_column($cursor->currentFrameRows(true), 'rowid');
    }, [3]],
    'peek next row remains physical sorted row' => [static fn () => $cursorFor()->peekNextRow()['rowid'], 2],
    'peek next order key reports duplicate peer key' => [static fn () => $cursorFor()->peekNextOrderKey(), [1.0]],
    'current filter passed reports current row not frame row' => [static fn () => $cursorFor()->currentFilterPassed(), true],
    'row2 current filter false while filtered frame still has truthy rows' => [static fn () => $at($cursorFor(), 1)->currentFilterPassed(), false],
    'nth value rejects non positive index' => [static function () use ($cursorFor): string {
        try {
            $cursorFor()->nthValue(0);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    }, 'SQLite VDBE window nth_value() index must be positive'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['vdbe window filter range exclude current next53 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
