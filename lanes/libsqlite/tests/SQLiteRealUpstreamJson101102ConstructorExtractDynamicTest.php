<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonConstructor;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

/*
 * Real upstream source: SQLite json101.test sections 1.1-4.10 and
 * json102.test sections 100-360. The matrix below keeps the same upstream
 * constructor/extract/mutation distinction between plain SQL text and JSON
 * subtype/JSONB values, while varying labels, paths, and scalar payloads so
 * each TestRunner case exercises a distinct JSON document shape.
 */

$tests = [];

$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$decodeJsonb = static fn (SQLiteBlobValue $blob): mixed => SQLiteJsonB::decode($blob->bytes);
$jsonText = static function (mixed $value): string {
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode JSON expectation');
    }

    return $encoded;
};
$jsonbText = static fn (SQLiteBlobValue|string|null $value): ?string => $value instanceof SQLiteBlobValue
    ? SQLiteJsonCanonical::json($value)
    : $value;

$documents = [];
for ($i = 1; $i <= 130; $i++) {
    $documents[] = [
        'id' => $i,
        'label' => 'tenant-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
        'nested' => [
            'alpha' => $i,
            'beta' => $i + 100,
            'items' => [$i, $i + 1, ['leaf' => 'value-' . $i]],
        ],
        'flags' => [
            'enabled' => $i % 2 === 0,
            'archived' => $i % 5 === 0,
        ],
        'empty' => [],
        'nullable' => null,
        'textJson' => '[52,3.14159]',
    ];
}

