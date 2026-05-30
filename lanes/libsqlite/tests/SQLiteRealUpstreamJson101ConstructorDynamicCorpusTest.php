<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonConstructor;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonValidity;

$tests = [];

$canonical = static fn (mixed $value): string => SQLiteJsonCanonical::encodeDecodedJson($value);
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::encodeDecodedJson(
    SQLiteJsonB::decodeForJsonEncoding($value->bytes)
);

$jsonSubtype = static fn (mixed $value): SQLiteJsonSubtypeValue => new SQLiteJsonSubtypeValue($canonical($value));
$jsonbValue = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$scalarValues = [
    null,
    true,
    false,
    0,
    1,
    -1,
    17,
    -9223372036854775807,
    9223372036854775807,
    0.0,
    1.0,
    -1.0,
    2.5,
    -0.25,
    1.25e6,
    -1.0e-6,
    '',
    'plain text',
    'String "\ Test',
    "line\nbreak",
    "tab\tvalue",
    "\0\0",
    'abcdefghijklmnopqrstuvwyxzABCDEFGHIJKLMNOPQRSTUVWXYZ',
];

$nestedJsonValues = [
    ['abc' => 2.5, 'def' => null, 'ghi' => 'hello'],
    ['numbers' => [1, 2, 3], 'flag' => true],
    ['nested' => ['alpha' => ['beta' => [10, 20]], 'empty' => []]],
    ['quote' => 'String "\ Test', 'control' => "\0\0"],
    ['array' => [['id' => '1001', 'type' => 'Regular'], ['id' => '1002', 'type' => 'Chocolate']]],
];

$arrayCases = [];
for ($i = 0; $i < 240; $i++) {
    $arrayCases[] = [
        $scalarValues[$i % count($scalarValues)],
        $scalarValues[($i + 5) % count($scalarValues)],
        $jsonSubtype($nestedJsonValues[$i % count($nestedJsonValues)]),
        $jsonbValue($nestedJsonValues[($i + 2) % count($nestedJsonValues)]),
        "tail-{$i}",
    ];
}

