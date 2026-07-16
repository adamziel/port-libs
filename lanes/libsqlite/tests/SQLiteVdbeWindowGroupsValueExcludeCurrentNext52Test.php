<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$rows = [
    ['rowid' => 1, 'site' => 1, 'option_name' => 'siteurl', 'bytes' => 10, 'include' => 1],
    ['rowid' => 2, 'site' => 1, 'option_name' => 'home', 'bytes' => 10, 'include' => 0],
    ['rowid' => 3, 'site' => 1, 'option_name' => 'blogname', 'bytes' => 20, 'include' => '1'],
    ['rowid' => 4, 'site' => 1, 'option_name' => 'cron', 'bytes' => 30, 'include' => true],
    ['rowid' => 5, 'site' => 1, 'option_name' => 'active_plugins', 'bytes' => 30, 'include' => '2'],
    ['rowid' => 6, 'site' => 1, 'option_name' => 'transient_feed', 'bytes' => 40, 'include' => null],
    ['rowid' => 7, 'site' => 2, 'option_name' => 'network_siteurl', 'bytes' => 10, 'include' => 1],
    ['rowid' => 8, 'site' => 2, 'option_name' => 'network_home', 'bytes' => 10, 'include' => ''],
    ['rowid' => 9, 'site' => 2, 'option_name' => 'network_blogname', 'bytes' => 20, 'include' => '1'],
    ['rowid' => 10, 'site' => 2, 'option_name' => 'network_plugins', 'bytes' => 30, 'include' => 0],
    ['rowid' => 11, 'site' => 2, 'option_name' => 'network_theme', 'bytes' => 30, 'include' => true],
];

$cursorFor = static fn (?string $filter = 'include', int $following = 1, int $preceding = 0): SQLiteVdbeWindowAggregateCursor => new SQLiteVdbeWindowAggregateCursor(
    $rows,
    'option_name',
    ['site'],
    ['bytes'],
    $filter,
    $preceding,
    $following,
    ['INTEGER'],
    [],
    ['NUMERIC'],
    [],
    [],
    [],
    'GROUPS',
    'CURRENT ROW',
);

$at = static function (SQLiteVdbeWindowAggregateCursor $cursor, int $position): SQLiteVdbeWindowAggregateCursor {
    for ($i = 0; $i < $position; $i++) {
        $cursor->next();
    }

    return $cursor;
};

$drainField = static function (SQLiteVdbeWindowAggregateCursor $cursor, string $field, bool $applyFilter = true): array {
    $actual = [];
    while (!$cursor->eof()) {
        $actual[] = $cursor->currentValueFrameSummary(2, $applyFilter)[$field];
        $cursor->next();
    }

    return $actual;
};

$drainPairField = static function (SQLiteVdbeWindowAggregateCursor $cursor, string $field, bool $applyFilter = true): array {
    $actual = [];
    while (!$cursor->eof()) {
        $pair = $cursor->currentNextValueFrameSummary(2, $applyFilter);
        $actual[] = [
            $pair['current'][$field],
            $pair['next'] === null ? null : $pair['next'][$field],
            $pair['advanced'],
            $cursor->currentRow()['rowid'],
        ];
        $cursor->next();
    }

    return $actual;
};

$tests = [];

$filteredCases = [
    'first values after group exclude current' => ['firstValue', ['blogname', 'siteurl', 'cron', 'active_plugins', 'cron', null, 'network_blogname', 'network_siteurl', 'network_theme', 'network_theme', null]],
    'last values after group exclude current' => ['lastValue', ['blogname', 'blogname', 'active_plugins', 'active_plugins', 'cron', null, 'network_blogname', 'network_blogname', 'network_theme', 'network_theme', null]],
    'second values after group exclude current' => ['nthValue', [null, 'blogname', 'active_plugins', null, null, null, null, 'network_blogname', null, null, null]],
    'rowids after filter and group exclude current' => ['rowids', [[3], [1, 3], [4, 5], [5], [4], [], [9], [7, 9], [11], [11], []]],
    'values after filter and group exclude current' => ['values', [['blogname'], ['siteurl', 'blogname'], ['cron', 'active_plugins'], ['active_plugins'], ['cron'], [], ['network_blogname'], ['network_siteurl', 'network_blogname'], ['network_theme'], ['network_theme'], []]],
];

