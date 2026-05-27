<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonAggregate;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

$tests = [];

$arrayRows = [
    ['a', 1, 1],
    ['b', 1, 0],
    ['c', 2, 1],
    ['d', 3, null],
    ['e', 3, 1],
];

$objectRows = [
    ['a', 10, 1, 1],
    ['b', 20, 1, 0],
    ['c', 30, 2, 1],
    ['d', 40, 3, null],
    ['e', 50, 3, 1],
];

$arrayModeCases = [
    'no others keeps filtered rows as empty contributors' => ['NO OTHERS', ['["a"]', '["a","c"]', '["c"]', '["c","e"]', '["e"]']],
    'current row excludes after filter evaluation' => ['CURRENT ROW', ['[]', '["a","c"]', '[]', '["c","e"]', '[]']],
    'group exclusion still emits filtered peer positions' => ['GROUP', ['[]', '["c"]', '[]', '["c"]', '[]']],
    'ties exclusion keeps current filtered output row' => ['TIES', ['["a"]', '["c"]', '["c"]', '["c"]', '["e"]']],
];

foreach ($arrayModeCases as $name => [$exclude, $expected]) {
    $tests['json aggregate window regression next11 array ' . $name] = static function (TestRunner $t) use ($arrayRows, $exclude, $expected): void {
        $t->same($expected, SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows($arrayRows, 1, 1, $exclude));
    };
}

$objectModeCases = [
    'no others keeps filtered rows as empty contributors' => ['NO OTHERS', ['{"a":10}', '{"a":10,"c":30}', '{"c":30}', '{"c":30,"e":50}', '{"e":50}']],
    'current row excludes after filter evaluation' => ['CURRENT ROW', ['{}', '{"a":10,"c":30}', '{}', '{"c":30,"e":50}', '{}']],
    'group exclusion still emits filtered peer positions' => ['GROUP', ['{}', '{"c":30}', '{}', '{"c":30}', '{}']],
    'ties exclusion keeps current filtered output row' => ['TIES', ['{"a":10}', '{"c":30}', '{"c":30}', '{"c":30}', '{"e":50}']],
];

foreach ($objectModeCases as $name => [$exclude, $expected]) {
    $tests['json aggregate window regression next11 object ' . $name] = static function (TestRunner $t) use ($objectRows, $exclude, $expected): void {
        $t->same($expected, SQLiteJsonAggregate::jsonGroupObjectWindowFrameRows($objectRows, 1, 1, $exclude));
    };
}

$filterCases = [
    'integer zero' => [0, ['[]', '["keep"]']],
    'integer nonzero' => [2, ['["filtered"]', '["filtered","keep"]']],
    'negative integer' => [-1, ['["filtered"]', '["filtered","keep"]']],
    'float zero' => [0.0, ['[]', '["keep"]']],
    'float nonzero' => [0.25, ['["filtered"]', '["filtered","keep"]']],
    'true boolean' => [true, ['["filtered"]', '["filtered","keep"]']],
    'false boolean' => [false, ['[]', '["keep"]']],
    'null' => [null, ['[]', '["keep"]']],
    'blank string' => ['   ', ['[]', '["keep"]']],
    'numeric zero string' => ['0', ['[]', '["keep"]']],
    'numeric nonzero string' => ['3.5', ['["filtered"]', '["filtered","keep"]']],
    'nonnumeric string' => ['yes', ['[]', '["keep"]']],
    'array value is truthy' => [[1], ['["filtered"]', '["filtered","keep"]']],
];

foreach ($filterCases as $name => [$filter, $expected]) {
    $tests['json aggregate window regression next11 filter truthiness ' . $name] = static function (TestRunner $t) use ($filter, $expected): void {
        $actual = SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows([
            ['filtered', 1, $filter],
            ['keep', 2, 1],
        ], 1, 0);
        $t->same($expected, $actual);
    };
}

