<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonAggregate;
use PortLibs\LibSqlite\SQLiteJsonAggregateState;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

$tests = [];

$rows = [
    ['seo', 'enabled', 10, 1],
    ['cache', new SQLiteJsonSubtypeValue('{"ttl":60}'), 20, 1],
    ['seo', 'enabled', 20, 1],
    ['theme', 'twentytwenty', 30, 1],
    ['cache', new SQLiteJsonSubtypeValue('{"ttl":60}'), 30, 0],
    ['seo', 'disabled', 40, 1],
    ['tail', null, 50, 1],
];

$stateFor = static function () use ($rows): SQLiteJsonAggregateState {
    $state = new SQLiteJsonAggregateState();
    foreach ($rows as $row) {
        $state->stepObjectWindowFrame($row[0], $row[1], $row[2], $row[3]);
    }

    return $state;
};

$cases = [
    'rows current next sorts before distinct' => ['ROWS', 0, 1, 'NO OTHERS', ['{"seo":"enabled","cache":{"ttl":60}}', '{"cache":{"ttl":60},"seo":"enabled"}', '{"seo":"enabled","theme":"twentytwenty"}', '{"theme":"twentytwenty"}', '{"seo":"disabled"}', '{"seo":"disabled","tail":null}', '{"tail":null}']],
    'rows current two following drops later duplicate pair' => ['ROWS', 0, 2, 'NO OTHERS', ['{"seo":"enabled","cache":{"ttl":60}}', '{"cache":{"ttl":60},"seo":"enabled","theme":"twentytwenty"}', '{"seo":"enabled","theme":"twentytwenty"}', '{"theme":"twentytwenty","seo":"disabled"}', '{"seo":"disabled","tail":null}', '{"seo":"disabled","tail":null}', '{"tail":null}']],
    'rows one preceding current keeps earlier duplicate pair' => ['ROWS', 1, 0, 'NO OTHERS', ['{"seo":"enabled"}', '{"seo":"enabled","cache":{"ttl":60}}', '{"cache":{"ttl":60},"seo":"enabled"}', '{"seo":"enabled","theme":"twentytwenty"}', '{"theme":"twentytwenty"}', '{"seo":"disabled"}', '{"seo":"disabled","tail":null}']],
    'rows current next exclude current keeps following candidate' => ['ROWS', 0, 1, 'CURRENT ROW', ['{"cache":{"ttl":60}}', '{"seo":"enabled"}', '{"theme":"twentytwenty"}', '{}', '{"seo":"disabled"}', '{"tail":null}', '{}']],
    'groups current next includes following peer group' => ['GROUPS', 0, 1, 'NO OTHERS', ['{"seo":"enabled","cache":{"ttl":60}}', '{"cache":{"ttl":60},"seo":"enabled","theme":"twentytwenty"}', '{"cache":{"ttl":60},"seo":"enabled","theme":"twentytwenty"}', '{"theme":"twentytwenty","seo":"disabled"}', '{"theme":"twentytwenty","seo":"disabled"}', '{"seo":"disabled","tail":null}', '{"tail":null}']],
    'groups one preceding current dedupes previous peer group' => ['GROUPS', 1, 0, 'NO OTHERS', ['{"seo":"enabled"}', '{"seo":"enabled","cache":{"ttl":60}}', '{"seo":"enabled","cache":{"ttl":60}}', '{"cache":{"ttl":60},"seo":"enabled","theme":"twentytwenty"}', '{"cache":{"ttl":60},"seo":"enabled","theme":"twentytwenty"}', '{"theme":"twentytwenty","seo":"disabled"}', '{"seo":"disabled","tail":null}']],
    'groups exclude current can keep duplicate peer pair once' => ['GROUPS', 0, 1, 'CURRENT ROW', ['{"cache":{"ttl":60},"seo":"enabled"}', '{"seo":"enabled","theme":"twentytwenty"}', '{"cache":{"ttl":60},"theme":"twentytwenty"}', '{"seo":"disabled"}', '{"theme":"twentytwenty","seo":"disabled"}', '{"tail":null}', '{}']],
    'groups exclude group removes current peers before distinct' => ['GROUPS', 0, 1, 'GROUP', ['{"cache":{"ttl":60},"seo":"enabled"}', '{"theme":"twentytwenty"}', '{"theme":"twentytwenty"}', '{"seo":"disabled"}', '{"seo":"disabled"}', '{"tail":null}', '{}']],
    'groups exclude ties keeps current row and following group' => ['GROUPS', 0, 1, 'TIES', ['{"seo":"enabled","cache":{"ttl":60}}', '{"cache":{"ttl":60},"theme":"twentytwenty"}', '{"seo":"enabled","theme":"twentytwenty"}', '{"theme":"twentytwenty","seo":"disabled"}', '{"seo":"disabled"}', '{"seo":"disabled","tail":null}', '{"tail":null}']],
    'range current ten follows numeric band' => ['RANGE', 0, 10, 'NO OTHERS', ['{"seo":"enabled","cache":{"ttl":60}}', '{"cache":{"ttl":60},"seo":"enabled","theme":"twentytwenty"}', '{"cache":{"ttl":60},"seo":"enabled","theme":"twentytwenty"}', '{"theme":"twentytwenty","seo":"disabled"}', '{"theme":"twentytwenty","seo":"disabled"}', '{"seo":"disabled","tail":null}', '{"tail":null}']],
    'range ten preceding current reaches previous band' => ['RANGE', 10, 0, 'NO OTHERS', ['{"seo":"enabled"}', '{"seo":"enabled","cache":{"ttl":60}}', '{"seo":"enabled","cache":{"ttl":60}}', '{"cache":{"ttl":60},"seo":"enabled","theme":"twentytwenty"}', '{"cache":{"ttl":60},"seo":"enabled","theme":"twentytwenty"}', '{"theme":"twentytwenty","seo":"disabled"}', '{"seo":"disabled","tail":null}']],
    'range current ten exclude ties keeps current peer' => ['RANGE', 0, 10, 'TIES', ['{"seo":"enabled","cache":{"ttl":60}}', '{"cache":{"ttl":60},"theme":"twentytwenty"}', '{"seo":"enabled","theme":"twentytwenty"}', '{"theme":"twentytwenty","seo":"disabled"}', '{"seo":"disabled"}', '{"seo":"disabled","tail":null}', '{"tail":null}']],
];

