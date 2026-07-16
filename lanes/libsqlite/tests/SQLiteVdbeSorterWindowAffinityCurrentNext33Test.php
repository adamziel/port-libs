<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$tests = [];

$rows = [
    ['rowid' => 101, 'site' => '1', 'autoload' => 'YES', 'ord' => '02', 'name' => 'Plugin_B', 'bytes' => 20, 'ok' => 1],
    ['rowid' => 102, 'site' => 1, 'autoload' => 'yes', 'ord' => 2, 'name' => 'plugin_a', 'bytes' => 10, 'ok' => '1'],
    ['rowid' => 103, 'site' => 1, 'autoload' => 'yes', 'ord' => new SQLiteBlobValue('2'), 'name' => 'plugin_c', 'bytes' => 30, 'ok' => 0],
    ['rowid' => 104, 'site' => 1, 'autoload' => 'yes', 'ord' => '10', 'name' => 'plugin_d', 'bytes' => 40, 'ok' => 1],
    ['rowid' => 105, 'site' => '01', 'autoload' => 'YES', 'ord' => '2.0', 'name' => 'plugin_e', 'bytes' => 50, 'ok' => -1],
    ['rowid' => 106, 'site' => 2, 'autoload' => 'no', 'ord' => null, 'name' => 'cache_z', 'bytes' => 5, 'ok' => 1],
    ['rowid' => 107, 'site' => 2, 'autoload' => 'NO', 'ord' => null, 'name' => 'cache_a', 'bytes' => 7, 'ok' => '0'],
    ['rowid' => 108, 'site' => 2, 'autoload' => 'yes', 'ord' => '1', 'name' => 'network_a', 'bytes' => 11, 'ok' => 'yes'],
    ['rowid' => 109, 'site' => 2, 'autoload' => 'YES', 'ord' => 1, 'name' => 'network_b', 'bytes' => null, 'ok' => 1],
    ['rowid' => 110, 'site' => 3, 'autoload' => null, 'ord' => '1', 'name' => 'late', 'bytes' => 99, 'ok' => 1],
];

$cursorFor = static fn (): SQLiteVdbeWindowAggregateCursor => new SQLiteVdbeWindowAggregateCursor(
    $rows,
    'bytes',
    ['site'],
    ['autoload', 'ord'],
    'ok',
    1,
    1,
    'C',
    ['BINARY'],
    'GC',
    ['NOCASE', 'BINARY'],
    [false, false],
    ['LAST', 'LAST']
);

$drain = static fn (): array => $cursorFor()->drainPeerSummaries();

$cases = [
    'drain emits every sorted input row' => static fn (): mixed => count($drain()),
    'sorted rowids preserve stable peer order' => static fn (): mixed => array_column($drain(), 'rowids'),
    'first peer starts at zero' => static fn (): mixed => $drain()[0]['peerStart'],
    'first peer ends after numeric ties' => static fn (): mixed => $drain()[0]['peerEnd'],
    'first peer has four rows' => static fn (): mixed => $drain()[0]['peerRows'],
    'first peer filtered rows skip false ok' => static fn (): mixed => $drain()[0]['filteredPeerRows'],
    'first peer rowids include text integer blob and real text ties' => static fn (): mixed => $drain()[0]['rowids'],
    'first peer values expose unfiltered frame values' => static fn (): mixed => $drain()[0]['values'],
    'second row remains in same peer start' => static fn (): mixed => $drain()[1]['peerStart'],
    'second row remains in same peer end' => static fn (): mixed => $drain()[1]['peerEnd'],
    'third row false filter does not remove peer membership' => static fn (): mixed => $drain()[2]['peerRows'],
    'fourth tied row carries numeric order key text' => static fn (): mixed => $drain()[3]['orderKey'],
    'site one ten order starts new peer' => static fn (): mixed => $drain()[4]['peerStart'],
    'site one ten order has one row' => static fn (): mixed => $drain()[4]['peerRows'],
    'site two no null peer starts after site one' => static fn (): mixed => $drain()[5]['peerStart'],
    'site two no null peer groups case variants' => static fn (): mixed => $drain()[5]['rowids'],
    'site two no null peer counts only true filter' => static fn (): mixed => $drain()[5]['filteredPeerRows'],
    'site two second null peer row has same range' => static fn (): mixed => [$drain()[6]['peerStart'], $drain()[6]['peerEnd']],
    'site two yes one peer follows nulls after no group' => static fn (): mixed => $drain()[7]['peerStart'],
    'site two yes one peer groups integer and text one' => static fn (): mixed => $drain()[7]['rowids'],
    'site two yes one peer nonnumeric filter is false' => static fn (): mixed => $drain()[7]['filteredPeerRows'],
    'site three null autoload appears last' => static fn (): mixed => $drain()[9]['rowids'],
    'site three partition key remains raw value' => static fn (): mixed => $drain()[9]['partitionKey'],
    'site three order key carries null autoload' => static fn (): mixed => $drain()[9]['orderKey'],
];

