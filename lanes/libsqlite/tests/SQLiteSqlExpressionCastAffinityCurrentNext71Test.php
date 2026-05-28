<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectExpression;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => '42.9', 'autoload' => 'yes', 'declared' => 'INTEGER'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => '042', 'autoload' => 'no', 'declared' => 'varchar(12)'],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => '4.25', 'autoload' => 'yes', 'declared' => 'DOUBLE PRECISION'],
    ['option_id' => 4, 'option_name' => 'stylesheet', 'option_value' => 'abc', 'autoload' => 'no', 'declared' => 'BOOLEAN'],
    ['option_id' => 5, 'option_name' => 'template', 'option_value' => '10e1', 'autoload' => 'yes', 'declared' => 'DECIMAL(10,2)'],
    ['option_id' => 6, 'option_name' => 'active_plugins', 'option_value' => new SQLiteBlobValue('07plugin'), 'autoload' => 'no', 'declared' => 'BLOB'],
];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$cast = static fn (mixed $value, string $target): array => ['type' => 'cast', 'operand' => $literal($value), 'target' => $target];

$expressionCases = [
    'unsigned big int uses integer affinity' => [$cast('9223372036854775808', 'UNSIGNED BIG INT'), PHP_INT_MAX],
    'int2 uses integer affinity' => [$cast('12.75', 'INT2'), 12],
    'varchar precision uses text affinity' => [$cast(42, 'VARCHAR(255)'), '42'],
    'national character uses text affinity' => [$cast(42.5, 'NATIONAL CHARACTER(20)'), '42.5'],
    'clob suffix uses text affinity' => [$cast(new SQLiteBlobValue('abc'), 'MYCLOB'), 'abc'],
    'double precision uses real affinity' => [$cast('4.25x', 'DOUBLE PRECISION'), 4.25],
    'floating point uses integer affinity because point contains int' => [$cast('4.25x', 'FLOATING POINT'), 4],
    'string uses numeric fallback affinity' => [$cast('10e1', 'STRING'), 100.0],
    'boolean uses numeric fallback affinity' => [$cast('true', 'BOOLEAN'), 0],
    'decimal precision uses numeric fallback affinity' => [$cast('12.50x', 'DECIMAL(10,2)'), 12.5],
    'blob precision uses none affinity' => [$cast('abc', 'BLOB(16)'), new SQLiteBlobValue('abc')],
    'none uses none affinity' => [$cast('abc', 'NONE'), new SQLiteBlobValue('abc')],
];

foreach ($expressionCases as $name => [$expression, $expected]) {
    $tests['sql expression cast affinity current next71 expression ' . $name] = static function (TestRunner $t) use ($expression, $expected): void {
        $actual = SQLiteSelectExpression::evaluate([], $expression);
        if ($expected instanceof SQLiteBlobValue) {
            $t->true($actual instanceof SQLiteBlobValue);
            $t->same($expected->bytes, $actual->bytes);
            return;
        }

        $t->same($expected, $actual);
    };
}

$column = static fn (string $sql, string $name): array => array_column(SQLiteSelectSql::execute($sql, ['wp_options' => $rows]), $name);
$first = static fn (string $sql, string $name): mixed => SQLiteSelectSql::execute($sql, ['wp_options' => $rows])[0][$name];

