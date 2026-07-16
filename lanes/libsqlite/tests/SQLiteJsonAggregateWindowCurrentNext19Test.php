<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonAggregateState;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

$tests = [];

$makeState = static function (): SQLiteJsonAggregateState {
    $state = new SQLiteJsonAggregateState();
    $state->stepArrayWindowFrame('siteurl', 10, 1);
    $state->stepArrayWindowFrame(new SQLiteJsonSubtypeValue('{"plugin":"seo"}'), 20, true);
    $state->stepArrayWindowFrame('home', 20, 0);
    $state->stepArrayWindowFrame(new SQLiteBlobValue(SQLiteJsonB::encode(['cache' => true])), 30, '1');
    $state->stepArrayWindowFrame('blogname', 40, null);

    $state->stepObjectWindowFrame('siteurl', 'https://example.test', 10, 1);
    $state->stepObjectWindowFrame('rules', new SQLiteJsonSubtypeValue('{"plugin":"seo"}'), 20, true);
    $state->stepObjectWindowFrame('home', 'https://example.test/home', 20, 0);
    $state->stepObjectWindowFrame('cache', new SQLiteBlobValue(SQLiteJsonB::encode(['enabled' => true])), 30, '1');
    $state->stepObjectWindowFrame('blogname', 'Example', 40, null);

    return $state;
};

$arrayCases = [
    'array current to next no others' => ['NO OTHERS', 1, ['["siteurl",{"plugin":"seo"}]', '[{"plugin":"seo"}]', '[{"cache":true}]', '[{"cache":true}]', '[]']],
    'array current to next current row exclusion' => ['CURRENT ROW', 1, ['[{"plugin":"seo"}]', '[]', '[{"cache":true}]', '[]', '[]']],
    'array current to next group exclusion' => ['GROUP', 1, ['[{"plugin":"seo"}]', '[]', '[{"cache":true}]', '[]', '[]']],
    'array current to next ties exclusion' => ['TIES', 1, ['["siteurl",{"plugin":"seo"}]', '[{"plugin":"seo"}]', '[{"cache":true}]', '[{"cache":true}]', '[]']],
    'array current to two following' => ['NO OTHERS', 2, ['["siteurl",{"plugin":"seo"}]', '[{"plugin":"seo"},{"cache":true}]', '[{"cache":true}]', '[{"cache":true}]', '[]']],
    'array lowercase exclude current row' => ['current row', 1, ['[{"plugin":"seo"}]', '[]', '[{"cache":true}]', '[]', '[]']],
];

foreach ($arrayCases as $name => [$exclude, $following, $expected]) {
    $tests['json aggregate window current next19 state ' . $name] = static function (TestRunner $t) use ($makeState, $exclude, $following, $expected): void {
        $state = $makeState();
        $t->same($expected, $state->finalizeWindowFrameArray(0, $following, $exclude));
    };
}

$objectCases = [
    'object current to next no others' => ['NO OTHERS', ['{"siteurl":"https://example.test","rules":{"plugin":"seo"}}', '{"rules":{"plugin":"seo"}}', '{"cache":{"enabled":true}}', '{"cache":{"enabled":true}}', '{}']],
    'object current to next current row exclusion' => ['CURRENT ROW', ['{"rules":{"plugin":"seo"}}', '{}', '{"cache":{"enabled":true}}', '{}', '{}']],
    'object current to next group exclusion' => ['GROUP', ['{"rules":{"plugin":"seo"}}', '{}', '{"cache":{"enabled":true}}', '{}', '{}']],
    'object current to next ties exclusion' => ['TIES', ['{"siteurl":"https://example.test","rules":{"plugin":"seo"}}', '{"rules":{"plugin":"seo"}}', '{"cache":{"enabled":true}}', '{"cache":{"enabled":true}}', '{}']],
    'object blank exclude defaults no others' => ['', ['{"siteurl":"https://example.test","rules":{"plugin":"seo"}}', '{"rules":{"plugin":"seo"}}', '{"cache":{"enabled":true}}', '{"cache":{"enabled":true}}', '{}']],
];

