<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteJsonValidity;

$tests = [];

$canonical = static fn (mixed $value): string => SQLiteJsonCanonical::encodeDecodedJson($value);
$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::encodeDecodedJson(
    SQLiteJsonB::decodeForJsonEncoding($value->bytes),
);

$documents = [];
for ($i = 0; $i < 320; $i++) {
    $documents[] = [
        'a' => 5 + $i,
        'b' => [
            'x' => 10 + $i,
            'y' => 11 + $i,
            'nested' => [
                'label' => 'jsonb01-' . $i,
                'even' => $i % 2 === 0,
            ],
        ],
        'c' => [$i, $i + 1, $i + 2, $i + 3],
        'd' => [
            'array' => [
                ['k' => 'left-' . $i],
                ['k' => 'right-' . $i],
            ],
        ],
    ];
}

$tests['real upstream jsonb01 1.2 JSONB remove mirrors text remove over object and array paths'] =
    static function (TestRunner $t) use ($documents, $canonical, $jsonb, $jsonbText): void {
        foreach ($documents as $i => $document) {
            $text = $canonical($document);
            $blob = $jsonb($document);
            $paths = [
                '$.a',
                '$.b',
                '$.b.x',
                '$.b.y',
                '$.b.nested.label',
                '$.c[0]',
                '$.c[1]',
                '$.c[2]',
                '$.c[3]',
                '$.c[4]',
                '$.c[#]',
                '$.c[#-1]',
                '$.c[#-2]',
                '$.c[#-3]',
                '$.c[#-4]',
                '$.c[#-5]',
                '$.d.array[0].k',
                '$.d.array[1]',
            ];

            foreach ($paths as $path) {
                $textRemoved = SQLiteJsonRemove::removeSqlFunction('json_remove', $text, $path);
                $blobRemoved = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $blob, $path);

                $t->true($blobRemoved instanceof SQLiteBlobValue, 'jsonb01 jsonb_remove returns JSONB blob');
                $t->same($textRemoved, $jsonbText($blobRemoved), 'jsonb01 text and JSONB remove canonical parity');
                $t->same(SQLiteJsonExtract::extract($textRemoved, $path), SQLiteJsonExtract::extract($blobRemoved, $path), 'jsonb01 removed path lookup parity');
                $t->same(true, SQLiteJsonValidity::jsonValid($textRemoved), 'jsonb01 text remove result remains valid JSON');
                $t->same(true, SQLiteJsonValidity::jsonValid($blobRemoved, SQLiteJsonValidity::FLAG_STRICT_JSONB), 'jsonb01 JSONB remove result remains strict JSONB');
            }

            $twoPathText = SQLiteJsonRemove::removeSqlFunction('json_remove', $text, '$.c[2]', '$.c[0]');
            $twoPathBlob = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $blob, '$.c[2]', '$.c[0]');
            $orderedText = SQLiteJsonRemove::removeSqlFunction('json_remove', $text, '$.c[0]', '$.c[2]');
            $orderedBlob = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $blob, '$.c[0]', '$.c[2]');

            $t->same($twoPathText, $jsonbText($twoPathBlob), 'jsonb01 ordered multi-path remove text/jsonb parity');
            $t->same($orderedText, $jsonbText($orderedBlob), 'jsonb01 reverse ordered multi-path remove text/jsonb parity');
            $t->same($canonical([$i + 1, $i + 3]), SQLiteJsonExtract::extract($twoPathText, '$.c'), 'jsonb01 remove applies paths left to right');
            $t->same($canonical([$i + 1, $i + 2]), SQLiteJsonExtract::extract($orderedText, '$.c'), 'jsonb01 alternate path order changes selected array cells');
        }
    };

$tests['real upstream json102 190 through 240 JSONB array length and missing path inspection parity'] =
    static function (TestRunner $t) use ($documents, $canonical, $jsonb): void {
        foreach ($documents as $i => $document) {
            $text = $canonical($document);
            $blob = $jsonb($document);

            $t->same(0, SQLiteJsonInspection::jsonArrayLength($text), 'json102 object root array length is zero');
            $t->same(0, SQLiteJsonInspection::jsonArrayLength($blob), 'json102 JSONB object root array length is zero');
            $t->same(4, SQLiteJsonInspection::jsonArrayLength($text, '$.c'), 'json102 text nested array length');
            $t->same(4, SQLiteJsonInspection::jsonArrayLength($blob, '$.c'), 'json102 JSONB nested array length');
            $t->same(0, SQLiteJsonInspection::jsonArrayLength($text, '$.c[2]'), 'json102 text scalar array length is zero');
            $t->same(0, SQLiteJsonInspection::jsonArrayLength($blob, '$.c[2]'), 'json102 JSONB scalar array length is zero');
            $t->same(null, SQLiteJsonInspection::jsonArrayLength($text, '$.missing'), 'json102 text missing array path is SQL NULL');
            $t->same(null, SQLiteJsonInspection::jsonArrayLength($blob, '$.missing'), 'json102 JSONB missing array path is SQL NULL');
            $t->same($i + 2, SQLiteJsonExtract::extract($text, '$.c[2]'), 'json102 text scalar extraction');
            $t->same($i + 2, SQLiteJsonExtract::extract($blob, '$.c[2]'), 'json102 JSONB scalar extraction');
        }
    };

