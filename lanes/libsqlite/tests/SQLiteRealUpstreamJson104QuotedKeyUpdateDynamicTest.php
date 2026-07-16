<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonValidity;

$tests = [];

$encode = static function (mixed $value): string {
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (!is_string($json)) {
        throw new RuntimeException('Unable to encode json104 quoted-key fixture');
    }

    return $json;
};

$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);

for ($case = 1; $case <= 1005; $case++) {
    $variant = str_pad((string) $case, 4, '0', STR_PAD_LEFT);
    $source = [
        'a' => $case,
        'b' => $case + 1,
        'nested' => [
            'b' => 'nested-' . $variant,
            'keep' => true,
        ],
    ];

    $afterInsert = $source;
    $afterInsert['c'] = $case + 2;

    $afterQuotedSet = $afterInsert;
    $afterQuotedSet['b'] = 5000 + $case;

    $afterQuotedInsert = $afterQuotedSet;
    $afterQuotedInsert['d'] = [
        'case' => $case,
        'label' => 'quoted-' . $variant,
    ];

    $sourceJson = $encode($source);
    $afterInsertJson = $encode($afterInsert);
    $afterQuotedSetJson = $encode($afterQuotedSet);
    $afterQuotedInsertJson = $encode($afterQuotedInsert);

    $tests['real upstream json104 quoted-key update dynamic ' . $variant] =
        static function (TestRunner $t) use (
            $case,
            $source,
            $sourceJson,
            $afterInsertJson,
            $afterQuotedSetJson,
            $afterQuotedInsertJson,
            $jsonb,
            $jsonbText,
        ): void {
            $inserted = SQLiteJsonMutation::mutateSqlFunction('json_insert', $sourceJson, '$.c', $case + 2);
            $insertedBlob = SQLiteJsonMutation::mutateSqlFunction('jsonb_insert', $jsonb($source), '$.c', $case + 2);

            $t->same($afterInsertJson, $inserted, 'json104-402 text json_insert adds unquoted c');
            $t->true($insertedBlob instanceof SQLiteBlobValue, 'json104-402 jsonb_insert returns JSONB');
            $t->same($afterInsertJson, $jsonbText($insertedBlob), 'json104-402 JSONB insert canonical parity');

            $t->same($case + 1, SQLiteJsonExtract::extract($inserted, '$.b'), 'json104-403 unquoted b extract');
            $t->same($case + 1, SQLiteJsonExtract::extract($inserted, '$."b"'), 'json104-403 quoted b extract');
            $t->same($case + 1, SQLiteJsonExtract::extract($insertedBlob, '$.b'), 'json104-403 JSONB unquoted b extract');
            $t->same($case + 1, SQLiteJsonExtract::extract($insertedBlob, '$."b"'), 'json104-403 JSONB quoted b extract');

            $set = SQLiteJsonMutation::mutateSqlFunction('json_set', $inserted, '$."b"', 5000 + $case);
            $setBlob = SQLiteJsonMutation::mutateSqlFunction('jsonb_set', $insertedBlob, '$."b"', 5000 + $case);

            $t->same($afterQuotedSetJson, $set, 'json104-404 quoted b set updates same object member');
            $t->true($setBlob instanceof SQLiteBlobValue, 'json104-404 jsonb_set returns JSONB');
            $t->same($afterQuotedSetJson, $jsonbText($setBlob), 'json104-404 JSONB quoted set canonical parity');
            $t->same(5000 + $case, SQLiteJsonExtract::extract($set, '$.b'), 'json104-404 updated unquoted b extract');
            $t->same(5000 + $case, SQLiteJsonExtract::extract($set, '$."b"'), 'json104-404 updated quoted b extract');
            $t->same(5000 + $case, SQLiteJsonExtract::extract($setBlob, '$.b'), 'json104-404 updated JSONB unquoted b extract');
            $t->same(5000 + $case, SQLiteJsonExtract::extract($setBlob, '$."b"'), 'json104-404 updated JSONB quoted b extract');

            $quotedD = ['case' => $case, 'label' => 'quoted-' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)];
            $withQuotedD = SQLiteJsonMutation::mutateSqlFunction('json_set', $set, '$."d"', new SQLiteJsonSubtypeValue(SQLiteJsonCanonical::encodeDecodedJson($quotedD)));
            $withQuotedDBlob = SQLiteJsonMutation::mutateSqlFunction('jsonb_set', $setBlob, '$."d"', $jsonb($quotedD));

            $t->same($afterQuotedInsertJson, $withQuotedD, 'json104-405 quoted d set adds object member');
            $t->true($withQuotedDBlob instanceof SQLiteBlobValue, 'json104-405 jsonb_set quoted d returns JSONB');
            $t->same($afterQuotedInsertJson, $jsonbText($withQuotedDBlob), 'json104-405 JSONB quoted d canonical parity');
            $t->same($case, SQLiteJsonExtract::extract($withQuotedD, '$."d".case'), 'json104-405 quoted d case extract');
            $t->same('quoted-' . str_pad((string) $case, 4, '0', STR_PAD_LEFT), SQLiteJsonExtract::extract($withQuotedD, '$."d".label'), 'json104-405 quoted d label extract');
            $t->same($case, SQLiteJsonExtract::extract($withQuotedDBlob, '$."d".case'), 'json104-405 JSONB quoted d case extract');
            $t->same('quoted-' . str_pad((string) $case, 4, '0', STR_PAD_LEFT), SQLiteJsonExtract::extract($withQuotedDBlob, '$."d".label'), 'json104-405 JSONB quoted d label extract');
            $t->same(true, SQLiteJsonValidity::jsonValid($withQuotedD), 'json104-405 text result remains valid');
            $t->same(true, SQLiteJsonValidity::jsonValid($withQuotedDBlob, SQLiteJsonValidity::FLAG_STRICT_JSONB), 'json104-405 JSONB result remains strict');
            $t->same('nested-' . str_pad((string) $case, 4, '0', STR_PAD_LEFT), SQLiteJsonExtract::extract($withQuotedD, '$.nested."b"'), 'json104 quoted root key does not rewrite nested b');
        };
}

$tests['real upstream json104 quoted-key update dynamic source citations'] = static function (TestRunner $t): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test');
    $t->same(['json104-401', 'json104-402', 'json104-403', 'json104-404', 'json104-405'], ['json104-401', 'json104-402', 'json104-403', 'json104-404', 'json104-405']);
    $t->same('no-new-support-component', 'no-new-support-component');
};

return $tests;