$frameCases = [
    'preceding zero following zero' => [0, 0, ['["a"]', '[]', '["c"]', '[]', '["e"]']],
    'preceding two following zero' => [2, 0, ['["a"]', '["a"]', '["a","c"]', '["c"]', '["c","e"]']],
    'preceding zero following two' => [0, 2, ['["a","c"]', '["c"]', '["c","e"]', '["e"]', '["e"]']],
    'wide frame preserves five output rows' => [9, 9, ['["a","c","e"]', '["a","c","e"]', '["a","c","e"]', '["a","c","e"]', '["a","c","e"]']],
];

foreach ($frameCases as $name => [$preceding, $following, $expected]) {
    $tests['json aggregate window regression next11 frame ' . $name] = static function (TestRunner $t) use ($arrayRows, $preceding, $following, $expected): void {
        $t->same($expected, SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows($arrayRows, $preceding, $following));
    };
}

$payloadRows = [
    [new SQLiteJsonSubtypeValue('{"plugin":"seo"}'), 1, 1],
    [new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => 'cache'])), 2, 0],
    [null, 3, 1],
    [true, 4, 1],
];

$payloadCases = [
    'subtype survives first frame' => [0, '{"plugin":"seo"}'],
    'filtered jsonb still has output row' => [1, '{"plugin":"seo"}'],
    'null payload participates as json null' => [2, 'null'],
    'boolean payload participates as json integer' => [3, '1'],
];

foreach ($payloadCases as $name => [$index, $contains]) {
    $tests['json aggregate window regression next11 payload ' . $name] = static function (TestRunner $t) use ($payloadRows, $index, $contains): void {
        $frames = SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows($payloadRows, 1, 1);
    $t->true(str_contains($frames[$index], $contains));
    };
}

$tests['json aggregate window regression next11 jsonb array dispatch preserves output row count'] = static function (TestRunner $t) use ($arrayRows): void {
    $frames = SQLiteJsonAggregate::jsonGroupArrayWindowFrameRowsSqlFunction('jsonb_group_array', $arrayRows, 1, 1);
    $t->same(5, count($frames));
};

$tests['json aggregate window regression next11 jsonb array dispatch decodes filtered position'] = static function (TestRunner $t) use ($arrayRows): void {
    $frames = SQLiteJsonAggregate::jsonGroupArrayWindowFrameRowsSqlFunction('jsonb_group_array', $arrayRows, 1, 1);
    $t->same(['a', 'c'], SQLiteJsonB::decode($frames[1]->bytes));
};

$tests['json aggregate window regression next11 jsonb object dispatch preserves output row count'] = static function (TestRunner $t) use ($objectRows): void {
    $frames = SQLiteJsonAggregate::jsonGroupObjectWindowFrameRowsSqlFunction('jsonb_group_object', $objectRows, 1, 1);
    $t->same(5, count($frames));
};

$tests['json aggregate window regression next11 jsonb object dispatch decodes filtered position'] = static function (TestRunner $t) use ($objectRows): void {
    $frames = SQLiteJsonAggregate::jsonGroupObjectWindowFrameRowsSqlFunction('jsonb_group_object', $objectRows, 1, 1);
    $t->same(['a' => 10, 'c' => 30], SQLiteJsonB::decode($frames[1]->bytes));
};

$tests['json aggregate window regression next11 all filtered array rows emit empty frames'] = static function (TestRunner $t): void {
    $t->same(['[]', '[]', '[]'], SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows([['a', 1, 0], ['b', 2, null], ['c', 3, false]], 1, 1));
};

$tests['json aggregate window regression next11 all filtered object rows emit empty frames'] = static function (TestRunner $t): void {
    $t->same(['{}', '{}', '{}'], SQLiteJsonAggregate::jsonGroupObjectWindowFrameRows([['a', 1, 1, 0], ['b', 2, 2, null], ['c', 3, 3, false]], 1, 1));
};

$tests['json aggregate window regression next11 filtered current row can still see preceding row'] = static function (TestRunner $t): void {
    $t->same(['["a"]', '["a"]'], SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows([['a', 1, 1], ['b', 2, 0]], 1, 0));
};