$sqlCases = [
    'unsigned big int target parses in projection' => ["SELECT CAST(option_value AS UNSIGNED BIG INT) AS value FROM wp_options WHERE option_id = 1", 'value', 42],
    'int target substring handles tinyint' => ["SELECT CAST(option_value AS TINYINT) AS value FROM wp_options WHERE option_id = 3", 'value', 4],
    'integer affinity wins over trailing text in floating point' => ["SELECT CAST('4.25' AS FLOATING POINT) AS value FROM wp_options LIMIT 1", 'value', 4],
    'double precision target parses as real' => ["SELECT CAST(option_value AS DOUBLE PRECISION) AS value FROM wp_options WHERE option_id = 3", 'value', 4.25],
    'real substring handles float target' => ["SELECT CAST(option_value AS FLOAT) AS value FROM wp_options WHERE option_id = 5", 'value', 100.0],
    'varchar precision target parses as text' => ["SELECT CAST(option_id AS VARCHAR(20)) AS value FROM wp_options WHERE option_id = 2", 'value', '2'],
    'native character target parses as text' => ["SELECT CAST(option_id AS NATIVE CHARACTER(12)) AS value FROM wp_options WHERE option_id = 2", 'value', '2'],
    'clob target parses as text' => ["SELECT CAST(option_value AS CLOB) AS value FROM wp_options WHERE option_id = 6", 'value', '07plugin'],
    'blob precision target parses as blob' => ["SELECT CAST(option_value AS BLOB(12)) AS value FROM wp_options WHERE option_id = 2", 'value', new SQLiteBlobValue('042')],
    'none target parses as blob' => ["SELECT CAST(option_value AS NONE) AS value FROM wp_options WHERE option_id = 2", 'value', new SQLiteBlobValue('042')],
    'decimal target uses numeric fallback' => ["SELECT CAST(option_value AS DECIMAL(10,2)) AS value FROM wp_options WHERE option_id = 5", 'value', 100.0],
    'boolean target uses numeric fallback zero' => ["SELECT CAST(option_value AS BOOLEAN) AS value FROM wp_options WHERE option_id = 4", 'value', 0],
    'date target uses numeric fallback' => ["SELECT CAST('2026-05-28' AS DATE) AS value FROM wp_options LIMIT 1", 'value', 2026],
    'datetime target uses numeric fallback' => ["SELECT CAST('2026-05-28 10:15' AS DATETIME) AS value FROM wp_options LIMIT 1", 'value', 2026],
    'string target uses numeric fallback' => ["SELECT CAST(option_value AS STRING) AS value FROM wp_options WHERE option_id = 5", 'value', 100.0],
    'numeric fallback can feed where equality' => ["SELECT option_name FROM wp_options WHERE CAST(option_value AS DECIMAL(10,2)) = 100 ORDER BY option_id", 'option_name', ['template']],
    'text affinity can feed where equality' => ["SELECT option_name FROM wp_options WHERE CAST(option_id AS VARCHAR(20)) = '2' ORDER BY option_id", 'option_name', ['home']],
    'integer affinity can feed where range' => ["SELECT option_name FROM wp_options WHERE CAST(option_value AS UNSIGNED BIG INT) >= 42 ORDER BY option_id", 'option_name', ['siteurl', 'home']],
    'real affinity can feed where range' => ["SELECT option_name FROM wp_options WHERE CAST(option_value AS DOUBLE PRECISION) > 4.5 ORDER BY option_id", 'option_name', ['siteurl', 'home', 'template', 'active_plugins']],
    'blob affinity has blob storage rank in comparison' => ["SELECT option_name FROM wp_options WHERE CAST(option_value AS BLOB) > CAST('zzz' AS TEXT) ORDER BY option_id", 'option_name', ['siteurl', 'home', 'blogname', 'stylesheet', 'template', 'active_plugins']],
    'none affinity has blob storage rank in comparison' => ["SELECT option_name FROM wp_options WHERE CAST(option_value AS NONE) > CAST('zzz' AS TEXT) ORDER BY option_id", 'option_name', ['siteurl', 'home', 'blogname', 'stylesheet', 'template', 'active_plugins']],
    'fallback numeric remains below text storage rank' => ["SELECT option_name FROM wp_options WHERE CAST(option_id AS BOOLEAN) < '0' ORDER BY option_id", 'option_name', ['siteurl', 'home', 'blogname', 'stylesheet', 'template', 'active_plugins']],
    'varchar text storage rank remains above numeric literal' => ["SELECT option_name FROM wp_options WHERE CAST(option_id AS VARCHAR(20)) > 100 ORDER BY option_id", 'option_name', ['siteurl', 'home', 'blogname', 'stylesheet', 'template', 'active_plugins']],
    'integer affinity does not match text literal list' => ["SELECT option_name FROM wp_options WHERE CAST(option_id AS UNSIGNED BIG INT) IN ('1','2') ORDER BY option_id", 'option_name', []],
    'text affinity does not match numeric literal list' => ["SELECT option_name FROM wp_options WHERE CAST(option_id AS VARCHAR(20)) IN (1,2) ORDER BY option_id", 'option_name', []],
    'decimal numeric fallback matches numeric literal list' => ["SELECT option_name FROM wp_options WHERE CAST(option_value AS DECIMAL(10,2)) IN (0, 42, 100) ORDER BY option_id", 'option_name', ['home', 'stylesheet', 'template']],
    'integer affinity works in between' => ["SELECT option_name FROM wp_options WHERE CAST(option_value AS UNSIGNED BIG INT) BETWEEN 4 AND 42 ORDER BY option_id", 'option_name', ['siteurl', 'home', 'blogname', 'template', 'active_plugins']],
    'text affinity works in order by' => ["SELECT option_name FROM wp_options ORDER BY CAST(option_id AS VARCHAR(20)) DESC LIMIT 3", 'option_name', ['active_plugins', 'template', 'stylesheet']],
    'real affinity works in order by' => ["SELECT option_name FROM wp_options ORDER BY CAST(option_value AS DOUBLE PRECISION) DESC, option_id LIMIT 3", 'option_name', ['template', 'siteurl', 'home']],
    'numeric fallback works in order by' => ["SELECT option_name FROM wp_options ORDER BY CAST(option_value AS DECIMAL(10,2)) DESC, option_id LIMIT 3", 'option_name', ['template', 'siteurl', 'home']],
    'cast type can be lower case with precision' => ["SELECT CAST(option_id AS varchar(20)) AS value FROM wp_options WHERE option_id = 3", 'value', '3'],
    'cast type can contain repeated spaces' => ["SELECT CAST(option_value AS DOUBLE   PRECISION) AS value FROM wp_options WHERE option_id = 3", 'value', 4.25],
    'cast type supports underscores as numeric fallback name' => ["SELECT CAST('123abc' AS plugin_number) AS value FROM wp_options LIMIT 1", 'value', 123],
    'unsupported punctuation target remains rejected' => ["SELECT CAST(option_id AS VARCHAR-20) AS value FROM wp_options LIMIT 1", 'error', InvalidArgumentException::class],
    'empty unsupported target remains rejected by parser' => ["SELECT CAST(option_id AS) AS value FROM wp_options LIMIT 1", 'error', InvalidArgumentException::class],
    'declared type column can be cast to text with varchar' => ["SELECT option_name || ':' || CAST(declared AS VARCHAR(40)) AS label FROM wp_options WHERE option_id IN (1,3) ORDER BY option_id", 'label', ['siteurl:INTEGER', 'blogname:DOUBLE PRECISION']],
    'declared type text can be cast through numeric fallback' => ["SELECT option_name FROM wp_options WHERE CAST(declared AS BOOLEAN) = 0 ORDER BY option_id", 'option_name', ['siteurl', 'home', 'blogname', 'stylesheet', 'template', 'active_plugins']],
    'precision numeric fallback works before grouped aggregate' => ["SELECT autoload, count(option_id) AS total FROM wp_options WHERE CAST(option_value AS DECIMAL(10,2)) >= 7 GROUP BY autoload HAVING total >= 2 ORDER BY autoload", 'autoload', ['no', 'yes']],
    'double precision works in hidden order expression' => ["SELECT option_name FROM wp_options ORDER BY CAST(option_value AS DOUBLE PRECISION) DESC, option_id LIMIT 2", 'option_name', ['template', 'siteurl']],
    'none affinity blob projection preserves bytes' => ["SELECT CAST(option_value AS NONE) AS value FROM wp_options WHERE option_id = 6", 'value', new SQLiteBlobValue('07plugin')],
    'int affinity from blob source reads numeric prefix' => ["SELECT CAST(option_value AS UNSIGNED BIG INT) AS value FROM wp_options WHERE option_id = 6", 'value', 7],
    'text affinity from blob source preserves bytes' => ["SELECT CAST(option_value AS CHARACTER(8)) AS value FROM wp_options WHERE option_id = 6", 'value', '07plugin'],
    'numeric fallback from blob source reads numeric prefix' => ["SELECT CAST(option_value AS BOOLEAN) AS value FROM wp_options WHERE option_id = 6", 'value', 7],
];

foreach ($sqlCases as $name => [$sql, $field, $expected]) {
    $tests['sql expression cast affinity current next71 select sql ' . $name] = static function (TestRunner $t) use ($sql, $field, $expected, $first, $column): void {
        if ($field === 'error') {
            $t->throws($expected, static fn () => SQLiteSelectSql::execute($sql, ['wp_options' => []]));
            return;
        }

        if ($expected instanceof SQLiteBlobValue) {
            $actual = $first($sql, $field);
            $t->true($actual instanceof SQLiteBlobValue);
            $t->same($expected->bytes, $actual->bytes);
            return;
        }
        if (is_array($expected)) {
            $t->same($expected, $column($sql, $field));
            return;
        }

        $t->same($expected, $first($sql, $field));
    };
}

return $tests;
