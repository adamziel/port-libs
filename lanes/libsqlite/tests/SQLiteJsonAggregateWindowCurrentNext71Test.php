<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonAggregate;
use PortLibs\LibSqlite\SQLiteJsonAggregateState;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

$tests = [];

$rows = [
    ['siteurl', 10, 1],
    [new SQLiteJsonSubtypeValue('{"plugin":"seo"}'), 20, true],
    ['home', 20, 0],
    [new SQLiteBlobValue(SQLiteJsonB::encode(['cache' => true])), 30, '1'],
    ['blogname', 40, null],
    ['theme_mods', 45, 1],
];

$pairs = [
    ['siteurl', 'https://example.test', 10, 1],
    ['rules', new SQLiteJsonSubtypeValue('{"plugin":"seo"}'), 20, true],
    ['home', 'https://example.test/home', 20, 0],
    ['cache', new SQLiteBlobValue(SQLiteJsonB::encode(['enabled' => true])), 30, '1'],
    ['blogname', 'Example', 40, null],
    ['theme_mods', new SQLiteJsonSubtypeValue('{"theme":"twentytwenty"}'), 45, 1],
];

$stateFor = static function () use ($rows, $pairs): SQLiteJsonAggregateState {
    $state = new SQLiteJsonAggregateState();
    foreach ($rows as $row) {
        $state->stepArrayWindowFrame($row[0], $row[1], $row[2]);
    }
    foreach ($pairs as $row) {
        $state->stepObjectWindowFrame($row[0], $row[1], $row[2], $row[3]);
    }

    return $state;
};

$arrayCases = [
    'rows current next keeps physical next row' => ['ROWS', 0, 1, 'NO OTHERS', ['["siteurl",{"plugin":"seo"}]', '[{"plugin":"seo"}]', '[{"cache":true}]', '[{"cache":true}]', '["theme_mods"]', '["theme_mods"]']],
    'groups current next includes full following peer group' => ['GROUPS', 0, 1, 'NO OTHERS', ['["siteurl",{"plugin":"seo"}]', '[{"plugin":"seo"},{"cache":true}]', '[{"plugin":"seo"},{"cache":true}]', '[{"cache":true}]', '["theme_mods"]', '["theme_mods"]']],
    'groups exclude current keeps current peers and following group' => ['GROUPS', 0, 1, 'CURRENT ROW', ['[{"plugin":"seo"}]', '[{"cache":true}]', '[{"plugin":"seo"},{"cache":true}]', '[]', '["theme_mods"]', '[]']],
    'groups exclude group removes current peers' => ['GROUPS', 0, 1, 'GROUP', ['[{"plugin":"seo"}]', '[{"cache":true}]', '[{"cache":true}]', '[]', '["theme_mods"]', '[]']],
    'groups exclude ties keeps current peer only' => ['GROUPS', 0, 1, 'TIES', ['["siteurl",{"plugin":"seo"}]', '[{"plugin":"seo"},{"cache":true}]', '[{"cache":true}]', '[{"cache":true}]', '["theme_mods"]', '["theme_mods"]']],
    'range current ten reaches numeric next band' => ['RANGE', 0, 10, 'NO OTHERS', ['["siteurl",{"plugin":"seo"}]', '[{"plugin":"seo"},{"cache":true}]', '[{"plugin":"seo"},{"cache":true}]', '[{"cache":true}]', '["theme_mods"]', '["theme_mods"]']],
    'range current fifteen reaches tail band' => ['RANGE', 0, 15, 'NO OTHERS', ['["siteurl",{"plugin":"seo"}]', '[{"plugin":"seo"},{"cache":true}]', '[{"plugin":"seo"},{"cache":true}]', '[{"cache":true},"theme_mods"]', '["theme_mods"]', '["theme_mods"]']],
    'range current ten exclude group removes peers' => ['RANGE', 0, 10, 'GROUP', ['[{"plugin":"seo"}]', '[{"cache":true}]', '[{"cache":true}]', '[]', '["theme_mods"]', '[]']],
    'range current ten exclude ties preserves current row' => ['RANGE', 0, 10, 'TIES', ['["siteurl",{"plugin":"seo"}]', '[{"plugin":"seo"},{"cache":true}]', '[{"cache":true}]', '[{"cache":true}]', '["theme_mods"]', '["theme_mods"]']],
];

