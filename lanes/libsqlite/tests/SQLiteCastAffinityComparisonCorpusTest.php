<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectExpression;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$rows = [
    ['setting_id' => 1, 'key_name' => 'baseurl', 'key_value' => '10', 'load_policy' => 'yes', 'bucket' => '2.5'],
    ['setting_id' => 2, 'key_name' => 'site_title', 'key_value' => '10abc', 'load_policy' => 'no', 'bucket' => '02'],
    ['setting_id' => 3, 'key_name' => 'tenant_label', 'key_value' => '9.75', 'load_policy' => 'yes', 'bucket' => '9e1'],
    ['setting_id' => 4, 'key_name' => 'style_variant', 'key_value' => 'abc', 'load_policy' => 'no', 'bucket' => '0'],
    ['setting_id' => 5, 'key_name' => 'layout_name', 'key_value' => '', 'load_policy' => 'yes', 'bucket' => null],
    ['setting_id' => 6, 'key_name' => 'module_registry', 'key_value' => new SQLiteBlobValue('11module'), 'load_policy' => 'no', 'bucket' => '11.5'],
];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$column = static fn (string $name): array => ['type' => 'column', 'name' => $name];
$cast = static fn (array $operand, string $target): array => ['type' => 'cast', 'operand' => $operand, 'target' => $target];