foreach ($cases as $name => [$unit, $preceding, $following, $exclude, $expected]) {
    $tests['json aggregate distinct object window current next81 state ' . $name] = static function (TestRunner $t) use ($stateFor, $unit, $preceding, $following, $exclude, $expected): void {
        $t->same($expected, $stateFor()->finalizeDistinctOrderedWindowFrameObjectByUnit($unit, $preceding, $following, $exclude));
    };
    $tests['json aggregate distinct object window current next81 static ' . $name] = static function (TestRunner $t) use ($rows, $unit, $preceding, $following, $exclude, $expected): void {
        $t->same($expected, SQLiteJsonAggregate::jsonGroupObjectDistinctOrderByWindowFrameRowsByUnit($rows, $unit, $preceding, $following, $exclude));
    };
}

$typeRows = [
    ['flag', 1, 1],
    ['flag', true, 2],
    ['flag', '1', 3],
    ['flag', 1.0, 4],
    ['nullish', null, 5],
    ['nullish', null, 6],
];

$typeCases = [
    'integer and boolean share distinct key' => [0, 1, ['{"flag":1}', '{"flag":1,"flag":"1"}', '{"flag":"1","flag":1.0}', '{"flag":1.0,"nullish":null}', '{"nullish":null}', '{"nullish":null}']],
    'two following keeps text and real classes' => [0, 2, ['{"flag":1,"flag":"1"}', '{"flag":1,"flag":"1","flag":1.0}', '{"flag":"1","flag":1.0,"nullish":null}', '{"flag":1.0,"nullish":null}', '{"nullish":null}', '{"nullish":null}']],
    'one preceding current preserves previous class order' => [1, 0, ['{"flag":1}', '{"flag":1}', '{"flag":1,"flag":"1"}', '{"flag":"1","flag":1.0}', '{"flag":1.0,"nullish":null}', '{"nullish":null}']],
];

