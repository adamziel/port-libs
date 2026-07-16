<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteSelectSql;

$jsonb = new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['enabled' => true, 'ttl' => 300]]));
$superficialJsonb = new SQLiteBlobValue("\x8b\xff" . str_repeat("\0", 7));
$subtype = new SQLiteJsonSubtypeValue('{"plugin":{"enabled":true,"ttl":300}}');

$settings = [
    ['option_id' => 1, 'option_name' => 'plugin_text', 'option_value' => '{"plugin":{"enabled":true}}', 'flag_value' => '1abc'],
    ['option_id' => 2, 'option_name' => 'plugin_json5', 'option_value' => '{plugin:{enabled:true,}}', 'flag_value' => '2.9'],
    ['option_id' => 3, 'option_name' => 'plugin_jsonb', 'option_value' => $jsonb, 'flag_value' => '8 trailing'],
    ['option_id' => 4, 'option_name' => 'plugin_subtype', 'option_value' => $subtype, 'flag_value' => true],
    ['option_id' => 5, 'option_name' => 'plugin_integer', 'option_value' => 123, 'flag_value' => 1.9],
    ['option_id' => 6, 'option_name' => 'plugin_real', 'option_value' => 12.5, 'flag_value' => new SQLiteBlobValue('1')],
    ['option_id' => 7, 'option_name' => 'plugin_false', 'option_value' => false, 'flag_value' => ' 1 '],
    ['option_id' => 8, 'option_name' => 'plugin_null', 'option_value' => null, 'flag_value' => 1],
    ['option_id' => 9, 'option_name' => 'plugin_superficial', 'option_value' => $superficialJsonb, 'flag_value' => 12],
];

$directCases = [
    'integer default is valid json number' => [123, SQLiteJsonValidity::FLAG_STRICT_TEXT, true],
    'negative integer default is valid json number' => [-7, SQLiteJsonValidity::FLAG_STRICT_TEXT, true],
    'zero integer default is valid json number' => [0, SQLiteJsonValidity::FLAG_STRICT_TEXT, true],
    'true default is valid json number one' => [true, SQLiteJsonValidity::FLAG_STRICT_TEXT, true],
    'false default is valid json number zero' => [false, SQLiteJsonValidity::FLAG_STRICT_TEXT, true],
    'finite real default is valid json number' => [12.5, SQLiteJsonValidity::FLAG_STRICT_TEXT, true],
    'real preserving zero fraction is valid json number' => [1.0, SQLiteJsonValidity::FLAG_STRICT_TEXT, true],
    'integer with json5 flag remains valid' => [123, SQLiteJsonValidity::FLAG_JSON5_TEXT, true],
    'integer rejected by jsonb only flag' => [123, SQLiteJsonValidity::FLAG_SUPERFICIAL_JSONB, false],
    'boolean rejected by strict jsonb flag' => [true, SQLiteJsonValidity::FLAG_STRICT_JSONB, false],
    'text json accepted with string flag prefix' => ['{"a":1}', '1abc', true],
    'json5 text accepted with decimal string flag' => ['{a:1,}', '2.9', true],
    'strict text rejected with blob jsonb flag' => ['{"a":1}', new SQLiteBlobValue('8'), false],
    'jsonb accepted with blob strict jsonb flag' => [$jsonb, new SQLiteBlobValue('8'), true],
    'superficial jsonb accepted with combined jsonb flags' => [$superficialJsonb, '12xyz', true],
    'superficial jsonb rejected by strict jsonb coerced flag' => [$superficialJsonb, 8.8, false],
    'subtype accepted with boolean true flag' => [$subtype, true, true],
    'subtype accepted with mixed numeric string flag' => [$subtype, '9 trailing', true],
    'subtype rejected by jsonb only numeric string flag' => [$subtype, '8 trailing', false],
    'null input remains sql null after string flag coercion' => [null, '1', null],
];

$tests = [];

foreach ($directCases as $name => [$value, $flags, $expected]) {
    $tests['json validity current next29 direct ' . $name] = static function (TestRunner $t) use ($value, $flags, $expected): void {
        $t->same($expected, SQLiteJsonValidity::jsonValidSqlFunction('JSON_VALID', $value, $flags));
    };
}

$flagCases = [
    'string flag with leading whitespace' => [' 2 ', true],
    'string flag with integer prefix' => ['1abc', true],
    'string flag with decimal prefix' => ['1.9', true],
    'blob flag with digit bytes' => [new SQLiteBlobValue('1'), true],
    'float flag truncates toward zero' => [1.9, true],
    'boolean true flag is one' => [true, true],
    'boolean false flag is rejected' => [false, 'throw'],
    'null flag is rejected' => [null, 'throw'],
    'non numeric string flag is rejected' => ['abc', 'throw'],
    'zero string flag is rejected' => ['0', 'throw'],
    'sixteen string flag is rejected' => ['16', 'throw'],
];

foreach ($flagCases as $name => [$flags, $expected]) {
    $tests['json validity current next29 flag coercion ' . $name] = static function (TestRunner $t) use ($flags, $expected): void {
        if ($expected === 'throw') {
            $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonValidity::jsonValidSqlFunction('json_valid', '{"a":1}', $flags));
            return;
        }

        $t->same($expected, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', '{"a":1}', $flags));
    };
}

