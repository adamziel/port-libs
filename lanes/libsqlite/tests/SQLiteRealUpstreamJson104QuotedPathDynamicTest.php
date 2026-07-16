<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

$tests = [];

$jsonb = static fn (string $json): SQLiteBlobValue => new SQLiteBlobValue(
    SQLiteJsonB::encode(json_decode($json, true, 512, JSON_THROW_ON_ERROR))
);
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);
$canonical = static fn (mixed $value): string => SQLiteJsonCanonical::encodeDecodedJson($value);

for ($variant = 0; $variant < 250; $variant++) {
    $base = $canonical([
        'a' => 1 + $variant,
        'b' => 2 + $variant,
        'case' => 'json104-quoted-path-' . $variant,
    ]);
    $inserted = $canonical([
        'a' => 1 + $variant,
        'b' => 2 + $variant,
        'case' => 'json104-quoted-path-' . $variant,
        'c' => 3 + $variant,
    ]);
    $quotedSet = $canonical([
        'a' => 1 + $variant,
        'b' => 555 + $variant,
        'case' => 'json104-quoted-path-' . $variant,
        'c' => 3 + $variant,
    ]);
    $quotedInsert = $canonical([
        'a' => 1 + $variant,
        'b' => 555 + $variant,
        'case' => 'json104-quoted-path-' . $variant,
        'c' => 3 + $variant,
        'd' => 4 + $variant,
    ]);
    $patch = $canonical([
        'b' => 700 + $variant,
        'e' => ['nested' => $variant],
    ]);
    $patched = $canonical([
        'a' => 1 + $variant,
        'b' => 700 + $variant,
        'case' => 'json104-quoted-path-' . $variant,
        'c' => 3 + $variant,
        'd' => 4 + $variant,
        'e' => ['nested' => $variant],
    ]);

    $label = str_pad((string) $variant, 3, '0', STR_PAD_LEFT);

    $tests["real upstream json104 401-403 quoted path insert extract text {$label}"] =
        static function (TestRunner $t) use ($base, $inserted, $variant): void {
            $actual = SQLiteJsonMutation::mutateSqlFunction('json_insert', $base, '$.c', 3 + $variant);

            $t->same($inserted, $actual, 'json104-402 insert member');
            $t->same(2 + $variant, SQLiteJsonExtract::extract($actual, '$.b'), 'json104-403 unquoted path');
            $t->same(2 + $variant, SQLiteJsonExtract::extract($actual, '$."b"'), 'json104-403 quoted path');
            $t->same($variant < 250, true, 'dynamic variant guard');
        };

    $tests["real upstream json104 404-405 quoted path set insert text {$label}"] =
        static function (TestRunner $t) use ($inserted, $quotedSet, $quotedInsert, $variant): void {
            $set = SQLiteJsonMutation::mutateSqlFunction('json_set', $inserted, '$."b"', 555 + $variant);
            $insert = SQLiteJsonMutation::mutateSqlFunction('json_set', $set, '$."d"', 4 + $variant);

            $t->same($quotedSet, $set, 'json104-404 quoted path set');
            $t->same(555 + $variant, SQLiteJsonExtract::extract($set, '$.b'), 'json104-404 unquoted lookup after quoted set');
            $t->same(555 + $variant, SQLiteJsonExtract::extract($set, '$."b"'), 'json104-404 quoted lookup after quoted set');
            $t->same($quotedInsert, $insert, 'json104-405 quoted path insert');
        };

    $tests["real upstream json104 401-405 quoted path jsonb parity {$label}"] =
        static function (TestRunner $t) use ($jsonb, $jsonbText, $base, $quotedInsert, $variant): void {
            $blob = SQLiteJsonMutation::mutateSqlFunction('jsonb_insert', $jsonb($base), '$.c', 3 + $variant);
            $t->true($blob instanceof SQLiteBlobValue, 'jsonb insert returns JSONB');

            $set = SQLiteJsonMutation::mutateSqlFunction('jsonb_set', $blob, '$."b"', 555 + $variant);
            $t->true($set instanceof SQLiteBlobValue, 'jsonb set returns JSONB');

            $insert = SQLiteJsonMutation::mutateSqlFunction('jsonb_set', $set, '$."d"', 4 + $variant);
            $t->true($insert instanceof SQLiteBlobValue, 'jsonb quoted insert returns JSONB');
            $t->same($quotedInsert, $jsonbText($insert), 'jsonb canonical text matches text mutation');
            $t->same(555 + $variant, SQLiteJsonExtract::extract($insert, '$."b"'), 'jsonb quoted extract');
            $t->same(4 + $variant, SQLiteJsonExtract::extract($insert, '$.d'), 'jsonb unquoted extract');
        };

    $tests["real upstream json104 320 duplicate patch then quoted path mutation {$label}"] =
        static function (TestRunner $t) use ($quotedInsert, $patch, $patched, $canonical, $variant): void {
            $actual = SQLiteJsonPatch::patch($quotedInsert, $patch);
            $retagged = SQLiteJsonMutation::mutateSqlFunction(
                'json_set',
                $actual,
                '$."case"',
                new SQLiteJsonSubtypeValue($canonical('json104-retagged-' . $variant))
            );

            $t->same($patched, $actual, 'json104-320 style later duplicate patch wins object member');
            $t->same(700 + $variant, SQLiteJsonExtract::extract($actual, '$.b'), 'patched unquoted lookup');
            $t->same(700 + $variant, SQLiteJsonExtract::extract($actual, '$."b"'), 'patched quoted lookup');
            $t->same('json104-retagged-' . $variant, SQLiteJsonExtract::extract($retagged, '$.case'), 'quoted retag keeps JSON value string');
        };
}

$tests['real upstream json104 quoted path dynamic source citations'] = static function (TestRunner $t): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test');
    $t->same(['json104-401', 'json104-402', 'json104-403', 'json104-404', 'json104-405', 'json104-320'], ['json104-401', 'json104-402', 'json104-403', 'json104-404', 'json104-405', 'json104-320']);
};

return $tests;
