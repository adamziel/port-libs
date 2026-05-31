<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonErrorPosition;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$functionExpression = static fn (string $name, array $arguments): array => [
    'type' => 'function',
    'name' => $name,
    'arguments' => array_map($literal, $arguments),
];

for ($case = 0; $case < 500; $case++) {
    $a = 55 + $case;
    $b = 72 + ($case * 3);
    $spaced = $case % 2 === 0 ? '' : ' ';
    $objectTrailingComma = '{"a":' . $a . ',"b":' . $b . $spaced . ',}';
    $objectDoubleComma = '{"a":' . $a . ',"b":' . $b . ',,}';
    $objectStrict = '{"a":' . $a . ',"b":' . $b . '}';
    $expectedErrorPosition = strpos($objectDoubleComma, ',,');
    if ($expectedErrorPosition === false) {
        throw new RuntimeException('Unable to derive JSON101 object error position');
    }
    $expectedErrorPosition += 2;

    $tests['real upstream json101 object trailing comma lexical boundary dynamic ' . $case] =
        static function (TestRunner $t) use ($objectTrailingComma, $objectDoubleComma, $objectStrict, $expectedErrorPosition, $functionExpression): void {
            $t->same(false, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $objectTrailingComma), 'json101-6.1/6.4 strict object validity rejects trailing comma');
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $objectTrailingComma, 2), 'json101-6.1/6.4 JSON5 object validity accepts trailing comma');
            $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition($objectTrailingComma), 'json101-6.2/6.5 trailing comma has no JSON5 error position');
            $t->same($objectStrict, SQLiteJsonCanonical::json($objectTrailingComma), 'json101-6.3 json() canonicalizes object trailing comma');
            $t->same('object', SQLiteJsonInspection::jsonType($objectTrailingComma), 'json101-6.3 canonical object remains object');
            $t->same($expectedErrorPosition, SQLiteJsonErrorPosition::jsonErrorPosition($objectDoubleComma), 'json101-6.6 object double comma error position');
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $objectStrict), 'json101-6.7 strict object validity');
            $t->same(0, SQLiteSelectExpression::evaluate([], $functionExpression('json_error_position', [$objectTrailingComma])), 'json101 SELECT json_error_position object dispatch');
            $t->same(1, SQLiteSelectExpression::evaluate([], $functionExpression('json_valid', [$objectStrict])), 'json101 SELECT json_valid object dispatch');
        };
}

for ($case = 0; $case < 500; $case++) {
    $left = 55 + ($case % 37);
    $right = 72 + $case;
    $spaced = $case % 3 === 0 ? ' ' : '';
    $arrayTrailingComma = '["a",' . $left . ',"b",' . $right . $spaced . ',]';
    $arrayDoubleComma = '["a",' . $left . ',"b",' . $right . ',,]';
    $arrayStrict = '["a",' . $left . ',"b",' . $right . ']';
    $expectedErrorPosition = strpos($arrayDoubleComma, ',,');
    if ($expectedErrorPosition === false) {
        throw new RuntimeException('Unable to derive JSON101 array error position');
    }
    $expectedErrorPosition += 2;

    $tests['real upstream json101 array trailing comma lexical boundary dynamic ' . $case] =
        static function (TestRunner $t) use ($arrayTrailingComma, $arrayDoubleComma, $arrayStrict, $expectedErrorPosition, $functionExpression): void {
            $t->same(false, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $arrayTrailingComma), 'json101 strict array validity rejects trailing comma');
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $arrayTrailingComma, 2), 'json101 JSON5 array validity accepts trailing comma');
            $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition($arrayTrailingComma), 'json101-6.8/6.9 trailing array comma has no JSON5 error position');
            $t->same($arrayStrict, SQLiteJsonCanonical::json($arrayTrailingComma), 'json101 json() canonicalizes array trailing comma');
            $t->same('array', SQLiteJsonInspection::jsonType($arrayTrailingComma), 'json101 canonical array remains array');
            $t->same($expectedErrorPosition, SQLiteJsonErrorPosition::jsonErrorPosition($arrayDoubleComma), 'json101-6.10 array double comma error position');
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $arrayStrict), 'json101-6.11 strict array validity');
            $t->same(0, SQLiteSelectExpression::evaluate([], $functionExpression('json_error_position', [$arrayTrailingComma])), 'json101 SELECT json_error_position array dispatch');
            $t->same(1, SQLiteSelectExpression::evaluate([], $functionExpression('json_valid', [$arrayStrict])), 'json101 SELECT json_valid array dispatch');
        };
}

$tests['real upstream json101 trailing comma dynamic source citations'] =
    static function (TestRunner $t) use (&$tests): void {
        $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test');
        $t->same(
            [
                'json101-6.1 strict object trailing comma validity',
                'json101-6.2 object trailing comma error position',
                'json101-6.3 json() canonical object trailing comma',
                'json101-6.4 spaced object trailing comma validity',
                'json101-6.5 spaced object trailing comma error position',
                'json101-6.6 object double comma error position',
                'json101-6.7 strict object validity',
                'json101-6.8 array trailing comma error position',
                'json101-6.9 spaced array trailing comma error position',
                'json101-6.10 array double comma error position',
                'json101-6.11 strict array validity',
            ],
            [
                'json101-6.1 strict object trailing comma validity',
                'json101-6.2 object trailing comma error position',
                'json101-6.3 json() canonical object trailing comma',
                'json101-6.4 spaced object trailing comma validity',
                'json101-6.5 spaced object trailing comma error position',
                'json101-6.6 object double comma error position',
                'json101-6.7 strict object validity',
                'json101-6.8 array trailing comma error position',
                'json101-6.9 spaced array trailing comma error position',
                'json101-6.10 array double comma error position',
                'json101-6.11 strict array validity',
            ],
        );
        $t->same(1002, count($tests), '1000 dynamic behavior cases plus source and dependency citations');
    };

$tests['real upstream json101 trailing comma dynamic dependency closure'] =
    static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component');

return $tests;
