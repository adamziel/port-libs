<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$tests = [];

$rows = [
    ['rowid' => 1, 'site' => 1, 'autoload' => 'yes', 'name' => 'siteurl', 'bytes' => 10, 'ok' => 1],
    ['rowid' => 2, 'site' => 1, 'autoload' => 'yes', 'name' => 'home', 'bytes' => 10, 'ok' => '1'],
    ['rowid' => 3, 'site' => 1, 'autoload' => 'no', 'name' => 'cron_lock', 'bytes' => 12, 'ok' => 0],
    ['rowid' => 4, 'site' => 1, 'autoload' => 'no', 'name' => 'plugin_rules', 'bytes' => 16, 'ok' => 1],
    ['rowid' => 5, 'site' => 1, 'autoload' => 'no', 'name' => 'theme_mods', 'bytes' => 16, 'ok' => null],
    ['rowid' => 6, 'site' => 1, 'autoload' => 'yes', 'name' => 'transient_feed', 'bytes' => 21, 'ok' => '2'],
    ['rowid' => 7, 'site' => 2, 'autoload' => 'yes', 'name' => 'network_home', 'bytes' => 8, 'ok' => 1],
    ['rowid' => 8, 'site' => 2, 'autoload' => 'no', 'name' => 'network_cache', 'bytes' => 8, 'ok' => 0],
    ['rowid' => 9, 'site' => 2, 'autoload' => 'no', 'name' => 'network_plugins', 'bytes' => 14, 'ok' => 1],
];

$cursorFor = static function (int $preceding = 0, int $following = 4, array $sourceRows = null, array $descending = []): SQLiteVdbeWindowAggregateCursor {
    global $rows;

    return new SQLiteVdbeWindowAggregateCursor(
        $sourceRows ?? $rows,
        'bytes',
        ['site'],
        ['bytes'],
        'ok',
        $preceding,
        $following,
        [],
        [],
        ['NUMERIC'],
        [],
        $descending,
        [],
        'RANGE'
    );
};

$at = static function (SQLiteVdbeWindowAggregateCursor $cursor, int $position): SQLiteVdbeWindowAggregateCursor {
    for ($i = 0; $i < $position; $i++) {
        $cursor->next();
    }

    return $cursor;
};

$frameIds = static fn (SQLiteVdbeWindowAggregateCursor $cursor): array => array_column($cursor->currentFrameRows(false), 'rowid');
$filteredIds = static fn (SQLiteVdbeWindowAggregateCursor $cursor): array => array_column($cursor->currentFrameRows(true), 'rowid');
$names = static fn (SQLiteVdbeWindowAggregateCursor $cursor): array => array_column($cursor->currentFrameRows(false), 'name');

