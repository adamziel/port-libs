<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$tests = [];

$rows = [
    ['rowid' => 1, 'site' => 1, 'option_name' => 'alpha', 'autoload' => 'yes', 'bytes' => 10, 'ok' => 1],
    ['rowid' => 2, 'site' => 1, 'option_name' => 'alpha', 'autoload' => 'no', 'bytes' => 10, 'ok' => 0],
    ['rowid' => 3, 'site' => 1, 'option_name' => 'beta', 'autoload' => 'yes', 'bytes' => 10, 'ok' => '1'],
    ['rowid' => 4, 'site' => 1, 'option_name' => 'cron', 'autoload' => 'no', 'bytes' => 20, 'ok' => null],
    ['rowid' => 5, 'site' => 1, 'option_name' => 'cron', 'autoload' => 'yes', 'bytes' => 20, 'ok' => true],
    ['rowid' => 6, 'site' => 1, 'option_name' => 'theme', 'autoload' => 'yes', 'bytes' => 30, 'ok' => '0.5'],
    ['rowid' => 7, 'site' => 1, 'option_name' => 'theme', 'autoload' => 'no', 'bytes' => 30, 'ok' => '0'],
    ['rowid' => 8, 'site' => 2, 'option_name' => 'alpha', 'autoload' => 'yes', 'bytes' => 11, 'ok' => 1],
    ['rowid' => 9, 'site' => 2, 'option_name' => 'alpha', 'autoload' => 'no', 'bytes' => 11, 'ok' => 0],
    ['rowid' => 10, 'site' => 2, 'option_name' => 'gamma', 'autoload' => 'yes', 'bytes' => 21, 'ok' => '2'],
    ['rowid' => 11, 'site' => 2, 'option_name' => 'gamma', 'autoload' => 'no', 'bytes' => 21, 'ok' => ''],
    ['rowid' => 12, 'site' => 2, 'option_name' => 'omega', 'autoload' => 'yes', 'bytes' => 31, 'ok' => -1],
];

$cursorFor = static fn (string $exclude = 'CURRENT ROW', ?string $filter = 'ok', int $preceding = 0, int $following = 1): SQLiteVdbeWindowAggregateCursor => new SQLiteVdbeWindowAggregateCursor(
    $rows,
    'bytes',
    ['site'],
    ['bytes', 'option_name'],
    $filter,
    $preceding,
    $following,
    ['INTEGER'],
    [],
    ['NUMERIC', 'TEXT'],
    ['BINARY', 'NOCASE'],
    [false, false],
    [],
    'GROUPS',
    $exclude,
);

$at = static function (SQLiteVdbeWindowAggregateCursor $cursor, int $position): SQLiteVdbeWindowAggregateCursor {
    for ($i = 0; $i < $position; $i++) {
        $cursor->next();
    }

    return $cursor;
};

$currentIdsAt = static fn (int $position, bool $filter = false): array => array_column($at($cursorFor(), $position)->currentNextFrameRows($filter)['current'], 'rowid');
$nextIdsAt = static fn (int $position, bool $filter = false): ?array => ($rows = $at($cursorFor(), $position)->currentNextFrameRows($filter)['next']) === null ? null : array_column($rows, 'rowid');
$summaryAt = static fn (int $position): array => $at($cursorFor(), $position)->currentNextSummary();

