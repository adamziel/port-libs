<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonAggregate;
use PortLibs\LibSqlite\SQLiteJsonAggregateState;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

$tests = [];

$key = static fn (mixed ...$terms): array => array_map(
    static fn (array $term): array => [
        'value' => $term[0],
        'direction' => $term[1] ?? 'ASC',
    ],
    $terms,
);

$rows = [
    ['theme_mods', $key([20, 'DESC'], ['b', 'ASC'])],
    ['siteurl', $key([30, 'DESC'], ['a', 'ASC'])],
    ['theme_mods', $key([30, 'DESC'], ['z', 'ASC'])],
    ['blogname', $key([40, 'DESC'], ['c', 'ASC'])],
    ['plugin_rules', $key([50, 'DESC'], ['b', 'ASC'])],
    ['plugin_queue', $key([50, 'DESC'], ['a', 'ASC'])],
    ['plugin_rules', $key([45, 'DESC'], ['z', 'ASC'])],
    ['empty_option', $key([null, 'DESC'], ['n', 'ASC'])],
];

$tests['json aggregate distinct order current source next102 array composite desc asc'] = static function (TestRunner $t) use ($rows): void {
    $t->same(
        '["plugin_queue","plugin_rules","blogname","siteurl","theme_mods","empty_option"]',
        SQLiteJsonAggregate::jsonGroupArrayDistinctOrderBy($rows),
    );
};

$tests['json aggregate distinct order current source next102 array composite desc desc'] = static function (TestRunner $t) use ($key): void {
    $rows = [
        ['theme_mods', $key([20, 'DESC'], ['b', 'DESC'])],
        ['siteurl', $key([30, 'DESC'], ['a', 'DESC'])],
        ['theme_mods', $key([30, 'DESC'], ['z', 'DESC'])],
        ['plugin_rules', $key([50, 'DESC'], ['b', 'DESC'])],
        ['plugin_queue', $key([50, 'DESC'], ['a', 'DESC'])],
    ];

    $t->same('["plugin_rules","plugin_queue","theme_mods","siteurl"]', SQLiteJsonAggregate::jsonGroupArrayDistinctOrderBy($rows));
};

$tests['json aggregate distinct order current source next102 array composite asc null first'] = static function (TestRunner $t) use ($key): void {
    $rows = [
        ['empty_option', $key([null, 'ASC'], ['n', 'ASC'])],
        ['zero_option', $key([0, 'ASC'], ['z', 'ASC'])],
        ['siteurl', $key([30, 'ASC'], ['a', 'ASC'])],
        ['theme_mods', $key([20, 'ASC'], ['b', 'ASC'])],
        ['theme_mods', $key([30, 'ASC'], ['z', 'ASC'])],
    ];

    $t->same('["empty_option","zero_option","theme_mods","siteurl"]', SQLiteJsonAggregate::jsonGroupArrayDistinctOrderBy($rows));
};

$tests['json aggregate distinct order current source next102 jsonb composite decodes'] = static function (TestRunner $t) use ($rows): void {
    $jsonb = SQLiteJsonAggregate::jsonGroupArrayDistinctOrderBySqlFunction('jsonb_group_array', $rows);

    $t->true($jsonb instanceof SQLiteBlobValue);
    $t->same(['plugin_queue', 'plugin_rules', 'blogname', 'siteurl', 'theme_mods', 'empty_option'], SQLiteJsonB::decode($jsonb->bytes));
};

$tests['json aggregate distinct order current source next102 state array composite keys'] = static function (TestRunner $t) use ($key): void {
    $state = new SQLiteJsonAggregateState();
    $state->stepArrayDistinctOrderBy('theme_mods', $key([20, 'DESC'], ['b', 'ASC']));
    $state->stepArrayDistinctOrderBy('siteurl', $key([30, 'DESC'], ['a', 'ASC']));
    $state->stepArrayDistinctOrderBy('theme_mods', $key([30, 'DESC'], ['z', 'ASC']));
    $state->stepArrayDistinctOrderBy('plugin_rules', $key([50, 'DESC'], ['b', 'ASC']));
    $state->stepArrayDistinctOrderBy('plugin_queue', $key([50, 'DESC'], ['a', 'ASC']));

    $t->same('["plugin_queue","plugin_rules","siteurl","theme_mods"]', $state->finalizeDistinctOrderedArray());
};

$tests['json aggregate distinct order current source next102 object composite desc asc'] = static function (TestRunner $t) use ($key): void {
    $rows = [
        ['theme_mods', 'old', $key([20, 'DESC'], ['b', 'ASC'])],
        ['siteurl', 'site', $key([30, 'DESC'], ['a', 'ASC'])],
        ['theme_mods', 'new', $key([30, 'DESC'], ['z', 'ASC'])],
        ['plugin_rules', 'rules-late', $key([50, 'DESC'], ['b', 'ASC'])],
        ['plugin_queue', 'queue', $key([50, 'DESC'], ['a', 'ASC'])],
        ['plugin_rules', 'rules-early', $key([45, 'DESC'], ['z', 'ASC'])],
    ];

    $t->same(
        '{"plugin_queue":"queue","plugin_rules":"rules-late","plugin_rules":"rules-early","siteurl":"site","theme_mods":"new","theme_mods":"old"}',
        SQLiteJsonAggregate::jsonGroupObjectDistinctOrderBy($rows),
    );
};

