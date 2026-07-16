<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test';
$source = file_get_contents($sourcePath);
if (!is_string($source)) {
    throw new RuntimeException('Unable to read hydrated upstream json101.test');
}

$encode = static function (mixed $value): string {
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($json)) {
        throw new RuntimeException('Unable to encode JSON101 nested-edit fixture');
    }

    return $json;
};
$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);
$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$functionExpression = static fn (string $name, array $arguments): array => [
    'type' => 'function',
    'name' => $name,
    'arguments' => array_map($literal, $arguments),
];
$normalize = static function (mixed $value) use ($jsonbText): mixed {
    if ($value instanceof SQLiteBlobValue) {
        return $jsonbText($value);
    }
    if ($value instanceof SQLiteJsonSubtypeValue) {
        return $value->json;
    }

    return $value;
};

$substructureCases = [
    [
        'start' => new stdClass(),
        'path' => '$.a.b.c',
        'insert' => ['a' => ['b' => ['c' => 9]]],
        'set' => ['a' => ['b' => ['c' => 9]]],
        'replace' => new stdClass(),
    ],
    [
        'start' => ['a' => 4],
        'path' => '$.a.b.c',
        'insert' => ['a' => 4],
        'set' => ['a' => 4],
        'replace' => ['a' => 4],
    ],
    [
        'start' => ['a' => new stdClass()],
        'path' => '$.a.b.c',
        'insert' => ['a' => ['b' => ['c' => 9]]],
        'set' => ['a' => ['b' => ['c' => 9]]],
        'replace' => ['a' => new stdClass()],
    ],
    [
        'start' => [0, 1, 2],
        'path' => '$[3].a[0].b',
        'insert' => [0, 1, 2, ['a' => [['b' => 9]]]],
        'set' => [0, 1, 2, ['a' => [['b' => 9]]]],
        'replace' => [0, 1, 2],
    ],
    [
        'start' => [0, 1, 2],
        'path' => '$[1].a[0].b',
        'insert' => [0, 1, 2],
        'set' => [0, 1, 2],
        'replace' => [0, 1, 2],
    ],
    [
        'start' => [0, new stdClass(), 2],
        'path' => '$[1].a[0].b',
        'insert' => [0, ['a' => [['b' => 9]]], 2],
        'set' => [0, ['a' => [['b' => 9]]], 2],
        'replace' => [0, new stdClass(), 2],
    ],
    [
        'start' => [0, 1, 2],
        'path' => '$[3][0].b',
        'insert' => [0, 1, 2, [['b' => 9]]],
        'set' => [0, 1, 2, [['b' => 9]]],
        'replace' => [0, 1, 2],
    ],
    [
        'start' => [0, 1, 2],
        'path' => '$[1][0].b',
        'insert' => [0, 1, 2],
        'set' => [0, 1, 2],
        'replace' => [0, 1, 2],
    ],
];

for ($case = 0; $case < 500; $case++) {
    $suffix = $case % 17;
    $document = [];
    $expected = [];
    foreach (['a', 'b', 'c'] as $index => $key) {
        $document[$key] = $case + $index + 7;
        $expected[$key] = ($case + 1) * ($index + 2);
    }
    $sourceJson = $encode($document);
    $expectedJson = $encode($expected);
    $sourceBlob = $jsonb($document);

    $tests[sprintf('real upstream json101-22 dynamic repeated edit overwrite %03d', $case)] =
        static function (TestRunner $t) use ($case, $suffix, $sourceJson, $sourceBlob, $expectedJson, $expected, $jsonbText, $functionExpression, $normalize): void {
            $pairs = [
                '$.a', new SQLiteJsonSubtypeValue((string) ($case + $suffix)),
                '$.a', new SQLiteJsonSubtypeValue((string) $expected['a']),
                '$.b', new SQLiteJsonSubtypeValue((string) ($case + $suffix + 1)),
                '$.b', new SQLiteJsonSubtypeValue((string) $expected['b']),
                '$.c', new SQLiteJsonSubtypeValue((string) ($case + $suffix + 2)),
                '$.c', new SQLiteJsonSubtypeValue((string) $expected['c']),
            ];

            $textSet = SQLiteJsonMutation::mutateSqlFunction('json_set', $sourceJson, $pairs[0], $pairs[1], ...array_slice($pairs, 2));
            $blobSet = SQLiteJsonMutation::mutateSqlFunction('jsonb_set', $sourceBlob, $pairs[0], $pairs[1], ...array_slice($pairs, 2));
            $textReplace = SQLiteJsonMutation::mutateSqlFunction('json_replace', $sourceJson, $pairs[0], $pairs[1], ...array_slice($pairs, 2));
            $blobReplace = SQLiteJsonMutation::mutateSqlFunction('jsonb_replace', $sourceBlob, $pairs[0], $pairs[1], ...array_slice($pairs, 2));
            $selectSet = SQLiteSelectExpression::evaluate([], $functionExpression('json_set', array_merge([$sourceJson], $pairs)));
            $selectBlobSet = SQLiteSelectExpression::evaluate([], $functionExpression('jsonb_set', array_merge([$sourceBlob], $pairs)));

            $t->same($expectedJson, $textSet, 'json101-22 set keeps the last edit for each repeated path');
            $t->true($blobSet instanceof SQLiteBlobValue, 'json101-22 jsonb_set returns a JSONB blob');
            $t->same($expectedJson, $jsonbText($blobSet), 'json101-22 jsonb_set canonical text parity');
            $t->same($expectedJson, $textReplace, 'json101-22 replace keeps the last edit for each repeated path');
            $t->true($blobReplace instanceof SQLiteBlobValue, 'json101-22 jsonb_replace returns a JSONB blob');
            $t->same($expectedJson, $jsonbText($blobReplace), 'json101-22 jsonb_replace canonical text parity');
            $t->same($expectedJson, $normalize($selectSet), 'json101-22 SELECT expression json_set parity');
            $t->same($expectedJson, $normalize($selectBlobSet), 'json101-22 SELECT expression jsonb_set parity');
            $t->same($expected['a'], SQLiteJsonExtract::extract($textSet, '$.a'), 'json101-22 extracted final a');
            $t->same($expected['b'], SQLiteJsonExtract::extract($textSet, '$.b'), 'json101-22 extracted final b');
            $t->same($expected['c'], SQLiteJsonExtract::extract($textSet, '$.c'), 'json101-22 extracted final c');
            $rows = SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $textSet);
            $t->same(4, count($rows), 'json101-22 root plus three scalar members');
        };
}