$expected = [
    'drain emits every sorted input row' => 10,
    'sorted rowids preserve stable peer order' => [[101, 102, 103, 105], [101, 102, 103, 105], [101, 102, 103, 105], [101, 102, 103, 105], [104], [106, 107], [106, 107], [108, 109], [108, 109], [110]],
    'first peer starts at zero' => 0,
    'first peer ends after numeric ties' => 3,
    'first peer has four rows' => 4,
    'first peer filtered rows skip false ok' => 3,
    'first peer rowids include text integer blob and real text ties' => [101, 102, 103, 105],
    'first peer values expose unfiltered frame values' => [20, 10, 30, 50],
    'second row remains in same peer start' => 0,
    'second row remains in same peer end' => 3,
    'third row false filter does not remove peer membership' => 4,
    'fourth tied row carries numeric order key text' => ['YES', '2.0'],
    'site one ten order starts new peer' => 4,
    'site one ten order has one row' => 1,
    'site two no null peer starts after site one' => 5,
    'site two no null peer groups case variants' => [106, 107],
    'site two no null peer counts only true filter' => 1,
    'site two second null peer row has same range' => [5, 6],
    'site two yes one peer follows nulls after no group' => 7,
    'site two yes one peer groups integer and text one' => [108, 109],
    'site two yes one peer nonnumeric filter is false' => 1,
    'site three null autoload appears last' => [110],
    'site three partition key remains raw value' => [3],
    'site three order key carries null autoload' => [null, '1'],
];

foreach ($cases as $name => $read) {
    $tests['vdbe sorter window affinity current next33 ' . $name] = static function (TestRunner $t) use ($read, $expected, $name): void {
        $t->same($expected[$name], $read());
    };
}

$tests['vdbe sorter window affinity current next33 current peer values apply filter by default'] = static function (TestRunner $t) use ($cursorFor): void {
    $t->same([20, 10, 50], $cursorFor()->currentPeerValues());
};

$tests['vdbe sorter window affinity current next33 current peer values can include false filters'] = static function (TestRunner $t) use ($cursorFor): void {
    $t->same([20, 10, 30, 50], $cursorFor()->currentPeerValues(false));
};

$tests['vdbe sorter window affinity current next33 current peer rows apply filter'] = static function (TestRunner $t) use ($cursorFor): void {
    $t->same([101, 102, 105], array_column($cursorFor()->currentPeerRows(true), 'rowid'));
};

$tests['vdbe sorter window affinity current next33 current frame still uses row frame bounds'] = static function (TestRunner $t) use ($cursorFor): void {
    $t->same([101, 102], array_column($cursorFor()->currentFrameRows(false), 'rowid'));
};

$tests['vdbe sorter window affinity current next33 second row frame is centered'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->next();
    $t->same([101, 102, 103], array_column($cursor->currentFrameRows(false), 'rowid'));
};

$tests['vdbe sorter window affinity current next33 peer grouping is independent of row frame'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->next();
    $t->same([101, 102, 103, 105], array_column($cursor->currentPeerRows(false), 'rowid'));
};

$tests['vdbe sorter window affinity current next33 rewind restores first peer'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->drainPeerSummaries();
    $cursor->rewind();
    $t->same([101, 102, 103, 105], $cursor->currentPeerSummary()['rowids']);
};

$tests['vdbe sorter window affinity current next33 drain peer summaries reaches eof'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->drainPeerSummaries();
    $t->true($cursor->eof());
};

$tests['vdbe sorter window affinity current next33 peer summary accepts custom rowid column'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([
        ['id' => 'a', 'ord' => '2', 'v' => 1],
        ['id' => 'b', 'ord' => 2, 'v' => 2],
    ], 'v', [], ['ord'], null, 0, 0, [], [], ['NUMERIC']);
    $t->same(['a', 'b'], $cursor->currentPeerSummary('id')['rowids']);
};