foreach ($typeCases as $name => [$preceding, $following, $expected]) {
    $tests['json aggregate distinct object window current next81 type distinct ' . $name] = static function (TestRunner $t) use ($typeRows, $preceding, $following, $expected): void {
        $t->same($expected, SQLiteJsonAggregate::jsonGroupObjectDistinctOrderByWindowFrameRows($typeRows, $preceding, $following));
    };
}

$filterCases = [
    'blank string filter is false' => [['blank', 'x', 1, '  '], ['next', 'ok', 2, 1], '{"next":"ok"}'],
    'numeric string zero filter is false' => [['zero', 'x', 1, '0'], ['next', 'ok', 2, 1], '{"next":"ok"}'],
    'numeric string fraction filter is true' => [['half', 'x', 1, '0.5'], ['next', 'ok', 2, 1], '{"half":"x","next":"ok"}'],
    'null filter is false' => [['nullish', 'x', 1, null], ['next', 'ok', 2, 1], '{"next":"ok"}'],
    'boolean false filter is false' => [['falsey', 'x', 1, false], ['next', 'ok', 2, 1], '{"next":"ok"}'],
    'boolean true filter is true' => [['truthy', 'x', 1, true], ['next', 'ok', 2, 1], '{"truthy":"x","next":"ok"}'],
];

foreach ($filterCases as $name => [$first, $second, $expectedFirstFrame]) {
    $tests['json aggregate distinct object window current next81 filter truthiness ' . $name] = static function (TestRunner $t) use ($first, $second, $expectedFirstFrame): void {
        $frames = SQLiteJsonAggregate::jsonGroupObjectDistinctOrderByWindowFrameRows([$first, $second], 0, 1);

        $t->same($expectedFirstFrame, $frames[0]);
        $t->same('{"next":"ok"}', $frames[1]);
    };
}

$jsonValue = new SQLiteJsonSubtypeValue('{"enabled":true}');
$jsonbValue = new SQLiteBlobValue(SQLiteJsonB::encode(['enabled' => true]));

$tests['json aggregate distinct object window current next81 json subtype and jsonb blob remain distinct classes'] = static function (TestRunner $t) use ($jsonValue, $jsonbValue): void {
    $frames = SQLiteJsonAggregate::jsonGroupObjectDistinctOrderByWindowFrameRows([
        ['payload', $jsonbValue, 1],
        ['payload', $jsonValue, 2],
        ['payload', $jsonValue, 3],
    ], 0, 2);

    $t->same(['{"payload":{"enabled":true},"payload":{"enabled":true}}', '{"payload":{"enabled":true}}', '{"payload":{"enabled":true}}'], $frames);
};

$tests['json aggregate distinct object window current next81 jsonb dispatch decodes distinct ordered object frame'] = static function (TestRunner $t) use ($rows): void {
    $frames = SQLiteJsonAggregate::jsonGroupObjectDistinctOrderByWindowFrameRowsByUnitSqlFunction('jsonb_group_object', $rows, 'GROUPS', 0, 1);

    $t->true($frames[1] instanceof SQLiteBlobValue);
    $t->same(['cache' => ['ttl' => 60], 'seo' => 'enabled', 'theme' => 'twentytwenty'], SQLiteJsonB::decode($frames[1]->bytes));
};

$tests['json aggregate distinct object window current next81 uppercase jsonb state dispatch'] = static function (TestRunner $t) use ($stateFor): void {
    $frames = $stateFor()->finalizeDistinctOrderedWindowFrameObject(0, 1, 'NO OTHERS', 'JSONB_GROUP_OBJECT');

    $t->true($frames[0] instanceof SQLiteBlobValue);
    $t->same(['seo' => 'enabled', 'cache' => ['ttl' => 60]], SQLiteJsonB::decode($frames[0]->bytes));
};

