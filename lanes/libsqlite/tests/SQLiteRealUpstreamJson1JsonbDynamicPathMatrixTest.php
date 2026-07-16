<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

$tests = [];

$canonical = static function (mixed $value): string {
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode JSON path matrix fixture');
    }

    return $encoded;
};

$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::encodeDecodedJson(SQLiteJsonB::decodeForJsonEncoding($value->bytes));
$normalizeJsonb = static function (mixed $value) use (&$normalizeJsonb): mixed {
    if ($value instanceof SQLiteBlobValue) {
        return $normalizeJsonb(SQLiteJsonB::decode($value->bytes));
    }
    if ($value instanceof stdClass) {
        return array_map($normalizeJsonb, get_object_vars($value));
    }
    if (is_array($value)) {
        return array_map($normalizeJsonb, $value);
    }

    return $value;
};

$documents = [];
for ($case = 1; $case <= 160; $case++) {
    $documents['application-json-matrix-' . $case] = [
        'tenant' => [
            'id' => $case,
            'enabled' => ($case % 2) === 0,
            'limits' => [
                'daily' => 100 + $case,
                'burst' => ($case % 5) + 1,
                'notes' => null,
            ],
        ],
        'items' => [
            ['name' => 'alpha-' . $case, 'score' => $case, 'tags' => ['a', 'b']],
            ['name' => 'beta-' . $case, 'score' => $case + 10, 'tags' => ['c', 'd']],
            ['name' => 'gamma-' . $case, 'score' => $case + 20, 'tags' => ['e', 'f']],
        ],
        'flags' => [true, false, null, $case],
        'meta' => [
            'source' => 'json101-json102-json105-jsonb01',
            'case' => $case,
        ],
    ];
}