$tests['vdbe sorter window affinity current next33 missing custom rowid yields null'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([
        ['ord' => '2', 'v' => 1],
        ['ord' => 2, 'v' => 2],
    ], 'v', [], ['ord'], null, 0, 0, [], [], ['NUMERIC']);
    $t->same([null, null], $cursor->currentPeerSummary('id')['rowids']);
};

$tests['vdbe sorter window affinity current next33 empty cursor peer rows throw at eof'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([], 'v', [], ['ord']);
    $t->throws(OutOfBoundsException::class, static fn () => $cursor->currentPeerRows());
};

$tests['vdbe sorter window affinity current next33 empty cursor peer summary throws at eof'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([], 'v', [], ['ord']);
    $t->throws(OutOfBoundsException::class, static fn () => $cursor->currentPeerSummary());
};

$tests['vdbe sorter window affinity current next33 invalid null placement rejects peer compare'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeWindowAggregateCursor([
        ['ord' => null, 'v' => 1],
        ['ord' => 1, 'v' => 2],
    ], 'v', [], ['ord'], null, 0, 0, [], [], [], [], [], ['SIDE']));
};

$tests['vdbe sorter window affinity current next33 binary partition separates numeric-looking text'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([
        ['rowid' => 1, 'site' => '01', 'ord' => 1, 'v' => 10],
        ['rowid' => 2, 'site' => 1, 'ord' => 1, 'v' => 20],
    ], 'v', ['site'], ['ord'], null, 0, 0, 'G', ['BINARY']);
    $t->same([1], $cursor->currentPeerSummary()['rowids']);
};

$tests['vdbe sorter window affinity current next33 numeric partition groups numeric-looking text'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([
        ['rowid' => 1, 'site' => '01', 'ord' => 1, 'v' => 10],
        ['rowid' => 2, 'site' => 1, 'ord' => 1, 'v' => 20],
    ], 'v', ['site'], ['ord'], null, 0, 0, 'C', ['BINARY']);
    $t->same([1, 2], $cursor->currentPeerSummary()['rowids']);
};

$tests['vdbe sorter window affinity current next33 text order affinity separates numeric peers lexically'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([
        ['rowid' => 1, 'ord' => '02', 'v' => 10],
        ['rowid' => 2, 'ord' => 2, 'v' => 20],
    ], 'v', [], ['ord'], null, 0, 0, [], [], 'G');
    $t->same([1], $cursor->currentPeerSummary()['rowids']);
};

$tests['vdbe sorter window affinity current next33 numeric order affinity groups numeric peers'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([
        ['rowid' => 1, 'ord' => '02', 'v' => 10],
        ['rowid' => 2, 'ord' => 2, 'v' => 20],
    ], 'v', [], ['ord'], null, 0, 0, [], [], 'C');
    $t->same([1, 2], $cursor->currentPeerSummary()['rowids']);
};

$tests['vdbe sorter window affinity current next33 rtrim order collation groups padded peers'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([
        ['rowid' => 1, 'ord' => 'plugin', 'v' => 10],
        ['rowid' => 2, 'ord' => 'plugin  ', 'v' => 20],
    ], 'v', [], ['ord'], null, 0, 0, [], [], 'G', ['RTRIM']);
    $t->same([1, 2], $cursor->currentPeerSummary()['rowids']);
};

$tests['vdbe sorter window affinity current next33 binary order collation separates padded peers'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([
        ['rowid' => 1, 'ord' => 'plugin', 'v' => 10],
        ['rowid' => 2, 'ord' => 'plugin  ', 'v' => 20],
    ], 'v', [], ['ord'], null, 0, 0, [], [], 'G', ['BINARY']);
    $t->same([1], $cursor->currentPeerSummary()['rowids']);
};

$tests['vdbe sorter window affinity current next33 descending equal peers keep input order'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([
        ['rowid' => 1, 'ord' => '2', 'v' => 10],
        ['rowid' => 2, 'ord' => 2, 'v' => 20],
    ], 'v', [], ['ord'], null, 0, 0, [], [], 'C', ['BINARY'], [true]);
    $t->same([1, 2], $cursor->currentPeerSummary()['rowids']);
};

$tests['vdbe sorter window affinity current next33 nulls first groups null peers before values'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([
        ['rowid' => 1, 'ord' => 1, 'v' => 10],
        ['rowid' => 2, 'ord' => null, 'v' => 20],
        ['rowid' => 3, 'ord' => null, 'v' => 30],
    ], 'v', [], ['ord'], null, 0, 0, [], [], [], [], [], ['FIRST']);
    $t->same([2, 3], $cursor->currentPeerSummary()['rowids']);
};

return $tests;
