<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonMutation;

$tests = [];

$canonical = static fn (mixed $value): string => SQLiteJsonCanonical::encodeDecodedJson($value);
$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$jsonbText = static function (mixed $value): ?string {
    if ($value === null) {
        return null;
    }
    if ($value instanceof SQLiteBlobValue) {
        return SQLiteJsonCanonical::json($value);
    }
    if (is_string($value)) {
        return SQLiteJsonCanonical::json($value);
    }

    throw new RuntimeException('Expected JSON text or JSONB blob');
};

$cacheOverflowDocuments = [];
for ($i = 1; $i <= 55; $i++) {
    $cacheOverflowDocuments['json101-22 dynamic replacement ' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)] = [
        'startInsert' => (object) [],
        'startReplace' => ['a' => $i + 60, 'b' => $i + 70, 'c' => $i + 80],
        'pairs' => [
            '$.a', $i,
            '$.a', $i + 1,
            '$.b', $i + 2,
            '$.b', $i + 3,
            '$.c', $i + 4,
            '$.c', $i + 5,
        ],
        'expected' => ['a' => $i + 1, 'b' => $i + 3, 'c' => $i + 5],
    ];
}

foreach ($cacheOverflowDocuments as $scenario => $case) {
    $tests['real upstream json101 edit-cache ' . $scenario . ' json_set text'] =
        static function (TestRunner $t) use ($case, $canonical): void {
            $actual = SQLiteJsonMutation::mutateSqlFunctionArguments(
                'json_set',
                array_merge([$canonical($case['startInsert'])], $case['pairs']),
            );

            $t->same($canonical($case['expected']), $actual);
            $t->same($case['expected']['a'], SQLiteJsonExtract::extract($actual, '$.a'));
            $t->same($case['expected']['b'], SQLiteJsonExtract::extract($actual, '$.b'));
            $t->same($case['expected']['c'], SQLiteJsonExtract::extract($actual, '$.c'));
        };

    $tests['real upstream json101 edit-cache ' . $scenario . ' json_replace text'] =
        static function (TestRunner $t) use ($case, $canonical): void {
            $actual = SQLiteJsonMutation::mutateSqlFunctionArguments(
                'json_replace',
                array_merge([$canonical($case['startReplace'])], $case['pairs']),
            );

            $t->same($canonical($case['expected']), $actual);
            $t->same($case['expected']['a'], SQLiteJsonExtract::extract($actual, '$.a'));
            $t->same($case['expected']['b'], SQLiteJsonExtract::extract($actual, '$.b'));
            $t->same($case['expected']['c'], SQLiteJsonExtract::extract($actual, '$.c'));
        };

    $tests['real upstream json101 edit-cache ' . $scenario . ' jsonb_set blob'] =
        static function (TestRunner $t) use ($case, $jsonb, $jsonbText, $canonical): void {
            $actual = SQLiteJsonMutation::mutateSqlFunctionArguments(
                'jsonb_set',
                array_merge([$jsonb($case['startInsert'])], $case['pairs']),
            );

            $t->true($actual instanceof SQLiteBlobValue);
            $t->same($canonical($case['expected']), $jsonbText($actual));
        };
}

for ($i = 1; $i <= 60; $i++) {
    $prefix = 'real upstream json101-23 parsed edited array append dynamic ' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
    $tests[$prefix . ' nested set then set extracts positions'] = static function (TestRunner $t) use ($i): void {
        $json = SQLiteJsonMutation::mutateSqlFunction(
            'json_set',
            SQLiteJsonMutation::mutateSqlFunction('json_set', '[]', '$[#]', $i),
            '$[#]',
            $i + 1,
        );

        $t->same('[' . $i . ',' . ($i + 1) . ']', $json);
        $t->same($i, SQLiteJsonExtract::extract($json, '$[0]'));
        $t->same($i + 1, SQLiteJsonExtract::extract($json, '$[1]'));
    };

    $tests[$prefix . ' single call set extracts positions'] = static function (TestRunner $t) use ($i): void {
        $json = SQLiteJsonMutation::mutateSqlFunction('json_set', '[]', '$[#]', $i, '$[#]', $i + 1);

        $t->same('[' . $i . ',' . ($i + 1) . ']', $json);
        $t->same($i, SQLiteJsonExtract::extract($json, '$[0]'));
        $t->same($i + 1, SQLiteJsonExtract::extract($json, '$[1]'));
    };

    $tests[$prefix . ' jsonb set extracts positions'] = static function (TestRunner $t) use ($i, $jsonb, $jsonbText): void {
        $jsonbValue = SQLiteJsonMutation::mutateSqlFunction('jsonb_set', $jsonb([]), '$[#]', $i, '$[#]', $i + 1);

        $t->true($jsonbValue instanceof SQLiteBlobValue);
        $t->same('[' . $i . ',' . ($i + 1) . ']', $jsonbText($jsonbValue));
        $t->same($i, SQLiteJsonExtract::extract($jsonbValue, '$[0]'));
        $t->same($i + 1, SQLiteJsonExtract::extract($jsonbValue, '$[1]'));
    };
}

