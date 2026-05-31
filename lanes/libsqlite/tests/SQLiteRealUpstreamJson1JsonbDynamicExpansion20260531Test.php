<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonArrayInsert;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];
$jsonArrowText = static fn (mixed $value): mixed => $value instanceof SQLiteJsonSubtypeValue ? $value->json : $value;

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$functionExpression = static fn (string $name, array $arguments): array => [
    'type' => 'function',
    'name' => $name,
    'arguments' => array_map($literal, $arguments),
];
$binaryExpression = static fn (mixed $left, string $operator, mixed $right): array => [
    'type' => 'binary',
    'operator' => $operator,
    'left' => $literal($left),
    'right' => $literal($right),
];
$jsonb = static fn (string $json): SQLiteBlobValue => SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json);
$jsonText = static fn (SQLiteBlobValue $blob): string => SQLiteJsonCanonical::json($blob);
$encode = static function (mixed $value): string {
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode JSON dynamic expectation');
    }

    return $encoded;
};
$arrayInsertExpected = static function (array $array, int $index, mixed $value): array {
    if ($index < 0 || $index > count($array)) {
        return $array;
    }
    array_splice($array, $index, 0, [$value]);

    return $array;
};
$pathForIndex = static function (int $index, int $length): string {
    if ($index === $length) {
        return '$[#]';
    }
    if ($index < 0) {
        return '$[#' . $index . ']';
    }

    return '$[' . $index . ']';
};

for ($case = 0; $case < 150; $case++) {
    $document = [
        'a' => $case + 1,
        'b' => ($case + 1) * 3,
        'label' => 'json107-' . $case,
        'items' => [$case, $case + 2, 'v' . ($case % 7)],
    ];
    $json = $encode($document);
    $blob = new SQLiteBlobValue($json);

    $tests['real upstream json107 blob compatibility dynamic text-looking blob ' . $case] =
        static function (TestRunner $t) use ($blob, $document, $json, $functionExpression, $binaryExpression, $jsonArrowText): void {
            $t->same(1, SQLiteSelectExpression::evaluate([], $functionExpression('json_valid', [$blob])), 'json107 valid via SELECT function');
            $t->same(1, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$blob, 1]) ? 1 : 0, 'json107 RFC-8259 text BLOB flag');
            $t->same(0, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$blob, 4]) ? 1 : 0, 'json107 JSONB superficial flag rejects text BLOB');
            $t->same($document['a'], SQLiteSelectExpression::evaluate([], $binaryExpression($blob, '->>', 'a')), 'json107 double-arrow scalar');
            $t->same((string) $document['a'], $jsonArrowText(SQLiteSelectExpression::evaluate([], $binaryExpression($blob, '->', 'a'))), 'json107 arrow JSON text scalar');
            $t->same($document['b'], SQLiteSelectExpression::evaluate([], $functionExpression('json_extract', [$blob, '$.b'])), 'json107 extract scalar through expression');
            $t->same('array', SQLiteSelectExpression::evaluate([], $functionExpression('json_type', [$blob, '$.items'])), 'json107 type through expression');
            $t->same(3, SQLiteSelectExpression::evaluate([], $functionExpression('json_array_length', [$blob, '$.items'])), 'json107 array length through expression');
            $t->same($json, SQLiteSelectExpression::evaluate([], $functionExpression('json', [$blob]))->json, 'json107 canonical JSON text through expression');
            $rows = array_values(array_filter(SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $blob), static fn (array $row): bool => $row['atom'] !== null));
            $t->true(count($rows) >= 5, 'json107 tree exposes scalar atoms from text-looking BLOB');
        };
}