foreach ($filteredCases as $name => [$field, $expected]) {
    $tests['vdbe window groups value exclude current next52 filtered ' . $name] = static function (TestRunner $t) use ($cursorFor, $drainField, $field, $expected): void {
        $t->same($expected, $drainField($cursorFor(), $field));
    };
}

$unfilteredCases = [
    'first values keep false and null rows' => ['firstValue', ['home', 'siteurl', 'cron', 'active_plugins', 'cron', null, 'network_home', 'network_siteurl', 'network_plugins', 'network_theme', 'network_plugins']],
    'last values keep false and null rows' => ['lastValue', ['blogname', 'blogname', 'active_plugins', 'transient_feed', 'transient_feed', null, 'network_blogname', 'network_blogname', 'network_theme', 'network_theme', 'network_plugins']],
    'second values keep physical peer order' => ['nthValue', ['blogname', 'blogname', 'active_plugins', 'transient_feed', 'transient_feed', null, 'network_blogname', 'network_blogname', 'network_theme', null, null]],
    'rowids keep unfiltered peers' => ['rowids', [[2, 3], [1, 3], [4, 5], [5, 6], [4, 6], [], [8, 9], [7, 9], [10, 11], [11], [10]]],
    'values keep unfiltered peers' => ['values', [['home', 'blogname'], ['siteurl', 'blogname'], ['cron', 'active_plugins'], ['active_plugins', 'transient_feed'], ['cron', 'transient_feed'], [], ['network_home', 'network_blogname'], ['network_siteurl', 'network_blogname'], ['network_plugins', 'network_theme'], ['network_theme'], ['network_plugins']]],
];

foreach ($unfilteredCases as $name => [$field, $expected]) {
    $tests['vdbe window groups value exclude current next52 unfiltered ' . $name] = static function (TestRunner $t) use ($cursorFor, $drainField, $field, $expected): void {
        $t->same($expected, $drainField($cursorFor(), $field, false));
    };
}

$pairCases = [
    'current next first values peek without advancing' => ['firstValue', [['blogname', 'siteurl', false, 1], ['siteurl', 'cron', false, 2], ['cron', 'active_plugins', false, 3], ['active_plugins', 'cron', false, 4], ['cron', null, false, 5], [null, 'network_blogname', false, 6], ['network_blogname', 'network_siteurl', false, 7], ['network_siteurl', 'network_theme', false, 8], ['network_theme', 'network_theme', false, 9], ['network_theme', null, false, 10], [null, null, false, 11]]],
    'current next second values peek without advancing' => ['nthValue', [[null, 'blogname', false, 1], ['blogname', 'active_plugins', false, 2], ['active_plugins', null, false, 3], [null, null, false, 4], [null, null, false, 5], [null, null, false, 6], [null, 'network_blogname', false, 7], ['network_blogname', null, false, 8], [null, null, false, 9], [null, null, false, 10], [null, null, false, 11]]],
    'current next rowids peek over partition boundary' => ['rowids', [[ [3], [1, 3], false, 1], [[1, 3], [4, 5], false, 2], [[4, 5], [5], false, 3], [[5], [4], false, 4], [[4], [], false, 5], [[], [9], false, 6], [[9], [7, 9], false, 7], [[7, 9], [11], false, 8], [[11], [11], false, 9], [[11], [], false, 10], [[], null, false, 11]]],
];

foreach ($pairCases as $name => [$field, $expected]) {
    $tests['vdbe window groups value exclude current next52 ' . $name] = static function (TestRunner $t) use ($cursorFor, $drainPairField, $field, $expected): void {
        $t->same($expected, $drainPairField($cursorFor(), $field));
    };
}

