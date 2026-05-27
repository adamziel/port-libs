<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$rows = [
    ['rowid' => 1, 'site' => 1, 'seq' => 1, 'weight' => 10, 'option_name' => 'siteurl', 'include' => 1],
    ['rowid' => 2, 'site' => 1, 'seq' => 2, 'weight' => 10, 'option_name' => 'home', 'include' => 0],
    ['rowid' => 3, 'site' => 1, 'seq' => 3, 'weight' => 20, 'option_name' => 'blogname', 'include' => '1'],
    ['rowid' => 4, 'site' => 1, 'seq' => 4, 'weight' => 30, 'option_name' => 'active_plugins', 'include' => true],
    ['rowid' => 5, 'site' => 2, 'seq' => 1, 'weight' => 10, 'option_name' => 'network_siteurl', 'include' => 1],
    ['rowid' => 6, 'site' => 2, 'seq' => 2, 'weight' => 20, 'option_name' => 'network_home', 'include' => null],
    ['rowid' => 7, 'site' => 2, 'seq' => 3, 'weight' => 20, 'option_name' => 'network_plugins', 'include' => '2'],
];

$cursorFor = static function (
    string $unit = 'ROWS',
    int|float $preceding = 0,
    int|float $following = 2,
    string $exclude = 'CURRENT ROW',
    ?string $filter = null,
    array $orderColumns = ['seq'],
    array|string $orderAffinities = 'D',
 ) use ($rows): SQLiteVdbeWindowAggregateCursor {
    return new SQLiteVdbeWindowAggregateCursor(
        $rows,
        'option_name',
        ['site'],
        $orderColumns,
        $filter,
        $preceding,
        $following,
        'D',
        [],
        $orderAffinities,
        [],
        [],
        [],
        $unit,
        $exclude,
    );
};

$drain = static function (SQLiteVdbeWindowAggregateCursor $cursor, string $method, mixed ...$arguments): array {
    $values = [];
    while (!$cursor->eof()) {
        $values[] = $cursor->{$method}(...$arguments);
        $cursor->next();
    }

    return $values;
};

$tests = [];

$valueCases = [
    'rows exclude current first value follows next physical row' => ['firstValue', [], ['home', 'blogname', 'active_plugins', null, 'network_home', 'network_plugins', null]],
    'rows exclude current last value clamps at partition end' => ['lastValue', [], ['blogname', 'active_plugins', 'active_plugins', null, 'network_plugins', 'network_plugins', null]],
    'rows exclude current nth two value uses second excluded-frame row' => ['nthValue', [2], ['blogname', 'active_plugins', null, null, 'network_plugins', null, null]],
    'rows exclude current nth three value is null at short tail' => ['nthValue', [3], [null, null, null, null, null, null, null]],
    'rows exclude current unfiltered first value keeps false-filter row' => ['firstValue', [false], ['home', 'blogname', 'active_plugins', null, 'network_home', 'network_plugins', null]],
    'rows exclude current unfiltered nth two keeps physical row order' => ['nthValue', [2, false], ['blogname', 'active_plugins', null, null, 'network_plugins', null, null]],
];

foreach ($valueCases as $name => [$method, $arguments, $expected]) {
    $tests['vdbe window value frame exclude current next48 ' . $name] = static function (TestRunner $t) use ($cursorFor, $drain, $method, $arguments, $expected): void {
        $t->same($expected, $drain($cursorFor(), $method, ...$arguments));
    };
}

$filteredCases = [
    'filter rows first value applies SQL truthiness after current exclusion' => ['firstValue', [true], ['blogname', 'blogname', 'active_plugins', null, 'network_plugins', 'network_plugins', null]],
    'filter rows last value applies SQL truthiness after current exclusion' => ['lastValue', [true], ['blogname', 'active_plugins', 'active_plugins', null, 'network_plugins', 'network_plugins', null]],
    'filter rows nth two value skips false and null filter rows' => ['nthValue', [2, true], [null, 'active_plugins', null, null, null, null, null]],
    'filter rows nth two unfiltered bypass returns physical second row' => ['nthValue', [2, false], ['blogname', 'active_plugins', null, null, 'network_plugins', null, null]],
    'filter rows first value unfiltered bypass returns false row' => ['firstValue', [false], ['home', 'blogname', 'active_plugins', null, 'network_home', 'network_plugins', null]],
    'filter rows last value unfiltered bypass sees null-filter peer row' => ['lastValue', [false], ['blogname', 'active_plugins', 'active_plugins', null, 'network_plugins', 'network_plugins', null]],
];

foreach ($filteredCases as $name => [$method, $arguments, $expected]) {
    $tests['vdbe window value frame exclude current next48 ' . $name] = static function (TestRunner $t) use ($cursorFor, $drain, $method, $arguments, $expected): void {
        $t->same($expected, $drain($cursorFor('ROWS', 0, 2, 'CURRENT ROW', 'include'), $method, ...$arguments));
    };
}

