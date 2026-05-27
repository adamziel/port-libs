<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$tests = [];

$rows = [
    ['rowid' => 1, 'site' => 1, 'bucket' => 1.0, 'name' => 'siteurl', 'value' => 'siteurl', 'include' => 1],
    ['rowid' => 2, 'site' => 1, 'bucket' => 1.0, 'name' => 'home', 'value' => 'home', 'include' => 0],
    ['rowid' => 3, 'site' => 1, 'bucket' => 1.5, 'name' => 'blogname', 'value' => 'blogname', 'include' => 1],
    ['rowid' => 4, 'site' => 1, 'bucket' => 2.0, 'name' => 'theme_mods', 'value' => null, 'include' => 1],
    ['rowid' => 5, 'site' => 1, 'bucket' => 2.0, 'name' => 'rewrite_rules', 'value' => 'rewrite_rules', 'include' => null],
    ['rowid' => 6, 'site' => 1, 'bucket' => 2.75, 'name' => 'object_cache', 'value' => 'object_cache', 'include' => 1],
    ['rowid' => 7, 'site' => 2, 'bucket' => 1.0, 'name' => 'network_siteurl', 'value' => 'network_siteurl', 'include' => 1],
    ['rowid' => 8, 'site' => 2, 'bucket' => 1.25, 'name' => 'network_home', 'value' => 'network_home', 'include' => '0'],
    ['rowid' => 9, 'site' => 2, 'bucket' => 1.25, 'name' => 'network_plugin', 'value' => 'network_plugin', 'include' => 1],
    ['rowid' => 10, 'site' => 2, 'bucket' => 2.0, 'name' => 'network_cache', 'value' => 'network_cache', 'include' => 1],
];

$cursorAt = static function (string $unit, int|float $preceding, int|float $following, int $position, string $exclude = 'NO OTHERS') use ($rows): SQLiteVdbeWindowAggregateCursor {
    $cursor = new SQLiteVdbeWindowAggregateCursor(
        $rows,
        'value',
        ['site'],
        ['bucket'],
        'include',
        $preceding,
        $following,
        'D',
        [],
        'D',
        [],
        [],
        [],
        $unit,
        $exclude,
    );
    for ($i = 0; $i < $position; $i++) {
        $cursor->next();
    }

    return $cursor;
};