$positionCases = [
    'row 1 filtered first is next group after false peer' => [0, true, 2, ['blogname', 'blogname', null, [3]]],
    'row 2 filtered second value reaches next group' => [1, true, 2, ['siteurl', 'blogname', 'blogname', [1, 3]]],
    'row 4 filtered frame keeps row 5 only from current peer group' => [3, true, 2, ['active_plugins', 'active_plugins', null, [5]]],
    'row 5 filtered frame keeps row 4 only from current peer group' => [4, true, 2, ['cron', 'cron', null, [4]]],
    'row 6 filtered tail is empty after excluding current' => [5, true, 2, [null, null, null, []]],
    'row 8 filtered second value crosses from peer to next group' => [7, true, 2, ['network_siteurl', 'network_blogname', 'network_blogname', [7, 9]]],
    'row 11 unfiltered frame keeps excluded peer complement only' => [10, false, 1, ['network_plugins', 'network_plugins', 'network_plugins', [10]]],
    'row 4 unfiltered frame reaches following null-filter group' => [3, false, 2, ['active_plugins', 'transient_feed', 'transient_feed', [5, 6]]],
];

foreach ($positionCases as $name => [$position, $applyFilter, $nth, $expected]) {
    $tests['vdbe window groups value exclude current next52 ' . $name] = static function (TestRunner $t) use ($cursorFor, $at, $position, $applyFilter, $nth, $expected): void {
        $summary = $at($cursorFor(), $position)->currentValueFrameSummary($nth, $applyFilter);
        $t->same($expected, [$summary['firstValue'], $summary['lastValue'], $summary['nthValue'], $summary['rowids']]);
    };
}

$tests['vdbe window groups value exclude current next52 preceding includes previous peer group'] = static function (TestRunner $t) use ($cursorFor, $at): void {
    $summary = $at($cursorFor('include', 1, 1), 3)->currentValueFrameSummary(3);
    $t->same(['blogname', 'active_plugins', null, [3, 5]], [$summary['firstValue'], $summary['lastValue'], $summary['nthValue'], $summary['rowids']]);
};

$tests['vdbe window groups value exclude current next52 no filter column treats apply filter as raw rows'] = static function (TestRunner $t) use ($cursorFor): void {
    $t->same([[2, 3], [1, 3], [4, 5], [5, 6], [4, 6], [], [8, 9], [7, 9], [10, 11], [11], [10]], $drain = (static function () use ($cursorFor): array {
        $cursor = $cursorFor(null);
        $actual = [];
        while (!$cursor->eof()) {
            $actual[] = $cursor->currentValueFrameSummary()['rowids'];
            $cursor->next();
        }

        return $actual;
    })());
};

$tests['vdbe window groups value exclude current next52 custom rowid column is reported'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor(
        [
            ['id' => 'a', 'site' => 1, 'value' => 'one', 'ord' => 1],
            ['id' => 'b', 'site' => 1, 'value' => 'two', 'ord' => 1],
            ['id' => 'c', 'site' => 1, 'value' => 'three', 'ord' => 2],
        ],
        'value',
        ['site'],
        ['ord'],
        null,
        0,
        1,
        ['INTEGER'],
        [],
        ['NUMERIC'],
        [],
        [],
        [],
        'GROUPS',
        'CURRENT ROW',
    );
    $t->same(['b', 'c'], $cursor->currentValueFrameSummary(2, true, 'id')['rowids']);
};

$summaryCases = [
    'base summaries frame starts after current exclusion' => ['frameStart', [1, 0, 3, 4, 3, null, 7, 6, 9, 10, 9]],
    'base summaries frame ends after current exclusion' => ['frameEnd', [2, 2, 4, 5, 5, null, 8, 8, 10, 10, 9]],
    'base summaries frame row counts after current exclusion' => ['frameRows', [2, 2, 2, 2, 2, 0, 2, 2, 2, 1, 1]],
    'base summaries filtered row counts after current exclusion' => ['filteredRows', [1, 2, 2, 1, 1, 0, 1, 2, 1, 1, 0]],
    'base summaries order keys stay on peer groups' => ['orderKey', [[10], [10], [20], [30], [30], [40], [10], [10], [20], [30], [30]]],
    'base summaries partition keys stay isolated' => ['partitionKey', [[1], [1], [1], [1], [1], [1], [2], [2], [2], [2], [2]]],
    'base summaries next partition key crosses site boundary only once' => ['nextPartitionKey', [[1], [1], [1], [1], [1], [2], [2], [2], [2], [2], null]],
    'base summaries next order key follows physical cursor order' => ['nextOrderKey', [[10], [20], [30], [30], [40], [10], [10], [20], [30], [30], null]],
    'base summaries current filter truthiness mirrors SQLite' => ['currentFilterPassed', [true, false, true, true, true, false, true, false, true, false, true]],
];