$excludeCases = [
    'no others first value returns first range peer row' => ['NO OTHERS', 'firstValue', ['siteurl', 'siteurl', 'blogname', 'active_plugins', 'network_siteurl', 'network_home', 'network_home']],
    'no others last value includes following range peers' => ['NO OTHERS', 'lastValue', ['blogname', 'blogname', 'active_plugins', 'active_plugins', 'network_plugins', 'network_plugins', 'network_plugins']],
    'no others nth two returns second range-frame row when available' => ['NO OTHERS', 'nthValue', ['home', 'home', 'active_plugins', null, 'network_home', 'network_plugins', 'network_plugins']],
    'exclude group removes current weight peers in range' => ['GROUP', 'firstValue', ['blogname', 'blogname', 'active_plugins', null, 'network_home', null, null]],
    'exclude ties keeps current and removes peer ties' => ['TIES', 'firstValue', ['siteurl', 'home', 'blogname', 'active_plugins', 'network_siteurl', 'network_home', 'network_plugins']],
    'exclude ties last value keeps current and future non-ties' => ['TIES', 'lastValue', ['blogname', 'blogname', 'active_plugins', 'active_plugins', 'network_plugins', 'network_home', 'network_plugins']],
];

foreach ($excludeCases as $name => [$exclude, $method, $expected]) {
    $tests['vdbe window value frame exclude current next48 ' . $name] = static function (TestRunner $t) use ($cursorFor, $drain, $exclude, $method, $expected): void {
        $arguments = $method === 'nthValue' ? [2] : [];
        $t->same($expected, $drain($cursorFor('RANGE', 0, 10, $exclude, null, ['weight'], 'D'), $method, ...$arguments));
    };
}

$groupsCases = [
    'groups current next first value skips current peer group current row only' => ['CURRENT ROW', 'firstValue', ['home', 'siteurl', 'active_plugins', null, 'network_home', 'network_plugins', 'network_home']],
    'groups current next last value reaches next peer group' => ['CURRENT ROW', 'lastValue', ['blogname', 'blogname', 'active_plugins', null, 'network_plugins', 'network_plugins', 'network_home']],
    'groups current next nth two crosses from peer to next group' => ['CURRENT ROW', 'nthValue', ['blogname', 'blogname', null, null, 'network_plugins', null, null]],
    'groups exclude group first value starts at next peer group' => ['GROUP', 'firstValue', ['blogname', 'blogname', 'active_plugins', null, 'network_home', null, null]],
    'groups exclude group last value removes all tail peers' => ['GROUP', 'lastValue', ['blogname', 'blogname', 'active_plugins', null, 'network_plugins', null, null]],
    'groups exclude ties first value keeps current row identity' => ['TIES', 'firstValue', ['siteurl', 'home', 'blogname', 'active_plugins', 'network_siteurl', 'network_home', 'network_plugins']],
    'groups exclude ties nth two can select next peer group' => ['TIES', 'nthValue', ['blogname', 'blogname', 'active_plugins', null, 'network_home', null, null]],
];

foreach ($groupsCases as $name => [$exclude, $method, $expected]) {
    $tests['vdbe window value frame exclude current next48 ' . $name] = static function (TestRunner $t) use ($cursorFor, $drain, $exclude, $method, $expected): void {
        $arguments = $method === 'nthValue' ? [2] : [];
        $t->same($expected, $drain($cursorFor('GROUPS', 0, 1, $exclude, null, ['weight'], 'D'), $method, ...$arguments));
    };
}

$summaryCases = [
    'summary first values are included in drain summaries' => ['firstValue', ['blogname', 'blogname', 'active_plugins', null, 'network_plugins', 'network_plugins', null]],
    'summary last values are included in drain summaries' => ['lastValue', ['blogname', 'active_plugins', 'active_plugins', null, 'network_plugins', 'network_plugins', null]],
    'summary nth values are included in drain summaries' => ['nthValue', [null, 'active_plugins', null, null, null, null, null]],
    'summary frame starts become null for empty excluded tail' => ['frameStart', [1, 2, 3, null, 5, 6, null]],
    'summary frame ends become null for empty excluded tail' => ['frameEnd', [2, 3, 3, null, 6, 6, null]],
    'summary frame rows count value frames after exclusion' => ['frameRows', [2, 2, 1, 0, 2, 1, 0]],
    'summary filtered rows counts SQL truthy value rows' => ['filteredRows', [1, 2, 1, 0, 1, 1, 0]],
];

foreach ($summaryCases as $name => [$field, $expected]) {
    $tests['vdbe window value frame exclude current next48 ' . $name] = static function (TestRunner $t) use ($cursorFor, $field, $expected): void {
        $t->same($expected, array_column($cursorFor('ROWS', 0, 2, 'CURRENT ROW', 'include')->drainSummaries('|', true), $field));
    };
}