$valueCases = [
    'groups row1 first current peer' => ['GROUPS', 0, 1, 0, 'NO OTHERS', 'first', null, 'siteurl'],
    'groups row1 last next peer' => ['GROUPS', 0, 1, 0, 'NO OTHERS', 'last', null, 'blogname'],
    'groups row1 nth second keeps unfiltered peer' => ['GROUPS', 0, 1, 0, 'NO OTHERS', 'nth', 2, 'home'],
    'groups row1 nth third next group' => ['GROUPS', 0, 1, 0, 'NO OTHERS', 'nth', 3, 'blogname'],
    'groups row2 first same peer' => ['GROUPS', 0, 1, 1, 'NO OTHERS', 'first', null, 'siteurl'],
    'groups row2 last same next peer' => ['GROUPS', 0, 1, 1, 'NO OTHERS', 'last', null, 'blogname'],
    'groups row3 first singleton current' => ['GROUPS', 0, 1, 2, 'NO OTHERS', 'first', null, 'blogname'],
    'groups row3 last duplicate following' => ['GROUPS', 0, 1, 2, 'NO OTHERS', 'last', null, 'rewrite_rules'],
    'groups row3 nth second null value preserved' => ['GROUPS', 0, 1, 2, 'NO OTHERS', 'nth', 2, null],
    'groups row4 first duplicate peer null' => ['GROUPS', 0, 1, 3, 'NO OTHERS', 'first', null, null],
    'groups row4 last following singleton' => ['GROUPS', 0, 1, 3, 'NO OTHERS', 'last', null, 'object_cache'],
    'groups row5 first duplicate peer null' => ['GROUPS', 0, 1, 4, 'NO OTHERS', 'first', null, null],
    'groups row5 nth second duplicate peer' => ['GROUPS', 0, 1, 4, 'NO OTHERS', 'nth', 2, 'rewrite_rules'],
    'groups row6 first tail singleton' => ['GROUPS', 0, 1, 5, 'NO OTHERS', 'first', null, 'object_cache'],
    'groups row6 nth second tail missing' => ['GROUPS', 0, 1, 5, 'NO OTHERS', 'nth', 2, null],
    'groups row7 first partition isolated' => ['GROUPS', 0, 1, 6, 'NO OTHERS', 'first', null, 'network_siteurl'],
    'groups row7 last duplicate following partition' => ['GROUPS', 0, 1, 6, 'NO OTHERS', 'last', null, 'network_plugin'],
    'groups row8 first duplicate network peer' => ['GROUPS', 0, 1, 7, 'NO OTHERS', 'first', null, 'network_home'],
    'groups row8 nth second network peer' => ['GROUPS', 0, 1, 7, 'NO OTHERS', 'nth', 2, 'network_plugin'],
    'groups row9 last next network group' => ['GROUPS', 0, 1, 8, 'NO OTHERS', 'last', null, 'network_cache'],
    'groups row10 last partition tail' => ['GROUPS', 0, 1, 9, 'NO OTHERS', 'last', null, 'network_cache'],
    'groups exclude current row1 first peer shifts' => ['GROUPS', 0, 1, 0, 'CURRENT ROW', 'first', null, 'home'],
    'groups exclude current row2 first keeps row1' => ['GROUPS', 0, 1, 1, 'CURRENT ROW', 'first', null, 'siteurl'],
    'groups exclude current row4 first duplicate row5' => ['GROUPS', 0, 1, 3, 'CURRENT ROW', 'first', null, 'rewrite_rules'],
    'groups exclude group row1 first next group' => ['GROUPS', 0, 1, 0, 'GROUP', 'first', null, 'blogname'],
    'groups exclude group row6 empties tail' => ['GROUPS', 0, 1, 5, 'GROUP', 'first', null, null],
    'groups exclude ties row1 keeps current then next' => ['GROUPS', 0, 1, 0, 'TIES', 'last', null, 'blogname'],
    'groups exclude ties row2 keeps current identity' => ['GROUPS', 0, 1, 1, 'TIES', 'first', null, 'home'],
    'groups preceding current row4 first previous singleton' => ['GROUPS', 1, 0, 3, 'NO OTHERS', 'first', null, 'blogname'],
    'groups preceding current row4 last duplicate peer' => ['GROUPS', 1, 0, 3, 'NO OTHERS', 'last', null, 'rewrite_rules'],
    'range row1 first current peer' => ['RANGE', 0.0, 0.5, 0, 'NO OTHERS', 'first', null, 'siteurl'],
    'range row1 last exact boundary' => ['RANGE', 0.0, 0.5, 0, 'NO OTHERS', 'last', null, 'blogname'],
    'range row1 nth second unfiltered peer' => ['RANGE', 0.0, 0.5, 0, 'NO OTHERS', 'nth', 2, 'home'],
    'range row3 first current singleton' => ['RANGE', 0.0, 0.5, 2, 'NO OTHERS', 'first', null, 'blogname'],
    'range row3 last duplicate following' => ['RANGE', 0.0, 0.5, 2, 'NO OTHERS', 'last', null, 'rewrite_rules'],
    'range row4 first duplicate peer null' => ['RANGE', 0.0, 0.5, 3, 'NO OTHERS', 'first', null, null],
    'range row4 last peer only for narrow following' => ['RANGE', 0.0, 0.5, 3, 'NO OTHERS', 'last', null, 'rewrite_rules'],
    'range row4 wider following reaches object cache' => ['RANGE', 0.0, 0.75, 3, 'NO OTHERS', 'last', null, 'object_cache'],
    'range row7 first network current' => ['RANGE', 0.0, 0.25, 6, 'NO OTHERS', 'first', null, 'network_siteurl'],
    'range row7 last boundary duplicate' => ['RANGE', 0.0, 0.25, 6, 'NO OTHERS', 'last', null, 'network_plugin'],
    'range row8 first duplicate network peer' => ['RANGE', 0.0, 0.25, 7, 'NO OTHERS', 'first', null, 'network_home'],
    'range row8 last no 2.0 outside band' => ['RANGE', 0.0, 0.25, 7, 'NO OTHERS', 'last', null, 'network_plugin'],
    'range row8 wider reaches network cache' => ['RANGE', 0.0, 0.75, 7, 'NO OTHERS', 'last', null, 'network_cache'],
    'range exclude current row1 first peer shifts' => ['RANGE', 0.0, 0.5, 0, 'CURRENT ROW', 'first', null, 'home'],
    'range exclude group row1 first next key' => ['RANGE', 0.0, 0.5, 0, 'GROUP', 'first', null, 'blogname'],
    'range exclude ties row2 first current identity' => ['RANGE', 0.0, 0.5, 1, 'TIES', 'first', null, 'home'],
    'range preceding current row3 first previous peer' => ['RANGE', 0.5, 0.0, 2, 'NO OTHERS', 'first', null, 'siteurl'],
    'range preceding current row3 last current' => ['RANGE', 0.5, 0.0, 2, 'NO OTHERS', 'last', null, 'blogname'],
    'range preceding current row10 first previous network peer' => ['RANGE', 0.75, 0.0, 9, 'NO OTHERS', 'first', null, 'network_home'],
    'range preceding current row10 nth third current' => ['RANGE', 0.75, 0.0, 9, 'NO OTHERS', 'nth', 3, 'network_cache'],
];