foreach ($summaryCases as $name => [$field, $expected]) {
    $tests['vdbe window groups value exclude current next52 ' . $name] = static function (TestRunner $t) use ($cursorFor, $field, $expected): void {
        $cursor = $cursorFor();
        $actual = [];
        while (!$cursor->eof()) {
            $actual[] = $cursor->currentSummary()[$field];
            $cursor->next();
        }
        $t->same($expected, $actual);
    };
}

$pairUnfilteredCases = [
    'unfiltered current next first values keep false peers' => ['firstValue', [['home', 'siteurl', false, 1], ['siteurl', 'cron', false, 2], ['cron', 'active_plugins', false, 3], ['active_plugins', 'cron', false, 4], ['cron', null, false, 5], [null, 'network_home', false, 6], ['network_home', 'network_siteurl', false, 7], ['network_siteurl', 'network_plugins', false, 8], ['network_plugins', 'network_theme', false, 9], ['network_theme', 'network_plugins', false, 10], ['network_plugins', null, false, 11]]],
    'unfiltered current next last values keep null-filter tail' => ['lastValue', [['blogname', 'blogname', false, 1], ['blogname', 'active_plugins', false, 2], ['active_plugins', 'transient_feed', false, 3], ['transient_feed', 'transient_feed', false, 4], ['transient_feed', null, false, 5], [null, 'network_blogname', false, 6], ['network_blogname', 'network_blogname', false, 7], ['network_blogname', 'network_theme', false, 8], ['network_theme', 'network_theme', false, 9], ['network_theme', 'network_plugins', false, 10], ['network_plugins', null, false, 11]]],
    'unfiltered current next rowids keep raw GROUPS frames' => ['rowids', [[ [2, 3], [1, 3], false, 1], [[1, 3], [4, 5], false, 2], [[4, 5], [5, 6], false, 3], [[5, 6], [4, 6], false, 4], [[4, 6], [], false, 5], [[], [8, 9], false, 6], [[8, 9], [7, 9], false, 7], [[7, 9], [10, 11], false, 8], [[10, 11], [11], false, 9], [[11], [10], false, 10], [[10], null, false, 11]]],
];

foreach ($pairUnfilteredCases as $name => [$field, $expected]) {
    $tests['vdbe window groups value exclude current next52 ' . $name] = static function (TestRunner $t) use ($cursorFor, $drainPairField, $field, $expected): void {
        $t->same($expected, $drainPairField($cursorFor(), $field, false));
    };
}

$tests['vdbe window groups value exclude current next52 pair summary does not move cursor'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $before = $cursor->currentRow()['rowid'];
    $pair = $cursor->currentNextValueFrameSummary();
    $t->same([1, 1, false, 'siteurl'], [$before, $cursor->currentRow()['rowid'], $pair['advanced'], $cursor->currentRow()['option_name']]);
};

$tests['vdbe window groups value exclude current next52 current summary does not move cursor'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->next();
    $before = $cursor->currentRow()['rowid'];
    $summary = $cursor->currentValueFrameSummary();
    $t->same([2, 2, ['siteurl', 'blogname']], [$before, $cursor->currentRow()['rowid'], $summary['values']]);
};