$expressionCases = [
    'cast integer from numeric text' => [$cast($literal(' 42abc'), 'INTEGER'), [], 42],
    'cast integer from real truncates toward zero' => [$cast($literal(-7.9), 'INTEGER'), [], -7],
    'cast integer from non numeric text returns zero' => [$cast($literal('modules'), 'INTEGER'), [], 0],
    'cast real from exponent text' => [$cast($literal('9e1tail'), 'REAL'), [], 90.0],
    'cast numeric keeps integer prefix as integer' => [$cast($literal('0012cache'), 'NUMERIC'), [], 12],
    'cast numeric keeps decimal prefix as real' => [$cast($literal('2.50cache'), 'NUMERIC'), [], 2.5],
    'cast text from integer' => [$cast($literal(12), 'TEXT'), [], '12'],
    'cast text from blob bytes' => [$cast($literal(new SQLiteBlobValue('abc')), 'TEXT'), [], 'abc'],
    'cast blob from text wraps bytes' => [$cast($literal('abc'), 'BLOB'), [], new SQLiteBlobValue('abc')],
    'cast null remains null' => [$cast($literal(null), 'INTEGER'), [], null],
    'cast column integer numeric prefix' => [$cast($column('key_value'), 'INTEGER'), $rows[1], 10],
    'cast column real numeric prefix' => [$cast($column('bucket'), 'REAL'), $rows[2], 90.0],
    'cast column text from blob' => [$cast($column('key_value'), 'TEXT'), $rows[5], '11module'],
    'cast column blob from integer text' => [$cast($column('key_value'), 'BLOB'), $rows[0], new SQLiteBlobValue('10')],
    'cast empty text to numeric zero' => [$cast($column('key_value'), 'NUMERIC'), $rows[4], 0],
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
    'integer cast equality includes text prefix' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(key_value AS INTEGER) = 10 ORDER BY setting_id", ['baseurl', 'site_title']],
    'integer cast excludes raw text storage comparison' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE key_value = 10 ORDER BY setting_id", []],
    'integer cast greater than numeric literal' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(key_value AS INTEGER) > 9 ORDER BY setting_id", ['baseurl', 'site_title', 'module_registry']],
    'integer cast less than numeric literal includes zero casts' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(key_value AS INTEGER) < 1 ORDER BY setting_id", ['style_variant', 'layout_name']],
    'real cast finds decimal prefix' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(key_value AS REAL) > 9.5 ORDER BY setting_id", ['baseurl', 'site_title', 'tenant_label', 'module_registry']],
    'numeric cast equality compares numeric classes' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(bucket AS NUMERIC) = 2 ORDER BY setting_id", ['site_title']],
    'numeric cast exponent comparison' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(bucket AS NUMERIC) >= 90 ORDER BY setting_id", ['tenant_label']],
    'text cast preserves lexical comparison' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(key_value AS TEXT) > '10' ORDER BY setting_id", ['site_title', 'tenant_label', 'style_variant', 'module_registry']],
    'blob cast compares after text storage class' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(key_value AS BLOB) > CAST('10' AS TEXT) ORDER BY setting_id", ['baseurl', 'site_title', 'tenant_label', 'style_variant', 'layout_name', 'module_registry']],
    'integer cast between numeric literals' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(key_value AS INTEGER) BETWEEN 9 AND 10 ORDER BY setting_id", ['baseurl', 'site_title', 'tenant_label']],
    'integer cast not between numeric literals' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(key_value AS INTEGER) NOT BETWEEN 1 AND 10 ORDER BY setting_id", ['style_variant', 'layout_name', 'module_registry']],
    'raw text between numeric literals stays false by storage rank' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE key_value BETWEEN 1 AND 10 ORDER BY setting_id", []],
    'integer cast in list' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(key_value AS INTEGER) IN (0, 11) ORDER BY setting_id", ['style_variant', 'layout_name', 'module_registry']],
    'integer cast not in list honors numeric affinity' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(key_value AS INTEGER) NOT IN (0, 10) ORDER BY setting_id", ['tenant_label', 'module_registry']],
    'real cast in list compares real and integer numeric values' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(bucket AS REAL) IN (2, 2.5, 11.5) ORDER BY setting_id", ['baseurl', 'site_title', 'module_registry']],
    'cast in conjunction with text predicate' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE load_policy = 'yes' AND CAST(key_value AS INTEGER) >= 9 ORDER BY setting_id", ['baseurl', 'tenant_label']],
    'cast in disjunction with null cast branch' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(bucket AS INTEGER) IS NULL OR CAST(bucket AS INTEGER) = 0 ORDER BY setting_id", ['style_variant', 'layout_name']],
    'cast text projection from blob' => ["SELECT key_name, CAST(key_value AS TEXT) AS value_text FROM app_settings WHERE key_name = 'module_registry'", ['module_registry:11module']],
    'cast integer projection from empty string' => ["SELECT key_name, CAST(key_value AS INTEGER) AS numeric_value FROM app_settings WHERE key_name IN ('style_variant','layout_name') ORDER BY setting_id", ['style_variant:0', 'layout_name:0']],
    'cast real projection from exponent' => ["SELECT key_name, CAST(bucket AS REAL) AS bucket_real FROM app_settings WHERE key_name = 'tenant_label'", ['tenant_label:90']],
    'order by integer cast' => ["SELECT setting_id AS id, key_name FROM app_settings ORDER BY CAST(key_value AS INTEGER) DESC, setting_id", ['module_registry', 'baseurl', 'site_title', 'tenant_label', 'style_variant', 'layout_name']],
    'order by text cast keeps lexical order' => ["SELECT setting_id AS id, key_name FROM app_settings ORDER BY CAST(key_value AS TEXT) ASC, setting_id LIMIT 4", ['layout_name', 'baseurl', 'site_title', 'module_registry']],
    'order by blob cast after text conversion' => ["SELECT setting_id AS id, key_name FROM app_settings ORDER BY CAST(key_value AS BLOB) DESC, setting_id LIMIT 3", ['style_variant', 'tenant_label', 'module_registry']],
    'cast in limit predicate' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE setting_id <= CAST('4x' AS INTEGER) ORDER BY setting_id", ['baseurl', 'site_title', 'tenant_label', 'style_variant']],
    'cast composed arithmetic comparison' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(key_value AS INTEGER) + CAST(bucket AS INTEGER) >= 12 ORDER BY setting_id", ['baseurl', 'site_title', 'tenant_label', 'module_registry']],
    'cast composed concatenation projection' => ["SELECT key_name, CAST(setting_id AS TEXT) || ':' || CAST(key_value AS TEXT) AS label FROM app_settings WHERE setting_id <= 2 ORDER BY setting_id", ['baseurl:1:10', 'site_title:2:10abc']],
    'cast numeric null comparison filters unknown' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(bucket AS NUMERIC) > 0 ORDER BY setting_id", ['baseurl', 'site_title', 'tenant_label', 'module_registry']],
    'cast numeric is null detects null input' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(bucket AS NUMERIC) IS NULL ORDER BY setting_id", ['layout_name']],
    'cast numeric is not null skips null input' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(bucket AS NUMERIC) IS NOT NULL ORDER BY setting_id", ['baseurl', 'site_title', 'tenant_label', 'style_variant', 'module_registry']],
    'cast text equality from numeric literal' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(setting_id AS TEXT) = '3' ORDER BY setting_id", ['tenant_label']],
    'cast blob equality from text literal' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(key_value AS BLOB) = CAST('10' AS BLOB) ORDER BY setting_id", ['baseurl']],
    'cast text blob equality from blob source' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(key_value AS TEXT) = '11module' ORDER BY setting_id", ['module_registry']],
    'cast integer not equal numeric' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(key_value AS INTEGER) <> 10 ORDER BY setting_id", ['tenant_label', 'style_variant', 'layout_name', 'module_registry']],
    'cast numeric less equal decimal' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(bucket AS NUMERIC) <= 2.5 ORDER BY setting_id", ['baseurl', 'site_title', 'style_variant']],
    'cast integer handles leading plus' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST('+10cache' AS INTEGER) = CAST(key_value AS INTEGER) ORDER BY setting_id", ['baseurl', 'site_title']],
    'cast integer handles leading minus' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST('-1cache' AS INTEGER) < CAST(key_value AS INTEGER) ORDER BY setting_id", ['baseurl', 'site_title', 'tenant_label', 'style_variant', 'layout_name', 'module_registry']],
    'cast real zero divide null remains filtered' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(key_value AS REAL) / CAST('0' AS INTEGER) > 1 ORDER BY setting_id", []],
    'cast integer modulo comparison' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(key_value AS INTEGER) % 2 = 1 ORDER BY setting_id", ['tenant_label', 'module_registry']],
    'cast integer shift comparison' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE 1 << CAST(bucket AS INTEGER) >= 4 ORDER BY setting_id", ['baseurl', 'site_title', 'tenant_label', 'module_registry']],
    'cast text like pattern' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(key_value AS TEXT) LIKE '10%' ORDER BY setting_id", ['baseurl', 'site_title']],
    'cast text glob pattern' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(key_value AS TEXT) GLOB '1*' ORDER BY setting_id", ['baseurl', 'site_title', 'module_registry']],
    'cast integer grouped having' => ["SELECT load_policy, sum(setting_id) AS total FROM app_settings GROUP BY load_policy HAVING sum(setting_id) > CAST('6' AS INTEGER) ORDER BY load_policy", ['no:12', 'yes:9']],
    'cast integer limit count' => ["SELECT setting_id AS id, key_name FROM app_settings ORDER BY setting_id LIMIT CAST('2x' AS INTEGER)", ['baseurl', 'site_title']],
    'cast integer limit comma offset' => ["SELECT setting_id AS id, key_name FROM app_settings ORDER BY setting_id LIMIT CAST('2x' AS INTEGER), CAST('3x' AS INTEGER)", ['tenant_label', 'style_variant', 'layout_name']],
    'cast integer hidden order column with limit' => ["SELECT setting_id AS id, key_name FROM app_settings ORDER BY CAST(bucket AS INTEGER) DESC, setting_id LIMIT 3", ['module_registry', 'tenant_label', 'baseurl']],
    'cast text comparison uses text ordering against numeric literal' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(setting_id AS TEXT) > 100 ORDER BY setting_id", ['site_title', 'tenant_label', 'style_variant', 'layout_name', 'module_registry']],
    'cast integer storage rank enables numeric false' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(setting_id AS INTEGER) > '100' ORDER BY setting_id", []],
    'cast blob storage rank beats text literal' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(setting_id AS BLOB) > '999' ORDER BY setting_id", ['baseurl', 'site_title', 'tenant_label', 'style_variant', 'layout_name', 'module_registry']],
    'cast numeric comparison against text literal stays false' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(setting_id AS NUMERIC) < '0' ORDER BY setting_id", []],
    'cast text not in numeric list compares textual ids' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(setting_id AS TEXT) NOT IN (1, 2, 3) ORDER BY setting_id", ['style_variant', 'layout_name', 'module_registry']],
    'cast integer in text literal list coerces numeric matches' => ["SELECT setting_id AS id, key_name FROM app_settings WHERE CAST(setting_id AS INTEGER) IN ('1', '2', '3') ORDER BY setting_id", ['baseurl', 'site_title', 'tenant_label']],
];

foreach ($sqlCases as $name => [$sql, $expected]) {
    $tests['upstream select sql cast affinity comparison corpus ' . $name] = static function (TestRunner $t) use ($sql, $rows, $expected): void {
        $actualRows = SQLiteSelectSql::execute($sql, ['app_settings' => $rows]);
        $actual = array_map(static function (array $row): string {
            unset($row['id']);

            return implode(':', array_map(static fn (mixed $value): string => $value === null ? '' : (string) $value, array_values($row)));
        }, $actualRows);
        $t->same($expected, $actual);
    };
}

return $tests;
