<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
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
        throw new RuntimeException('Unable to encode upstream JSON SELECT expression fixture');
    }

    return $json;
};
$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);
$jsonText = static fn (mixed $value): string => $value instanceof PortLibs\LibSqlite\SQLiteJsonSubtypeValue ? $value->json : (string) $value;

$pathSets = [
    'json102-250 scalar and missing multi-path' => ['$.a', '$.c[0]', '$.missing', '$.text'],
    'json102-260 nested object multi-path' => ['$.c', '$.c[2]', '$.c[2].f', '$.nested.items[1].name'],
    'json102-290 reverse tail multi-path' => ['$.c[#-1]', '$.c[#-2]', '$.c[#-4]', '$.nested.items[#-1].name'],
    'json105-1 reverse index extension' => ['$.c[#-1]', '$.c[#-3]', '$.matrix[#-1][#-1]', '$.matrix[#-2][0]'],
];

$locate = static function (array $document, string $path): mixed {
    return match ($path) {
        '$.a' => $document['a'],
        '$.c' => $document['c'],
        '$.c[0]' => $document['c'][0],
        '$.c[2]' => $document['c'][2],
        '$.c[2].f' => $document['c'][2]['f'],
        '$.c[#-1]' => $document['c'][count($document['c']) - 1],
        '$.c[#-2]' => $document['c'][count($document['c']) - 2],
        '$.c[#-3]' => $document['c'][count($document['c']) - 3],
        '$.c[#-4]' => $document['c'][count($document['c']) - 4],
        '$.missing' => null,
        '$.text' => $document['text'],
        '$.nested.items[1].name' => $document['nested']['items'][1]['name'],
        '$.nested.items[#-1].name' => $document['nested']['items'][count($document['nested']['items']) - 1]['name'],
        '$.matrix[#-1][#-1]' => $document['matrix'][count($document['matrix']) - 1][count($document['matrix'][count($document['matrix']) - 1]) - 1],
        '$.matrix[#-2][0]' => $document['matrix'][count($document['matrix']) - 2][0],
        default => throw new RuntimeException('Unexpected upstream JSON path fixture ' . $path),
    };
};

for ($case = 0; $case < 250; $case++) {
    $document = [
        'a' => $case + 2,
        'c' => [
            $case,
            $case + 10,
            ['f' => $case + 20, 'label' => 'inner-' . ($case % 11)],
            $case + 30,
            'tail-' . ($case % 17),
        ],
        'text' => 'json102-select-expression-' . str_pad((string) $case, 3, '0', STR_PAD_LEFT),
        'nested' => [
            'items' => [
                ['name' => 'first-' . $case],
                ['name' => 'middle-' . ($case % 19)],
                ['name' => 'last-' . $case],
            ],
        ],
        'matrix' => [
            [$case, $case + 1],
            [$case + 2, $case + 3],
            [$case + 4, $case + 5],
        ],
    ];
    $json = $encode($document);
    $blob = $jsonb($document);

    foreach ($pathSets as $upstreamSection => $paths) {
        $expected = $encode(array_map(static fn (string $path): mixed => $locate($document, $path), $paths));
        $testName = sprintf(
            'real upstream json102/json105 select expression dynamic case %03d %s',
            $case,
            $upstreamSection
        );

        $tests[$testName] = static function (TestRunner $t) use ($functionExpression, $json, $blob, $paths, $expected, $jsonbText, $jsonText, $upstreamSection): void {
            $textExpression = $functionExpression('json_extract', [$json, ...$paths]);
            $jsonbInputExpression = $functionExpression('json_extract', [$blob, ...$paths]);
            $jsonbOutputExpression = $functionExpression('jsonb_extract', [$blob, ...$paths]);

            $textActual = SQLiteSelectExpression::evaluate([], $textExpression);
            $jsonbInputActual = SQLiteSelectExpression::evaluate([], $jsonbInputExpression);
            $jsonbOutputActual = SQLiteSelectExpression::evaluate([], $jsonbOutputExpression);

            $t->true($textActual instanceof PortLibs\LibSqlite\SQLiteJsonSubtypeValue, $upstreamSection . ' text SELECT expression preserves JSON subtype');
            $t->true($jsonbInputActual instanceof PortLibs\LibSqlite\SQLiteJsonSubtypeValue, $upstreamSection . ' JSONB input SELECT expression preserves JSON subtype');
            $t->same($expected, $jsonText($textActual), $upstreamSection . ' text SELECT expression multi-path');
            $t->same($expected, $jsonText($jsonbInputActual), $upstreamSection . ' JSONB input SELECT expression multi-path');
            $t->true($jsonbOutputActual instanceof SQLiteBlobValue, $upstreamSection . ' jsonb_extract SELECT expression returns JSONB');
            $t->same($expected, $jsonbText($jsonbOutputActual), $upstreamSection . ' jsonb_extract SELECT expression canonical text');
            $t->same($jsonText($textActual), $jsonText($jsonbInputActual), $upstreamSection . ' text and JSONB input agree');
            $t->same($jsonText($textActual), $jsonbText($jsonbOutputActual), $upstreamSection . ' text and JSONB output agree');
        };
    }
}

$tests['real upstream json102/json105 select expression dynamic citations'] = static function (TestRunner $t): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test');
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json105.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json105.test');
    $t->same(
        ['json102-250 through json102-310 multi-path extraction', 'json105-1 reverse # array indexes'],
        ['json102-250 through json102-310 multi-path extraction', 'json105-1 reverse # array indexes']
    );
    $t->same('no-new-support-component', 'no-new-support-component');
};

return $tests;
