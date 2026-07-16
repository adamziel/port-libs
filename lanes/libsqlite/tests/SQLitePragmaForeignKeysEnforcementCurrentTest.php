<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaForeignKeysEnforcement;

$tables = [
    'wp_sites' => [
        ['blog_id' => 1, 'domain' => 'example.test'],
        ['blog_id' => 2, 'domain' => 'network.test'],
    ],
    'wp_options' => [
        ['option_id' => 10, 'blog_id' => 1, 'option_name' => 'siteurl'],
    ],
    'wp_optionmeta' => [
        ['meta_id' => 20, 'blog_id' => 1, 'option_name' => 'siteurl', 'meta_key' => 'autoload'],
    ],
];
$foreignKeys = [
    [
        'table' => 'wp_options',
        'parent' => 'wp_sites',
        'columns' => ['blog_id' => 'blog_id'],
        'id' => 0,
    ],
    [
        'table' => 'wp_optionmeta',
        'parent' => 'wp_options',
        'columns' => [
            ['child' => 'blog_id', 'parent' => 'blog_id', 'affinity' => 'integer'],
            ['child' => 'option_name', 'parent' => 'option_name', 'affinity' => 'text', 'collation' => 'nocase'],
        ],
        'id' => 1,
    ],
];

$validOption = ['option_id' => 11, 'blog_id' => 2, 'option_name' => 'home'];
$invalidOption = ['option_id' => 12, 'blog_id' => 99, 'option_name' => 'missing'];
$nullOption = ['option_id' => 13, 'blog_id' => null, 'option_name' => 'nullable'];
$invalidMeta = ['meta_id' => 21, 'blog_id' => 1, 'option_name' => 'missing', 'meta_key' => 'stale'];
$caseMeta = ['meta_id' => 22, 'blog_id' => 1, 'option_name' => 'SITEURL', 'meta_key' => 'autoload'];

$insert = static fn (array $rows, array $options = [], string $table = 'wp_options'): array => SQLitePragmaForeignKeysEnforcement::insertRows(
    $tables,
    $foreignKeys,
    $table,
    $rows,
    $options,
);

$tests = [
    'pragma foreign keys enforcement current accepts valid child insert' => static function (TestRunner $t) use ($insert, $validOption): void {
        $result = $insert([$validOption], ['foreign_keys' => true]);
        $t->same('ok', $result['status']);
        $t->same(2, count($result['tables']['wp_options']));
    },
    'pragma foreign keys enforcement current reports enabled pragma' => static function (TestRunner $t) use ($insert, $validOption): void {
        $t->same(true, $insert([$validOption], ['foreign_keys' => true])['foreign_keys']);
    },
    'pragma foreign keys enforcement current has no violation rows for valid insert' => static function (TestRunner $t) use ($insert, $validOption): void {
        $t->same([], $insert([$validOption], ['foreign_keys' => true])['violations']);
    },
    'pragma foreign keys enforcement current rejects immediate missing parent' => static function (TestRunner $t) use ($insert, $invalidOption): void {
        $t->throws(InvalidArgumentException::class, static fn () => $insert([$invalidOption], ['foreign_keys' => true]));
    },
    'pragma foreign keys enforcement current disabled pragma admits missing parent' => static function (TestRunner $t) use ($insert, $invalidOption): void {
        $result = $insert([$invalidOption], ['foreign_keys' => false]);
        $t->same('foreign_keys_disabled', $result['status']);
        $t->same(99, $result['tables']['wp_options'][1]['blog_id']);
    },
    'pragma foreign keys enforcement current disabled pragma records deferred check rows' => static function (TestRunner $t) use ($insert, $invalidOption): void {
        $result = $insert([$invalidOption], ['foreign_keys' => false]);
        $t->same('wp_options', $result['violations'][0]['table']);
    },
    'pragma foreign keys enforcement current null child key short circuits' => static function (TestRunner $t) use ($insert, $nullOption): void {
        $result = $insert([$nullOption], ['foreign_keys' => true]);
        $t->same('ok', $result['status']);
        $t->same(null, $result['tables']['wp_options'][1]['blog_id']);
    },
    'pragma foreign keys enforcement current defers violation until commit' => static function (TestRunner $t) use ($insert, $invalidOption): void {
        $result = $insert([$invalidOption], ['foreign_keys' => true, 'defer_foreign_keys' => true]);
        $t->same('deferred', $result['status']);
        $t->same(1, count($result['deferred_violations']));
    },
    'pragma foreign keys enforcement current commit rejects deferred violations' => static function (TestRunner $t) use ($insert, $invalidOption): void {
        $result = $insert([$invalidOption], ['foreign_keys' => true, 'defer_foreign_keys' => true]);
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeysEnforcement::commit($result));
    },
    'pragma foreign keys enforcement current commit accepts clean deferred batch' => static function (TestRunner $t) use ($insert, $validOption): void {
        $result = $insert([$validOption], ['foreign_keys' => true, 'defer_foreign_keys' => true]);
        $t->same('committed', SQLitePragmaForeignKeysEnforcement::commit($result)['status']);
    },
    'pragma foreign keys enforcement current composite nocase parent matches' => static function (TestRunner $t) use ($insert, $caseMeta): void {
        $result = $insert([$caseMeta], ['foreign_keys' => true], 'wp_optionmeta');
        $t->same('ok', $result['status']);
        $t->same('SITEURL', $result['tables']['wp_optionmeta'][1]['option_name']);
    },
    'pragma foreign keys enforcement current composite missing parent rejects' => static function (TestRunner $t) use ($insert, $invalidMeta): void {
        $t->throws(InvalidArgumentException::class, static fn () => $insert([$invalidMeta], ['foreign_keys' => true], 'wp_optionmeta'));
    },
    'pragma foreign keys enforcement current parses on pragma sql' => static function (TestRunner $t): void {
        $t->same(true, SQLitePragmaForeignKeysEnforcement::parseForeignKeysPragma('PRAGMA foreign_keys = ON'));
    },
    'pragma foreign keys enforcement current parses numeric off pragma sql' => static function (TestRunner $t): void {
        $t->same(false, SQLitePragmaForeignKeysEnforcement::parseForeignKeysPragma('PRAGMA foreign_keys=0;'));
    },
    'pragma foreign keys enforcement current rejects query pragma shape' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeysEnforcement::parseForeignKeysPragma('PRAGMA foreign_keys'));
    },
    'pragma foreign keys enforcement current rejects malformed target table' => static function (TestRunner $t) use ($tables, $foreignKeys): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeysEnforcement::insertRows($tables, $foreignKeys, 'bad-table', []));
    },
];

return $tests;
