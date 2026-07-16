<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonAggregate;
use PortLibs\LibSqlite\SQLiteJsonAggregateState;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

$tests = [];

$rows = [
    ['theme_mods', 30, 1],
    ['seo_rules', 10, 1],
    ['cache_rules', 20, 1],
    ['seo_rules', 25, 1],
    ['cache_rules', 35, 0],
    ['home', 40, 1],
    ['seo_rules', 45, 1],
];

$stateFor = static function () use ($rows): SQLiteJsonAggregateState {
    $state = new SQLiteJsonAggregateState();
    foreach ($rows as $row) {
        $state->stepArrayWindowFrame($row[0], $row[1], $row[2]);
    }

    return $state;
};

$rowsCases = [
    'rows current next sorts before distinct' => [0, 1, 'NO OTHERS', ['["seo_rules","cache_rules"]', '["cache_rules","seo_rules"]', '["seo_rules","theme_mods"]', '["theme_mods"]', '["home"]', '["home","seo_rules"]', '["seo_rules"]']],
    'rows current two following skips duplicate after first sorted hit' => [0, 2, 'NO OTHERS', ['["seo_rules","cache_rules"]', '["cache_rules","seo_rules","theme_mods"]', '["seo_rules","theme_mods"]', '["theme_mods","home"]', '["home","seo_rules"]', '["home","seo_rules"]', '["seo_rules"]']],
    'rows one preceding current keeps earlier duplicate winner' => [1, 0, 'NO OTHERS', ['["seo_rules"]', '["seo_rules","cache_rules"]', '["cache_rules","seo_rules"]', '["seo_rules","theme_mods"]', '["theme_mods"]', '["home"]', '["home","seo_rules"]']],
    'rows current next exclude current keeps following row only' => [0, 1, 'CURRENT ROW', ['["cache_rules"]', '["seo_rules"]', '["theme_mods"]', '[]', '["home"]', '["seo_rules"]', '[]']],
    'rows current next exclude ties preserves current because no peers' => [0, 1, 'TIES', ['["seo_rules","cache_rules"]', '["cache_rules","seo_rules"]', '["seo_rules","theme_mods"]', '["theme_mods"]', '["home"]', '["home","seo_rules"]', '["seo_rules"]']],
];

foreach ($rowsCases as $name => [$preceding, $following, $exclude, $expected]) {
    $tests['json aggregate distinct order window current next75 state ' . $name] = static function (TestRunner $t) use ($stateFor, $preceding, $following, $exclude, $expected): void {
        $t->same($expected, $stateFor()->finalizeDistinctOrderedWindowFrameArray($preceding, $following, $exclude));
    };
}

$peerRows = [
    ['seo_rules', 10, 1],
    ['cache_rules', 20, 1],
    ['seo_rules', 20, 1],
    ['theme_mods', 30, 1],
    ['seo_rules', 30, 1],
    ['home', 40, 1],
];

