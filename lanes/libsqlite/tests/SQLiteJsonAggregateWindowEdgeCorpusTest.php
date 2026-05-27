<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonAggregate;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

$tests = [];

$jsonRules = new SQLiteJsonSubtypeValue('[{"plugin":"seo"}]');
$jsonbSummary = new SQLiteBlobValue(SQLiteJsonB::encode(['pending' => 2]));

$arrayRows = [
    ['siteurl', 'a', 1],
    ['home', 'a', 1],
    [$jsonRules, 'b', 1],
    [$jsonbSummary, 'c', 0],
    ['theme_mods', 'c', 1],
];

$objectRows = [
    ['siteurl', 'https://example.test', 'a', 1],
    ['home', 'https://example.test/home', 'a', 1],
    ['rules', $jsonRules, 'b', 1],
    ['summary', $jsonbSummary, 'c', 0],
    ['theme', 'twentytwentyfive', 'c', 1],
];

$arrayCases = [
    'array no others keeps filtered peer rows' => ['NO OTHERS', ['["siteurl","home"]', '["siteurl","home",[{"plugin":"seo"}]]', '["home",[{"plugin":"seo"}]]', '[[{"plugin":"seo"}],"theme_mods"]', '["theme_mods"]']],
    'array current row excludes current only' => ['CURRENT ROW', ['["home"]', '["siteurl",[{"plugin":"seo"}]]', '["home"]', '[[{"plugin":"seo"}],"theme_mods"]', '[]']],
    'array group excludes all order peers' => ['GROUP', ['[]', '[[{"plugin":"seo"}]]', '["home"]', '[[{"plugin":"seo"}]]', '[]']],
    'array ties keeps current and removes peer ties' => ['TIES', ['["siteurl"]', '["home",[{"plugin":"seo"}]]', '["home",[{"plugin":"seo"}]]', '[[{"plugin":"seo"}]]', '["theme_mods"]']],
    'array lowercase exclude mode is accepted' => ['ties', ['["siteurl"]', '["home",[{"plugin":"seo"}]]', '["home",[{"plugin":"seo"}]]', '[[{"plugin":"seo"}]]', '["theme_mods"]']],
    'array blank exclude defaults to no others' => ['', ['["siteurl","home"]', '["siteurl","home",[{"plugin":"seo"}]]', '["home",[{"plugin":"seo"}]]', '[[{"plugin":"seo"}],"theme_mods"]', '["theme_mods"]']],
];

foreach ($arrayCases as $name => [$exclude, $expected]) {
    $tests['json aggregate window edge ' . $name] = static function (TestRunner $t) use ($arrayRows, $exclude, $expected): void {
        $t->same($expected, SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows($arrayRows, 1, 1, $exclude));
    };
}

$objectCases = [
    'object no others keeps filtered peer rows' => ['NO OTHERS', ['{"siteurl":"https://example.test","home":"https://example.test/home"}', '{"siteurl":"https://example.test","home":"https://example.test/home","rules":[{"plugin":"seo"}]}', '{"home":"https://example.test/home","rules":[{"plugin":"seo"}]}', '{"rules":[{"plugin":"seo"}],"theme":"twentytwentyfive"}', '{"theme":"twentytwentyfive"}']],
    'object current row excludes current only' => ['CURRENT ROW', ['{"home":"https://example.test/home"}', '{"siteurl":"https://example.test","rules":[{"plugin":"seo"}]}', '{"home":"https://example.test/home"}', '{"rules":[{"plugin":"seo"}],"theme":"twentytwentyfive"}', '{}']],
    'object group excludes all order peers' => ['GROUP', ['{}', '{"rules":[{"plugin":"seo"}]}', '{"home":"https://example.test/home"}', '{"rules":[{"plugin":"seo"}]}', '{}']],
    'object ties keeps current and removes peer ties' => ['TIES', ['{"siteurl":"https://example.test"}', '{"home":"https://example.test/home","rules":[{"plugin":"seo"}]}', '{"home":"https://example.test/home","rules":[{"plugin":"seo"}]}', '{"rules":[{"plugin":"seo"}]}', '{"theme":"twentytwentyfive"}']],
    'object lowercase exclude mode is accepted' => ['group', ['{}', '{"rules":[{"plugin":"seo"}]}', '{"home":"https://example.test/home"}', '{"rules":[{"plugin":"seo"}]}', '{}']],
    'object blank exclude defaults to no others' => ['', ['{"siteurl":"https://example.test","home":"https://example.test/home"}', '{"siteurl":"https://example.test","home":"https://example.test/home","rules":[{"plugin":"seo"}]}', '{"home":"https://example.test/home","rules":[{"plugin":"seo"}]}', '{"rules":[{"plugin":"seo"}],"theme":"twentytwentyfive"}', '{"theme":"twentytwentyfive"}']],
];

