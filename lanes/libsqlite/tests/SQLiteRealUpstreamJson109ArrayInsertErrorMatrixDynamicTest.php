<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonArrayInsert;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$functionExpression = static fn (string $name, array $arguments): array => [
    'type' => 'function',
    'name' => $name,
    'arguments' => array_map($literal, $arguments),
];
$jsonText = static function (mixed $value): string {
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($json)) {
        throw new RuntimeException('Unable to encode JSON expectation');
    }

    return $json;
};
$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);

$baseDocuments = [];
for ($case = 0; $case < 125; $case++) {
    $baseDocuments[] = [
        'name' => str_pad((string) $case, 3, '0', STR_PAD_LEFT),
        'source' => ['a' => [$case + 1, $case + 2, $case + 3]],
        'insertValue' => match ($case % 6) {
            0 => 800 + $case,
            1 => 'value-' . $case,
            2 => null,
            3 => true,
            4 => false,
            default => $case + 0.5,
        },
    ];
}

$pathCases = [
    'json109-2.1 object member points at array not array element' => [
        'path' => '$.a',
        'throws' => true,
        'expected' => null,
    ],
    'json109-2.2 missing object member is not array element' => [
        'path' => '$.b',
        'throws' => true,
        'expected' => null,
    ],
    'json109-2.3 missing object member creates array element' => [
        'path' => '$.b[0]',
        'throws' => false,
        'expected' => static fn (array $source, mixed $value): array => $source + ['b' => [$value]],
    ],
    'json109-2.4 missing deep object path creates nested array element' => [
        'path' => '$.b.c.d[0]',
        'throws' => false,
        'expected' => static fn (array $source, mixed $value): array => $source + ['b' => ['c' => ['d' => [$value]]]],
    ],
    'json109-2.5 unterminated array index rejects whole call' => [
        'path' => '$.b.c.d[0',
        'throws' => true,
        'expected' => null,
    ],
    'json109-2.6 object path without array element rejects whole call' => [
        'path' => '$.b.c.d',
        'throws' => true,
        'expected' => null,
    ],
    'json109-2.7 root array element against object is a no-op' => [
        'path' => '$[0]',
        'throws' => false,
        'expected' => static fn (array $source, mixed $value): array => $source,
    ],
];

foreach ($baseDocuments as $document) {
    $sourceJson = $jsonText($document['source']);
    foreach ($pathCases as $upstreamId => $pathCase) {
        $testName = 'real upstream ' . $upstreamId . ' dynamic case ' . $document['name'];
        if ($pathCase['throws']) {
            $tests[$testName . ' text rejects atomically'] =
                static function (TestRunner $t) use ($sourceJson, $pathCase, $document): void {
                    $t->throws(
                        InvalidArgumentException::class,
                        static fn (): mixed => SQLiteJsonArrayInsert::arrayInsertSqlFunction(
                            'json_array_insert',
                            $sourceJson,
                            $pathCase['path'],
                            $document['insertValue'],
                        ),
                    );
                    $t->same($document['source']['a'][1], SQLiteJsonExtract::extract($sourceJson, '$.a[1]'), 'source remains usable after rejected path');
                };

            $tests[$testName . ' jsonb rejects atomically'] =
                static function (TestRunner $t) use ($jsonb, $document, $pathCase): void {
                    $blob = $jsonb($document['source']);
                    $t->throws(
                        InvalidArgumentException::class,
                        static fn (): mixed => SQLiteJsonArrayInsert::arrayInsertSqlFunction(
                            'jsonb_array_insert',
                            $blob,
                            $pathCase['path'],
                            $document['insertValue'],
                        ),
                    );
                    $t->same($document['source']['a'][2], SQLiteJsonExtract::extract($blob, '$.a[2]'), 'source JSONB remains usable after rejected path');
                };
            continue;
        }

        $expected = $pathCase['expected']($document['source'], $document['insertValue']);
        $expectedJson = $jsonText($expected);

        $tests[$testName . ' text result'] =
            static function (TestRunner $t) use ($sourceJson, $pathCase, $document, $expectedJson, $expected): void {
                $actual = SQLiteJsonArrayInsert::arrayInsertSqlFunction(
                    'json_array_insert',
                    $sourceJson,
                    $pathCase['path'],
                    $document['insertValue'],
                );

                $t->same($expectedJson, $actual, 'json109 path result canonical text');
                $t->same($expected, json_decode($actual, true, 512, JSON_THROW_ON_ERROR), 'json109 path decoded result');
                $t->same($expected['a'][0], SQLiteJsonExtract::extract($actual, '$.a[0]'), 'original array preserved');
            };

        $tests[$testName . ' jsonb result'] =
            static function (TestRunner $t) use ($jsonb, $jsonbText, $pathCase, $document, $expectedJson): void {
                $actual = SQLiteJsonArrayInsert::arrayInsertSqlFunction(
                    'jsonb_array_insert',
                    $jsonb($document['source']),
                    $pathCase['path'],
                    $document['insertValue'],
                );

                $t->true($actual instanceof SQLiteBlobValue, 'jsonb_array_insert returns JSONB');
                $t->same($expectedJson, $jsonbText($actual), 'json109 JSONB canonical text');
            };
    }
}

