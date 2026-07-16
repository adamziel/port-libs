<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteIncrementalBlobIoPlan;

$rows = [
    ['rowid' => 1, 'option_name' => 'siteurl', 'option_value' => new SQLiteBlobValue('https://example.test')],
    ['rowid' => 2, 'option_name' => '_site_transient_update_plugins', 'option_value' => new SQLiteBlobValue(str_repeat("\0", 12))],
    ['rowid' => 3, 'option_name' => 'blogname', 'option_value' => 'Example Site'],
    ['id' => 4, 'option_name' => 'binary_alias', 'option_value' => new SQLiteBlobValue("abc\x00def")],
];

$openWritable = SQLiteIncrementalBlobIoPlan::open($rows, [
    'table' => 'wp_options',
    'column' => 'option_value',
    'rowid' => 2,
]);
$openReadonly = SQLiteIncrementalBlobIoPlan::open($rows, [
    'database' => 'main',
    'table' => 'wp_options',
    'column' => 'option_value',
    'rowid' => 1,
    'readonly' => true,
]);
$written = SQLiteIncrementalBlobIoPlan::write($rows, $openWritable, 0, new SQLiteBlobValue('plugin'));
$writtenTail = SQLiteIncrementalBlobIoPlan::write($written['rows'], array_replace($openWritable, ['payload' => $written['payload']]), 6, '-cache');
$reopened = SQLiteIncrementalBlobIoPlan::reopen($rows, $openReadonly, 4);

