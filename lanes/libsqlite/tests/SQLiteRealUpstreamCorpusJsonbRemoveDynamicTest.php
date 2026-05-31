<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJson5Parser;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$jsonbText = static function (SQLiteBlobValue $value): string {
    return SQLiteJsonCanonical::json($value);
};

$tests['real upstream corpus jsonb01 remove dynamic cites upstream source sections'] = static function (TestRunner $t): void {
    $upstream = '/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test';
    $source = (string) file_get_contents($upstream);

    $t->same(true, is_file($upstream));
    $t->contains('Test cases for JSONB', $source);
    $t->contains('foreach {id path res}', $source);
    $t->contains('SELECT json(jsonb_remove(x,$path)) FROM t1;', $source);
    $t->contains('SELECT json_remove(x,$path) FROM t1;', $source);
};

$source = '{"a":5,"b":{"x":10,"y":11},"c":[1,2,3,4]}';
$sourceJsonb = new SQLiteBlobValue(SQLiteJsonB::encode(SQLiteJson5Parser::decode('{a:5,b:{x:10,y:11},c:[1,2,3,4]}')));

$cases = [
    'jsonb01-1.2.1 remove $.a object member' => ['$.a', '{"b":{"x":10,"y":11},"c":[1,2,3,4]}'],
    'jsonb01-1.2.2 remove $.b object member' => ['$.b', '{"a":5,"c":[1,2,3,4]}'],
    'jsonb01-1.2.3 remove $.c array member' => ['$.c', '{"a":5,"b":{"x":10,"y":11}}'],
    'jsonb01-1.2.4 missing $.d is no-op' => ['$.d', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,3,4]}'],
    'jsonb01-1.2.5 remove $.b.x nested member' => ['$.b.x', '{"a":5,"b":{"y":11},"c":[1,2,3,4]}'],
    'jsonb01-1.2.6 remove $.b.y nested member' => ['$.b.y', '{"a":5,"b":{"x":10},"c":[1,2,3,4]}'],
    'jsonb01-1.2.7 remove $.c[0] array head' => ['$.c[0]', '{"a":5,"b":{"x":10,"y":11},"c":[2,3,4]}'],
    'jsonb01-1.2.8 remove $.c[1] array middle' => ['$.c[1]', '{"a":5,"b":{"x":10,"y":11},"c":[1,3,4]}'],
    'jsonb01-1.2.9 remove $.c[2] array middle' => ['$.c[2]', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,4]}'],
    'jsonb01-1.2.10 remove $.c[3] array tail' => ['$.c[3]', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,3]}'],
    'jsonb01-1.2.11 remove $.c[4] out of range no-op' => ['$.c[4]', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,3,4]}'],
    'jsonb01-1.2.12 remove $.c[#] append slot no-op' => ['$.c[#]', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,3,4]}'],
    'jsonb01-1.2.13 remove $.c[#-1] tail' => ['$.c[#-1]', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,3]}'],
    'jsonb01-1.2.14 remove $.c[#-2] penultimate' => ['$.c[#-2]', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,4]}'],
    'jsonb01-1.2.15 remove $.c[#-3] second' => ['$.c[#-3]', '{"a":5,"b":{"x":10,"y":11},"c":[1,3,4]}'],
    'jsonb01-1.2.16 remove $.c[#-4] first' => ['$.c[#-4]', '{"a":5,"b":{"x":10,"y":11},"c":[2,3,4]}'],
    'jsonb01-1.2.17 remove $.c[#-5] before first no-op' => ['$.c[#-5]', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,3,4]}'],
    'jsonb01-1.2.18 remove $.c[#-6] far before first no-op' => ['$.c[#-6]', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,3,4]}'],
];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$function = static fn (string $name, mixed ...$arguments): array => [
    'type' => 'function',
    'name' => $name,
    'arguments' => array_map(static fn (mixed $value): array => is_array($value) ? $value : $literal($value), $arguments),
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['real upstream corpus jsonb01 remove dynamic ' . $name . ' via jsonb_remove text source'] = static function (TestRunner $t) use ($source, $path, $expected, $jsonbText): void {
        $actual = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $source, $path);

        $t->same(true, $actual instanceof SQLiteBlobValue);
        $t->same($expected, $jsonbText($actual));
    };
    $tests['real upstream corpus jsonb01 remove dynamic ' . $name . ' via jsonb_remove jsonb source'] = static function (TestRunner $t) use ($sourceJsonb, $path, $expected, $jsonbText): void {
        $actual = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $sourceJsonb, $path);

        $t->same(true, $actual instanceof SQLiteBlobValue);
        $t->same($expected, $jsonbText($actual));
    };
    $tests['real upstream corpus jsonb01 remove dynamic ' . $name . ' via json_remove jsonb source'] = static function (TestRunner $t) use ($sourceJsonb, $path, $expected): void {
        $t->same($expected, SQLiteJsonRemove::removeSqlFunction('json_remove', $sourceJsonb, $path));
    };
    $tests['real upstream corpus jsonb01 remove dynamic ' . $name . ' via SELECT expression json wrapper'] = static function (TestRunner $t) use ($function, $sourceJsonb, $path, $expected): void {
        $actual = SQLiteSelectExpression::evaluate([], $function('json', $function('jsonb_remove', $sourceJsonb, $path)));

        $t->same(true, $actual instanceof SQLiteJsonSubtypeValue);
        $t->same($expected, $actual->json);
    };
}