foreach ($arrayCases as $name => [$unit, $preceding, $following, $exclude, $expected]) {
    $tests['json aggregate window current next71 array ' . $name] = static function (TestRunner $t) use ($stateFor, $unit, $preceding, $following, $exclude, $expected): void {
        $t->same($expected, $stateFor()->finalizeWindowFrameArrayByUnit($unit, $preceding, $following, $exclude));
    };
}

$objectCases = [
    'groups current next includes full peer object group' => ['GROUPS', 0, 1, 'NO OTHERS', ['{"siteurl":"https://example.test","rules":{"plugin":"seo"}}', '{"rules":{"plugin":"seo"},"cache":{"enabled":true}}', '{"rules":{"plugin":"seo"},"cache":{"enabled":true}}', '{"cache":{"enabled":true}}', '{"theme_mods":{"theme":"twentytwenty"}}', '{"theme_mods":{"theme":"twentytwenty"}}']],
    'groups exclude current keeps peer object labels' => ['GROUPS', 0, 1, 'CURRENT ROW', ['{"rules":{"plugin":"seo"}}', '{"cache":{"enabled":true}}', '{"rules":{"plugin":"seo"},"cache":{"enabled":true}}', '{}', '{"theme_mods":{"theme":"twentytwenty"}}', '{}']],
    'groups exclude group removes current object peers' => ['GROUPS', 0, 1, 'GROUP', ['{"rules":{"plugin":"seo"}}', '{"cache":{"enabled":true}}', '{"cache":{"enabled":true}}', '{}', '{"theme_mods":{"theme":"twentytwenty"}}', '{}']],
    'range current fifteen keeps tail object band' => ['RANGE', 0, 15, 'NO OTHERS', ['{"siteurl":"https://example.test","rules":{"plugin":"seo"}}', '{"rules":{"plugin":"seo"},"cache":{"enabled":true}}', '{"rules":{"plugin":"seo"},"cache":{"enabled":true}}', '{"cache":{"enabled":true},"theme_mods":{"theme":"twentytwenty"}}', '{"theme_mods":{"theme":"twentytwenty"}}', '{"theme_mods":{"theme":"twentytwenty"}}']],
    'range current ten exclude ties keeps current object peer' => ['RANGE', 0, 10, 'TIES', ['{"siteurl":"https://example.test","rules":{"plugin":"seo"}}', '{"rules":{"plugin":"seo"},"cache":{"enabled":true}}', '{"cache":{"enabled":true}}', '{"cache":{"enabled":true}}', '{"theme_mods":{"theme":"twentytwenty"}}', '{"theme_mods":{"theme":"twentytwenty"}}']],
];

foreach ($objectCases as $name => [$unit, $preceding, $following, $exclude, $expected]) {
    $tests['json aggregate window current next71 object ' . $name] = static function (TestRunner $t) use ($stateFor, $unit, $preceding, $following, $exclude, $expected): void {
        $t->same($expected, $stateFor()->finalizeWindowFrameObjectByUnit($unit, $preceding, $following, $exclude));
    };
}

$tests['json aggregate window current next71 array jsonb groups dispatch decodes peer frames'] = static function (TestRunner $t) use ($stateFor): void {
    $frames = $stateFor()->finalizeWindowFrameArrayByUnit('GROUPS', 0, 1, 'NO OTHERS', 'jsonb_group_array');
    $t->true($frames[1] instanceof SQLiteBlobValue);
    $t->same([['plugin' => 'seo'], ['cache' => true]], SQLiteJsonB::decode($frames[1]->bytes));
};

$tests['json aggregate window current next71 object jsonb range dispatch decodes tail frame'] = static function (TestRunner $t) use ($stateFor): void {
    $frames = $stateFor()->finalizeWindowFrameObjectByUnit('RANGE', 0, 15, 'NO OTHERS', 'jsonb_group_object');
    $t->true($frames[3] instanceof SQLiteBlobValue);
    $t->same(['cache' => ['enabled' => true], 'theme_mods' => ['theme' => 'twentytwenty']], SQLiteJsonB::decode($frames[3]->bytes));
};