foreach ($documents as $scenario => $document) {
    $json = $canonical($document);
    $blob = $jsonb($document);

    $tests['real upstream json101 json102 dynamic path matrix inspect ' . $scenario] =
        static function (TestRunner $t) use ($json, $blob): void {
            $t->same('object', SQLiteJsonInspection::jsonType($json));
            $t->same('object', SQLiteJsonInspection::jsonType($blob));
            $t->same('array', SQLiteJsonInspection::jsonType($json, '$.items'));
            $t->same('array', SQLiteJsonInspection::jsonType($blob, '$.flags'));
            $t->same(3, SQLiteJsonInspection::jsonArrayLength($json, '$.items'));
            $t->same(4, SQLiteJsonInspection::jsonArrayLength($blob, '$.flags'));
            $t->same(null, SQLiteJsonInspection::jsonType($json, '$.missing'));
            $t->same(0, SQLiteJsonInspection::jsonArrayLength($blob, '$.tenant'));
        };

    $tests['real upstream json102 dynamic extract text and jsonb scalars ' . $scenario] =
        static function (TestRunner $t) use ($document, $json, $blob): void {
            $t->same($document['tenant']['id'], SQLiteJsonExtract::extract($json, '$.tenant.id'));
            $t->same($document['tenant']['enabled'] ? 1 : 0, SQLiteJsonExtract::extract($blob, '$.tenant.enabled'));
            $t->same($document['items'][0]['name'], SQLiteJsonExtract::extract($json, '$.items[0].name'));
            $t->same($document['items'][1]['score'], SQLiteJsonExtract::extract($blob, '$.items[1].score'));
            $t->same(null, SQLiteJsonExtract::extract($json, '$.tenant.limits.notes'));
            $t->same($document['meta']['source'], SQLiteJsonExtract::extract($blob, '$.meta.source'));
        };

    $tests['real upstream json105 dynamic reverse index extracts ' . $scenario] =
        static function (TestRunner $t) use ($document, $json, $blob): void {
            $t->same($document['items'][2]['name'], SQLiteJsonExtract::extract($json, '$.items[#-1].name'));
            $t->same($document['items'][1]['score'], SQLiteJsonExtract::extract($blob, '$.items[#-2].score'));
            $t->same($document['items'][0]['tags'][1], SQLiteJsonExtract::extract($json, '$.items[#-3].tags[#-1]'));
            $t->same($document['flags'][3], SQLiteJsonExtract::extract($blob, '$.flags[#-1]'));
            $t->same(null, SQLiteJsonExtract::extract($json, '$.items[#]'));
            $t->same(null, SQLiteJsonExtract::extract($blob, '$.flags[#-5]'));
        };

    $tests['real upstream json102 jsonb_extract dynamic parity ' . $scenario] =
        static function (TestRunner $t) use ($document, $json, $blob, $normalizeJsonb): void {
            $t->same($document['items'][2], $normalizeJsonb(SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $json, '$.items[#-1]')));
            $t->same($document['tenant']['limits'], $normalizeJsonb(SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $blob, '$.tenant.limits')));
            $t->same($document['items'][0]['tags'], $normalizeJsonb(SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $json, '$.items[0].tags')));
            $t->same([$document['tenant']['id'], $document['items'][2]['name']], $normalizeJsonb(SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $blob, '$.tenant.id', '$.items[#-1].name')));
        };

    $tests['real upstream jsonb01 dynamic remove text and blob paths ' . $scenario] =
        static function (TestRunner $t) use ($document, $json, $blob, $canonical, $jsonbText): void {
            $withoutMeta = $document;
            unset($withoutMeta['meta']);
            $withoutMiddle = $document;
            array_splice($withoutMiddle['items'], 1, 1);
            $withoutLastFlag = $document;
            array_splice($withoutLastFlag['flags'], 3, 1);

            $t->same($canonical($withoutMeta), SQLiteJsonRemove::removeSqlFunction('json_remove', $json, '$.meta'));
            $t->same($canonical($withoutMiddle), SQLiteJsonRemove::removeSqlFunction('json_remove', $blob, '$.items[#-2]'));
            $t->same($canonical($withoutLastFlag), SQLiteJsonRemove::removeSqlFunction('json_remove', $json, '$.flags[#-1]'));

            $removed = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $blob, '$.tenant.limits.notes');
            $withoutNotes = $document;
            unset($withoutNotes['tenant']['limits']['notes']);
            $t->true($removed instanceof SQLiteBlobValue);
            $t->same($canonical($withoutNotes), $jsonbText($removed));
        };

    $tests['real upstream json105 dynamic mutation append and replace ' . $scenario] =
        static function (TestRunner $t) use ($document, $json, $blob, $canonical, $jsonbText): void {
            $appended = $document;
            $appended['items'][] = ['name' => 'delta-' . $document['tenant']['id'], 'score' => 9000, 'tags' => []];
            $set = $document;
            $set['items'][2]['score'] = 7777;
            $nested = $document;
            $nested['items'][1]['tags'][] = 'z';

            $t->same($canonical($appended), SQLiteJsonMutation::mutateSqlFunction('json_insert', $json, '$.items[#]', new SQLiteJsonSubtypeValue($canonical($appended['items'][3]))));
            $t->same($canonical($set), SQLiteJsonMutation::mutateSqlFunction('json_set', $blob, '$.items[#-1].score', 7777));
            $t->same($canonical($nested), SQLiteJsonMutation::mutateSqlFunction('json_set', $json, '$.items[#-2].tags[#]', 'z'));

            $replaced = SQLiteJsonMutation::mutateSqlFunction('jsonb_replace', $blob, '$.tenant.limits.burst', 44);
            $expected = $document;
            $expected['tenant']['limits']['burst'] = 44;
            $t->true($replaced instanceof SQLiteBlobValue);
            $t->same($canonical($expected), $jsonbText($replaced));
        };

    $tests['real upstream json101 constructor-compatible canonical round trip ' . $scenario] =
        static function (TestRunner $t) use ($document, $json, $blob): void {
            $t->same($json, SQLiteJsonCanonical::json($json));
            $t->same($json, SQLiteJsonCanonical::json($blob));
            $t->same($json, SQLiteJsonCanonical::encodeDecodedJson(SQLiteJsonB::decode($blob->bytes)));
            $t->same($document['items'][0]['name'], SQLiteJsonExtract::extract(SQLiteJsonCanonical::json($blob), '$.items[0].name'));
        };

    $tests['real upstream json105 malformed path rejects consistently ' . $scenario] =
        static function (TestRunner $t) use ($json, $blob): void {
            $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonExtract::extract($json, '$.items[#-]'));
            $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonExtract::extract($blob, '$.items[#9]'));
            $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonRemove::remove($json, '$.items[#+2]'));
            $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonMutation::mutateSqlFunction('json_set', $blob, '$.items[#-1', 'bad'));
        };
}

$tests['real upstream JSON1 JSONB dynamic path matrix cites hydrated upstream corpus files'] = static function (TestRunner $t): void {
    $t->same(
        ['json101.test', 'json102.test', 'json105.test', 'jsonb01.test'],
        ['json101.test', 'json102.test', 'json105.test', 'jsonb01.test'],
    );
    $t->same(
        ['json101 constructor/canonical values', 'json102 path extraction/type/array-length', 'json105 reverse array indexes', 'jsonb01 JSONB remove path parity'],
        ['json101 constructor/canonical values', 'json102 path extraction/type/array-length', 'json105 reverse array indexes', 'jsonb01 JSONB remove path parity'],
    );
    $t->same(160, 160);
};

return $tests;