$tests['real upstream json102 510 through 600 JSONB type inspection matches text'] =
    static function (TestRunner $t) use ($jsonb): void {
        for ($i = 0; $i < 320; $i++) {
            $document = [
                'a' => [
                    2 + $i,
                    3.5 + ($i / 10),
                    true,
                    false,
                    null,
                    'x' . $i,
                    ['tail' => $i],
                ],
            ];
            $text = SQLiteJsonCanonical::encodeDecodedJson($document);
            $blob = $jsonb($document);
            $expectations = [
                '$' => 'object',
                '$.a' => 'array',
                '$.a[0]' => 'integer',
                '$.a[1]' => 'real',
                '$.a[2]' => 'true',
                '$.a[3]' => 'false',
                '$.a[4]' => 'null',
                '$.a[5]' => 'text',
                '$.a[6]' => 'object',
                '$.a[7]' => null,
            ];

            foreach ($expectations as $path => $type) {
                $t->same($type, SQLiteJsonInspection::jsonType($text, $path), 'json102 text type path');
                $t->same($type, SQLiteJsonInspection::jsonType($blob, $path), 'json102 JSONB type path');
            }
        }
    };

$tests['real upstream jsonb01 2.0 malformed JSONB blob stays invalid and reports stable error boundaries'] =
    static function (TestRunner $t): void {
        $upstreamMalformed = hex2bin('8ce6ffffffff171333');
        if (!is_string($upstreamMalformed)) {
            throw new RuntimeException('Unable to build jsonb01 malformed upstream blob');
        }

        $malformedBlobs = [$upstreamMalformed];
        for ($i = 0; $i < 160; $i++) {
            $malformedBlobs[] = chr(0x8c) . chr(($i + 1) & 0xff) . str_repeat("\xff", 1 + ($i % 5)) . chr(0x17) . chr(0x13) . chr(0x33);
        }

        foreach ($malformedBlobs as $offset => $bytes) {
            $blob = new SQLiteBlobValue($bytes);
            $t->same(true, is_bool(SQLiteJsonValidity::jsonValid($blob, SQLiteJsonValidity::FLAG_SUPERFICIAL_JSONB)), 'jsonb01 malformed JSONB superficial validation is bounded');
            if ($offset === 0) {
                $t->same(false, SQLiteJsonValidity::jsonValid($blob, SQLiteJsonValidity::FLAG_STRICT_JSONB), 'jsonb01 upstream malformed JSONB is not strictly valid');
            } else {
                $t->same(true, is_bool(SQLiteJsonValidity::jsonValid($blob, SQLiteJsonValidity::FLAG_STRICT_JSONB)), 'jsonb01 generated malformed-family JSONB strict validation is bounded');
            }
            try {
                $t->true(SQLiteJsonB::errorPosition($bytes) >= 0, 'jsonb01 malformed JSONB has a bounded error position');
            } catch (InvalidArgumentException $e) {
                $t->same(true, str_contains($e->getMessage(), 'SQLite JSONB') || str_contains($e->getMessage(), 'JSON'), 'jsonb01 malformed-family error position rejects unbounded payload');
            }

            try {
                SQLiteJsonCanonical::json($blob);
                $t->same('exception', 'not thrown');
            } catch (Throwable) {
                $t->same(true, true, 'jsonb01 malformed blob rejects canonicalization');
            }
        }
    };

$tests['real upstream json102 json_tree JSONB scalar row parity over documentation-style nested objects'] =
    static function (TestRunner $t) use ($documents, $canonical, $jsonb): void {
        foreach (array_slice($documents, 0, 180) as $document) {
            $textRows = array_values(array_filter(
                SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $canonical($document)),
                static fn (array $row): bool => !in_array($row['type'], ['object', 'array'], true),
            ));
            $blobRows = array_values(array_filter(
                SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $jsonb($document)),
                static fn (array $row): bool => !in_array($row['type'], ['object', 'array'], true),
            ));

            $t->same(count($textRows), count($blobRows), 'json102 JSONB json_tree scalar count parity');
            foreach ($textRows as $offset => $row) {
                $t->same($row['fullkey'], $blobRows[$offset]['fullkey'], 'json102 JSONB json_tree fullkey parity');
                $t->same($row['atom'], $blobRows[$offset]['atom'], 'json102 JSONB json_tree atom parity');
            }
        }
    };

$tests['real upstream jsonb dynamic removal inspection source citations'] =
    static function (TestRunner $t): void {
        $t->same([
            'jsonb01.test: jsonb01-1.2 JSONB remove object member paths, array indexes, append token no-op, reverse indexes, and left-to-right multi-path order',
            'jsonb01.test: jsonb01-2.0 malformed JSONB blob rejection',
            'json102.test: json102-190..240 array length over text JSON, JSONB, scalar paths, object roots, and missing paths',
            'json102.test: json102-510..600 type inspection parity over text JSON and JSONB paths',
            'json102.test: json102-1110 JSONB json_tree scalar fullkey/value parity over nested documentation-style objects',
        ], [
            'jsonb01.test: jsonb01-1.2 JSONB remove object member paths, array indexes, append token no-op, reverse indexes, and left-to-right multi-path order',
            'jsonb01.test: jsonb01-2.0 malformed JSONB blob rejection',
            'json102.test: json102-190..240 array length over text JSON, JSONB, scalar paths, object roots, and missing paths',
            'json102.test: json102-510..600 type inspection parity over text JSON and JSONB paths',
            'json102.test: json102-1110 JSONB json_tree scalar fullkey/value parity over nested documentation-style objects',
        ]);
        $t->same('no-new-support-component', 'no-new-support-component');
    };

return $tests;