foreach ($objectCases as $name => [$exclude, $expected]) {
    $tests['json aggregate window edge ' . $name] = static function (TestRunner $t) use ($objectRows, $exclude, $expected): void {
        $t->same($expected, SQLiteJsonAggregate::jsonGroupObjectWindowFrameRows($objectRows, 1, 1, $exclude));
    };
}

$tests['json aggregate window edge array unbounded preceding clamps'] = static function (TestRunner $t) use ($arrayRows): void {
    $t->same(['["siteurl"]', '["siteurl","home"]', '["siteurl","home",[{"plugin":"seo"}]]', '["siteurl","home",[{"plugin":"seo"}]]', '["siteurl","home",[{"plugin":"seo"}],"theme_mods"]'], SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows($arrayRows, 9, 0));
};

$tests['json aggregate window edge array unbounded following clamps'] = static function (TestRunner $t) use ($arrayRows): void {
    $t->same(['["siteurl","home",[{"plugin":"seo"}],"theme_mods"]', '["home",[{"plugin":"seo"}],"theme_mods"]', '[[{"plugin":"seo"}],"theme_mods"]', '["theme_mods"]', '["theme_mods"]'], SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows($arrayRows, 0, 9));
};

$tests['json aggregate window edge object unbounded preceding clamps'] = static function (TestRunner $t) use ($objectRows): void {
    $t->same(['{"siteurl":"https://example.test"}', '{"siteurl":"https://example.test","home":"https://example.test/home"}', '{"siteurl":"https://example.test","home":"https://example.test/home","rules":[{"plugin":"seo"}]}', '{"siteurl":"https://example.test","home":"https://example.test/home","rules":[{"plugin":"seo"}]}', '{"siteurl":"https://example.test","home":"https://example.test/home","rules":[{"plugin":"seo"}],"theme":"twentytwentyfive"}'], SQLiteJsonAggregate::jsonGroupObjectWindowFrameRows($objectRows, 9, 0));
};

$tests['json aggregate window edge object unbounded following clamps'] = static function (TestRunner $t) use ($objectRows): void {
    $t->same(['{"siteurl":"https://example.test","home":"https://example.test/home","rules":[{"plugin":"seo"}],"theme":"twentytwentyfive"}', '{"home":"https://example.test/home","rules":[{"plugin":"seo"}],"theme":"twentytwentyfive"}', '{"rules":[{"plugin":"seo"}],"theme":"twentytwentyfive"}', '{"theme":"twentytwentyfive"}', '{"theme":"twentytwentyfive"}'], SQLiteJsonAggregate::jsonGroupObjectWindowFrameRows($objectRows, 0, 9));
};

$tests['json aggregate window edge false filters produce no output rows'] = static function (TestRunner $t): void {
    $t->same(['[]', '[]'], SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows([['siteurl', 1, 0], ['home', 2, null]], 1, 1));
};

$tests['json aggregate window edge empty arrays stay empty'] = static function (TestRunner $t): void {
    $t->same([], SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows([], 1, 1));
};

$tests['json aggregate window edge empty objects stay empty'] = static function (TestRunner $t): void {
    $t->same([], SQLiteJsonAggregate::jsonGroupObjectWindowFrameRows([], 1, 1));
};

$tests['json aggregate window edge jsonb array dispatch decodes frames'] = static function (TestRunner $t) use ($arrayRows): void {
    $frames = SQLiteJsonAggregate::jsonGroupArrayWindowFrameRowsSqlFunction('JSONB_GROUP_ARRAY', $arrayRows, 1, 1, 'TIES');
    $t->true($frames[0] instanceof SQLiteBlobValue);
    $t->same([['siteurl'], ['home', [['plugin' => 'seo']]], ['home', [['plugin' => 'seo']]], [[['plugin' => 'seo']]], ['theme_mods']], array_map(static fn (SQLiteBlobValue $frame): mixed => SQLiteJsonB::decode($frame->bytes), $frames));
};