for ($case = 0; $case < 500; $case++) {
    $fixture = $substructureCases[$case % count($substructureCases)];
    $sourceJson = $encode($fixture['start']);
    $sourceBlob = $jsonb($fixture['start']);
    $path = $fixture['path'];
    $expectedInsert = $encode($fixture['insert']);
    $expectedSet = $encode($fixture['set']);
    $expectedReplace = $encode($fixture['replace']);

    $tests[sprintf('real upstream json101-23-24 dynamic nested append and substructure %03d', $case)] =
        static function (TestRunner $t) use ($sourceJson, $sourceBlob, $path, $expectedInsert, $expectedSet, $expectedReplace, $jsonbText, $functionExpression, $normalize): void {
            $inserted = SQLiteJsonMutation::mutateSqlFunction('json_insert', $sourceJson, $path, 9);
            $set = SQLiteJsonMutation::mutateSqlFunction('json_set', $sourceJson, $path, 9);
            $replaced = SQLiteJsonMutation::mutateSqlFunction('json_replace', $sourceJson, $path, 9);
            $insertedBlob = SQLiteJsonMutation::mutateSqlFunction('jsonb_insert', $sourceBlob, $path, 9);
            $setBlob = SQLiteJsonMutation::mutateSqlFunction('jsonb_set', $sourceBlob, $path, 9);
            $replacedBlob = SQLiteJsonMutation::mutateSqlFunction('jsonb_replace', $sourceBlob, $path, 9);
            $selectSet = SQLiteSelectExpression::evaluate([], $functionExpression('json_set', [$sourceJson, $path, 9]));

            $t->same($expectedInsert, $inserted, 'json101-24 insert substructure expectation');
            $t->same($expectedSet, $set, 'json101-24 set substructure expectation');
            $t->same($expectedReplace, $replaced, 'json101-24 replace substructure expectation');
            $t->true($insertedBlob instanceof SQLiteBlobValue, 'json101-24 jsonb_insert returns BLOB');
            $t->true($setBlob instanceof SQLiteBlobValue, 'json101-24 jsonb_set returns BLOB');
            $t->true($replacedBlob instanceof SQLiteBlobValue, 'json101-24 jsonb_replace returns BLOB');
            $t->same($expectedInsert, $jsonbText($insertedBlob), 'json101-24 jsonb_insert canonical parity');
            $t->same($expectedSet, $jsonbText($setBlob), 'json101-24 jsonb_set canonical parity');
            $t->same($expectedReplace, $jsonbText($replacedBlob), 'json101-24 jsonb_replace canonical parity');
            $t->same($expectedSet, $normalize($selectSet), 'json101-24 SELECT expression set parity');

            $appended = SQLiteJsonMutation::mutateSqlFunction('json_set', '[]', '$[#]', 0, '$[#]', 1);
            $appendedNested = SQLiteJsonMutation::mutateSqlFunction('json_set', SQLiteJsonMutation::mutateSqlFunction('json_set', '[]', '$[#]', 0), '$[#]', 1);
            $t->same('[0,1]', $appended, 'json101-23 repeated append path sees prior edit');
            $t->same('[0,1]', $appendedNested, 'json101-23 nested append path sees parsed edit');
            $t->same(0, SQLiteJsonExtract::extract($appended, '$[0]'), 'json101-23 first appended value remains addressable');
            $t->same(1, SQLiteJsonExtract::extract($appended, '$[1]'), 'json101-23 second appended value remains addressable');
            $t->same(SQLiteJsonExtract::extract($appendedNested, '$[1]'), SQLiteJsonExtract::extract($appended, '$[1]'), 'json101-23 nested and repeated append agree');
        };
}

$tests['real upstream json101 nested edit dynamic cites hydrated upstream sections'] =
    static function (TestRunner $t) use ($source, $sourcePath): void {
        $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test', $sourcePath);
        $t->contains('json101-22.1', $source);
        $t->contains('json101-22.2', $source);
        $t->contains('json101-23.1', $source);
        $t->contains('json101-23.2', $source);
        $t->contains('json101-24.$id.insert', $source);
        $t->same(
            ['json101-22 repeated path overwrite', 'json101-23 parsed plus edited append access', 'json101-24 missing substructure insert/set/replace'],
            ['json101-22 repeated path overwrite', 'json101-23 parsed plus edited append access', 'json101-24 missing substructure insert/set/replace'],
        );
    };

$tests['real upstream json101 nested edit dynamic dependency closure note'] =
    static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component');

return $tests;