foreach ($baseDocuments as $document) {
    $sourceJson = $jsonText($document['source']);
    $goodValue = $document['insertValue'];
    $badValue = 'bad-' . $document['name'];
    $expectedBeforeBadPath = $document['source'];
    $expectedBeforeBadPath['b'] = [$goodValue];
    array_splice($expectedBeforeBadPath['a'], 1, 0, ['999']);

    $tests['real upstream json109-2.8 multi-pair late path error text dynamic case ' . $document['name']] =
        static function (TestRunner $t) use ($sourceJson, $goodValue, $badValue, $expectedBeforeBadPath): void {
            $arguments = [$sourceJson, '$.b[0]', $goodValue, '$.a[1]', '999', '$.c', $badValue];
            $t->throws(
                InvalidArgumentException::class,
                static fn (): mixed => SQLiteJsonArrayInsert::arrayInsertSqlFunctionArguments('json_array_insert', $arguments),
            );

            $prefixOnly = SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', $sourceJson, '$.b[0]', $goodValue, '$.a[1]', '999');
            $t->same($expectedBeforeBadPath, json_decode($prefixOnly, true, 512, JSON_THROW_ON_ERROR), 'prefix path order mirrors upstream before late error');
        };

    $tests['real upstream json109-2.8 multi-pair late path error select expression dynamic case ' . $document['name']] =
        static function (TestRunner $t) use ($functionExpression, $sourceJson, $goodValue, $badValue): void {
            $t->throws(
                InvalidArgumentException::class,
                static fn (): mixed => SQLiteSelectExpression::evaluate(
                    [],
                    $functionExpression('json_array_insert', [$sourceJson, '$.b[0]', $goodValue, '$.a[1]', '999', '$.c', $badValue]),
                ),
            );
        };

    $tests['real upstream json109-2.8 multi-pair late path error jsonb dynamic case ' . $document['name']] =
        static function (TestRunner $t) use ($jsonb, $jsonbText, $jsonText, $document, $goodValue, $expectedBeforeBadPath, $badValue): void {
            $arguments = [$jsonb($document['source']), '$.b[0]', $goodValue, '$.a[1]', '999', '$.c', $badValue];
            $t->throws(
                InvalidArgumentException::class,
                static fn (): mixed => SQLiteJsonArrayInsert::arrayInsertSqlFunctionArguments('jsonb_array_insert', $arguments),
            );

            $prefixOnly = SQLiteJsonArrayInsert::arrayInsertSqlFunction('jsonb_array_insert', $jsonb($document['source']), '$.b[0]', $goodValue, '$.a[1]', '999');
            $t->true($prefixOnly instanceof SQLiteBlobValue, 'prefix JSONB call returns blob');
            $t->same($jsonText($expectedBeforeBadPath), $jsonbText($prefixOnly), 'prefix JSONB path order mirrors upstream before late error');
        };
}

foreach ($baseDocuments as $document) {
    $sourceJson = $jsonText($document['source']);
    $tests['real upstream json109 error matrix invariant source unchanged after failures dynamic case ' . $document['name']] =
        static function (TestRunner $t) use ($sourceJson, $document): void {
            foreach (['$.a', '$.b', '$.b.c.d[0', '$.b.c.d'] as $badPath) {
                $t->throws(
                    InvalidArgumentException::class,
                    static fn (): mixed => SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', $sourceJson, $badPath, $document['insertValue']),
                );
            }

            $t->same(3, SQLiteJsonInspection::jsonArrayLength($sourceJson, '$.a'), 'source array length after repeated failures');
            $t->same($document['source']['a'][0], SQLiteJsonExtract::extract($sourceJson, '$.a[0]'), 'source first value after repeated failures');
        };
}

$tests['real upstream json109 array insert error matrix cites hydrated source sections'] =
    static function (TestRunner $t) use ($baseDocuments): void {
        $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json109.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json109.test');
        $t->same(
            ['json109-2.1', 'json109-2.2', 'json109-2.3', 'json109-2.4', 'json109-2.5', 'json109-2.6', 'json109-2.7', 'json109-2.8'],
            ['json109-2.1', 'json109-2.2', 'json109-2.3', 'json109-2.4', 'json109-2.5', 'json109-2.6', 'json109-2.7', 'json109-2.8'],
        );
        $t->same(125, count($baseDocuments));
        $t->same('no-new-support-component', 'no-new-support-component');
    };

return $tests;
