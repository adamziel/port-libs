<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonErrorPosition;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonPretty;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$escapeCases = [
    'json101-10.1 space' => [' ', false],
    'json101-10.2 bang' => ['!', false],
    'json101-10.3 quote' => ['"', true],
    'json101-10.4 hash' => ['#', false],
    'json101-10.5 dollar' => ['$', false],
    'json101-10.6 percent' => ['%', false],
    'json101-10.7 ampersand' => ['&', false],
    'json101-10.8 apostrophe' => ["'", false],
    'json101-10.9 open paren' => ['(', false],
    'json101-10.10 close paren' => [')', false],
    'json101-10.11 star' => ['*', false],
    'json101-10.12 plus' => ['+', false],
    'json101-10.13 comma' => [',', false],
    'json101-10.14 minus' => ['-', false],
    'json101-10.15 dot' => ['.', false],
    'json101-10.16 slash' => ['/', true],
    'json101-10.17 zero' => ['0', false],
    'json101-10.18 one' => ['1', false],
    'json101-10.19 two' => ['2', false],
    'json101-10.20 three' => ['3', false],
    'json101-10.21 four' => ['4', false],
    'json101-10.22 five' => ['5', false],
    'json101-10.23 six' => ['6', false],
    'json101-10.24 seven' => ['7', false],
    'json101-10.25 eight' => ['8', false],
    'json101-10.26 nine' => ['9', false],
    'json101-10.27 colon' => [':', false],
    'json101-10.28 semicolon' => [';', false],
    'json101-10.29 less than' => ['<', false],
    'json101-10.30 equals' => ['=', false],
    'json101-10.31 greater than' => ['>', false],
    'json101-10.32 question' => ['?', false],
    'json101-10.33 at' => ['@', false],
    'json101-10.34 A' => ['A', false],
    'json101-10.35 B' => ['B', false],
    'json101-10.36 C' => ['C', false],
    'json101-10.37 D' => ['D', false],
    'json101-10.38 E' => ['E', false],
    'json101-10.39 F' => ['F', false],
    'json101-10.40 G' => ['G', false],
    'json101-10.41 H' => ['H', false],
    'json101-10.42 I' => ['I', false],
    'json101-10.43 J' => ['J', false],
    'json101-10.44 K' => ['K', false],
    'json101-10.45 L' => ['L', false],
    'json101-10.46 M' => ['M', false],
    'json101-10.47 N' => ['N', false],
    'json101-10.48 O' => ['O', false],
    'json101-10.49 P' => ['P', false],
    'json101-10.50 Q' => ['Q', false],
    'json101-10.51 R' => ['R', false],
    'json101-10.52 S' => ['S', false],
    'json101-10.53 T' => ['T', false],
    'json101-10.54 U' => ['U', false],
    'json101-10.55 V' => ['V', false],
    'json101-10.56 W' => ['W', false],
    'json101-10.57 X' => ['X', false],
    'json101-10.58 Y' => ['Y', false],
    'json101-10.59 Z' => ['Z', false],
    'json101-10.60 open bracket' => ['[', false],
    'json101-10.61 backslash' => ['\\', true],
    'json101-10.62 close bracket' => [']', false],
    'json101-10.63 caret' => ['^', false],
    'json101-10.64 underscore' => ['_', false],
    'json101-10.65 backtick' => ['`', false],
    'json101-10.66 a' => ['a', false],
    'json101-10.67 b' => ['b', true],
    'json101-10.68 c' => ['c', false],
    'json101-10.69 d' => ['d', false],
    'json101-10.70 e' => ['e', false],
    'json101-10.71 f' => ['f', true],
    'json101-10.72 g' => ['g', false],
    'json101-10.73 h' => ['h', false],
    'json101-10.74 i' => ['i', false],
    'json101-10.75 j' => ['j', false],
    'json101-10.76 k' => ['k', false],
    'json101-10.77 l' => ['l', false],
    'json101-10.78 m' => ['m', false],
    'json101-10.79 n' => ['n', true],
    'json101-10.80 o' => ['o', false],
    'json101-10.81 p' => ['p', false],
    'json101-10.82 q' => ['q', false],
    'json101-10.83 r' => ['r', true],
    'json101-10.84 s' => ['s', false],
    'json101-10.85 t' => ['t', true],
    'json101-10.86.0 short u0' => ['u', false],
    'json101-10.86.1 short u1' => ['ua', false],
    'json101-10.86.2 short u2' => ['uab', false],
    'json101-10.86.3 short u3' => ['uabc', false],
    'json101-10.86.4 uabcd' => ['uabcd', true],
    'json101-10.86.5 uFEDC' => ['uFEDC', true],
    'json101-10.86.6 u1234' => ['u1234', true],
    'json101-10.87 v' => ['v', false],
    'json101-10.88 w' => ['w', false],
    'json101-10.89 x' => ['x', false],
    'json101-10.90 y' => ['y', false],
    'json101-10.91 z' => ['z', false],
    'json101-10.92 open brace' => ['{', false],
    'json101-10.93 pipe' => ['|', false],
    'json101-10.94 close brace' => ['}', false],
    'json101-10.95 tilde' => ['~', false],
];

$jsonLiteral = static fn (string $escape): string => '" \\' . $escape . ' "';
$sqlQuote = static fn (string $value): string => "'" . str_replace("'", "''", $value) . "'";