$peerCases = [
    'groups current next keeps first value in ordered peer frame' => ['GROUPS', 0, 1, 'NO OTHERS', ['["seo_rules","cache_rules"]', '["cache_rules","seo_rules","theme_mods"]', '["cache_rules","seo_rules","theme_mods"]', '["theme_mods","seo_rules","home"]', '["theme_mods","seo_rules","home"]', '["home"]']],
    'groups one preceding current dedupes previous peer group' => ['GROUPS', 1, 0, 'NO OTHERS', ['["seo_rules"]', '["seo_rules","cache_rules"]', '["seo_rules","cache_rules"]', '["cache_rules","seo_rules","theme_mods"]', '["cache_rules","seo_rules","theme_mods"]', '["theme_mods","seo_rules","home"]']],
    'groups exclude current keeps duplicate peer value when present' => ['GROUPS', 0, 1, 'CURRENT ROW', ['["cache_rules","seo_rules"]', '["seo_rules","theme_mods"]', '["cache_rules","theme_mods","seo_rules"]', '["seo_rules","home"]', '["theme_mods","home"]', '[]']],
    'groups exclude group removes current peers before distinct' => ['GROUPS', 0, 1, 'GROUP', ['["cache_rules","seo_rules"]', '["theme_mods","seo_rules"]', '["theme_mods","seo_rules"]', '["home"]', '["home"]', '[]']],
    'groups exclude ties keeps current row then following group' => ['GROUPS', 0, 1, 'TIES', ['["seo_rules","cache_rules"]', '["cache_rules","theme_mods","seo_rules"]', '["seo_rules","theme_mods"]', '["theme_mods","home"]', '["seo_rules","home"]', '["home"]']],
    'range current ten follows numeric band' => ['RANGE', 0, 10, 'NO OTHERS', ['["seo_rules","cache_rules"]', '["cache_rules","seo_rules","theme_mods"]', '["cache_rules","seo_rules","theme_mods"]', '["theme_mods","seo_rules","home"]', '["theme_mods","seo_rules","home"]', '["home"]']],
    'range ten preceding current reaches previous numeric band' => ['RANGE', 10, 0, 'NO OTHERS', ['["seo_rules"]', '["seo_rules","cache_rules"]', '["seo_rules","cache_rules"]', '["cache_rules","seo_rules","theme_mods"]', '["cache_rules","seo_rules","theme_mods"]', '["theme_mods","seo_rules","home"]']],
    'range current ten exclude ties keeps current peer winner' => ['RANGE', 0, 10, 'TIES', ['["seo_rules","cache_rules"]', '["cache_rules","theme_mods","seo_rules"]', '["seo_rules","theme_mods"]', '["theme_mods","home"]', '["seo_rules","home"]', '["home"]']],
];

foreach ($peerCases as $name => [$unit, $preceding, $following, $exclude, $expected]) {
    $tests['json aggregate distinct order window current next75 static ' . $name] = static function (TestRunner $t) use ($peerRows, $unit, $preceding, $following, $exclude, $expected): void {
        $t->same($expected, SQLiteJsonAggregate::jsonGroupArrayDistinctOrderByWindowFrameRowsByUnit($peerRows, $unit, $preceding, $following, $exclude));
    };
}

$tests['json aggregate distinct order window current next75 json subtype and jsonb blob use distinct storage classes'] = static function (TestRunner $t): void {
    $json = new SQLiteJsonSubtypeValue('{"enabled":true}');
    $jsonb = new SQLiteBlobValue(SQLiteJsonB::encode(['enabled' => true]));
    $frames = SQLiteJsonAggregate::jsonGroupArrayDistinctOrderByWindowFrameRows([
        [$jsonb, 1],
        [$json, 2],
        [$json, 3],
    ], 0, 2);

    $t->same(['[{"enabled":true},{"enabled":true}]', '[{"enabled":true}]', '[{"enabled":true}]'], $frames);
};

$tests['json aggregate distinct order window current next75 jsonb dispatch decodes ordered distinct frame'] = static function (TestRunner $t) use ($peerRows): void {
    $frames = SQLiteJsonAggregate::jsonGroupArrayDistinctOrderByWindowFrameRowsByUnitSqlFunction('jsonb_group_array', $peerRows, 'GROUPS', 0, 1);

    $t->true($frames[1] instanceof SQLiteBlobValue);
    $t->same(['cache_rules', 'seo_rules', 'theme_mods'], SQLiteJsonB::decode($frames[1]->bytes));
};

$tests['json aggregate distinct order window current next75 uppercase jsonb state dispatch'] = static function (TestRunner $t) use ($stateFor): void {
    $frames = $stateFor()->finalizeDistinctOrderedWindowFrameArray(0, 1, 'NO OTHERS', 'JSONB_GROUP_ARRAY');

    $t->true($frames[0] instanceof SQLiteBlobValue);
    $t->same(['seo_rules', 'cache_rules'], SQLiteJsonB::decode($frames[0]->bytes));
};