$tests['json aggregate window regression next11 filtered current row can still see following row'] = static function (TestRunner $t): void {
    $t->same(['["b"]', '["b"]'], SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows([['a', 1, 0], ['b', 2, 1]], 0, 1));
};

$tests['json aggregate window regression next11 exclude group can empty only peer group'] = static function (TestRunner $t): void {
    $t->same(['[]', '["c"]', '["b"]'], SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows([['a', 1, 1], ['b', 1, 1], ['c', 2, 1]], 1, 1, 'GROUP'));
};

$tests['json aggregate window regression next11 exclude ties preserves filtered current row frame shape'] = static function (TestRunner $t): void {
    $t->same(['["a"]', '["c"]', '["c"]'], SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows([['a', 1, 1], ['b', 1, 0], ['c', 2, 1]], 1, 1, 'TIES'));
};

$tests['json aggregate window regression next11 mixed numeric text order keys keep stable text order'] = static function (TestRunner $t): void {
    $t->same(['["one","ten"]', '["ten","two"]', '["two"]'], SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows([['ten', '10', 1], ['two', '2', 1], ['one', '1', 1]], 0, 1));
};

$tests['json aggregate window regression next11 null order key peers sort before text'] = static function (TestRunner $t): void {
    $t->same(['["nil","zed"]', '["zed"]'], SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows([['zed', 'z', 1], ['nil', null, 1]], 0, 1));
};

$tests['json aggregate window regression next11 object duplicate labels preserve textual duplicates'] = static function (TestRunner $t): void {
    $t->same(['{"dup":1,"dup":2}', '{"dup":1,"dup":2,"tail":3}', '{"dup":2,"tail":3}'], SQLiteJsonAggregate::jsonGroupObjectWindowFrameRows([['dup', 1, 1, 1], ['dup', 2, 2, 1], ['tail', 3, 3, 1]], 1, 1));
};

$tests['json aggregate window regression next11 jsonb object duplicate labels keeps last decoded value'] = static function (TestRunner $t): void {
    $frames = SQLiteJsonAggregate::jsonGroupObjectWindowFrameRowsSqlFunction('jsonb_group_object', [['dup', 1, 1, 1], ['dup', 2, 2, 1]], 1, 1);
    $t->same(['dup' => 2], SQLiteJsonB::decode($frames[0]->bytes));
};

$tests['json aggregate window regression next11 uppercase text array dispatch accepts filter regression'] = static function (TestRunner $t) use ($arrayRows): void {
    $t->same(SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows($arrayRows, 1, 1), SQLiteJsonAggregate::jsonGroupArrayWindowFrameRowsSqlFunction('JSON_GROUP_ARRAY', $arrayRows, 1, 1));
};

$tests['json aggregate window regression next11 uppercase text object dispatch accepts filter regression'] = static function (TestRunner $t) use ($objectRows): void {
    $t->same(SQLiteJsonAggregate::jsonGroupObjectWindowFrameRows($objectRows, 1, 1), SQLiteJsonAggregate::jsonGroupObjectWindowFrameRowsSqlFunction('JSON_GROUP_OBJECT', $objectRows, 1, 1));
};

$tests['json aggregate window regression next11 invalid object filter row still validates shape first'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupObjectWindowFrameRows([['a', 1]], 1, 1));
};

$tests['json aggregate window regression next11 invalid array filter row still validates shape first'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows([['a']], 1, 1));
};

$tests['json aggregate window regression next11 negative bounds rejected before filter handling'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows([['a', 1, 0]], -1, 0));
};

$tests['json aggregate window regression next11 invalid exclude rejected before filtered rows vanish'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows([['a', 1, 0]], 1, 1, 'OTHERS'));
};

$tests['json aggregate window regression next11 zero width frame over filtered row emits empty aggregate'] = static function (TestRunner $t): void {
    $t->same(['[]'], SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows([['filtered', 1, 0]], 0, 0));
};

return $tests;