$tests = [
    'incremental blob io corpus opens writable handle' => static fn (TestRunner $t) => $t->same('open', $openWritable['status']),
    'incremental blob io corpus preserves default main schema' => static fn (TestRunner $t) => $t->same('main', $openWritable['database']),
    'incremental blob io corpus records table name' => static fn (TestRunner $t) => $t->same('wp_options', $openWritable['table']),
    'incremental blob io corpus records column name' => static fn (TestRunner $t) => $t->same('option_value', $openWritable['column']),
    'incremental blob io corpus records rowid' => static fn (TestRunner $t) => $t->same(2, $openWritable['rowid']),
    'incremental blob io corpus reports blob byte length' => static fn (TestRunner $t) => $t->same(12, $openWritable['bytes']),
    'incremental blob io corpus exposes payload bytes' => static fn (TestRunner $t) => $t->same(str_repeat("\0", 12), $openWritable['payload']->bytes),
    'incremental blob io corpus records open dependencies' => static fn (TestRunner $t) => $t->same(['sqlite3-blob-open', 'sqlite3-blob-bytes'], $openWritable['dependencies']),
    'incremental blob io corpus preserves readonly flag' => static fn (TestRunner $t) => $t->same(true, $openReadonly['readonly']),
    'incremental blob io corpus reads prefix bytes' => static function (TestRunner $t) use ($openReadonly): void {
        $read = SQLiteIncrementalBlobIoPlan::read($openReadonly, 0, 5);
        $t->same('https', $read['bytes']->bytes);
    },
    'incremental blob io corpus reads interior bytes' => static function (TestRunner $t) use ($openReadonly): void {
        $read = SQLiteIncrementalBlobIoPlan::read($openReadonly, 8, 7);
        $t->same('example', $read['bytes']->bytes);
    },
    'incremental blob io corpus read at end returns empty blob' => static function (TestRunner $t) use ($openReadonly): void {
        $read = SQLiteIncrementalBlobIoPlan::read($openReadonly, $openReadonly['bytes'], 4);
        $t->same('', $read['bytes']->bytes);
    },
    'incremental blob io corpus read reports eof when request reaches end' => static function (TestRunner $t) use ($openReadonly): void {
        $read = SQLiteIncrementalBlobIoPlan::read($openReadonly, 16, 4);
        $t->same(true, $read['eof']);
    },
    'incremental blob io corpus read reports non eof for short prefix' => static function (TestRunner $t) use ($openReadonly): void {
        $read = SQLiteIncrementalBlobIoPlan::read($openReadonly, 0, 4);
        $t->same(false, $read['eof']);
    },
    'incremental blob io corpus writes first chunk in place' => static fn (TestRunner $t) => $t->same("plugin\0\0\0\0\0\0", $written['payload']->bytes),
    'incremental blob io corpus write reports byte count' => static fn (TestRunner $t) => $t->same(6, $written['written']),
    'incremental blob io corpus write updates returned row image' => static fn (TestRunner $t) => $t->same("plugin\0\0\0\0\0\0", $written['rows'][1]['option_value']->bytes),
    'incremental blob io corpus second write preserves fixed size' => static fn (TestRunner $t) => $t->same('plugin-cache', $writtenTail['payload']->bytes),
    'incremental blob io corpus second write keeps other rows untouched' => static fn (TestRunner $t) => $t->same('https://example.test', $writtenTail['rows'][0]['option_value']->bytes),
    'incremental blob io corpus reopens handle to id alias row' => static fn (TestRunner $t) => $t->same(4, $reopened['rowid']),
    'incremental blob io corpus reopen preserves readonly flag' => static fn (TestRunner $t) => $t->same(true, $reopened['readonly']),
    'incremental blob io corpus reopen reads binary nul bytes' => static fn (TestRunner $t) => $t->same("abc\x00def", $reopened['payload']->bytes),
    'incremental blob io corpus reopen records dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite3-blob-reopen', $reopened['dependencies'], true)),
    'incremental blob io corpus closes open handle' => static function (TestRunner $t) use ($openWritable): void {
        $closed = SQLiteIncrementalBlobIoPlan::close($openWritable);
        $t->same('closed', $closed['status']);
    },
    'incremental blob io corpus close records rowid' => static function (TestRunner $t) use ($openWritable): void {
        $closed = SQLiteIncrementalBlobIoPlan::close($openWritable);
        $t->same(2, $closed['rowid']);
    },
    'incremental blob io corpus rejects writing readonly handle' => static fn (TestRunner $t) => $t->throws(RuntimeException::class, static fn () => SQLiteIncrementalBlobIoPlan::write($rows, $openReadonly, 0, 'http')),
    'incremental blob io corpus rejects write past end' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteIncrementalBlobIoPlan::write($rows, $openWritable, 10, 'overflow')),
    'incremental blob io corpus rejects negative write offset' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteIncrementalBlobIoPlan::write($rows, $openWritable, -1, 'x')),
    'incremental blob io corpus rejects read past end' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteIncrementalBlobIoPlan::read($openReadonly, 200, 1)),
    'incremental blob io corpus rejects negative read amount' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteIncrementalBlobIoPlan::read($openReadonly, 0, -1)),
    'incremental blob io corpus rejects missing rowid' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteIncrementalBlobIoPlan::open($rows, ['table' => 'wp_options', 'column' => 'option_value', 'rowid' => 99])),
    'incremental blob io corpus rejects non blob column value' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteIncrementalBlobIoPlan::open($rows, ['table' => 'wp_options', 'column' => 'option_value', 'rowid' => 3])),
    'incremental blob io corpus rejects missing column' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteIncrementalBlobIoPlan::open($rows, ['table' => 'wp_options', 'column' => 'missing', 'rowid' => 1])),
    'incremental blob io corpus rejects malformed table name' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteIncrementalBlobIoPlan::open($rows, ['table' => 'wp-options', 'column' => 'option_value', 'rowid' => 1])),
    'incremental blob io corpus rejects malformed column name' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteIncrementalBlobIoPlan::open($rows, ['table' => 'wp_options', 'column' => 'option-value', 'rowid' => 1])),
    'incremental blob io corpus rejects malformed schema name' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteIncrementalBlobIoPlan::open($rows, ['database' => 'main;drop', 'table' => 'wp_options', 'column' => 'option_value', 'rowid' => 1])),
    'incremental blob io corpus rejects non positive rowid' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteIncrementalBlobIoPlan::open($rows, ['table' => 'wp_options', 'column' => 'option_value', 'rowid' => 0])),
    'incremental blob io corpus rejects closed handle read' => static function (TestRunner $t) use ($openWritable): void {
        $closed = SQLiteIncrementalBlobIoPlan::close($openWritable);
        $t->throws(RuntimeException::class, static fn () => SQLiteIncrementalBlobIoPlan::read($closed, 0, 1));
    },
    'incremental blob io corpus rejects closed handle write' => static function (TestRunner $t) use ($rows, $openWritable): void {
        $closed = SQLiteIncrementalBlobIoPlan::close($openWritable);
        $t->throws(RuntimeException::class, static fn () => SQLiteIncrementalBlobIoPlan::write($rows, $closed, 0, 'x'));
    },
    'incremental blob io corpus rejects closed handle reopen' => static function (TestRunner $t) use ($rows, $openWritable): void {
        $closed = SQLiteIncrementalBlobIoPlan::close($openWritable);
        $t->throws(RuntimeException::class, static fn () => SQLiteIncrementalBlobIoPlan::reopen($rows, $closed, 1));
    },
];

return $tests;
