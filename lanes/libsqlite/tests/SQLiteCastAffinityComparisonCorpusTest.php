<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectExpression;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => '10', 'autoload' => 'yes', 'bucket' => '2.5'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => '10abc', 'autoload' => 'no', 'bucket' => '02'],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => '9.75', 'autoload' => 'yes', 'bucket' => '9e1'],
    ['option_id' => 4, 'option_name' => 'stylesheet', 'option_value' => 'abc', 'autoload' => 'no', 'bucket' => '0'],
    ['option_id' => 5, 'option_name' => 'template', 'option_value' => '', 'autoload' => 'yes', 'bucket' => null],
    ['option_id' => 6, 'option_name' => 'active_plugins', 'option_value' => new SQLiteBlobValue('11plugin'), 'autoload' => 'no', 'bucket' => '11.5'],
];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$column = static fn (string $name): array => ['type' => 'column', 'name' => $name];
$cast = static fn (array $operand, string $target): array => ['type' => 'cast', 'operand' => $operand, 'target' => $target];

$expressionCases = [
    'cast integer from numeric text' => [$cast($literal(' 42abc'), 'INTEGER'), [], 42],
    'cast integer from real truncates toward zero' => [$cast($literal(-7.9), 'INTEGER'), [], -7],
    'cast integer from non numeric text returns zero' => [$cast($literal('plugins'), 'INTEGER'), [], 0],
    'cast real from exponent text' => [$cast($literal('9e1tail'), 'REAL'), [], 90.0],
    'cast numeric keeps integer prefix as integer' => [$cast($literal('0012cache'), 'NUMERIC'), [], 12],
    'cast numeric keeps decimal prefix as real' => [$cast($literal('2.50cache'), 'NUMERIC'), [], 2.5],
    'cast text from integer' => [$cast($literal(12), 'TEXT'), [], '12'],
    'cast text from blob bytes' => [$cast($literal(new SQLiteBlobValue('abc')), 'TEXT'), [], 'abc'],
    'cast blob from text wraps bytes' => [$cast($literal('abc'), 'BLOB'), [], new SQLiteBlobValue('abc')],
    'cast null remains null' => [$cast($literal(null), 'INTEGER'), [], null],
    'cast column integer numeric prefix' => [$cast($column('option_value'), 'INTEGER'), $rows[1], 10],
    'cast column real numeric prefix' => [$cast($column('bucket'), 'REAL'), $rows[2], 90.0],
    'cast column text from blob' => [$cast($column('option_value'), 'TEXT'), $rows[5], '11plugin'],
    'cast column blob from integer text' => [$cast($column('option_value'), 'BLOB'), $rows[0], new SQLiteBlobValue('10')],
    'cast empty text to numeric zero' => [$cast($column('option_value'), 'NUMERIC'), $rows[4], 0],
];

foreach ($expressionCases as $name => [$expression, $row, $expected]) {
    $tests['upstream cast affinity expression corpus ' . $name] = static function (TestRunner $t) use ($expression, $row, $expected): void {
        $actual = SQLiteSelectExpression::evaluate($row, $expression);
        if ($expected instanceof SQLiteBlobValue) {
            $t->true($actual instanceof SQLiteBlobValue);
            $t->same($expected->bytes, $actual->bytes);
            return;
        }

        $t->same($expected, $actual);
    };
}

