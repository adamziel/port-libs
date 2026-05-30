<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$firstValue = static function (string $sql): mixed {
    $rows = SQLiteSelectSql::execute($sql, []);
    if (count($rows) !== 1) {
        throw new RuntimeException("Expected one SELECT row for {$sql}");
    }

    return array_values($rows[0])[0];
};

$values = [
    ['id' => 'null', 'sql' => 'NULL', 'value' => null],
    ['id' => 'empty-text', 'sql' => "''", 'value' => ''],
    ['id' => 'space', 'sql' => "' '", 'value' => ' '],
    ['id' => 'alpha', 'sql' => "'alpha'", 'value' => 'alpha'],
    ['id' => 'bravo', 'sql' => "'bravo'", 'value' => 'bravo'],
    ['id' => 'mixed-case', 'sql' => "'AbC'", 'value' => 'AbC'],
    ['id' => 'digit-text', 'sql' => "'123'", 'value' => '123'],
    ['id' => 'real-text', 'sql' => "'45.60'", 'value' => '45.60'],
    ['id' => 'zero-text', 'sql' => "'0'", 'value' => '0'],
    ['id' => 'negative-text', 'sql' => "'-7'", 'value' => '-7'],
    ['id' => 'quote-text', 'sql' => "'a''b'", 'value' => "a'b"],
    ['id' => 'comma-text', 'sql' => "','", 'value' => ','],
    ['id' => 'slash-text', 'sql' => "'a/b'", 'value' => 'a/b'],
    ['id' => 'underscore-text', 'sql' => "'a_b'", 'value' => 'a_b'],
    ['id' => 'percent-text', 'sql' => "'a%b'", 'value' => 'a%b'],
    ['id' => 'unicode-text', 'sql' => "'caf\xc3\xa9'", 'value' => "caf\xc3\xa9"],
    ['id' => 'zero-int', 'sql' => '0', 'value' => 0],
    ['id' => 'one-int', 'sql' => '1', 'value' => 1],
    ['id' => 'two-int', 'sql' => '2', 'value' => 2],
    ['id' => 'ten-int', 'sql' => '10', 'value' => 10],
    ['id' => 'negative-int', 'sql' => '-5', 'value' => -5],
    ['id' => 'large-int', 'sql' => '123456', 'value' => 123456],
    ['id' => 'real-zero', 'sql' => '0.0', 'value' => 0.0],
    ['id' => 'real-half', 'sql' => '0.5', 'value' => 0.5],
    ['id' => 'real-whole', 'sql' => '5.0', 'value' => 5.0],
    ['id' => 'real-negative', 'sql' => '-2.25', 'value' => -2.25],
    ['id' => 'exp-real', 'sql' => '1.25e2', 'value' => 125.0],
    ['id' => 'leading-real', 'sql' => '.75', 'value' => 0.75],
    ['id' => 'true-ish', 'sql' => "'true'", 'value' => 'true'],
    ['id' => 'false-ish', 'sql' => "'false'", 'value' => 'false'],
    ['id' => 'json-ish', 'sql' => '\'{"k":1}\'', 'value' => '{"k":1}'],
    ['id' => 'path-ish', 'sql' => "'$.a[0]'", 'value' => '$.a[0]'],
];

$stringify = static function (mixed $value): ?string {
    if ($value === null) {
        return null;
    }
    if (is_float($value) && floor($value) === $value) {
        return sprintf('%.1f', $value);
    }

    return (string) $value;
};

foreach ($values as $left) {
    foreach ($values as $right) {
        $name = sprintf('real upstream e_expr-5 bulk concat %s with %s', $left['id'], $right['id']);
        $tests[$name] = static function (TestRunner $t) use ($firstValue, $left, $right, $stringify): void {
            $sql = sprintf('SELECT %s || %s', $left['sql'], $right['sql']);
            $expected = $left['value'] === null || $right['value'] === null
                ? null
                : $stringify($left['value']) . $stringify($right['value']);

            $t->same($expected, $firstValue($sql), $sql);
        };
    }
}

return $tests;