foreach ([1, 2, 3, 4] as $nth) {
    $tests['vdbe window value frame exclude current next48 nth value direct row1 ' . $nth] = static function (TestRunner $t) use ($cursorFor, $nth): void {
        $expected = [1 => 'home', 2 => 'blogname', 3 => null, 4 => null][$nth];
        $t->same($expected, $cursorFor()->nthValue($nth));
    };
}

$tests['vdbe window value frame exclude current next48 empty frame first value is null'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    for ($i = 0; $i < 3; $i++) {
        $cursor->next();
    }
    $t->same(null, $cursor->firstValue());
};

$tests['vdbe window value frame exclude current next48 empty frame last value is null'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    for ($i = 0; $i < 3; $i++) {
        $cursor->next();
    }
    $t->same(null, $cursor->lastValue());
};

$tests['vdbe window value frame exclude current next48 empty frame nth value is null'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    for ($i = 0; $i < 3; $i++) {
        $cursor->next();
    }
    $t->same(null, $cursor->nthValue(1));
};

$tests['vdbe window value frame exclude current next48 nth value rejects zero'] = static function (TestRunner $t) use ($cursorFor): void {
    $t->throws(InvalidArgumentException::class, static fn () => $cursorFor()->nthValue(0));
};

$tests['vdbe window value frame exclude current next48 nth value rejects negative'] = static function (TestRunner $t) use ($cursorFor): void {
    $t->throws(InvalidArgumentException::class, static fn () => $cursorFor()->nthValue(-1));
};

$tests['vdbe window value frame exclude current next48 methods throw at eof'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    while (!$cursor->eof()) {
        $cursor->next();
    }
    $t->throws(OutOfBoundsException::class, static fn () => $cursor->firstValue());
};

$tests['vdbe window value frame exclude current next48 rewind restores value frame'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    $cursor->next();
    $cursor->next();
    $cursor->rewind();
    $t->same('home', $cursor->firstValue());
};

$positionCases = [
    'row1 frame rowids exclude current' => [0, 'currentFrameRows', false, [2, 3]],
    'row2 frame rowids exclude current' => [1, 'currentFrameRows', false, [3, 4]],
    'row3 frame rowids exclude current' => [2, 'currentFrameRows', false, [4]],
    'row4 frame rowids exclude current is empty' => [3, 'currentFrameRows', false, []],
    'row5 frame rowids stay in second partition' => [4, 'currentFrameRows', false, [6, 7]],
    'row6 frame rowids stay in second partition' => [5, 'currentFrameRows', false, [7]],
    'row7 frame rowids empty at partition tail' => [6, 'currentFrameRows', false, []],
    'row1 filtered rowids skip false next row' => [0, 'currentFrameRows', true, [3]],
    'row2 filtered rowids keep two truthy rows' => [1, 'currentFrameRows', true, [3, 4]],
    'row5 filtered rowids skip null next row' => [4, 'currentFrameRows', true, [7]],
];

foreach ($positionCases as $name => [$advance, $_method, $applyFilter, $expected]) {
    $tests['vdbe window value frame exclude current next48 ' . $name] = static function (TestRunner $t) use ($cursorFor, $advance, $applyFilter, $expected): void {
        $cursor = $cursorFor('ROWS', 0, 2, 'CURRENT ROW', 'include');
        for ($i = 0; $i < $advance; $i++) {
            $cursor->next();
        }
        $t->same($expected, array_column($cursor->currentFrameRows($applyFilter), 'rowid'));
    };
}

$directCases = [
    'row2 direct first filtered value' => [1, 'firstValue', [true], 'blogname'],
    'row2 direct second filtered value' => [1, 'nthValue', [2, true], 'active_plugins'],
    'row2 direct last filtered value' => [1, 'lastValue', [true], 'active_plugins'],
    'row5 direct first filtered value' => [4, 'firstValue', [true], 'network_plugins'],
    'row5 direct second filtered value missing' => [4, 'nthValue', [2, true], null],
    'row6 direct unfiltered first value sees row7' => [5, 'firstValue', [false], 'network_plugins'],
    'row7 direct unfiltered last value missing' => [6, 'lastValue', [false], null],
    'row1 direct unfiltered first value sees false-filter row' => [0, 'firstValue', [false], 'home'],
    'row1 direct filtered nth one skips false-filter row' => [0, 'nthValue', [1, true], 'blogname'],
    'row1 direct filtered nth two missing after filter' => [0, 'nthValue', [2, true], null],
];

foreach ($directCases as $name => [$advance, $method, $arguments, $expected]) {
    $tests['vdbe window value frame exclude current next48 ' . $name] = static function (TestRunner $t) use ($cursorFor, $advance, $method, $arguments, $expected): void {
        $cursor = $cursorFor('ROWS', 0, 2, 'CURRENT ROW', 'include');
        for ($i = 0; $i < $advance; $i++) {
            $cursor->next();
        }
        $t->same($expected, $cursor->{$method}(...$arguments));
    };
}

return $tests;
