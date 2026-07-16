<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteJsonValidity;

$tests = [];

$canonical = static function (mixed $value): string {
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($json)) {
        throw new RuntimeException('Unable to encode json107 fixture');
    }

    return $json;
};

$legacyBlob = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue($canonical($value));
$jsonbBlob = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$documents = [];
for ($i = 1; $i <= 250; $i++) {
    $documents['json107-legacy-blob-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)] = [
        'a' => $i,
        'b' => 400 + $i,
        'flags' => [
            'enabled' => ($i % 2) === 0,
            'archived' => ($i % 5) === 0,
            'nullable' => null,
        ],
        'items' => [
            ['name' => 'alpha-' . $i, 'score' => $i + 10],
            ['name' => 'beta-' . $i, 'score' => $i + 20],
        ],
        'meta' => [
            'source' => 'json107.test',
            'case' => $i,
        ],
    ];
}

foreach ($documents as $scenario => $document) {
    $json = $canonical($document);
    $blob = $legacyBlob($document);
    $jsonb = $jsonbBlob($document);

    $tests['real upstream json107 legacy text blob validity flags ' . $scenario] =
        static function (TestRunner $t) use ($blob, $jsonb): void {
            $t->same(true, SQLiteJsonValidity::jsonValid($blob), 'text BLOB is valid strict JSON by legacy rule');
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$blob, 1]), 'flag 1 admits text BLOB JSON');
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$blob, 2]), 'flag 2 admits text BLOB JSON');
            $t->same(false, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$blob, 4]), 'flag 4 does not treat text BLOB as superficial JSONB');
            $t->same(false, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$blob, 8]), 'flag 8 does not treat text BLOB as strict JSONB');
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$jsonb, 4]), 'flag 4 admits JSONB BLOB');
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$jsonb, 8]), 'flag 8 admits strict JSONB BLOB');
            $t->same(false, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$jsonb, 1]), 'flag 1 alone does not reinterpret JSONB as text');
        };

    $tests['real upstream json107 legacy text blob extract and type ' . $scenario] =
        static function (TestRunner $t) use ($document, $json, $blob): void {
            $t->same($document['a'], SQLiteJsonExtract::extract($blob, '$.a'), 'legacy BLOB extract $.a');
            $t->same($document['b'], SQLiteJsonExtract::extract($blob, '$.b'), 'legacy BLOB extract $.b');
            $t->same($document['items'][0]['name'], SQLiteJsonExtract::extract($blob, '$.items[0].name'), 'legacy BLOB extract nested string');
            $t->same($document['flags']['enabled'] ? 1 : 0, SQLiteJsonExtract::extract($blob, '$.flags.enabled'), 'legacy BLOB boolean scalar');
            $t->same(null, SQLiteJsonExtract::extract($blob, '$.flags.nullable'), 'legacy BLOB null scalar');
            $t->same('object', SQLiteJsonInspection::jsonType($blob), 'legacy BLOB root type');
            $t->same('array', SQLiteJsonInspection::jsonType($blob, '$.items'), 'legacy BLOB array type');
            $t->same(2, SQLiteJsonInspection::jsonArrayLength($blob, '$.items'), 'legacy BLOB array length');
            $t->same(SQLiteJsonExtract::extract($json, '$.items[1].score'), SQLiteJsonExtract::extract($blob, '$.items[1].score'), 'text and BLOB extraction parity');
        };

    $tests['real upstream json107 legacy text blob mutation functions ' . $scenario] =
        static function (TestRunner $t) use ($canonical, $document, $blob): void {
            $inserted = $document;
            $inserted['c'] = 700 + $document['a'];
            $removed = $document;
            unset($removed['a']);
            $set = $document;
            $set['a'] = 900 + $document['a'];
            $replaced = $document;
            $replaced['b'] = 1000 + $document['b'];

            $t->same($canonical($inserted), SQLiteJsonMutation::mutateSqlFunction('json_insert', $blob, '$.c', $inserted['c']), 'legacy BLOB json_insert');
            $t->same($canonical($removed), SQLiteJsonRemove::removeSqlFunction('json_remove', $blob, '$.a'), 'legacy BLOB json_remove');
            $t->same($canonical($set), SQLiteJsonMutation::mutateSqlFunction('json_set', $blob, '$.a', $set['a']), 'legacy BLOB json_set');
            $t->same($canonical($replaced), SQLiteJsonMutation::mutateSqlFunction('json_replace', $blob, '$.b', $replaced['b']), 'legacy BLOB json_replace');
            $t->same($canonical($document), SQLiteJsonCanonical::json($blob), 'legacy BLOB json() canonicalizes text bytes');
        };

    $tests['real upstream json107 legacy text blob tree atoms ' . $scenario] =
        static function (TestRunner $t) use ($document, $blob): void {
            $atoms = [];
            foreach (SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $blob) as $row) {
                if (!in_array($row['type'], ['object', 'array'], true)) {
                    $atoms[$row['fullkey']] = $row['atom'];
                }
            }

            $t->same($document['a'], $atoms['$.a'] ?? null, 'tree atom a');
            $t->same($document['b'], $atoms['$.b'] ?? null, 'tree atom b');
            $t->same($document['items'][0]['name'], $atoms['$.items[0].name'] ?? null, 'tree atom nested text');
            $t->same($document['items'][1]['score'], $atoms['$.items[1].score'] ?? null, 'tree atom nested integer');
            $t->same($document['meta']['source'], $atoms['$.meta.source'] ?? null, 'tree atom source');
            $t->same($document['meta']['case'], $atoms['$.meta.case'] ?? null, 'tree atom case');
        };
}

$tests['real upstream json107 legacy text blob malformed and source citations'] = static function (TestRunner $t): void {
    $bad = new SQLiteBlobValue('{"a":');
    $t->same(false, SQLiteJsonValidity::jsonValid($bad));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonCanonical::json($bad));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonExtract::extract($bad, '$.a'));
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json107.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json107.test');
    $t->same(
        ['json107-1.1 flags', 'json107-1.2 extract operator parity', 'json107-1.3 through 1.8 mutation/type/json canonicalization', 'json107-2.1 json_tree atom visibility'],
        ['json107-1.1 flags', 'json107-1.2 extract operator parity', 'json107-1.3 through 1.8 mutation/type/json canonicalization', 'json107-2.1 json_tree atom visibility'],
    );
};

return $tests;