foreach ($valueCases as $name => [$unit, $preceding, $following, $position, $exclude, $method, $nth, $expected]) {
    $tests['vdbe window value groups range current next50 ' . $name] = static function (TestRunner $t) use ($cursorAt, $unit, $preceding, $following, $position, $exclude, $method, $nth, $expected): void {
        $cursor = $cursorAt($unit, $preceding, $following, $position, $exclude);
        $actual = match ($method) {
            'first' => $cursor->firstValue(),
            'last' => $cursor->lastValue(),
            'nth' => $cursor->nthValue((int) $nth),
        };
        $t->same($expected, $actual);
    };
}

$summaryCases = [
    'summary includes first value' => [static fn () => $cursorAt('GROUPS', 0, 1, 0)->currentSummary()['firstValue'], 'siteurl'],
    'summary includes last value' => [static fn () => $cursorAt('GROUPS', 0, 1, 0)->currentSummary()['lastValue'], 'blogname'],
    'summary includes second value' => [static fn () => $cursorAt('GROUPS', 0, 1, 0)->currentSummary()['nthValue'], 'home'],
    'summary reports null first value' => [static fn () => $cursorAt('GROUPS', 0, 1, 3)->currentSummary()['firstValue'], null],
    'drain first values follow groups frames' => [static fn () => array_column($cursorAt('GROUPS', 0, 1, 0)->drainSummaries(), 'firstValue'), ['siteurl', 'siteurl', 'blogname', null, null, 'object_cache', 'network_siteurl', 'network_home', 'network_home', 'network_cache']],
    'drain last values follow groups frames' => [static fn () => array_column($cursorAt('GROUPS', 0, 1, 0)->drainSummaries(), 'lastValue'), ['blogname', 'blogname', 'rewrite_rules', 'object_cache', 'object_cache', 'object_cache', 'network_plugin', 'network_cache', 'network_cache', 'network_cache']],
    'drain nth values follow groups frames' => [static fn () => array_column($cursorAt('GROUPS', 0, 1, 0)->drainSummaries(), 'nthValue'), ['home', 'home', null, 'rewrite_rules', 'rewrite_rules', null, 'network_home', 'network_plugin', 'network_plugin', null]],
    'range drain first values follow numeric band' => [static fn () => array_column($cursorAt('RANGE', 0.0, 0.5, 0)->drainSummaries(), 'firstValue'), ['siteurl', 'siteurl', 'blogname', null, null, 'object_cache', 'network_siteurl', 'network_home', 'network_home', 'network_cache']],
    'range drain last values follow numeric band' => [static fn () => array_column($cursorAt('RANGE', 0.0, 0.5, 0)->drainSummaries(), 'lastValue'), ['blogname', 'blogname', 'rewrite_rules', 'rewrite_rules', 'rewrite_rules', 'object_cache', 'network_plugin', 'network_plugin', 'network_plugin', 'network_cache']],
    'range drain nth values preserve unfiltered false rows' => [static fn () => array_column($cursorAt('RANGE', 0.0, 0.5, 0)->drainSummaries(), 'nthValue'), ['home', 'home', null, 'rewrite_rules', 'rewrite_rules', null, 'network_home', 'network_plugin', 'network_plugin', null]],
];

foreach ($summaryCases as $name => [$callback, $expected]) {
    $tests['vdbe window value groups range current next50 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['vdbe window value groups range current next50 rejects zero nth value'] = static function (TestRunner $t) use ($cursorAt): void {
    $t->throws(InvalidArgumentException::class, static fn () => $cursorAt('GROUPS', 0, 1, 0)->nthValue(0));
};

$tests['vdbe window value groups range current next50 rejects negative nth value'] = static function (TestRunner $t) use ($cursorAt): void {
    $t->throws(InvalidArgumentException::class, static fn () => $cursorAt('RANGE', 0.0, 0.5, 0)->nthValue(-1));
};

return $tests;
