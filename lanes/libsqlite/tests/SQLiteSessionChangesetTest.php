<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSessionChangeset;

$columns = ['option_id', 'blog_id', 'option_name', 'option_value', 'autoload'];
$pk = ['option_id'];
$before = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => 'blogname', 'option_value' => 'Old Site', 'autoload' => 'yes'],
];
$after = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://new.test', 'autoload' => 'yes'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => 'blogname', 'option_value' => 'New Site', 'autoload' => 'no'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => 'new_plugin_setting', 'option_value' => '{"enabled":true}', 'autoload' => 'no'],
];

$changeset = static fn (): array => SQLiteSessionChangeset::diff('wp_options', $GLOBALS['columns'], $GLOBALS['pk'], $GLOBALS['before'], $GLOBALS['after']);
$roundTrip = static fn (): array => SQLiteSessionChangeset::decode(SQLiteSessionChangeset::encode([$changeset()]))[0];

return [
    'session changeset diff records table name' => static fn (TestRunner $t) => $t->same('wp_options', $changeset()['table']),
    'session changeset diff preserves column order' => static fn (TestRunner $t) => $t->same($GLOBALS['columns'], $changeset()['columns']),
    'session changeset diff records primary key' => static fn (TestRunner $t) => $t->same(['option_id'], $changeset()['primary_key']),
    'session changeset diff emits two updates' => static fn (TestRunner $t) => $t->same(['update', 'delete', 'update', 'insert'], array_column($changeset()['changes'], 'op')),
    'session changeset update carries old primary key' => static fn (TestRunner $t) => $t->same(1, $changeset()['changes'][0]['old']['option_id']),
    'session changeset update carries new value' => static fn (TestRunner $t) => $t->same('https://new.test', $changeset()['changes'][0]['new']['option_value']),
    'session changeset update marks unchanged columns undefined' => static fn (TestRunner $t) => $t->same(['undefined' => true], $changeset()['changes'][0]['old']['autoload']),
    'session changeset delete carries full old row' => static fn (TestRunner $t) => $t->same('home', $changeset()['changes'][1]['old']['option_name']),
    'session changeset insert carries full new row' => static fn (TestRunner $t) => $t->same('new_plugin_setting', $changeset()['changes'][3]['new']['option_name']),
    'session changeset skips unchanged rows' => static function (TestRunner $t): void {
        $diff = SQLiteSessionChangeset::diff('wp_options', $GLOBALS['columns'], $GLOBALS['pk'], [$GLOBALS['before'][0]], [$GLOBALS['before'][0]]);
        $t->same([], $diff['changes']);
    },
    'session changeset round trip decodes table header' => static fn (TestRunner $t) => $t->same('wp_options', $roundTrip()['table']),
    'session changeset round trip decodes primary key flags' => static fn (TestRunner $t) => $t->same(['c0'], $roundTrip()['primary_key']),
    'session changeset round trip decodes operation order' => static fn (TestRunner $t) => $t->same(['update', 'delete', 'update', 'insert'], array_column($roundTrip()['changes'], 'op')),
    'session changeset round trip decodes text values' => static fn (TestRunner $t) => $t->same('https://new.test', $roundTrip()['changes'][0]['new']['c3']),
    'session changeset round trip decodes integer values' => static fn (TestRunner $t) => $t->same(4, $roundTrip()['changes'][3]['new']['c0']),
    'session changeset round trip decodes null values' => static function (TestRunner $t): void {
        $payload = SQLiteSessionChangeset::encode([[
            'table' => 'wp_options',
            'columns' => ['option_id', 'option_name', 'option_value'],
            'primary_key' => ['option_id'],
            'changes' => [['op' => 'insert', 'new' => ['option_id' => 9, 'option_name' => 'empty', 'option_value' => null]]],
        ]]);
        $t->same(null, SQLiteSessionChangeset::decode($payload)[0]['changes'][0]['new']['c2']);
    },
    'session changeset round trip decodes blob values' => static function (TestRunner $t): void {
        $payload = SQLiteSessionChangeset::encode([[
            'table' => 'wp_options',
            'columns' => ['option_id', 'option_name', 'option_value'],
            'primary_key' => ['option_id'],
            'changes' => [['op' => 'insert', 'new' => ['option_id' => 9, 'option_name' => 'blob', 'option_value' => ['blob' => "\x00jsonb"]]]],
        ]]);
        $t->same(['blob' => "\x00jsonb"], SQLiteSessionChangeset::decode($payload)[0]['changes'][0]['new']['c2']);
    },
    'session changeset round trip decodes floats' => static function (TestRunner $t): void {
        $payload = SQLiteSessionChangeset::encode([[
            'table' => 'stats',
            'columns' => ['id', 'score'],
            'primary_key' => ['id'],
            'changes' => [['op' => 'insert', 'new' => ['id' => 1, 'score' => 3.5]]],
        ]]);
        $t->same(3.5, SQLiteSessionChangeset::decode($payload)[0]['changes'][0]['new']['c1']);
    },
    'session changeset apply updates matching rows' => static fn (TestRunner $t) => $t->same('https://new.test', SQLiteSessionChangeset::apply($GLOBALS['before'], $changeset())['rows'][0]['option_value']),
    'session changeset apply deletes matching rows' => static fn (TestRunner $t) => $t->same([1, 3, 4], array_column(SQLiteSessionChangeset::apply($GLOBALS['before'], $changeset())['rows'], 'option_id')),
    'session changeset apply inserts new rows' => static fn (TestRunner $t) => $t->same('new_plugin_setting', SQLiteSessionChangeset::apply($GLOBALS['before'], $changeset())['rows'][2]['option_name']),
    'session changeset apply records applied operations' => static fn (TestRunner $t) => $t->same(['update', 'delete', 'update', 'insert'], array_column(SQLiteSessionChangeset::apply($GLOBALS['before'], $changeset())['applied'], 'op')),
    'session changeset apply has no conflict for current source' => static fn (TestRunner $t) => $t->same([], SQLiteSessionChangeset::apply($GLOBALS['before'], $changeset())['conflicts']),
    'session changeset insert conflict is omitted by default' => static function (TestRunner $t): void {
        $change = ['table' => 'wp_options', 'columns' => $GLOBALS['columns'], 'primary_key' => $GLOBALS['pk'], 'changes' => [['op' => 'insert', 'new' => $GLOBALS['before'][0]]]];
        $t->same('conflict', SQLiteSessionChangeset::apply($GLOBALS['before'], $change)['conflicts'][0]['type']);
    },
    'session changeset insert conflict replace overwrites row' => static function (TestRunner $t): void {
        $row = $GLOBALS['before'][0];
        $row['option_value'] = 'replacement';
        $change = ['table' => 'wp_options', 'columns' => $GLOBALS['columns'], 'primary_key' => $GLOBALS['pk'], 'changes' => [['op' => 'insert', 'new' => $row]]];
        $t->same('replacement', SQLiteSessionChangeset::apply($GLOBALS['before'], $change, 'replace')['rows'][0]['option_value']);
    },
    'session changeset insert conflict abort throws' => static function (TestRunner $t): void {
        $change = ['table' => 'wp_options', 'columns' => $GLOBALS['columns'], 'primary_key' => $GLOBALS['pk'], 'changes' => [['op' => 'insert', 'new' => $GLOBALS['before'][0]]]];
        $t->throws(RuntimeException::class, static fn () => SQLiteSessionChangeset::apply($GLOBALS['before'], $change, 'abort'));
    },
    'session changeset delete notfound conflict is reported' => static function (TestRunner $t): void {
        $change = ['table' => 'wp_options', 'columns' => $GLOBALS['columns'], 'primary_key' => $GLOBALS['pk'], 'changes' => [['op' => 'delete', 'old' => ['option_id' => 99, 'blog_id' => 1, 'option_name' => 'missing', 'option_value' => 'x', 'autoload' => 'no']]]];
        $t->same('notfound', SQLiteSessionChangeset::apply($GLOBALS['before'], $change)['conflicts'][0]['type']);
    },
    'session changeset update notfound conflict is reported' => static function (TestRunner $t): void {
        $change = ['table' => 'wp_options', 'columns' => $GLOBALS['columns'], 'primary_key' => $GLOBALS['pk'], 'changes' => [['op' => 'update', 'old' => ['option_id' => 99], 'new' => ['option_id' => 99, 'option_value' => 'x']]]];
        $t->same('notfound', SQLiteSessionChangeset::apply($GLOBALS['before'], $change)['conflicts'][0]['type']);
    },
    'session changeset delete data conflict is reported' => static function (TestRunner $t): void {
        $old = $GLOBALS['before'][0];
        $old['option_value'] = 'not-current';
        $change = ['table' => 'wp_options', 'columns' => $GLOBALS['columns'], 'primary_key' => $GLOBALS['pk'], 'changes' => [['op' => 'delete', 'old' => $old]]];
        $t->same('data', SQLiteSessionChangeset::apply($GLOBALS['before'], $change)['conflicts'][0]['type']);
    },
    'session changeset update data conflict is reported' => static function (TestRunner $t): void {
        $old = $GLOBALS['before'][0];
        $old['option_value'] = 'not-current';
        $new = $old;
        $new['option_value'] = 'new';
        $change = ['table' => 'wp_options', 'columns' => $GLOBALS['columns'], 'primary_key' => $GLOBALS['pk'], 'changes' => [['op' => 'update', 'old' => $old, 'new' => $new]]];
        $t->same('data', SQLiteSessionChangeset::apply($GLOBALS['before'], $change)['conflicts'][0]['type']);
    },
    'session changeset composite primary keys match joined key' => static function (TestRunner $t): void {
        $diff = SQLiteSessionChangeset::diff('wp_sitemeta', ['site_id', 'meta_key', 'meta_value'], ['site_id', 'meta_key'], [['site_id' => 1, 'meta_key' => 'a', 'meta_value' => 'old']], [['site_id' => 1, 'meta_key' => 'a', 'meta_value' => 'new']]);
        $t->same('new', SQLiteSessionChangeset::apply([['site_id' => 1, 'meta_key' => 'a', 'meta_value' => 'old']], $diff)['rows'][0]['meta_value']);
    },
    'session changeset rejects missing primary key data' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteSessionChangeset::apply($GLOBALS['before'], ['table' => 'wp_options', 'columns' => $GLOBALS['columns'], 'primary_key' => $GLOBALS['pk'], 'changes' => [['op' => 'insert', 'new' => ['option_name' => 'bad']]]])),
    'session changeset rejects empty table shape' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteSessionChangeset::diff('', [], [], [], [])),
    'session changeset rejects primary key outside columns' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteSessionChangeset::diff('x', ['a'], ['b'], [], [])),
    'session changeset rejects unsupported conflict policy' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteSessionChangeset::apply($GLOBALS['before'], $changeset(), 'retry')),
    'session changeset rejects unsupported operation encode' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteSessionChangeset::encode([['table' => 'x', 'columns' => ['id'], 'primary_key' => ['id'], 'changes' => [['op' => 'patch']]]])),
    'session changeset rejects operation before table header' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteSessionChangeset::decode('I')),
    'session changeset rejects unsupported binary tag' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteSessionChangeset::decode('X')),
    'session changeset rejects truncated table name' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteSessionChangeset::decode('T' . "\x01\x01\x04wp")),
    'session changeset rejects truncated record value' => static function (TestRunner $t): void {
        $payload = SQLiteSessionChangeset::encode([['table' => 'x', 'columns' => ['id'], 'primary_key' => ['id'], 'changes' => [['op' => 'insert', 'new' => ['id' => 1]]]]]);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSessionChangeset::decode(substr($payload, 0, -1)));
    },
];