$tests['json aggregate window current next71 direct static array groups matches state'] = static function (TestRunner $t) use ($rows, $stateFor): void {
    $t->same(
        $stateFor()->finalizeWindowFrameArrayByUnit('GROUPS', 0, 1),
        SQLiteJsonAggregate::jsonGroupArrayWindowFrameRowsByUnit($rows, 'GROUPS', 0, 1),
    );
};

$tests['json aggregate window current next71 direct static object range matches state'] = static function (TestRunner $t) use ($pairs, $stateFor): void {
    $t->same(
        $stateFor()->finalizeWindowFrameObjectByUnit('RANGE', 0, 15),
        SQLiteJsonAggregate::jsonGroupObjectWindowFrameRowsByUnit($pairs, 'RANGE', 0, 15),
    );
};

$tests['json aggregate window current next71 nonnumeric range falls back to peer group'] = static function (TestRunner $t): void {
    $rows = [['a', 'k'], ['b', 'k'], ['c', 'z']];
    $t->same(['["a","b"]', '["a","b"]', '["c"]'], SQLiteJsonAggregate::jsonGroupArrayWindowFrameRowsByUnit($rows, 'RANGE', 0, 1));
};

$tests['json aggregate window current next71 fractional range accepts real offsets'] = static function (TestRunner $t): void {
    $rows = [['a', 1.0], ['b', 1.25], ['c', 1.75], ['d', 2.0]];
    $t->same(['["a","b"]', '["b"]', '["c","d"]', '["d"]'], SQLiteJsonAggregate::jsonGroupArrayWindowFrameRowsByUnit($rows, 'RANGE', 0.0, 0.3));
};

$tests['json aggregate window current next71 rejects fractional rows frame'] = static function (TestRunner $t) use ($rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArrayWindowFrameRowsByUnit($rows, 'ROWS', 0.5, 1));
};

$tests['json aggregate window current next71 rejects fractional groups frame'] = static function (TestRunner $t) use ($pairs): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupObjectWindowFrameRowsByUnit($pairs, 'GROUPS', 0, 1.5));
};

$tests['json aggregate window current next71 rejects unknown frame unit'] = static function (TestRunner $t) use ($rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArrayWindowFrameRowsByUnit($rows, 'BANDS', 0, 1));
};

$tests['json aggregate window current next71 rejects negative range following'] = static function (TestRunner $t) use ($rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArrayWindowFrameRowsByUnit($rows, 'RANGE', 0, -1));
};

foreach (['NO OTHERS', 'CURRENT ROW', 'GROUP', 'TIES'] as $exclude) {
    $tests['json aggregate window current next71 groups object exclude mode remains jsonb encodable ' . strtolower(str_replace(' ', '-', $exclude))] = static function (TestRunner $t) use ($stateFor, $exclude): void {
        $frames = $stateFor()->finalizeWindowFrameObjectByUnit('GROUPS', 0, 1, $exclude, 'jsonb_group_object');
        $t->true($frames[0] instanceof SQLiteBlobValue);
        $t->true(is_array(SQLiteJsonB::decode($frames[0]->bytes)));
    };
}

$directArrayCases = [
    'rows one preceding current' => ['ROWS', 1, 0, 'NO OTHERS', ['["siteurl"]', '["siteurl",{"plugin":"seo"}]', '[{"plugin":"seo"}]', '[{"cache":true}]', '[{"cache":true}]', '["theme_mods"]']],
    'groups one preceding current' => ['GROUPS', 1, 0, 'NO OTHERS', ['["siteurl"]', '["siteurl",{"plugin":"seo"}]', '["siteurl",{"plugin":"seo"}]', '[{"plugin":"seo"},{"cache":true}]', '[{"cache":true}]', '["theme_mods"]']],
    'range ten preceding current' => ['RANGE', 10, 0, 'NO OTHERS', ['["siteurl"]', '["siteurl",{"plugin":"seo"}]', '["siteurl",{"plugin":"seo"}]', '[{"plugin":"seo"},{"cache":true}]', '[{"cache":true}]', '["theme_mods"]']],
    'rows one preceding exclude current' => ['ROWS', 1, 0, 'CURRENT ROW', ['[]', '["siteurl"]', '[{"plugin":"seo"}]', '[]', '[{"cache":true}]', '[]']],
    'groups one preceding exclude group' => ['GROUPS', 1, 0, 'GROUP', ['[]', '["siteurl"]', '["siteurl"]', '[{"plugin":"seo"}]', '[{"cache":true}]', '[]']],
    'range ten preceding exclude ties' => ['RANGE', 10, 0, 'TIES', ['["siteurl"]', '["siteurl",{"plugin":"seo"}]', '["siteurl"]', '[{"plugin":"seo"},{"cache":true}]', '[{"cache":true}]', '["theme_mods"]']],
];