foreach ($objectCases as $name => [$exclude, $expected]) {
    $tests['json aggregate window current next19 state ' . $name] = static function (TestRunner $t) use ($makeState, $exclude, $expected): void {
        $state = $makeState();
        $t->same($expected, $state->finalizeWindowFrameObject(0, 1, $exclude));
    };
}

$tests['json aggregate window current next19 state jsonb array dispatch'] = static function (TestRunner $t) use ($makeState): void {
    $frames = $makeState()->finalizeWindowFrameArray(0, 1, 'TIES', 'jsonb_group_array');
    $t->true($frames[0] instanceof SQLiteBlobValue);
    $t->same(['siteurl', ['plugin' => 'seo']], SQLiteJsonB::decode($frames[0]->bytes));
    $t->same([['cache' => true]], SQLiteJsonB::decode($frames[2]->bytes));
};

$tests['json aggregate window current next19 state jsonb object dispatch'] = static function (TestRunner $t) use ($makeState): void {
    $frames = $makeState()->finalizeWindowFrameObject(0, 1, 'CURRENT ROW', 'jsonb_group_object');
    $t->true($frames[0] instanceof SQLiteBlobValue);
    $t->same(['rules' => ['plugin' => 'seo']], SQLiteJsonB::decode($frames[0]->bytes));
    $t->same(['cache' => ['enabled' => true]], SQLiteJsonB::decode($frames[2]->bytes));
};

$tests['json aggregate window current next19 state summary counts frame rows'] = static function (TestRunner $t) use ($makeState): void {
    $summary = $makeState()->summary();
    $t->same(5, $summary['windowArrayFrameRows']);
    $t->same(5, $summary['windowObjectFrameRows']);
};

$tests['json aggregate window current next19 state appends plain window rows independently'] = static function (TestRunner $t): void {
    $state = new SQLiteJsonAggregateState();
    $state->stepArrayWindow('siteurl');
    $state->stepArrayWindowFrame('home', 1);
    $t->same(['["siteurl"]'], $state->finalizeWindowedArray(0));
    $t->same(['["home"]'], $state->finalizeWindowFrameArray(0));
};

$tests['json aggregate window current next19 state appends ordered window rows independently'] = static function (TestRunner $t): void {
    $state = new SQLiteJsonAggregateState();
    $state->stepArrayOrderByWindow('siteurl', 2);
    $state->stepArrayWindowFrame('home', 1);
    $t->same(['["siteurl"]'], $state->finalizeOrderedWindowedArray(0));
    $t->same(['["home"]'], $state->finalizeWindowFrameArray(0));
};

$tests['json aggregate window current next19 state object plain window rows independently'] = static function (TestRunner $t): void {
    $state = new SQLiteJsonAggregateState();
    $state->stepObjectWindow('siteurl', 'https://example.test');
    $state->stepObjectWindowFrame('home', 'https://example.test/home', 1);
    $t->same(['{"siteurl":"https://example.test"}'], $state->finalizeWindowedObject(0));
    $t->same(['{"home":"https://example.test/home"}'], $state->finalizeWindowFrameObject(0));
};

$tests['json aggregate window current next19 state object ordered window rows independently'] = static function (TestRunner $t): void {
    $state = new SQLiteJsonAggregateState();
    $state->stepObjectOrderByWindow('siteurl', 'https://example.test', 2);
    $state->stepObjectWindowFrame('home', 'https://example.test/home', 1);
    $t->same(['{"siteurl":"https://example.test"}'], $state->finalizeOrderedWindowedObject(0));
    $t->same(['{"home":"https://example.test/home"}'], $state->finalizeWindowFrameObject(0));
};

$tests['json aggregate window current next19 state filtered current sees following row'] = static function (TestRunner $t): void {
    $state = new SQLiteJsonAggregateState();
    $state->stepArrayWindowFrame('filtered', 1, 0);
    $state->stepArrayWindowFrame('next', 2, 1);
    $t->same(['["next"]', '["next"]'], $state->finalizeWindowFrameArray(0, 1));
};