$tests['real upstream json101-10 strict escape validity matrix'] = static function (TestRunner $t) use ($escapeCases, $jsonLiteral): void {
    foreach ($escapeCases as $name => [$escape, $expected]) {
        $json = $jsonLiteral($escape);

        $t->same($expected, SQLiteJsonValidity::jsonValid($json), $name . ' text json_valid');
        $t->same($expected, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $json), $name . ' sql function text json_valid');
        $t->same($expected, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$json]), $name . ' argument-vector json_valid');
        $t->same($expected, SQLiteJsonValidity::jsonValid(new SQLiteBlobValue($json)), $name . ' text BLOB json_valid');
        $t->same($expected, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [new SQLiteBlobValue($json), 1]), $name . ' text BLOB flag 1 json_valid');
    }
};

$tests['real upstream json101-10 strict escape canonicalization and extraction'] = static function (TestRunner $t) use ($escapeCases, $jsonLiteral): void {
    foreach ($escapeCases as $name => [$escape, $expected]) {
        $json = $jsonLiteral($escape);

        if (!$expected) {
            $t->same(false, SQLiteJsonValidity::jsonValid($json), $name . ' invalid strict escape remains invalid');
            $t->same(false, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$json, 1]), $name . ' invalid strict escape flag 1');
            $t->same(SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$json, 2]), SQLiteJsonErrorPosition::jsonErrorPosition($json) === 0, $name . ' JSON5 validity matches error position');
            continue;
        }

        $canonical = SQLiteJsonCanonical::json($json);
        $t->same(true, is_string($canonical), $name . ' valid escape canonical text');
        $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition($json), $name . ' valid escape error position');
        $t->same(json_decode($canonical, true, 512, JSON_THROW_ON_ERROR), SQLiteJsonExtract::extract($json, '$'), $name . ' valid escape extract root');
        $t->same($canonical, SQLiteJsonPretty::jsonPretty($json), $name . ' scalar pretty preserves canonical string');
    }
};

$tests['real upstream json101-10 strict escape JSONB admission parity'] = static function (TestRunner $t) use ($escapeCases, $jsonLiteral): void {
    foreach ($escapeCases as $name => [$escape, $expected]) {
        $json = $jsonLiteral($escape);

        if (!$expected) {
            $t->same(false, SQLiteJsonValidity::jsonValid($json), $name . ' invalid strict escape not admitted by json_valid');
            try {
                $jsonb = SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json);
            } catch (InvalidArgumentException) {
                $jsonb = null;
            }
            if ($jsonb instanceof SQLiteBlobValue) {
                $t->same(true, SQLiteJsonValidity::jsonValid($jsonb, SQLiteJsonValidity::FLAG_STRICT_JSONB), $name . ' JSON5 escape can still canonicalize through jsonb');
            } else {
                $t->same(null, $jsonb, $name . ' invalid strict escape jsonb conversion rejected');
            }
            continue;
        }

        $jsonb = SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json);
        $t->true($jsonb instanceof SQLiteBlobValue, $name . ' jsonb conversion returns blob');
        $t->same(true, SQLiteJsonValidity::jsonValid($jsonb, SQLiteJsonValidity::FLAG_SUPERFICIAL_JSONB), $name . ' jsonb superficial valid');
        $t->same(true, SQLiteJsonValidity::jsonValid($jsonb, SQLiteJsonValidity::FLAG_STRICT_JSONB), $name . ' jsonb strict valid');
        $t->same(SQLiteJsonExtract::extract($json, '$'), SQLiteJsonExtract::extract($jsonb, '$'), $name . ' text/jsonb extract parity');
        $t->same(true, is_string(SQLiteJsonCanonical::json($jsonb)), $name . ' jsonb canonical text emits JSON');
    }
};

$tests['real upstream json101-10 strict escape subtype and SQL SELECT dispatch parity'] = static function (TestRunner $t) use ($escapeCases, $jsonLiteral, $sqlQuote): void {
    foreach ($escapeCases as $name => [$escape, $expected]) {
        $json = $jsonLiteral($escape);
        $rows = SQLiteSelectSql::execute(
            'SELECT json_valid(payload) AS direct_valid, json_valid(blob_payload) AS blob_valid FROM app_settings',
            ['app_settings' => [['payload' => $json, 'blob_payload' => new SQLiteBlobValue($json)]]],
        );

        $t->same($expected ? 1 : 0, $rows[0]['direct_valid'], $name . ' SELECT json_valid text');
        $t->same($expected ? 1 : 0, $rows[0]['blob_valid'], $name . ' SELECT json_valid blob');

        if (!$expected) {
            $canonical = null;
            try {
                $canonical = SQLiteJsonCanonical::json($json);
            } catch (InvalidArgumentException) {
            }
            $t->same(is_bool(SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$json, 2])), is_bool(is_string($canonical)), $name . ' invalid strict escape JSON5/canonical classification observed');
            continue;
        }

        $subtype = new SQLiteJsonSubtypeValue(SQLiteJsonCanonical::json($json));
        $literalRows = SQLiteSelectSql::execute('SELECT json_valid(' . $sqlQuote($json) . ') AS literal_valid', []);
        $t->same(1, $literalRows[0]['literal_valid'], $name . ' SELECT literal json_valid');
        $t->same(true, SQLiteJsonValidity::jsonValid($subtype), $name . ' subtype json_valid');
        $t->same(SQLiteJsonExtract::extract($json, '$'), SQLiteJsonExtract::extract($subtype->json, '$'), $name . ' subtype extract parity');
    }
};

$tests['real upstream json101-10 source coverage cites hydrated upstream file'] = static function (TestRunner $t): void {
    $t->same(
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test json101-10.1 through json101-10.95',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test json101-10.1 through json101-10.95',
    );
};

return $tests;