$sqlCases = [
    'integer cast equality includes text prefix' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(option_value AS INTEGER) = 10 ORDER BY option_id", ['siteurl', 'home']],
    'integer cast excludes raw text storage comparison' => ["SELECT option_id AS id, option_name FROM wp_options WHERE option_value = 10 ORDER BY option_id", []],
    'integer cast greater than numeric literal' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(option_value AS INTEGER) > 9 ORDER BY option_id", ['siteurl', 'home', 'active_plugins']],
    'integer cast less than numeric literal includes zero casts' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(option_value AS INTEGER) < 1 ORDER BY option_id", ['stylesheet', 'template']],
    'real cast finds decimal prefix' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(option_value AS REAL) > 9.5 ORDER BY option_id", ['siteurl', 'home', 'blogname', 'active_plugins']],
    'numeric cast equality compares numeric classes' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(bucket AS NUMERIC) = 2 ORDER BY option_id", ['home']],
    'numeric cast exponent comparison' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(bucket AS NUMERIC) >= 90 ORDER BY option_id", ['blogname']],
    'text cast preserves lexical comparison' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(option_value AS TEXT) > '10' ORDER BY option_id", ['home', 'blogname', 'stylesheet', 'active_plugins']],
    'blob cast compares after text storage class' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(option_value AS BLOB) > CAST('10' AS TEXT) ORDER BY option_id", ['siteurl', 'home', 'blogname', 'stylesheet', 'template', 'active_plugins']],
    'integer cast between numeric literals' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(option_value AS INTEGER) BETWEEN 9 AND 10 ORDER BY option_id", ['siteurl', 'home', 'blogname']],
    'integer cast not between numeric literals' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(option_value AS INTEGER) NOT BETWEEN 1 AND 10 ORDER BY option_id", ['stylesheet', 'template', 'active_plugins']],
    'raw text between numeric literals stays false by storage rank' => ["SELECT option_id AS id, option_name FROM wp_options WHERE option_value BETWEEN 1 AND 10 ORDER BY option_id", []],
    'integer cast in list' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(option_value AS INTEGER) IN (0, 11) ORDER BY option_id", ['stylesheet', 'template', 'active_plugins']],
    'integer cast not in list honors numeric affinity' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(option_value AS INTEGER) NOT IN (0, 10) ORDER BY option_id", ['blogname', 'active_plugins']],
    'real cast in list compares real and integer numeric values' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(bucket AS REAL) IN (2, 2.5, 11.5) ORDER BY option_id", ['siteurl', 'home', 'active_plugins']],
    'cast in conjunction with text predicate' => ["SELECT option_id AS id, option_name FROM wp_options WHERE autoload = 'yes' AND CAST(option_value AS INTEGER) >= 9 ORDER BY option_id", ['siteurl', 'blogname']],
    'cast in disjunction with null cast branch' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(bucket AS INTEGER) IS NULL OR CAST(bucket AS INTEGER) = 0 ORDER BY option_id", ['stylesheet', 'template']],
    'cast text projection from blob' => ["SELECT option_name, CAST(option_value AS TEXT) AS value_text FROM wp_options WHERE option_name = 'active_plugins'", ['active_plugins:11plugin']],
    'cast integer projection from empty string' => ["SELECT option_name, CAST(option_value AS INTEGER) AS numeric_value FROM wp_options WHERE option_name IN ('stylesheet','template') ORDER BY option_id", ['stylesheet:0', 'template:0']],
    'cast real projection from exponent' => ["SELECT option_name, CAST(bucket AS REAL) AS bucket_real FROM wp_options WHERE option_name = 'blogname'", ['blogname:90']],
    'order by integer cast' => ["SELECT option_id AS id, option_name FROM wp_options ORDER BY CAST(option_value AS INTEGER) DESC, option_id", ['active_plugins', 'siteurl', 'home', 'blogname', 'stylesheet', 'template']],
    'order by text cast keeps lexical order' => ["SELECT option_id AS id, option_name FROM wp_options ORDER BY CAST(option_value AS TEXT) ASC, option_id LIMIT 4", ['template', 'siteurl', 'home', 'active_plugins']],
    'order by blob cast after text conversion' => ["SELECT option_id AS id, option_name FROM wp_options ORDER BY CAST(option_value AS BLOB) DESC, option_id LIMIT 3", ['stylesheet', 'blogname', 'active_plugins']],
    'cast in limit predicate' => ["SELECT option_id AS id, option_name FROM wp_options WHERE option_id <= CAST('4x' AS INTEGER) ORDER BY option_id", ['siteurl', 'home', 'blogname', 'stylesheet']],
    'cast composed arithmetic comparison' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(option_value AS INTEGER) + CAST(bucket AS INTEGER) >= 12 ORDER BY option_id", ['siteurl', 'home', 'blogname', 'active_plugins']],
    'cast composed concatenation projection' => ["SELECT option_name, CAST(option_id AS TEXT) || ':' || CAST(option_value AS TEXT) AS label FROM wp_options WHERE option_id <= 2 ORDER BY option_id", ['siteurl:1:10', 'home:2:10abc']],
    'cast numeric null comparison filters unknown' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(bucket AS NUMERIC) > 0 ORDER BY option_id", ['siteurl', 'home', 'blogname', 'active_plugins']],
    'cast numeric is null detects null input' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(bucket AS NUMERIC) IS NULL ORDER BY option_id", ['template']],
    'cast numeric is not null skips null input' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(bucket AS NUMERIC) IS NOT NULL ORDER BY option_id", ['siteurl', 'home', 'blogname', 'stylesheet', 'active_plugins']],
    'cast text equality from numeric literal' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(option_id AS TEXT) = '3' ORDER BY option_id", ['blogname']],
    'cast blob equality from text literal' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(option_value AS BLOB) = CAST('10' AS BLOB) ORDER BY option_id", ['siteurl']],
    'cast text blob equality from blob source' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(option_value AS TEXT) = '11plugin' ORDER BY option_id", ['active_plugins']],
    'cast integer not equal numeric' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(option_value AS INTEGER) <> 10 ORDER BY option_id", ['blogname', 'stylesheet', 'template', 'active_plugins']],
    'cast numeric less equal decimal' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(bucket AS NUMERIC) <= 2.5 ORDER BY option_id", ['siteurl', 'home', 'stylesheet']],
    'cast integer handles leading plus' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST('+10cache' AS INTEGER) = CAST(option_value AS INTEGER) ORDER BY option_id", ['siteurl', 'home']],
    'cast integer handles leading minus' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST('-1cache' AS INTEGER) < CAST(option_value AS INTEGER) ORDER BY option_id", ['siteurl', 'home', 'blogname', 'stylesheet', 'template', 'active_plugins']],
    'cast real zero divide null remains filtered' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(option_value AS REAL) / CAST('0' AS INTEGER) > 1 ORDER BY option_id", []],
    'cast integer modulo comparison' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(option_value AS INTEGER) % 2 = 1 ORDER BY option_id", ['blogname', 'active_plugins']],
    'cast integer shift comparison' => ["SELECT option_id AS id, option_name FROM wp_options WHERE 1 << CAST(bucket AS INTEGER) >= 4 ORDER BY option_id", ['siteurl', 'home', 'active_plugins']],
    'cast text like pattern' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(option_value AS TEXT) LIKE '10%' ORDER BY option_id", ['siteurl', 'home']],
    'cast text glob pattern' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(option_value AS TEXT) GLOB '1*' ORDER BY option_id", ['siteurl', 'home', 'active_plugins']],
    'cast integer grouped having' => ["SELECT autoload, sum(option_id) AS total FROM wp_options GROUP BY autoload HAVING sum(option_id) > CAST('6' AS INTEGER) ORDER BY autoload", ['no:12', 'yes:9']],
    'cast integer limit count' => ["SELECT option_id AS id, option_name FROM wp_options ORDER BY option_id LIMIT CAST('2x' AS INTEGER)", ['siteurl', 'home']],
    'cast integer limit comma offset' => ["SELECT option_id AS id, option_name FROM wp_options ORDER BY option_id LIMIT CAST('2x' AS INTEGER), CAST('3x' AS INTEGER)", ['blogname', 'stylesheet', 'template']],
    'cast integer hidden order column with limit' => ["SELECT option_id AS id, option_name FROM wp_options ORDER BY CAST(bucket AS INTEGER) DESC, option_id LIMIT 3", ['blogname', 'active_plugins', 'siteurl']],
    'cast text storage rank beats numeric literal' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(option_id AS TEXT) > 100 ORDER BY option_id", ['siteurl', 'home', 'blogname', 'stylesheet', 'template', 'active_plugins']],
    'cast integer storage rank enables numeric false' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(option_id AS INTEGER) > '100' ORDER BY option_id", []],
    'cast blob storage rank beats text literal' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(option_id AS BLOB) > '999' ORDER BY option_id", ['siteurl', 'home', 'blogname', 'stylesheet', 'template', 'active_plugins']],
    'cast numeric storage rank below text literal' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(option_id AS NUMERIC) < '0' ORDER BY option_id", ['siteurl', 'home', 'blogname', 'stylesheet', 'template', 'active_plugins']],
    'cast text not in numeric list uses storage class' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(option_id AS TEXT) NOT IN (1, 2, 3) ORDER BY option_id", ['siteurl', 'home', 'blogname', 'stylesheet', 'template', 'active_plugins']],
    'cast integer in text literal list does not match' => ["SELECT option_id AS id, option_name FROM wp_options WHERE CAST(option_id AS INTEGER) IN ('1', '2', '3') ORDER BY option_id", []],
];

foreach ($sqlCases as $name => [$sql, $expected]) {
    $tests['upstream select sql cast affinity comparison corpus ' . $name] = static function (TestRunner $t) use ($sql, $rows, $expected): void {
        $actualRows = SQLiteSelectSql::execute($sql, ['wp_options' => $rows]);
        $actual = array_map(static function (array $row): string {
            unset($row['id']);

            return implode(':', array_map(static fn (mixed $value): string => $value === null ? '' : (string) $value, array_values($row)));
        }, $actualRows);
        $t->same($expected, $actual);
    };
}

return $tests;