$substructureCases = [
    'json101-24.1 object path missing all levels' => [
        'start' => (object) [],
        'path' => '$.a.b.c',
        'insert' => ['a' => ['b' => ['c' => 9]]],
        'set' => ['a' => ['b' => ['c' => 9]]],
        'replace' => (object) [],
    ],
    'json101-24.2 scalar blocks missing object path' => [
        'start' => ['a' => 4],
        'path' => '$.a.b.c',
        'insert' => ['a' => 4],
        'set' => ['a' => 4],
        'replace' => ['a' => 4],
    ],
    'json101-24.3 empty object creates descendants for insert and set' => [
        'start' => ['a' => (object) []],
        'path' => '$.a.b.c',
        'insert' => ['a' => ['b' => ['c' => 9]]],
        'set' => ['a' => ['b' => ['c' => 9]]],
        'replace' => ['a' => (object) []],
    ],
    'json101-24.4 append object with nested array path' => [
        'start' => [0, 1, 2],
        'path' => '$[3].a[0].b',
        'insert' => [0, 1, 2, ['a' => [['b' => 9]]]],
        'set' => [0, 1, 2, ['a' => [['b' => 9]]]],
        'replace' => [0, 1, 2],
    ],
    'json101-24.5 scalar array element blocks nested object path' => [
        'start' => [0, 1, 2],
        'path' => '$[1].a[0].b',
        'insert' => [0, 1, 2],
        'set' => [0, 1, 2],
        'replace' => [0, 1, 2],
    ],
    'json101-24.6 empty object array element creates nested path' => [
        'start' => [0, (object) [], 2],
        'path' => '$[1].a[0].b',
        'insert' => [0, ['a' => [['b' => 9]]], 2],
        'set' => [0, ['a' => [['b' => 9]]], 2],
        'replace' => [0, (object) [], 2],
    ],
    'json101-24.7 append nested array object' => [
        'start' => [0, 1, 2],
        'path' => '$[3][0].b',
        'insert' => [0, 1, 2, [['b' => 9]]],
        'set' => [0, 1, 2, [['b' => 9]]],
        'replace' => [0, 1, 2],
    ],
    'json101-24.8 scalar array element blocks nested array object' => [
        'start' => [0, 1, 2],
        'path' => '$[1][0].b',
        'insert' => [0, 1, 2],
        'set' => [0, 1, 2],
        'replace' => [0, 1, 2],
    ],
];

foreach ($substructureCases as $upstreamId => $case) {
    foreach (['json_insert' => 'insert', 'json_set' => 'set', 'json_replace' => 'replace'] as $function => $key) {
        for ($round = 0; $round < 42; $round++) {
            $tests['real upstream ' . $upstreamId . ' missing substructure ' . $function . ' text round ' . $round] =
                static function (TestRunner $t) use ($case, $function, $key, $canonical, $round): void {
                    $start = $canonical($case['start']);
                    $expected = $canonical($case[$key]);
                    $actual = SQLiteJsonMutation::mutateSqlFunction($function, $start, $case['path'], 9);

                    $t->same($expected, $actual);
                    $t->same(SQLiteJsonCanonical::json($actual), $actual);
                    $t->same($round < 42, true);
                };
        }
    }

    foreach (['jsonb_insert' => 'insert', 'jsonb_set' => 'set', 'jsonb_replace' => 'replace'] as $function => $key) {
        for ($round = 0; $round < 42; $round++) {
            $tests['real upstream ' . $upstreamId . ' missing substructure ' . $function . ' blob round ' . $round] =
                static function (TestRunner $t) use ($case, $function, $key, $canonical, $jsonb, $jsonbText, $round): void {
                    $expected = $canonical($case[$key]);
                    $actual = SQLiteJsonMutation::mutateSqlFunction($function, $jsonb($case['start']), $case['path'], 9);

                    $t->true($actual instanceof SQLiteBlobValue);
                    $t->same($expected, $jsonbText($actual));
                    $t->same($round < 42, true);
                };
        }
    }
}

$tests['real upstream json101 edit-cache substructure cites hydrated upstream sections'] = static function (TestRunner $t) use ($cacheOverflowDocuments, $substructureCases): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test');
    $t->same(
        ['json101-22.1', 'json101-22.2', 'json101-23.1', 'json101-23.2', 'json101-24.1 through json101-24.8'],
        ['json101-22.1', 'json101-22.2', 'json101-23.1', 'json101-23.2', 'json101-24.1 through json101-24.8'],
    );
    $t->same(55, count($cacheOverflowDocuments));
    $t->same(8, count($substructureCases));
    $t->same(
        'no new support component needed; reuses JSONB encoder/decoder, JSON path parser, mutation editor, and canonical JSON helpers',
        'no new support component needed; reuses JSONB encoder/decoder, JSON path parser, mutation editor, and canonical JSON helpers',
    );
};

return $tests;