$tests['json aggregate distinct order window current next75 filtered current row can still see next kept duplicate'] = static function (TestRunner $t): void {
    $frames = SQLiteJsonAggregate::jsonGroupArrayDistinctOrderByWindowFrameRows([
        ['seo_rules', 1, 0],
        ['seo_rules', 2, 1],
        ['cache_rules', 3, 1],
    ], 0, 1);

    $t->same(['["seo_rules"]', '["seo_rules","cache_rules"]', '["cache_rules"]'], $frames);
};

$tests['json aggregate distinct order window current next75 nonnumeric range falls back to peer group'] = static function (TestRunner $t): void {
    $frames = SQLiteJsonAggregate::jsonGroupArrayDistinctOrderByWindowFrameRowsByUnit([
        ['seo_rules', 'same'],
        ['seo_rules', 'same'],
        ['cache_rules', 'same'],
        ['home', 'tail'],
    ], 'RANGE', 0, 10);

    $t->same(['["seo_rules","cache_rules"]', '["seo_rules","cache_rules"]', '["seo_rules","cache_rules"]', '["home"]'], $frames);
};

$tests['json aggregate distinct order window current next75 fractional range accepts real offsets'] = static function (TestRunner $t): void {
    $frames = SQLiteJsonAggregate::jsonGroupArrayDistinctOrderByWindowFrameRowsByUnit([
        ['a', 1.0],
        ['b', 1.25],
        ['a', 1.5],
        ['c', 2.0],
    ], 'RANGE', 0.0, 0.3);

    $t->same(['["a","b"]', '["b","a"]', '["a"]', '["c"]'], $frames);
};

$tests['json aggregate distinct order window current next75 summary reuses window frame rows'] = static function (TestRunner $t) use ($stateFor): void {
    $summary = $stateFor()->summary();

    $t->same(7, $summary['windowArrayFrameRows']);
    $t->same(['["seo_rules","cache_rules"]'], array_slice($stateFor()->finalizeDistinctOrderedWindowFrameArray(0, 1), 0, 1));
};

$tests['json aggregate distinct order window current next75 rejects malformed row'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArrayDistinctOrderByWindowFrameRows([['seo_rules']], 0, 1));
};

$tests['json aggregate distinct order window current next75 rejects bad function dispatch'] = static function (TestRunner $t) use ($rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArrayDistinctOrderByWindowFrameRowsSqlFunction('json_group_object', $rows, 0, 1));
};

$tests['json aggregate distinct order window current next75 rejects fractional rows unit'] = static function (TestRunner $t) use ($rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArrayDistinctOrderByWindowFrameRowsByUnit($rows, 'ROWS', 0.5, 1));
};

$tests['json aggregate distinct order window current next75 rejects negative following'] = static function (TestRunner $t) use ($rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArrayDistinctOrderByWindowFrameRows($rows, 0, -1));
};

$tests['json aggregate distinct order window current next75 rejects unsupported exclude'] = static function (TestRunner $t) use ($rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArrayDistinctOrderByWindowFrameRows($rows, 0, 1, 'SIDEWAYS'));
};

$typeRows = [
    [1, 1],
    [true, 2],
    ['1', 3],
    [1.0, 4],
    [null, 5],
    [null, 6],
];

$typeCases = [
    'integer and boolean share SQLite integer distinct key' => [0, 1, ['[1]', '[1,"1"]', '["1",1.0]', '[1.0,null]', '[null]', '[null]']],
    'two following sees text and real after integer class' => [0, 2, ['[1,"1"]', '[1,"1",1.0]', '["1",1.0,null]', '[1.0,null]', '[null]', '[null]']],
    'one preceding current keeps previous type class first' => [1, 0, ['[1]', '[1]', '[1,"1"]', '["1",1.0]', '[1.0,null]', '[null]']],
];