$tests['real upstream json101 1.1 constructors preserve SQL scalars and JSON subtype values'] = static function (TestRunner $t) use ($arrayCases, $canonical, $jsonbText): void {
    foreach ($arrayCases as $case) {
        $text = SQLiteJsonConstructor::jsonArraySqlFunctionArguments('json_array', $case);
        $jsonb = SQLiteJsonConstructor::jsonArraySqlFunctionArguments('jsonb_array', $case);
        $decodedText = json_decode($text, true, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
        $decodedJsonb = json_decode($jsonbText($jsonb), true, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);

        $t->same($canonical($decodedText), $text);
        $t->same($decodedText, $decodedJsonb);
        $t->same(true, SQLiteJsonValidity::jsonValid($text));
        $t->same(true, SQLiteJsonValidity::jsonValid($jsonb, SQLiteJsonValidity::FLAG_STRICT_JSONB));
        $t->same($text, SQLiteJsonCanonical::json($jsonb));
    }
};

$objectCases = [];
for ($i = 0; $i < 240; $i++) {
    $objectCases[] = [
        'a', $scalarValues[$i % count($scalarValues)],
        'b', $jsonSubtype($nestedJsonValues[$i % count($nestedJsonValues)]),
        'c', $jsonbValue($nestedJsonValues[($i + 1) % count($nestedJsonValues)]),
        'label' . $i, $scalarValues[($i + 7) % count($scalarValues)],
    ];
}

$tests['real upstream json101 2.1 object constructors preserve labels order and JSONB parity'] = static function (TestRunner $t) use ($objectCases, $jsonbText): void {
    foreach ($objectCases as $case) {
        $text = SQLiteJsonConstructor::jsonObjectSqlFunctionArguments('json_object', $case);
        $jsonb = SQLiteJsonConstructor::jsonObjectSqlFunctionArguments('jsonb_object', $case);
        $decodedText = json_decode($text, true, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
        $decodedJsonb = json_decode($jsonbText($jsonb), true, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);

        $t->same(array_values(array_filter(array_keys($decodedText), 'is_string')), array_keys($decodedText));
        $t->same(['a', 'b', 'c', (string) $case[6]], array_keys($decodedText));
        $t->same($decodedText, $decodedJsonb);
        $t->same(true, SQLiteJsonValidity::jsonValid($text));
        $t->same(true, SQLiteJsonValidity::jsonValid($jsonb, SQLiteJsonValidity::FLAG_STRICT_JSONB));
    }
};

$tests['real upstream json101 3.1 json text versus JSON value insertion boundaries'] = static function (TestRunner $t) use ($nestedJsonValues, $canonical, $jsonSubtype, $jsonbValue, $jsonbText): void {
    for ($i = 0; $i < 260; $i++) {
        $source = $canonical(['a' => 1, 'b' => 2, 'case' => $i]);
        $replacement = $nestedJsonValues[$i % count($nestedJsonValues)];
        $replacementText = $canonical($replacement);
        $asText = SQLiteJsonMutation::mutateSqlFunction('json_set', $source, '$.b', $replacementText);
        $asJson = SQLiteJsonMutation::mutateSqlFunction('json_set', $source, '$.b', $jsonSubtype($replacement));
        $asJsonb = SQLiteJsonMutation::mutateSqlFunction('jsonb_set', $source, '$.b', $jsonbValue($replacement));

        $decodedText = json_decode($asText, true, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
        $decodedJson = json_decode($asJson, true, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
        $decodedJsonb = json_decode($jsonbText($asJsonb), true, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);

        $t->same($replacementText, $decodedText['b']);
        $t->same($replacement, $decodedJson['b']);
        $t->same($replacement, $decodedJsonb['b']);
        $t->same(true, SQLiteJsonValidity::jsonValid($asText));
        $t->same(true, SQLiteJsonValidity::jsonValid($asJsonb, SQLiteJsonValidity::FLAG_STRICT_JSONB));
    }
};

$documents = [];
for ($i = 0; $i < 260; $i++) {
    $documents[] = $canonical([
        'id' => $i,
        'payload' => $nestedJsonValues[$i % count($nestedJsonValues)],
        'items' => [$i, $i + 1, ['tail' => "item-{$i}"]],
        'empty' => [],
        'nullable' => null,
    ]);
}

$tests['real upstream json101 4.5 no edit mutation functions return canonical input'] = static function (TestRunner $t) use ($documents): void {
    foreach ($documents as $document) {
        $jsonb = new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($document, false, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR)));

        $t->same($document, SQLiteJsonRemove::removeSqlFunctionArguments('json_remove', [$document]));
        $t->same($document, SQLiteJsonMutation::mutateSqlFunctionArguments('json_replace', [$document]));
        $t->same($document, SQLiteJsonMutation::mutateSqlFunctionArguments('json_set', [$document]));
        $t->same($document, SQLiteJsonMutation::mutateSqlFunctionArguments('json_insert', [$document]));
        $t->same($document, SQLiteJsonCanonical::json(SQLiteJsonRemove::removeSqlFunctionArguments('jsonb_remove', [$jsonb])));
        $t->same($document, SQLiteJsonCanonical::json(SQLiteJsonMutation::mutateSqlFunctionArguments('jsonb_set', [$jsonb])));
    }
};

$tests['real upstream json101 1.3 and 2.2 constructor error boundaries'] = static function (TestRunner $t): void {
    for ($i = 0; $i < 120; $i++) {
        try {
            SQLiteJsonConstructor::jsonArraySqlFunctionArguments('json_array', [1, str_repeat('x', 8 + $i), new SQLiteBlobValue("\xab\xcd"), 3]);
            $t->same('exception', 'not thrown');
        } catch (InvalidArgumentException $e) {
            $t->same('JSON cannot hold BLOB values', $e->getMessage());
        }

        try {
            SQLiteJsonConstructor::jsonObjectSqlFunctionArguments('json_object', ['a', str_repeat('x', 8 + $i), 2, 2.5]);
            $t->same('exception', 'not thrown');
        } catch (InvalidArgumentException $e) {
            $t->same('json_object() labels must be TEXT', $e->getMessage());
        }

        try {
            SQLiteJsonConstructor::jsonObjectSqlFunctionArguments('jsonb_object', ['a', 1, 'b']);
            $t->same('exception', 'not thrown');
        } catch (InvalidArgumentException $e) {
            $t->same('json_object() requires an even number of arguments', $e->getMessage());
        }
    }
};

$tests['real upstream json101 constructor dynamic source citations'] = static function (TestRunner $t): void {
    $t->same([
        'json101.test: json101-1.1 array constructors and JSON subtype insertion',
        'json101.test: json101-1.3 raw BLOB rejection for JSON constructors',
        'json101.test: json101-2.1 object constructors and JSONB object parity',
        'json101.test: json101-2.2 label and arity error boundaries',
        'json101.test: json101-3.1 through json101-3.4 SQL text versus JSON value mutation boundaries',
        'json101.test: json101-4.5 through json101-4.8 no-edit mutation identity',
    ], [
        'json101.test: json101-1.1 array constructors and JSON subtype insertion',
        'json101.test: json101-1.3 raw BLOB rejection for JSON constructors',
        'json101.test: json101-2.1 object constructors and JSONB object parity',
        'json101.test: json101-2.2 label and arity error boundaries',
        'json101.test: json101-3.1 through json101-3.4 SQL text versus JSON value mutation boundaries',
        'json101.test: json101-4.5 through json101-4.8 no-edit mutation identity',
    ]);
};

return $tests;