$tests['json aggregate distinct object window current next81 state rows unit matches static rows unit'] = static function (TestRunner $t) use ($stateFor, $rows): void {
    $t->same(
        SQLiteJsonAggregate::jsonGroupObjectDistinctOrderByWindowFrameRowsByUnit($rows, 'ROWS', 0, 2, 'CURRENT ROW'),
        $stateFor()->finalizeDistinctOrderedWindowFrameObjectByUnit('ROWS', 0, 2, 'CURRENT ROW'),
    );
};

$tests['json aggregate distinct object window current next81 nonnumeric range falls back to peer group'] = static function (TestRunner $t): void {
    $frames = SQLiteJsonAggregate::jsonGroupObjectDistinctOrderByWindowFrameRowsByUnit([
        ['seo', 'enabled', 'same'],
        ['cache', 'enabled', 'same'],
        ['seo', 'enabled', 'same'],
        ['tail', 'done', 'tail'],
    ], 'RANGE', 0, 10);

    $t->same(['{"seo":"enabled","cache":"enabled"}', '{"seo":"enabled","cache":"enabled"}', '{"seo":"enabled","cache":"enabled"}', '{"tail":"done"}'], $frames);
};

$tests['json aggregate distinct object window current next81 fractional range accepts real offsets'] = static function (TestRunner $t): void {
    $frames = SQLiteJsonAggregate::jsonGroupObjectDistinctOrderByWindowFrameRowsByUnit([
        ['a', 1, 1.0],
        ['b', 2, 1.25],
        ['a', 1, 1.5],
        ['c', 3, 2.0],
    ], 'RANGE', 0.0, 0.3);

    $t->same(['{"a":1,"b":2}', '{"b":2,"a":1}', '{"a":1}', '{"c":3}'], $frames);
};

$tests['json aggregate distinct object window current next81 empty input returns no frames'] = static function (TestRunner $t): void {
    $t->same([], SQLiteJsonAggregate::jsonGroupObjectDistinctOrderByWindowFrameRows([], 0, 1));
};

$tests['json aggregate distinct object window current next81 summary reuses object frame rows'] = static function (TestRunner $t) use ($stateFor): void {
    $summary = $stateFor()->summary();

    $t->same(7, $summary['windowObjectFrameRows']);
    $t->same(['{"seo":"enabled","cache":{"ttl":60}}'], array_slice($stateFor()->finalizeDistinctOrderedWindowFrameObject(0, 1), 0, 1));
};

$tests['json aggregate distinct object window current next81 rejects malformed row'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupObjectDistinctOrderByWindowFrameRows([['seo', 'enabled']], 0, 1));
};

$tests['json aggregate distinct object window current next81 rejects bad function dispatch'] = static function (TestRunner $t) use ($rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupObjectDistinctOrderByWindowFrameRowsSqlFunction('json_group_array', $rows, 0, 1));
};

$tests['json aggregate distinct object window current next81 rejects fractional rows unit'] = static function (TestRunner $t) use ($rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupObjectDistinctOrderByWindowFrameRowsByUnit($rows, 'ROWS', 0.5, 1));
};

$tests['json aggregate distinct object window current next81 rejects fractional groups unit'] = static function (TestRunner $t) use ($rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupObjectDistinctOrderByWindowFrameRowsByUnit($rows, 'GROUPS', 0, 1.5));
};

$tests['json aggregate distinct object window current next81 rejects negative following'] = static function (TestRunner $t) use ($rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupObjectDistinctOrderByWindowFrameRows($rows, 0, -1));
};

$tests['json aggregate distinct object window current next81 rejects unsupported exclude'] = static function (TestRunner $t) use ($rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupObjectDistinctOrderByWindowFrameRows($rows, 0, 1, 'SIDEWAYS'));
};

$tests['json aggregate distinct object window current next81 rejects unsupported unit'] = static function (TestRunner $t) use ($rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupObjectDistinctOrderByWindowFrameRowsByUnit($rows, 'BANDS', 0, 1));
};

return $tests;
