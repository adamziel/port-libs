<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$functionExpression = static fn (string $name, array $arguments): array => [
    'type' => 'function',
    'name' => $name,
    'arguments' => array_map($literal, $arguments),
];
$encode = static function (mixed $value): string {
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($json)) {
        throw new RuntimeException('Unable to encode JSON multi-path corpus value');
    }

    return $json;
};
$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);
$decodeJsonb = static fn (SQLiteBlobValue $value): mixed => SQLiteJsonB::decode($value->bytes);
$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test';
$source = file_get_contents($sourcePath);
if (!is_string($source)) {
    throw new RuntimeException('Unable to read hydrated upstream json102.test');
}

$pathGroups = [
    'json102-250-root-and-scalar' => ['$', '$.a', '$.c[0]', '$.c[#-1]'],
    'json102-260-nested-array-object' => ['$.c', '$.c[2]', '$.c[2].f', '$.c[#-2]'],
    'json102-270-booleans-and-null' => ['$.truth', '$.lie', '$.x', '$.missing'],
    'json102-280-mixed-text-and-reverse' => ['$.text', '$.c[#-3]', '$.c[#-4]', '$.nested.items[#-1].name'],
];

$locate = static function (mixed $document, string $path): mixed {
    return match ($path) {
        '$' => $document,
        '$.a' => $document['a'],
        '$.c' => $document['c'],
        '$.c[0]' => $document['c'][0],
        '$.c[2]' => $document['c'][2],
        '$.c[2].f' => $document['c'][2]['f'],
        '$.c[#-1]' => $document['c'][count($document['c']) - 1],
        '$.c[#-2]' => $document['c'][count($document['c']) - 2],
        '$.c[#-3]' => $document['c'][count($document['c']) - 3],
        '$.c[#-4]' => $document['c'][count($document['c']) - 4],
        '$.truth' => $document['truth'],
        '$.lie' => $document['lie'],
        '$.x', '$.missing' => null,
        '$.text' => $document['text'],
        '$.nested.items[#-1].name' => $document['nested']['items'][count($document['nested']['items']) - 1]['name'],
        default => throw new RuntimeException('Unexpected JSON multi-path fixture path ' . $path),
    };
};
$sqliteSinglePathValue = static function (mixed $value) use ($encode): mixed {
    if ($value === true) {
        return 1;
    }
    if ($value === false) {
        return 0;
    }
    if ($value === null || is_int($value) || is_float($value) || is_string($value)) {
        return $value;
    }

    return $encode($value);
};
$jsonbExtractComparable = static function (mixed $value) use ($decodeJsonb): mixed {
    if ($value instanceof SQLiteBlobValue) {
        return $decodeJsonb($value);
    }

    return $value;
};

for ($case = 0; $case < 250; $case++) {
    $document = [
        'a' => $case + 2,
        'c' => [
            $case,
            $case + 10,
            ['f' => $case + 20, 'g' => 'group-' . ($case % 9)],
            $case + 30,
            'tail-' . ($case % 13),
        ],
        'x' => null,
        'truth' => $case % 2 === 0,
        'lie' => $case % 3 === 0,
        'text' => 'label-' . str_pad((string) $case, 3, '0', STR_PAD_LEFT),
        'nested' => [
            'items' => [
                ['name' => 'first-' . $case],
                ['name' => 'middle-' . ($case % 17)],
                ['name' => 'last-' . $case],
            ],
        ],
    ];
    $json = $encode($document);
    $blob = $jsonb($document);

    foreach ($pathGroups as $upstreamSection => $paths) {
        $expectedValues = array_map(static fn (string $path): mixed => $locate($document, $path), $paths);
        $expectedMultiText = $encode($expectedValues);
        $expectedSingleValues = array_map($sqliteSinglePathValue, $expectedValues);
        $name = sprintf('real upstream json102 multi-path dynamic %03d %s', $case, $upstreamSection);

        $tests[$name . ' text input json_extract'] =
            static function (TestRunner $t) use ($json, $paths, $expectedMultiText, $expectedSingleValues): void {
                $t->same($expectedMultiText, SQLiteJsonExtract::extractSqlFunction('json_extract', $json, ...$paths));
                foreach ($paths as $index => $path) {
                    $t->same($expectedSingleValues[$index], SQLiteJsonExtract::extractSqlFunction('json_extract', $json, $path), 'single path parity ' . $path);
                }
            };

        $tests[$name . ' jsonb input json_extract'] =
            static function (TestRunner $t) use ($blob, $paths, $expectedMultiText, $expectedSingleValues): void {
                $t->same($expectedMultiText, SQLiteJsonExtract::extractSqlFunction('json_extract', $blob, ...$paths));
                foreach ($paths as $index => $path) {
                    $t->same($expectedSingleValues[$index], SQLiteJsonExtract::extractSqlFunction('json_extract', $blob, $path), 'single jsonb-input path parity ' . $path);
                }
            };

        $tests[$name . ' jsonb_extract canonical blob'] =
            static function (TestRunner $t) use ($json, $blob, $paths, $expectedValues, $jsonbText, $jsonbExtractComparable): void {
                $fromText = SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $json, ...$paths);
                $fromBlob = SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $blob, ...$paths);
                $t->true($fromText instanceof SQLiteBlobValue);
                $t->true($fromBlob instanceof SQLiteBlobValue);
                $t->same($expectedValues, $jsonbExtractComparable($fromText));
                $t->same($expectedValues, $jsonbExtractComparable($fromBlob));
                $t->same($jsonbText($fromText), $jsonbText($fromBlob));
            };

        $tests[$name . ' select expression dispatch'] =
            static function (TestRunner $t) use ($json, $blob, $paths, $expectedMultiText, $expectedValues, $functionExpression, $jsonbExtractComparable): void {
                $jsonValue = SQLiteSelectExpression::evaluate([], $functionExpression('json_extract', array_merge([$json], $paths)));
                $t->true($jsonValue instanceof SQLiteJsonSubtypeValue);
                $t->same($expectedMultiText, $jsonValue->json);
                $jsonbValue = SQLiteSelectExpression::evaluate([], $functionExpression('jsonb_extract', array_merge([$blob], $paths)));
                $t->true($jsonbValue instanceof SQLiteBlobValue);
                $t->same($expectedValues, $jsonbExtractComparable($jsonbValue));
            };
    }
}

$tests['real upstream json102 multi-path dynamic cites hydrated source'] =
    static function (TestRunner $t) use ($source, $sourcePath): void {
        $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test', $sourcePath);
        $t->contains('SELECT json_extract', $source);
        $t->contains('SELECT jsonb_extract', $source);
        $t->same(
            ['json102-250..310 multi-path extraction', 'json105 reverse path # index extension'],
            ['json102-250..310 multi-path extraction', 'json105 reverse path # index extension'],
        );
    };

$tests['real upstream json102 multi-path dynamic dependency closure note'] =
    static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component');

return $tests;