foreach ($directArrayCases as $name => [$unit, $preceding, $following, $exclude, $expected]) {
    $tests['json aggregate window current next71 direct array ' . $name] = static function (TestRunner $t) use ($rows, $unit, $preceding, $following, $exclude, $expected): void {
        $t->same($expected, SQLiteJsonAggregate::jsonGroupArrayWindowFrameRowsByUnit($rows, $unit, $preceding, $following, $exclude));
    };
}

$directObjectCases = [
    'rows one preceding current' => ['ROWS', 1, 0, 'NO OTHERS', ['{"siteurl":"https://example.test"}', '{"siteurl":"https://example.test","rules":{"plugin":"seo"}}', '{"rules":{"plugin":"seo"}}', '{"cache":{"enabled":true}}', '{"cache":{"enabled":true}}', '{"theme_mods":{"theme":"twentytwenty"}}']],
    'groups one preceding current' => ['GROUPS', 1, 0, 'NO OTHERS', ['{"siteurl":"https://example.test"}', '{"siteurl":"https://example.test","rules":{"plugin":"seo"}}', '{"siteurl":"https://example.test","rules":{"plugin":"seo"}}', '{"rules":{"plugin":"seo"},"cache":{"enabled":true}}', '{"cache":{"enabled":true}}', '{"theme_mods":{"theme":"twentytwenty"}}']],
    'range ten preceding current' => ['RANGE', 10, 0, 'NO OTHERS', ['{"siteurl":"https://example.test"}', '{"siteurl":"https://example.test","rules":{"plugin":"seo"}}', '{"siteurl":"https://example.test","rules":{"plugin":"seo"}}', '{"rules":{"plugin":"seo"},"cache":{"enabled":true}}', '{"cache":{"enabled":true}}', '{"theme_mods":{"theme":"twentytwenty"}}']],
    'rows one preceding exclude current' => ['ROWS', 1, 0, 'CURRENT ROW', ['{}', '{"siteurl":"https://example.test"}', '{"rules":{"plugin":"seo"}}', '{}', '{"cache":{"enabled":true}}', '{}']],
    'groups one preceding exclude group' => ['GROUPS', 1, 0, 'GROUP', ['{}', '{"siteurl":"https://example.test"}', '{"siteurl":"https://example.test"}', '{"rules":{"plugin":"seo"}}', '{"cache":{"enabled":true}}', '{}']],
    'range ten preceding exclude ties' => ['RANGE', 10, 0, 'TIES', ['{"siteurl":"https://example.test"}', '{"siteurl":"https://example.test","rules":{"plugin":"seo"}}', '{"siteurl":"https://example.test"}', '{"rules":{"plugin":"seo"},"cache":{"enabled":true}}', '{"cache":{"enabled":true}}', '{"theme_mods":{"theme":"twentytwenty"}}']],
];

foreach ($directObjectCases as $name => [$unit, $preceding, $following, $exclude, $expected]) {
    $tests['json aggregate window current next71 direct object ' . $name] = static function (TestRunner $t) use ($pairs, $unit, $preceding, $following, $exclude, $expected): void {
        $t->same($expected, SQLiteJsonAggregate::jsonGroupObjectWindowFrameRowsByUnit($pairs, $unit, $preceding, $following, $exclude));
    };
}

return $tests;
