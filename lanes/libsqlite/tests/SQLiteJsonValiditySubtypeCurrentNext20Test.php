<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteSelectSql;

$settings = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_settings',
        'option_value' => new SQLiteJsonSubtypeValue('{"plugin":{"enabled":true,"modes":["sync","cache"],"ttl":300}}'),
        'autoload' => 'yes',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_empty',
        'option_value' => new SQLiteJsonSubtypeValue('{"plugin":{"enabled":false,"modes":[],"ttl":0}}'),
        'autoload' => 'no',
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_json5_text',
        'option_value' => '{plugin:{enabled:true,modes:["sync",],ttl:300}}',
        'autoload' => 'yes',
    ],
    [
        'option_id' => 4,
        'option_name' => 'plugin_jsonb',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['enabled' => true, 'ttl' => 450]])),
        'autoload' => 'yes',
    ],
    [
        'option_id' => 5,
        'option_name' => 'plugin_null',
        'option_value' => null,
        'autoload' => 'no',
    ],
];

$subtype = new SQLiteJsonSubtypeValue('{"plugin":{"enabled":true,"modes":["sync","cache"],"ttl":300}}');
$arraySubtype = new SQLiteJsonSubtypeValue('["sync","cache"]');
$malformedSubtype = new SQLiteJsonSubtypeValue('{plugin:true}');

return [
    'json validity subtype current next20 accepts strict subtype default flag' => static fn (TestRunner $t) => $t->true(
        SQLiteJsonValidity::jsonValid($subtype),
    ),
    'json validity subtype current next20 accepts strict subtype flag one' => static fn (TestRunner $t) => $t->true(
        SQLiteJsonValidity::jsonValid($subtype, SQLiteJsonValidity::FLAG_STRICT_TEXT),
    ),
    'json validity subtype current next20 accepts strict subtype flag two' => static fn (TestRunner $t) => $t->true(
        SQLiteJsonValidity::jsonValid($subtype, SQLiteJsonValidity::FLAG_JSON5_TEXT),
    ),
    'json validity subtype current next20 accepts strict subtype combined text flags' => static fn (TestRunner $t) => $t->true(
        SQLiteJsonValidity::jsonValid($subtype, SQLiteJsonValidity::FLAG_STRICT_TEXT | SQLiteJsonValidity::FLAG_JSON5_TEXT),
    ),
    'json validity subtype current next20 rejects subtype with jsonb-only superficial flag' => static fn (TestRunner $t) => $t->same(
        false,
        SQLiteJsonValidity::jsonValid($subtype, SQLiteJsonValidity::FLAG_SUPERFICIAL_JSONB),
    ),
    'json validity subtype current next20 rejects subtype with jsonb-only strict flag' => static fn (TestRunner $t) => $t->same(
        false,
        SQLiteJsonValidity::jsonValid($subtype, SQLiteJsonValidity::FLAG_STRICT_JSONB),
    ),
    'json validity subtype current next20 accepts subtype with mixed text and jsonb flags' => static fn (TestRunner $t) => $t->true(
        SQLiteJsonValidity::jsonValid($subtype, SQLiteJsonValidity::FLAG_STRICT_TEXT | SQLiteJsonValidity::FLAG_STRICT_JSONB),
    ),
    'json validity subtype current next20 validates array subtype' => static fn (TestRunner $t) => $t->true(
        SQLiteJsonValidity::jsonValid($arraySubtype),
    ),
    'json validity subtype current next20 rejects noncanonical malformed subtype' => static fn (TestRunner $t) => $t->same(
        false,
        SQLiteJsonValidity::jsonValid($malformedSubtype),
    ),
    'json validity subtype current next20 sql function accepts subtype' => static fn (TestRunner $t) => $t->true(
        SQLiteJsonValidity::jsonValidSqlFunction('JSON_VALID', $subtype),
    ),
    'json validity subtype current next20 sql function arguments accepts subtype flag' => static fn (TestRunner $t) => $t->true(
        SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$subtype, 2]),
    ),
    'json validity subtype current next20 sql function arguments rejects subtype jsonb-only flag' => static fn (TestRunner $t) => $t->same(
        false,
        SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$subtype, 4]),
    ),
    'json validity subtype current next20 select projection validates subtype rows' => static fn (TestRunner $t) => $t->same(
        [['option_name' => 'plugin_settings', 'ok' => 1], ['option_name' => 'plugin_empty', 'ok' => 1]],
        SQLiteSelectSql::execute(
            'SELECT option_name, json_valid(option_value) AS ok FROM wp_options WHERE option_id IN (1, 2) ORDER BY option_id',
            ['wp_options' => $settings],
        ),
    ),
    'json validity subtype current next20 select where filters subtype rows' => static fn (TestRunner $t) => $t->same(
        ['plugin_settings', 'plugin_empty'],
        array_column(SQLiteSelectSql::execute(
            'SELECT option_name FROM wp_options WHERE json_valid(option_value) = 1 AND option_id < 3 ORDER BY option_id',
            ['wp_options' => $settings],
        ), 'option_name'),
    ),
    'json validity subtype current next20 select distinguishes json5 text from subtype default' => static fn (TestRunner $t) => $t->same(
        [['option_id' => 1, 'ok' => 1], ['option_id' => 3, 'ok' => 0]],
        SQLiteSelectSql::execute(
            'SELECT option_id, json_valid(option_value) AS ok FROM wp_options WHERE option_id IN (1, 3) ORDER BY option_id',
            ['wp_options' => $settings],
        ),
    ),
    'json validity subtype current next20 select json5 flag still accepts json5 text' => static fn (TestRunner $t) => $t->same(
        [['option_id' => 1, 'ok' => 1], ['option_id' => 3, 'ok' => 1]],
        SQLiteSelectSql::execute(
            'SELECT option_id, json_valid(option_value, 2) AS ok FROM wp_options WHERE option_id IN (1, 3) ORDER BY option_id',
            ['wp_options' => $settings],
        ),
    ),
    'json validity subtype current next20 select jsonb-only flag rejects subtype but accepts jsonb' => static fn (TestRunner $t) => $t->same(
        [['option_id' => 1, 'ok' => 0], ['option_id' => 4, 'ok' => 1]],
        SQLiteSelectSql::execute(
            'SELECT option_id, json_valid(option_value, 4) AS ok FROM wp_options WHERE option_id IN (1, 4) ORDER BY option_id',
            ['wp_options' => $settings],
        ),
    ),
    'json validity subtype current next20 select mixed strict and jsonb flags accepts subtype and jsonb' => static fn (TestRunner $t) => $t->same(
        [['option_id' => 1, 'ok' => 1], ['option_id' => 4, 'ok' => 1]],
        SQLiteSelectSql::execute(
            'SELECT option_id, json_valid(option_value, 5) AS ok FROM wp_options WHERE option_id IN (1, 4) ORDER BY option_id',
            ['wp_options' => $settings],
        ),
    ),
    'json validity subtype current next20 select null subtype input stays null' => static fn (TestRunner $t) => $t->same(
        [['option_id' => 5, 'ok' => null]],
        SQLiteSelectSql::execute(
            'SELECT option_id, json_valid(option_value) AS ok FROM wp_options WHERE option_id = 5',
            ['wp_options' => $settings],
        ),
    ),
    'json validity subtype current next20 select rejects nonscalar json_valid input' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute(
            'SELECT json_valid(option_value) AS ok FROM wp_options WHERE option_id = 6',
            ['wp_options' => [['option_id' => 6, 'option_value' => ['not' => 'sql-scalar']]]],
        ),
    ),
];