$tests['vdbe window groups value exclude current next52 rewind restores first filtered frame'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->next();
    $cursor->next();
    $cursor->currentNextValueFrameSummary();
    $cursor->rewind();
    $t->same(['blogname', [3]], [$cursor->currentValueFrameSummary()['firstValue'], $cursor->currentValueFrameSummary()['rowids']]);
};

$tests['vdbe window groups value exclude current next52 following zero leaves current peer complement'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor('include', 0);
    $t->same([[], ['siteurl'], [], ['active_plugins'], ['cron'], [], [], ['network_siteurl'], [], ['network_theme'], []], $drain = (static function () use ($cursor): array {
        $actual = [];
        while (!$cursor->eof()) {
            $actual[] = $cursor->currentValueFrameSummary()['values'];
            $cursor->next();
        }

        return $actual;
    })());
};

$tests['vdbe window groups value exclude current next52 following two reaches two peer groups ahead'] = static function (TestRunner $t) use ($cursorFor): void {
    $summary = $cursorFor('include', 2)->currentValueFrameSummary(3);
    $t->same(['blogname', 'active_plugins', 'active_plugins', [3, 4, 5]], [$summary['firstValue'], $summary['lastValue'], $summary['nthValue'], $summary['rowids']]);
};

$tests['vdbe window groups value exclude current next52 following two next peek also spans ahead'] = static function (TestRunner $t) use ($cursorFor): void {
    $pair = $cursorFor('include', 2)->currentNextValueFrameSummary(3);
    $t->same([['blogname', 'cron', 'active_plugins'], ['siteurl', 'blogname', 'cron', 'active_plugins']], [$pair['current']['values'], $pair['next']['values']]);
};

$tests['vdbe window groups value exclude current next52 preceding one row 3 sees prior peer group'] = static function (TestRunner $t) use ($cursorFor, $at): void {
    $summary = $at($cursorFor('include', 1, 1), 2)->currentValueFrameSummary(4);
    $t->same(['siteurl', 'active_plugins', null, [1, 4, 5]], [$summary['firstValue'], $summary['lastValue'], $summary['nthValue'], $summary['rowids']]);
};

$tests['vdbe window groups value exclude current next52 preceding one next peek recomputes next current exclusion'] = static function (TestRunner $t) use ($cursorFor, $at): void {
    $pair = $at($cursorFor('include', 1, 1), 2)->currentNextValueFrameSummary();
    $t->same([[1, 4, 5], [3, 5]], [$pair['current']['rowids'], $pair['next']['rowids']]);
};

$tests['vdbe window groups value exclude current next52 missing rowid column reports nulls'] = static function (TestRunner $t) use ($cursorFor): void {
    $t->same([null], $cursorFor()->currentValueFrameSummary(2, true, 'missing_rowid')['rowids']);
};

$tests['vdbe window groups value exclude current next52 next value summary at final row is null'] = static function (TestRunner $t) use ($cursorFor, $at): void {
    $pair = $at($cursorFor(), 10)->currentNextValueFrameSummary();
    $t->same([[], null, false], [$pair['current']['values'], $pair['next'], $pair['advanced']]);
};

$tests['vdbe window groups value exclude current next52 rejects zero nth in value summary'] = static function (TestRunner $t) use ($cursorFor): void {
    $t->throws(InvalidArgumentException::class, static fn () => $cursorFor()->currentValueFrameSummary(0));
};

$tests['vdbe window groups value exclude current next52 rejects negative nth in current next summary'] = static function (TestRunner $t) use ($cursorFor): void {
    $t->throws(InvalidArgumentException::class, static fn () => $cursorFor()->currentNextValueFrameSummary(-1));
};

$tests['vdbe window groups value exclude current next52 eof current value summary throws'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    while (!$cursor->eof()) {
        $cursor->next();
    }
    $t->throws(OutOfBoundsException::class, static fn () => $cursor->currentValueFrameSummary());
};

$tests['vdbe window groups value exclude current next52 eof current next value summary throws'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    while (!$cursor->eof()) {
        $cursor->next();
    }
    $t->throws(OutOfBoundsException::class, static fn () => $cursor->currentNextValueFrameSummary());
};

return $tests;