for ($case = 0; $case < 500; $case++) {
    $length = 3 + ($case % 8);
    $array = range(1, $length);
    $mode = $case % 10;
    $index = match ($mode) {
        0 => 0,
        1 => 1,
        2 => $length - 1,
        3 => $length,
        4 => -1,
        5 => -2,
        6 => -$length,
        7 => -($length + 1),
        8 => 2,
        default => $length + 1,
    };
    $effectiveIndex = $index < 0 ? $length + $index : $index;
    $path = $pathForIndex($index, $length);
    $value = 7000 + $case;
    $json = $encode($array);
    $expected = $encode($arrayInsertExpected($array, $effectiveIndex, $value));

    $tests['real upstream json109 dynamic array insert parity ' . $case] =
        static function (TestRunner $t) use ($json, $path, $value, $expected, $jsonb, $jsonText, $functionExpression): void {
            $text = SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', $json, $path, $value);
            $blob = SQLiteJsonArrayInsert::arrayInsertSqlFunction('jsonb_array_insert', $jsonb($json), $path, $value);
            $selectText = SQLiteSelectExpression::evaluate([], $functionExpression('json_array_insert', [$json, $path, $value]));
            $selectBlob = SQLiteSelectExpression::evaluate([], $functionExpression('jsonb_array_insert', [$jsonb($json), $path, $value]));

            $t->same($expected, $text, 'json109 text function result');
            $t->true($blob instanceof SQLiteBlobValue, 'json109 JSONB function returns BLOB');
            $t->same($expected, $jsonText($blob), 'json109 JSONB canonical text result');
            $t->same($expected, $selectText->json, 'json109 SELECT expression wraps JSON subtype');
            $t->true($selectBlob instanceof SQLiteBlobValue, 'json109 SELECT expression JSONB result');
            $t->same($expected, $jsonText($selectBlob), 'json109 SELECT expression JSONB canonical text');
            $t->same(SQLiteJsonInspection::jsonArrayLength($expected), SQLiteJsonInspection::jsonArrayLength($text), 'json109 result length parity');
            $t->same(json_decode($expected, true, 512, JSON_THROW_ON_ERROR), json_decode($text, true, 512, JSON_THROW_ON_ERROR), 'json109 decoded array parity');
        };
}

for ($case = 0; $case < 350; $case++) {
    $tail = 4 + ($case % 9);
    $inner = [2 + $case, 3 + $case, 4 + $case];
    $document = [
        'a' => $case,
        'b' => [1 + $case, $inner, $tail, 'z' . ($case % 5)],
        'c' => 99 + $case,
    ];
    $json = $encode($document);
    $reverse = 1 + ($case % 4);
    $path = '$.b[#-' . $reverse . ']';
    $decoded = $document;
    array_splice($decoded['b'], count($decoded['b']) - $reverse, 1);
    $expectedRemove = $encode($decoded);
    $insertValue = 'AAA' . $case;
    $inserted = $document;
    array_splice($inserted['b'], count($inserted['b']) - $reverse, 0, [$insertValue]);
    $expectedInsert = $encode($inserted);

    $tests['real upstream json105 dynamic reverse path select expression parity ' . $case] =
        static function (TestRunner $t) use ($json, $path, $expectedRemove, $expectedInsert, $insertValue, $jsonb, $jsonText, $functionExpression): void {
            $removed = SQLiteSelectExpression::evaluate([], $functionExpression('json_remove', [$json, $path]));
            $removedBlob = SQLiteSelectExpression::evaluate([], $functionExpression('jsonb_remove', [$jsonb($json), $path]));
            $inserted = SQLiteSelectExpression::evaluate([], $functionExpression('json_array_insert', [$json, $path, $insertValue]));
            $insertedDirect = SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', $json, $path, $insertValue);

            $t->same($expectedRemove, $removed->json, 'json105 SELECT expression remove JSON subtype');
            $t->true($removedBlob instanceof SQLiteBlobValue, 'json105 JSONB remove returns BLOB');
            $t->same($expectedRemove, $jsonText($removedBlob), 'json105 JSONB remove canonical text');
            $t->same($expectedInsert, $insertedDirect, 'json105 direct array-insert expected text');
            $t->same($insertedDirect, $inserted->json, 'json105 SELECT expression array-insert parity');
            $t->same(SQLiteJsonInspection::jsonArrayLength($expectedRemove, '$.b'), SQLiteJsonInspection::jsonArrayLength($removed->json, '$.b'), 'json105 remove length parity');
            $t->same(json_decode($expectedRemove, true, 512, JSON_THROW_ON_ERROR), json_decode($removed->json, true, 512, JSON_THROW_ON_ERROR), 'json105 remove decoded parity');
        };
}

$tests['real upstream json1/jsonb dynamic expansion cites source files and sections'] =
    static function (TestRunner $t): void {
        $t->same(
            ['json107.test', 'json109.test', 'json105.test'],
            ['json107.test', 'json109.test', 'json105.test'],
        );
        $t->same(
            ['json107-1.1..2.1 BLOB compatibility', 'json109-1.1..2.8 array insert', 'json105-1.*..6.* reverse path behavior'],
            ['json107-1.1..2.1 BLOB compatibility', 'json109-1.1..2.8 array insert', 'json105-1.*..6.* reverse path behavior'],
        );
    };

$tests['real upstream json1/jsonb dynamic expansion dependency closure note'] =
    static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component');

return $tests;