$selectCases = [
    'projects numeric json values as valid' => [
        'SELECT option_name, json_valid(option_value) AS ok FROM wp_options WHERE option_id BETWEEN 5 AND 7 ORDER BY option_id',
        [['option_name' => 'plugin_integer', 'ok' => 1], ['option_name' => 'plugin_real', 'ok' => 1], ['option_name' => 'plugin_false', 'ok' => 1]],
    ],
    'coerces row string flags in projection' => [
        'SELECT option_name, json_valid(option_value, flag_value) AS ok FROM wp_options WHERE option_id IN (1, 2, 3) ORDER BY option_id',
        [['option_name' => 'plugin_text', 'ok' => 1], ['option_name' => 'plugin_json5', 'ok' => 1], ['option_name' => 'plugin_jsonb', 'ok' => 1]],
    ],
    'coerces row boolean and float flags in projection' => [
        'SELECT option_name, json_valid(option_value, flag_value) AS ok FROM wp_options WHERE option_id IN (4, 5, 6) ORDER BY option_id',
        [['option_name' => 'plugin_subtype', 'ok' => 1], ['option_name' => 'plugin_integer', 'ok' => 1], ['option_name' => 'plugin_real', 'ok' => 1]],
    ],
    'preserves sql null input result' => [
        'SELECT option_name, json_valid(option_value, flag_value) AS ok FROM wp_options WHERE option_id = 8',
        [['option_name' => 'plugin_null', 'ok' => null]],
    ],
    'accepts superficial jsonb with combined jsonb flag' => [
        'SELECT option_name, json_valid(option_value, flag_value) AS ok FROM wp_options WHERE option_id = 9',
        [['option_name' => 'plugin_superficial', 'ok' => 1]],
    ],
    'filters copied application rows using coerced flags' => [
        'SELECT option_name FROM wp_options WHERE json_valid(option_value, flag_value) = 1 ORDER BY option_id',
        [['option_name' => 'plugin_text'], ['option_name' => 'plugin_json5'], ['option_name' => 'plugin_jsonb'], ['option_name' => 'plugin_subtype'], ['option_name' => 'plugin_integer'], ['option_name' => 'plugin_real'], ['option_name' => 'plugin_false'], ['option_name' => 'plugin_superficial']],
    ],
    'filters scalar json numbers with default validity' => [
        'SELECT option_name FROM wp_options WHERE json_valid(option_value) = 1 AND option_id BETWEEN 5 AND 7 ORDER BY option_id',
        [['option_name' => 'plugin_integer'], ['option_name' => 'plugin_real'], ['option_name' => 'plugin_false']],
    ],
    'distinguishes jsonb only flag from text scalar values' => [
        'SELECT option_name FROM wp_options WHERE json_valid(option_value, 4) = 1 ORDER BY option_id',
        [['option_name' => 'plugin_jsonb'], ['option_name' => 'plugin_superficial']],
    ],
    'accepts mixed text and jsonb flags across storage classes' => [
        'SELECT option_name FROM wp_options WHERE json_valid(option_value, 5) = 1 ORDER BY option_id',
        [['option_name' => 'plugin_text'], ['option_name' => 'plugin_jsonb'], ['option_name' => 'plugin_subtype'], ['option_name' => 'plugin_integer'], ['option_name' => 'plugin_real'], ['option_name' => 'plugin_false'], ['option_name' => 'plugin_superficial']],
    ],
    'orders projected validity after flag coercion' => [
        'SELECT option_name, json_valid(option_value, flag_value) AS ok FROM wp_options WHERE option_id <= 4 ORDER BY ok DESC, option_name',
        [['option_name' => 'plugin_json5', 'ok' => 1], ['option_name' => 'plugin_jsonb', 'ok' => 1], ['option_name' => 'plugin_subtype', 'ok' => 1], ['option_name' => 'plugin_text', 'ok' => 1]],
    ],
];

foreach ($selectCases as $name => [$sql, $expected]) {
    $tests['json validity current next29 select ' . $name] = static function (TestRunner $t) use ($sql, $expected, $settings): void {
        $t->same($expected, SQLiteSelectSql::execute($sql, ['wp_options' => $settings]));
    };
}

$tests['json validity current next29 select rejects invalid row flag'] = static fn (TestRunner $t) => $t->throws(
    InvalidArgumentException::class,
    static fn () => SQLiteSelectSql::execute(
        'SELECT json_valid(option_value, flag_value) AS ok FROM wp_options WHERE option_id = 10',
        ['wp_options' => [['option_id' => 10, 'option_value' => '{"a":1}', 'flag_value' => 'abc']]],
    ),
);

$tests['json validity current next29 select rejects array input value'] = static fn (TestRunner $t) => $t->throws(
    InvalidArgumentException::class,
    static fn () => SQLiteSelectSql::execute(
        'SELECT json_valid(option_value) AS ok FROM wp_options WHERE option_id = 11',
        ['wp_options' => [['option_id' => 11, 'option_value' => ['not' => 'a sql scalar']]]],
    ),
);

$tests['json validity current next29 sql function rejects unknown function'] = static fn (TestRunner $t) => $t->throws(
    InvalidArgumentException::class,
    static fn () => SQLiteJsonValidity::jsonValidSqlFunction('json_error_position', '{"a":1}', '1'),
);

return $tests;