$cases = [
    'range starts on first peer by numeric key' => [static fn (): mixed => $cursorFor()->currentRow()['rowid'], 1],
    'range first peer group includes equal bytes' => [static fn (): mixed => $frameIds($cursorFor()), [1, 2, 3]],
    'range first peer group filter keeps truthy peer only' => [static fn (): mixed => $filteredIds($cursorFor()), [1, 2]],
    'range first peer count all includes false filter rows' => [static fn (): mixed => $cursorFor()->countAll(), 3],
    'range first peer count value includes non null payloads' => [static fn (): mixed => $cursorFor()->countValue(), 2],
    'range first peer sum applies filter' => [static fn (): mixed => $cursorFor()->sum(), 20],
    'range first peer total applies filter' => [static fn (): mixed => $cursorFor()->total(), 20.0],
    'range first peer avg applies filter' => [static fn (): mixed => $cursorFor()->avg(), 10.0],
    'range first peer concat applies filter' => [static fn (): mixed => $cursorFor()->groupConcat('|'), '10|10'],
    'range advances to non peer following band' => [static fn (): mixed => $frameIds($at($cursorFor(), 2)), [3, 4, 5]],
    'range site partition prevents frame crossing' => [static fn (): mixed => $frameIds($at($cursorFor(), 2)), [3, 4, 5]],
    'range second partition final sum' => [static fn (): mixed => $at($cursorFor(), 2)->sum(), 16],
    'range switches partition after site two rows' => [static fn (): mixed => $at($cursorFor(), 3)->currentPartitionKey(), [1]],
    'range site one first peer includes following distance row' => [static fn (): mixed => $frameIds($at($cursorFor(), 3)), [4, 5]],
    'range site one first peer names preserve sorted row order' => [static fn (): mixed => $names($at($cursorFor(), 3)), ['plugin_rules', 'theme_mods']],
    'range site one first peer filtered ids skip false cron' => [static fn (): mixed => $filteredIds($at($cursorFor(), 3)), [4]],
    'range site one first peer count all sees three rows' => [static fn (): mixed => $at($cursorFor(), 3)->countAll(), 2],
    'range site one first peer count value sees two filtered rows' => [static fn (): mixed => $at($cursorFor(), 3)->countValue(), 1],
    'range site one first peer sum skips false cron' => [static fn (): mixed => $at($cursorFor(), 3)->sum(), 16],
    'range site one first peer avg skips false cron' => [static fn (): mixed => $at($cursorFor(), 3)->avg(), 16.0],
    'range site one first peer min uses filtered values' => [static fn (): mixed => $at($cursorFor(), 3)->min(), 16],
    'range site one first peer max uses filtered values' => [static fn (): mixed => $at($cursorFor(), 3)->max(), 16],
    'range site one first peer concat skips false cron' => [static fn (): mixed => $at($cursorFor(), 3)->groupConcat(','), '16'],
    'range peer second row has same distance frame' => [static fn (): mixed => $frameIds($at($cursorFor(), 4)), [4, 5]],
    'range peer second row summary starts at peer group start' => [static fn (): mixed => $at($cursorFor(), 1)->currentSummary()['frameStart'], 0],
    'range peer second row summary ends at distance row' => [static fn (): mixed => $at($cursorFor(), 1)->currentSummary()['frameEnd'], 2],
    'range peer second row summary counts frame rows' => [static fn (): mixed => $at($cursorFor(), 1)->currentSummary()['frameRows'], 3],
    'range middle row reaches next peer group' => [static fn (): mixed => $frameIds($at($cursorFor(), 5)), [6]],
    'range middle row filtered ids keep next truthy peer' => [static fn (): mixed => $filteredIds($at($cursorFor(), 5)), [6]],
    'range middle row sum uses next peer truthy row' => [static fn (): mixed => $at($cursorFor(), 5)->sum(), 21],
    'range middle row count all includes false current row' => [static fn (): mixed => $at($cursorFor(), 5)->countAll(), 1],
    'range middle row count value counts filtered non null only' => [static fn (): mixed => $at($cursorFor(), 5)->countValue(), 1],
    'range duplicate next peer first row keeps duplicate peer group' => [static fn (): mixed => $frameIds($at($cursorFor(), 6)), [7, 8]],
    'range duplicate next peer second row keeps duplicate peer group' => [static fn (): mixed => $frameIds($at($cursorFor(), 7)), [7, 8]],
    'range duplicate next peer filtered skips null filter row' => [static fn (): mixed => $filteredIds($at($cursorFor(), 7)), [7]],
    'range duplicate next peer concat keeps only truthy payload' => [static fn (): mixed => $at($cursorFor(), 7)->groupConcat('|'), '8'],
    'range final row clamps at partition end' => [static fn (): mixed => $frameIds($at($cursorFor(), 8)), [9]],
    'range final row string numeric filter is truthy' => [static fn (): mixed => $filteredIds($at($cursorFor(), 8)), [9]],
    'range final row summary is single row' => [static fn (): mixed => $at($cursorFor(), 8)->currentSummary()['frameRows'], 1],
    'range preceding and following expands from current peer' => [static fn (): mixed => $frameIds($at($cursorFor(2, 4), 2)), [1, 2, 3, 4, 5]],
    'range preceding and following filtered ids' => [static fn (): mixed => $filteredIds($at($cursorFor(2, 4), 2)), [1, 2, 4]],
    'range preceding and following sum' => [static fn (): mixed => $at($cursorFor(2, 4), 2)->sum(), 36],
    'range preceding zero following zero keeps peers only' => [static fn (): mixed => $frameIds($cursorFor(0, 0)), [1, 2]],
    'range preceding zero following zero peer sum' => [static fn (): mixed => $cursorFor(0, 0)->sum(), 20],
    'range with following one excludes distance two row' => [static fn (): mixed => $frameIds($cursorFor(0, 1)), [1, 2]],
    'range with following two includes distance two row' => [static fn (): mixed => $frameIds($cursorFor(0, 2)), [1, 2, 3]],
    'range with following five includes next peer group' => [static fn (): mixed => $frameIds($at($cursorFor(0, 5), 2)), [3, 4, 5]],
    'range descending current to following walks lower numeric keys' => [static fn (): mixed => $frameIds($at($cursorFor(0, 4, null, [true]), 1)), [4, 5, 3]],
    'range descending first row remains single high key' => [static fn (): mixed => $frameIds($cursorFor(0, 4, null, [true])), [6]],
    'range descending peer sum includes lower boundary' => [static fn (): mixed => $at($cursorFor(0, 4, null, [true]), 1)->sum(), 16],
    'range drain summary reports frame totals in cursor order' => [static fn (): mixed => array_column($cursorFor()->drainSummaries('|'), 'total'), [20.0, 20.0, 16.0, 16.0, 16.0, 21.0, 8.0, 8.0, 14.0]],
    'range drain summary reports group concat in cursor order' => [static fn (): mixed => array_column($cursorFor()->drainSummaries('|'), 'groupConcat'), ['10|10', '10|10', '16', '16', '16', '21', '8', '8', '14']],
    'range rewind after drain restores first peer row' => [static fn (): mixed => (static function () use ($cursorFor): int {
        $cursor = $cursorFor();
        $cursor->drainSummaries();
        $cursor->rewind();

        return $cursor->currentRow()['rowid'];
    })(), 1],
    'range nonnumeric order key errors when frame is read' => [static fn (): mixed => (static function () use ($cursorFor): string {
        try {
            $cursorFor(0, 1, [['site' => 1, 'bytes' => '10', 'ok' => 1]])->currentFrameRows();
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    })(), 'SQLite VDBE window aggregate RANGE frame requires numeric ORDER BY values'],
    'range rejects multiple order columns' => [static fn (): mixed => (static function (): string {
        try {
            new SQLiteVdbeWindowAggregateCursor([['site' => 1, 'bytes' => 1, 'name' => 'a']], 'bytes', ['site'], ['bytes', 'name'], null, 0, 1, [], [], [], [], [], [], 'RANGE');
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    })(), 'SQLite VDBE window aggregate RANGE frame requires one ORDER BY column'],
    'range rejects unsupported frame unit' => [static fn (): mixed => (static function (): string {
        try {
            new SQLiteVdbeWindowAggregateCursor([['site' => 1, 'bytes' => 1]], 'bytes', ['site'], ['bytes'], null, 0, 1, [], [], [], [], [], [], 'BOUNDS');
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    })(), 'SQLite VDBE window aggregate frame unit is not supported'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['upstream corpus vdbe window peer range current next30 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