$tests['json aggregate window edge jsonb object dispatch decodes frames'] = static function (TestRunner $t) use ($objectRows): void {
    $frames = SQLiteJsonAggregate::jsonGroupObjectWindowFrameRowsSqlFunction('JSONB_GROUP_OBJECT', $objectRows, 1, 1, 'CURRENT ROW');
    $t->true($frames[0] instanceof SQLiteBlobValue);
    $t->same([
        ['home' => 'https://example.test/home'],
        ['siteurl' => 'https://example.test', 'rules' => [['plugin' => 'seo']]],
        ['home' => 'https://example.test/home'],
        ['rules' => [['plugin' => 'seo']], 'theme' => 'twentytwentyfive'],
        [],
    ], array_map(static fn (SQLiteBlobValue $frame): mixed => SQLiteJsonB::decode($frame->bytes), $frames));
};

$tests['json aggregate window edge text array dispatch preserves strings'] = static function (TestRunner $t) use ($arrayRows): void {
    $t->same(SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows($arrayRows, 1, 1, 'GROUP'), SQLiteJsonAggregate::jsonGroupArrayWindowFrameRowsSqlFunction('json_group_array', $arrayRows, 1, 1, 'GROUP'));
};

$tests['json aggregate window edge text object dispatch preserves strings'] = static function (TestRunner $t) use ($objectRows): void {
    $t->same(SQLiteJsonAggregate::jsonGroupObjectWindowFrameRows($objectRows, 1, 1, 'GROUP'), SQLiteJsonAggregate::jsonGroupObjectWindowFrameRowsSqlFunction('json_group_object', $objectRows, 1, 1, 'GROUP'));
};

$tests['json aggregate window edge null peer groups exclude together'] = static function (TestRunner $t): void {
    $rows = [['first', null, 1], ['second', null, 1], ['third', 1, 1]];
    $t->same(['[]', '["third"]', '["second"]'], SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows($rows, 1, 1, 'GROUP'));
};

$tests['json aggregate window edge duplicate object labels remain in frame text'] = static function (TestRunner $t): void {
    $rows = [['dup', 1, 1, 1], ['dup', 2, 1, 1], ['other', 3, 2, 1]];
    $t->same(['{"dup":1}', '{"dup":2,"other":3}', '{"dup":2,"other":3}'], SQLiteJsonAggregate::jsonGroupObjectWindowFrameRows($rows, 1, 1, 'TIES'));
};

$tests['json aggregate window edge blob json payload participates in included frame'] = static function (TestRunner $t) use ($jsonbSummary): void {
    $rows = [['siteurl', 1, 1], [$jsonbSummary, 2, 1]];
    $t->same(['["siteurl",{"pending":2}]', '["siteurl",{"pending":2}]'], SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows($rows, 1, 1));
};

$tests['json aggregate window edge rejects invalid exclude mode'] = static function (TestRunner $t) use ($arrayRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows($arrayRows, 1, 1, 'SIDEWAYS'));
};

$tests['json aggregate window edge rejects negative preceding'] = static function (TestRunner $t) use ($arrayRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows($arrayRows, -1, 1));
};

$tests['json aggregate window edge rejects negative following'] = static function (TestRunner $t) use ($arrayRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows($arrayRows, 1, -1));
};

$tests['json aggregate window edge rejects malformed array row'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows([['siteurl']], 1, 1));
};

$tests['json aggregate window edge rejects malformed object row'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupObjectWindowFrameRows([['siteurl', 'https://example.test']], 1, 1));
};

$tests['json aggregate window edge rejects invalid array dispatch function'] = static function (TestRunner $t) use ($arrayRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArrayWindowFrameRowsSqlFunction('json_group', $arrayRows, 1, 1));
};

$tests['json aggregate window edge rejects invalid object dispatch function'] = static function (TestRunner $t) use ($objectRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupObjectWindowFrameRowsSqlFunction('jsonb_group', $objectRows, 1, 1));
};

return $tests;