$cases = [
    'current frame excludes only row 1 before filter' => [static fn (): mixed => $currentIdsAt(0), [2, 3]],
    'current filtered frame omits false peer row 2' => [static fn (): mixed => $currentIdsAt(0, true), [3]],
    'next frame for peer row 2 excludes row 2 not row 1' => [static fn (): mixed => $nextIdsAt(0), [1, 3]],
    'next filtered frame for peer row 2 keeps rows 1 and 3' => [static fn (): mixed => $nextIdsAt(0, true), [1, 3]],
    'cursor position is unchanged after current next frame peek' => [static fn (): mixed => (static function () use ($cursorFor): array {
        $cursor = $cursorFor();
        $before = $cursor->currentRow()['rowid'];
        $peek = $cursor->currentNextFrameRows(true);

        return [$before, $cursor->currentRow()['rowid'], $peek['advanced']];
    })(), [1, 1, false]],
    'next summary for row 1 reports next position' => [static fn (): mixed => $summaryAt(0)['next']['position'], 1],
    'next summary for row 1 reports next order key' => [static fn (): mixed => $summaryAt(0)['next']['orderKey'], [10, 'alpha']],
    'next summary for row 1 keeps false current filter bit' => [static fn (): mixed => $summaryAt(0)['next']['currentFilterPassed'], false],
    'next summary for row 1 reports filtered rows after excluding next current row' => [static fn (): mixed => $summaryAt(0)['next']['filteredRows'], 2],
    'current summary for row 1 reports filtered rows after excluding current row' => [static fn (): mixed => $summaryAt(0)['current']['filteredRows'], 1],
    'row 3 frame reaches next peer group before filtering' => [static fn (): mixed => $currentIdsAt(2), [4, 5]],
    'row 3 filtered frame keeps truthy rows from next group after excluding current' => [static fn (): mixed => $currentIdsAt(2, true), [5]],
    'row 3 next frame excludes row 4 from cron group' => [static fn (): mixed => $nextIdsAt(2), [5, 6, 7]],
    'row 3 next filtered frame keeps row 5 and row 6' => [static fn (): mixed => $nextIdsAt(2, true), [5, 6]],
    'row 4 false current filter still appears in current summary' => [static fn (): mixed => $summaryAt(3)['current']['currentFilterPassed'], false],
    'row 4 current filtered frame keeps next truthy theme row' => [static fn (): mixed => $currentIdsAt(3, true), [5, 6]],
    'row 5 next summary points at next peer group' => [static fn (): mixed => $summaryAt(4)['next']['orderKey'], [30, 'theme']],
    'row 5 next summary false string zero filter bit' => [static fn (): mixed => $summaryAt(4)['next']['currentFilterPassed'], true],
    'row 6 current frame excludes row 6 and filters away row 7' => [static fn (): mixed => $currentIdsAt(5, true), []],
    'row 6 next frame excludes row 7 and keeps row 6' => [static fn (): mixed => $nextIdsAt(5, true), [6]],
    'row 7 has no next row inside partition but next source is site 2' => [static fn (): mixed => $summaryAt(6)['current']['nextPartitionKey'], [2]],
    'row 7 next summary starts second partition' => [static fn (): mixed => $summaryAt(6)['next']['partitionKey'], [2]],
    'row 7 next filtered frame in second partition keeps row 10' => [static fn (): mixed => $nextIdsAt(6, true), [10]],
    'row 8 current frame excludes row 8 and filters row 9 false' => [static fn (): mixed => $currentIdsAt(7, true), [10]],
    'row 8 next frame keeps row 8 and row 10' => [static fn (): mixed => $nextIdsAt(7, true), [8, 10]],
    'row 9 next summary reports truthy gamma filter' => [static fn (): mixed => $summaryAt(8)['next']['currentFilterPassed'], true],
    'row 10 current filtered frame includes current peer row 10 excluded from false peer row 11 next peek' => [static fn (): mixed => [$currentIdsAt(9, true), $nextIdsAt(9, true)], [[12], [10, 12]]],
    'row 11 empty string filter is false in next summary' => [static fn (): mixed => $summaryAt(9)['next']['currentFilterPassed'], false],
    'row 11 current filtered frame keeps row 10 and row 12' => [static fn (): mixed => $currentIdsAt(10, true), [10, 12]],
    'row 12 current frame excludes final row and has empty filtered frame' => [static fn (): mixed => [$currentIdsAt(11), $currentIdsAt(11, true)], [[], []]],
    'row 12 current next summary has null next' => [static fn (): mixed => $summaryAt(11)['next'], null],
    'row 12 current next frame rows has null next' => [static fn (): mixed => $nextIdsAt(11, true), null],
    'drain summaries remain compatible with existing API' => [static fn (): mixed => array_column($cursorFor()->drainSummaries('|'), 'filteredRows'), [1, 2, 1, 2, 1, 0, 1, 1, 2, 1, 2, 0]],
    'current next summaries can be drained manually without advancing by peek' => [static fn (): mixed => (static function () use ($cursorFor): array {
        $cursor = $cursorFor();
        $actual = [];
        while (!$cursor->eof()) {
            $pair = $cursor->currentNextSummary();
            $actual[] = [$pair['current']['position'], $pair['next']['position'] ?? null, $cursor->currentRow()['rowid']];
            $cursor->next();
        }

        return $actual;
    })(), [[0, 1, 1], [1, 2, 2], [2, 3, 3], [3, 4, 4], [4, 5, 5], [5, 6, 6], [6, 7, 7], [7, 8, 8], [8, 9, 9], [9, 10, 10], [10, 11, 11], [11, null, 12]]],
    'current next frame rows without filter drain by rowid' => [static fn (): mixed => (static function () use ($cursorFor): array {
        $cursor = $cursorFor();
        $actual = [];
        while (!$cursor->eof()) {
            $pair = $cursor->currentNextFrameRows();
            $actual[] = [array_column($pair['current'], 'rowid'), $pair['next'] === null ? null : array_column($pair['next'], 'rowid')];
            $cursor->next();
        }

        return $actual;
    })(), [[[2, 3], [1, 3]], [[1, 3], [4, 5]], [[4, 5], [5, 6, 7]], [[5, 6, 7], [4, 6, 7]], [[4, 6, 7], [7]], [[7], [6]], [[6], [9, 10, 11]], [[9, 10, 11], [8, 10, 11]], [[8, 10, 11], [11, 12]], [[11, 12], [10, 12]], [[10, 12], []], [[], null]]],
    'exclude group current next removes whole peer groups' => [static fn (): mixed => (static function () use ($cursorFor): array {
        $cursor = $cursorFor('GROUP');

        return [array_column($cursor->currentNextFrameRows(true)['current'], 'rowid'), array_column($cursor->currentNextFrameRows(true)['next'], 'rowid')];
    })(), [[3], [3]]],
    'exclude ties current next keeps only current peer identity' => [static fn (): mixed => (static function () use ($cursorFor): array {
        $cursor = $cursorFor('TIES');

        return [array_column($cursor->currentNextFrameRows(true)['current'], 'rowid'), array_column($cursor->currentNextFrameRows(true)['next'], 'rowid')];
    })(), [[1, 3], [3]]],
    'no filter current next returns raw excluded rows' => [static fn (): mixed => (static function () use ($cursorFor): array {
        $cursor = $cursorFor('CURRENT ROW', null);

        return [array_column($cursor->currentNextFrameRows(true)['current'], 'rowid'), array_column($cursor->currentNextFrameRows(true)['next'], 'rowid')];
    })(), [[2, 3], [1, 3]]],
    'preceding group next peek includes previous peer group' => [static fn (): mixed => (static function () use ($cursorFor, $at): array {
        $cursor = $at($cursorFor('CURRENT ROW', 'ok', 1, 1), 3);

        return [array_column($cursor->currentNextFrameRows(true)['current'], 'rowid'), array_column($cursor->currentNextFrameRows(true)['next'], 'rowid')];
    })(), [[3, 5, 6], [3, 6]]],
    'rewind after current next peeks restores first row' => [static fn (): mixed => (static function () use ($cursorFor): int {
        $cursor = $cursorFor();
        $cursor->currentNextSummary();
        $cursor->next();
        $cursor->currentNextFrameRows(true);
        $cursor->rewind();

        return $cursor->currentRow()['rowid'];
    })(), 1],
    'peek next summary at eof throws current access after drain' => [static fn (): mixed => (static function () use ($cursorFor): string {
        try {
            $cursor = $cursorFor();
            $cursor->drainSummaries('|');
            $cursor->peekNextSummary();
        } catch (OutOfBoundsException $exception) {
            return $exception->getMessage();
        }

        return 'no exception';
    })(), 'no exception'],
    'current next summary at eof throws' => [static fn (): mixed => (static function () use ($cursorFor): string {
        try {
            $cursor = $cursorFor();
            $cursor->drainSummaries('|');
            $cursor->currentNextSummary();
        } catch (OutOfBoundsException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    })(), 'SQLite VDBE window aggregate cursor is at EOF'],
    'current next frame rows at eof throws' => [static fn (): mixed => (static function () use ($cursorFor): string {
        try {
            $cursor = $cursorFor();
            $cursor->drainSummaries('|');
            $cursor->currentNextFrameRows();
        } catch (OutOfBoundsException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    })(), 'SQLite VDBE window aggregate cursor is at EOF'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['vdbe window groups filter exclude current next49 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