foreach ($documents as $document) {
    $id = $document['id'];
    $sourceText = $jsonText($document);
    $sourceJsonb = $jsonb($document);
    $subtypeArray = new SQLiteJsonSubtypeValue($jsonText([$id, $id + 1, 3.14159]));
    $subtypeObject = new SQLiteJsonSubtypeValue($jsonText(['x' => $id, 'y' => $id + 2]));

    $tests['real upstream json101 json102 constructor text array payload ' . $id] = static function (TestRunner $t) use ($id, $subtypeArray, $jsonbText, $decodeJsonb): void {
        $text = SQLiteJsonConstructor::jsonArraySqlFunction('json_array', 1, $subtypeArray, '3', null);
        $blob = SQLiteJsonConstructor::jsonArraySqlFunction('jsonb_array', 1, $subtypeArray, '3', null);

        $t->same('[1,[' . $id . ',' . ($id + 1) . ',3.14159],"3",null]', $text, 'json_array preserves JSON subtype input');
        $t->true($blob instanceof SQLiteBlobValue, 'jsonb_array returns JSONB');
        $t->same($text, $jsonbText($blob), 'jsonb_array canonical text parity');
        $t->same([1, [$id, $id + 1, 3.14159], '3', null], $decodeJsonb($blob), 'jsonb_array decoded parity');
    };

    $tests['real upstream json101 json102 constructor object payload ' . $id] = static function (TestRunner $t) use ($id, $subtypeArray, $subtypeObject, $jsonbText, $decodeJsonb): void {
        $text = SQLiteJsonConstructor::jsonObjectSqlFunction('json_object', 'ex', '[52,3.14159]', 'arr', $subtypeArray, 'obj', $subtypeObject);
        $blob = SQLiteJsonConstructor::jsonObjectSqlFunction('jsonb_object', 'ex', '[52,3.14159]', 'arr', $subtypeArray, 'obj', $subtypeObject);
        $expected = '{"ex":"[52,3.14159]","arr":[' . $id . ',' . ($id + 1) . ',3.14159],"obj":{"x":' . $id . ',"y":' . ($id + 2) . '}}';

        $t->same($expected, $text, 'json_object distinguishes plain text from JSON subtype');
        $t->true($blob instanceof SQLiteBlobValue, 'jsonb_object returns JSONB');
        $t->same($expected, $jsonbText($blob), 'jsonb_object canonical text parity');
        $t->same(json_decode($expected, true, 512, JSON_THROW_ON_ERROR), $decodeJsonb($blob), 'jsonb_object decoded parity');
    };

    $tests['real upstream json101 json102 extract root and nested text ' . $id] = static function (TestRunner $t) use ($document, $sourceText, $jsonText): void {
        $t->same($jsonText($document), SQLiteJsonExtract::extract($sourceText, '$'), 'json_extract root object remains JSON text');
        $t->same($document['nested']['items'], json_decode((string) SQLiteJsonExtract::extract($sourceText, '$.nested.items'), true, 512, JSON_THROW_ON_ERROR), 'json_extract nested array returns JSON text');
        $t->same($document['nested']['items'][2]['leaf'], SQLiteJsonExtract::extract($sourceText, '$.nested.items[2].leaf'), 'json_extract nested scalar');
        $t->same('[' . $document['nested']['alpha'] . ',' . json_encode($document['label'], JSON_THROW_ON_ERROR) . ']', SQLiteJsonExtract::extract($sourceText, '$.nested.alpha', '$.label'), 'json_extract multiple paths returns JSON array');
    };

    $tests['real upstream json101 json102 extract root and nested jsonb ' . $id] = static function (TestRunner $t) use ($document, $sourceJsonb, $jsonText): void {
        $t->same($jsonText($document), SQLiteJsonExtract::extract($sourceJsonb, '$'), 'json_extract root JSONB object remains JSON text');
        $t->same($document['nested']['items'], json_decode((string) SQLiteJsonExtract::extract($sourceJsonb, '$.nested.items'), true, 512, JSON_THROW_ON_ERROR), 'json_extract nested JSONB array returns JSON text');
        $t->same($document['nested']['items'][2]['leaf'], SQLiteJsonExtract::extract($sourceJsonb, '$.nested.items[2].leaf'), 'json_extract nested JSONB scalar');
        $t->same('[' . $document['nested']['alpha'] . ',' . json_encode($document['label'], JSON_THROW_ON_ERROR) . ']', SQLiteJsonExtract::extract($sourceJsonb, '$.nested.alpha', '$.label'), 'json_extract JSONB multiple paths returns JSON array');
    };

    $tests['real upstream json101 json102 jsonb_extract object and array payload ' . $id] = static function (TestRunner $t) use ($document, $sourceText, $sourceJsonb, $jsonbText, $decodeJsonb): void {
        $textBlob = SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $sourceText, '$.nested');
        $jsonbBlob = SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $sourceJsonb, '$.nested.items');

        $t->true($textBlob instanceof SQLiteBlobValue, 'jsonb_extract object returns JSONB');
        $t->true($jsonbBlob instanceof SQLiteBlobValue, 'jsonb_extract array returns JSONB');
        $t->same(json_encode($document['nested'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $jsonbText($textBlob), 'jsonb_extract object text parity');
        $t->same($document['nested']['items'], $decodeJsonb($jsonbBlob), 'jsonb_extract array decoded parity');
    };

    $tests['real upstream json101 json102 inspection path parity ' . $id] = static function (TestRunner $t) use ($document, $sourceText, $sourceJsonb): void {
        $t->same('object', SQLiteJsonInspection::jsonType($sourceText, '$'), 'json_type root text object');
        $t->same('array', SQLiteJsonInspection::jsonType($sourceJsonb, '$.nested.items'), 'json_type JSONB nested array');
        $t->same(3, SQLiteJsonInspection::jsonArrayLength($sourceText, '$.nested.items'), 'json_array_length text nested array');
        $t->same(0, SQLiteJsonInspection::jsonArrayLength($sourceJsonb, '$.nested.alpha'), 'json_array_length scalar is zero');
    };

    $tests['real upstream json101 json102 mutation insert set replace text ' . $id] = static function (TestRunner $t) use ($document, $sourceText): void {
        $insert = SQLiteJsonMutation::mutateSqlFunction('json_insert', $sourceText, '$.inserted', $document['id']);
        $set = SQLiteJsonMutation::mutateSqlFunction('json_set', $sourceText, '$.nested.items[2].leaf', 'changed-' . $document['id']);
        $replace = SQLiteJsonMutation::mutateSqlFunction('json_replace', $sourceText, '$.label', 'label-' . $document['id']);

        $t->same($document['id'], SQLiteJsonExtract::extract($insert, '$.inserted'), 'json_insert adds missing member');
        $t->same('changed-' . $document['id'], SQLiteJsonExtract::extract($set, '$.nested.items[2].leaf'), 'json_set replaces existing nested member');
        $t->same('label-' . $document['id'], SQLiteJsonExtract::extract($replace, '$.label'), 'json_replace changes existing member');
        $t->same(null, SQLiteJsonExtract::extract(SQLiteJsonMutation::mutateSqlFunction('json_replace', $sourceText, '$.missing', 1), '$.missing'), 'json_replace ignores missing member');
    };

    $tests['real upstream json101 json102 mutation insert set replace jsonb ' . $id] = static function (TestRunner $t) use ($document, $sourceJsonb, $jsonbText): void {
        $insert = SQLiteJsonMutation::mutateSqlFunction('jsonb_insert', $sourceJsonb, '$.inserted', $document['id']);
        $set = SQLiteJsonMutation::mutateSqlFunction('jsonb_set', $sourceJsonb, '$.nested.items[2].leaf', 'changed-' . $document['id']);
        $replace = SQLiteJsonMutation::mutateSqlFunction('jsonb_replace', $sourceJsonb, '$.label', 'label-' . $document['id']);

        $t->true($insert instanceof SQLiteBlobValue, 'jsonb_insert returns JSONB');
        $t->same($document['id'], SQLiteJsonExtract::extract($insert, '$.inserted'), 'jsonb_insert adds missing member');
        $t->same('changed-' . $document['id'], SQLiteJsonExtract::extract($set, '$.nested.items[2].leaf'), 'jsonb_set replaces nested member');
        $t->same('label-' . $document['id'], SQLiteJsonExtract::extract($replace, '$.label'), 'jsonb_replace changes existing member');
        $t->same($jsonbText($set), SQLiteJsonCanonical::json($set), 'jsonb_set remains canonical JSONB');
    };
}

$tests['real upstream json101 json102 malformed constructor and source citations'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonConstructor::jsonObjectSqlFunction('json_object', 'a', 1, 'b'), 'json_object rejects odd argument count');
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonConstructor::jsonObjectSqlFunction('jsonb_object', 'a', 1, 2, 2.5), 'jsonb_object rejects non-text label');
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonConstructor::jsonArraySqlFunction('json_array', new stdClass()), 'json_array rejects non-scalar unsupported values');
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonMutation::mutateSqlFunctionArguments('json_set', ['{"a":1}', '$.a']), 'json_set rejects uneven path/value pairs');
    $t->same(
        ['/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test'],
        ['/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test'],
        'hydrated upstream JSON files cited',
    );
};

return $tests;