foreach ($typeCases as $name => [$preceding, $following, $expected]) {
    $tests['json aggregate distinct order window current next75 type distinct ' . $name] = static function (TestRunner $t) use ($typeRows, $preceding, $following, $expected): void {
        $t->same($expected, SQLiteJsonAggregate::jsonGroupArrayDistinctOrderByWindowFrameRows($typeRows, $preceding, $following));
    };
}

$nullOrderRows = [
    ['null_order', null, 1],
    ['alpha', 'a', 1],
    ['alpha', 'b', 1],
    ['beta', 'b', 1],
    ['tail', 'z', 1],
];

$nullOrderCases = [
    'null order key sorts before text keys' => ['ROWS', 0, 1, 'NO OTHERS', ['["null_order","alpha"]', '["alpha"]', '["alpha","beta"]', '["beta","tail"]', '["tail"]']],
    'groups null order key remains isolated peer group' => ['GROUPS', 0, 1, 'NO OTHERS', ['["null_order","alpha"]', '["alpha","beta"]', '["alpha","beta","tail"]', '["alpha","beta","tail"]', '["tail"]']],
    'groups exclude ties with text peers keeps current text winner' => ['GROUPS', 0, 1, 'TIES', ['["null_order","alpha"]', '["alpha","beta"]', '["alpha","tail"]', '["beta","tail"]', '["tail"]']],
    'range text keys falls back to current peer group' => ['RANGE', 0, 1, 'NO OTHERS', ['["null_order"]', '["alpha"]', '["alpha","beta"]', '["alpha","beta"]', '["tail"]']],
];

foreach ($nullOrderCases as $name => [$unit, $preceding, $following, $exclude, $expected]) {
    $tests['json aggregate distinct order window current next75 null text order ' . $name] = static function (TestRunner $t) use ($nullOrderRows, $unit, $preceding, $following, $exclude, $expected): void {
        $t->same($expected, SQLiteJsonAggregate::jsonGroupArrayDistinctOrderByWindowFrameRowsByUnit($nullOrderRows, $unit, $preceding, $following, $exclude));
    };
}

$filterCases = [
    'blank string filter is false' => [['blank', 1, '  '], ['next', 2, 1], '["next"]'],
    'numeric string zero filter is false' => [['zero', 1, '0'], ['next', 2, 1], '["next"]'],
    'numeric string fraction filter is true' => [['half', 1, '0.5'], ['next', 2, 1], '["half","next"]'],
    'null filter is false' => [['nullish', 1, null], ['next', 2, 1], '["next"]'],
    'boolean false filter is false' => [['falsey', 1, false], ['next', 2, 1], '["next"]'],
    'boolean true filter is true' => [['truthy', 1, true], ['next', 2, 1], '["truthy","next"]'],
];

foreach ($filterCases as $name => [$first, $second, $expectedFirstFrame]) {
    $tests['json aggregate distinct order window current next75 filter truthiness ' . $name] = static function (TestRunner $t) use ($first, $second, $expectedFirstFrame): void {
        $frames = SQLiteJsonAggregate::jsonGroupArrayDistinctOrderByWindowFrameRows([$first, $second], 0, 1);

        $t->same($expectedFirstFrame, $frames[0]);
        $t->same('["next"]', $frames[1]);
    };
}

$tests['json aggregate distinct order window current next75 state by unit matches static by unit'] = static function (TestRunner $t) use ($stateFor, $rows): void {
    $t->same(
        SQLiteJsonAggregate::jsonGroupArrayDistinctOrderByWindowFrameRowsByUnit($rows, 'ROWS', 0, 2, 'CURRENT ROW'),
        $stateFor()->finalizeDistinctOrderedWindowFrameArrayByUnit('ROWS', 0, 2, 'CURRENT ROW'),
    );
};

$tests['json aggregate distinct order window current next75 empty input returns no frames'] = static function (TestRunner $t): void {
    $t->same([], SQLiteJsonAggregate::jsonGroupArrayDistinctOrderByWindowFrameRows([], 0, 1));
};

return $tests;