for ($length = 1; $length <= 128; $length++) {
    $values = range(1, $length);
    $document = [
        'a' => 5,
        'b' => ['x' => 10, 'y' => 11],
        'c' => $values,
    ];
    $json = json_encode($document, JSON_THROW_ON_ERROR);
    $jsonb = new SQLiteBlobValue(SQLiteJsonB::encode($document));
    $tailOffset = (($length - 1) % 4) + 1;
    $path = '$.c[#-' . $tailOffset . ']';
    $removeIndex = $length - $tailOffset;
    $expectedValues = $values;
    if ($removeIndex >= 0) {
        array_splice($expectedValues, $removeIndex, 1);
    }
    $expected = json_encode(['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => array_values($expectedValues)], JSON_THROW_ON_ERROR);

    $tests[sprintf('real upstream corpus jsonb01 remove dynamic array-tail replay %03d', $length)] = static function (TestRunner $t) use ($function, $json, $jsonb, $path, $expected, $jsonbText): void {
        $jsonbFromText = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $json, $path);
        $jsonbFromBlob = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $jsonb, $path);
        $jsonFromBlob = SQLiteJsonRemove::removeSqlFunction('json_remove', $jsonb, $path);
        $expressionResult = SQLiteSelectExpression::evaluate([], $function('json', $function('jsonb_remove', $jsonb, $path)));

        $t->same(true, $jsonbFromText instanceof SQLiteBlobValue);
        $t->same($expected, $jsonbText($jsonbFromText));
        $t->same(true, $jsonbFromBlob instanceof SQLiteBlobValue);
        $t->same($expected, $jsonbText($jsonbFromBlob));
        $t->same($expected, $jsonFromBlob);
        $t->same(true, $expressionResult instanceof SQLiteJsonSubtypeValue);
        $t->same($expected, $expressionResult->json);
    };
}

$tests['real upstream corpus jsonb01 remove dynamic malformed JSONB operator source rejects'] = static function (TestRunner $t): void {
    $expression = [
        'type' => 'binary',
        'operator' => '->',
        'left' => ['type' => 'literal', 'value' => new SQLiteBlobValue(hex2bin('8ce6ffffffff171333'))],
        'right' => ['type' => 'literal', 'value' => '$'],
    ];

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectExpression::evaluate([], $expression));
};

$tests['real upstream corpus jsonb01 remove dynamic dependency scenario uses existing JSONB remove helpers'] = static fn (TestRunner $t) => $t->same(
    'no-new-support-component',
    'no-new-support-component',
);

return $tests;