$tests['json aggregate window current next19 state filtered object current sees following row'] = static function (TestRunner $t): void {
    $state = new SQLiteJsonAggregateState();
    $state->stepObjectWindowFrame('filtered', 1, 1, 0);
    $state->stepObjectWindowFrame('next', 2, 2, 1);
    $t->same(['{"next":2}', '{"next":2}'], $state->finalizeWindowFrameObject(0, 1));
};

$tests['json aggregate window current next19 state peer ties uses ordered row position'] = static function (TestRunner $t): void {
    $state = new SQLiteJsonAggregateState();
    $state->stepArrayWindowFrame('b', 1);
    $state->stepArrayWindowFrame('a', 1);
    $state->stepArrayWindowFrame('c', 2);
    $t->same(['["b"]', '["a","c"]', '["c"]'], $state->finalizeWindowFrameArray(0, 1, 'TIES'));
};

$tests['json aggregate window current next19 state peer group excludes following peer'] = static function (TestRunner $t): void {
    $state = new SQLiteJsonAggregateState();
    $state->stepArrayWindowFrame('b', 1);
    $state->stepArrayWindowFrame('a', 1);
    $state->stepArrayWindowFrame('c', 2);
    $t->same(['[]', '["c"]', '[]'], $state->finalizeWindowFrameArray(0, 1, 'GROUP'));
};

$tests['json aggregate window current next19 state numeric string filter participates in current next frame'] = static function (TestRunner $t): void {
    $state = new SQLiteJsonAggregateState();
    $state->stepArrayWindowFrame('zero', 1, '0');
    $state->stepArrayWindowFrame('half', 2, '0.5');
    $state->stepArrayWindowFrame('blank', 3, '   ');
    $t->same(['["half"]', '["half"]', '[]'], $state->finalizeWindowFrameArray(0, 1));
    $t->same(3, $state->summary()['windowArrayFrameRows']);
};

$tests['json aggregate window current next19 state object jsonb final frame decodes empty object'] = static function (TestRunner $t): void {
    $state = new SQLiteJsonAggregateState();
    $state->stepObjectWindowFrame('kept', 1, 1, true);
    $state->stepObjectWindowFrame('filtered', 2, 2, false);
    $frames = $state->finalizeWindowFrameObject(0, 0, 'NO OTHERS', 'JSONB_GROUP_OBJECT');
    $t->same(['kept' => 1], SQLiteJsonB::decode($frames[0]->bytes));
    $t->same([], SQLiteJsonB::decode($frames[1]->bytes));
};

$tests['json aggregate window current next19 state duplicate object labels preserve frame text'] = static function (TestRunner $t): void {
    $state = new SQLiteJsonAggregateState();
    $state->stepObjectWindowFrame('dup', 1, 1);
    $state->stepObjectWindowFrame('dup', 2, 2);
    $t->same(['{"dup":1,"dup":2}', '{"dup":2}'], $state->finalizeWindowFrameObject(0, 1));
};

$tests['json aggregate window current next19 state empty frame arrays return no rows'] = static function (TestRunner $t): void {
    $state = new SQLiteJsonAggregateState();
    $t->same([], $state->finalizeWindowFrameArray(0, 1));
};

$tests['json aggregate window current next19 state empty frame objects return no rows'] = static function (TestRunner $t): void {
    $state = new SQLiteJsonAggregateState();
    $t->same([], $state->finalizeWindowFrameObject(0, 1));
};

$tests['json aggregate window current next19 state rejects invalid array dispatch'] = static function (TestRunner $t) use ($makeState): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeState()->finalizeWindowFrameArray(0, 1, 'NO OTHERS', 'json_group'));
};

$tests['json aggregate window current next19 state rejects invalid object dispatch'] = static function (TestRunner $t) use ($makeState): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeState()->finalizeWindowFrameObject(0, 1, 'NO OTHERS', 'json_group'));
};

$tests['json aggregate window current next19 state rejects invalid exclude'] = static function (TestRunner $t) use ($makeState): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeState()->finalizeWindowFrameArray(0, 1, 'SIDEWAYS'));
};

$tests['json aggregate window current next19 state rejects negative following'] = static function (TestRunner $t) use ($makeState): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeState()->finalizeWindowFrameObject(0, -1));
};

return $tests;