$tests['json aggregate distinct order current source next102 object jsonb composite decodes'] = static function (TestRunner $t) use ($key): void {
    $rows = [
        ['siteurl', 'site', $key([30, 'DESC'], ['a', 'ASC'])],
        ['theme_mods', 'new', $key([30, 'DESC'], ['z', 'ASC'])],
        ['plugin_rules', 'rules', $key([50, 'DESC'], ['b', 'ASC'])],
        ['plugin_queue', 'queue', $key([50, 'DESC'], ['a', 'ASC'])],
    ];
    $jsonb = SQLiteJsonAggregate::jsonGroupObjectDistinctOrderBySqlFunction('jsonb_group_object', $rows);

    $t->true($jsonb instanceof SQLiteBlobValue);
    $t->same(['plugin_queue' => 'queue', 'plugin_rules' => 'rules', 'siteurl' => 'site', 'theme_mods' => 'new'], SQLiteJsonB::decode($jsonb->bytes));
};

$tests['json aggregate distinct order current source next102 window rows composite groups'] = static function (TestRunner $t) use ($key): void {
    $frames = SQLiteJsonAggregate::jsonGroupArrayDistinctOrderByWindowFrameRowsByUnit([
        ['siteurl', $key([1, 'ASC'], ['b', 'ASC'])],
        ['home', $key([1, 'ASC'], ['a', 'ASC'])],
        ['siteurl', $key([2, 'ASC'], ['z', 'ASC'])],
        ['plugin_rules', $key([2, 'ASC'], ['a', 'ASC'])],
    ], 'GROUPS', 0, 1);

    $t->same(['["home","siteurl"]', '["siteurl","plugin_rules"]', '["plugin_rules","siteurl"]', '["siteurl"]'], $frames);
};

$tests['json aggregate distinct order current source next102 window object composite groups'] = static function (TestRunner $t) use ($key): void {
    $frames = SQLiteJsonAggregate::jsonGroupObjectDistinctOrderByWindowFrameRowsByUnit([
        ['siteurl', 'site', $key([1, 'ASC'], ['b', 'ASC'])],
        ['home', 'home', $key([1, 'ASC'], ['a', 'ASC'])],
        ['siteurl', 'site-late', $key([2, 'ASC'], ['z', 'ASC'])],
        ['plugin_rules', 'rules', $key([2, 'ASC'], ['a', 'ASC'])],
    ], 'GROUPS', 0, 1);

    $t->same(['{"home":"home","siteurl":"site"}', '{"siteurl":"site","plugin_rules":"rules"}', '{"plugin_rules":"rules","siteurl":"site-late"}', '{"siteurl":"site-late"}'], $frames);
};

$tests['json aggregate distinct order current source next102 rejects mismatched composite key counts'] = static function (TestRunner $t) use ($key): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArrayDistinctOrderBy([
        ['siteurl', $key([1, 'ASC'])],
        ['home', $key([1, 'ASC'], ['a', 'ASC'])],
    ]));
};

$tests['json aggregate distinct order current source next102 rejects malformed composite direction'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArrayDistinctOrderBy([
        ['siteurl', [['value' => 1, 'direction' => 'SIDEWAYS']]],
    ]));
};

$tests['json aggregate distinct order current source next102 subtype distinct remains ordered'] = static function (TestRunner $t) use ($key): void {
    $rows = [
        [new SQLiteJsonSubtypeValue('{"name":"theme"}'), $key([1, 'ASC'], ['z', 'ASC'])],
        [new SQLiteJsonSubtypeValue('{"name":"site"}'), $key([1, 'ASC'], ['a', 'ASC'])],
        [new SQLiteJsonSubtypeValue('{"name":"theme"}'), $key([0, 'ASC'], ['a', 'ASC'])],
    ];

    $t->same('[{"name":"theme"},{"name":"site"}]', SQLiteJsonAggregate::jsonGroupArrayDistinctOrderBy($rows));
};

$variants = [
    'priority desc tie asc' => [$rows, '["plugin_queue","plugin_rules","blogname","siteurl","theme_mods","empty_option"]'],
    'priority desc tie desc' => [
        [
            ['theme_mods', $key([20, 'DESC'], ['b', 'DESC'])],
            ['siteurl', $key([30, 'DESC'], ['a', 'DESC'])],
            ['theme_mods', $key([30, 'DESC'], ['z', 'DESC'])],
            ['plugin_rules', $key([50, 'DESC'], ['b', 'DESC'])],
            ['plugin_queue', $key([50, 'DESC'], ['a', 'DESC'])],
        ],
        '["plugin_rules","plugin_queue","theme_mods","siteurl"]',
    ],
    'tie asc priority desc' => [
        [
            ['theme_mods', $key(['b', 'ASC'], [20, 'DESC'])],
            ['siteurl', $key(['a', 'ASC'], [30, 'DESC'])],
            ['theme_mods', $key(['z', 'ASC'], [30, 'DESC'])],
            ['plugin_rules', $key(['b', 'ASC'], [50, 'DESC'])],
            ['plugin_queue', $key(['a', 'ASC'], [50, 'DESC'])],
        ],
        '["plugin_queue","siteurl","plugin_rules","theme_mods"]',
    ],
    'three terms' => [
        [
            ['theme_mods', $key(['yes', 'DESC'], [20, 'DESC'], ['b', 'ASC'])],
            ['siteurl', $key(['yes', 'DESC'], [30, 'DESC'], ['a', 'ASC'])],
            ['plugin_rules', $key(['no', 'DESC'], [50, 'DESC'], ['b', 'ASC'])],
            ['plugin_queue', $key(['no', 'DESC'], [50, 'DESC'], ['a', 'ASC'])],
        ],
        '["siteurl","theme_mods","plugin_queue","plugin_rules"]',
    ],
];

foreach ($variants as $name => [$variantRows, $expected]) {
    $tests['json aggregate distinct order current source next102 variant ' . $name] = static function (TestRunner $t) use ($variantRows, $expected): void {
        $t->same($expected, SQLiteJsonAggregate::jsonGroupArrayDistinctOrderBy($variantRows));
    };
}

return $tests;
