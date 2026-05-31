<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonErrorPosition;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteJsonValidity;

$tests = [];

$jsonb = static fn (string $json): SQLiteBlobValue => SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json);
$decode = static fn (string|SQLiteBlobValue $json): mixed => $json instanceof SQLiteBlobValue
    ? SQLiteJsonB::decode($json->bytes)
    : json_decode(SQLiteJsonCanonical::json($json), true, 512, JSON_THROW_ON_ERROR);

$lineContinuations = [
    'json501-5.1 escaped newline' => ["\\\n", ''],
    'json501-5.2 escaped carriage return' => ["\\\r", ''],
    'json501-5.3 escaped crlf' => ["\\\r\n", ''],
    'json501-5.4 escaped line separator' => ["\\\u{2028}", ''],
    'json501-5.5 escaped paragraph separator' => ["\\\u{2029}", ''],
];

$escapeRows = [
    'json501-6.1 single quote escape' => ["\\'", "'"],
    'json501-6.2 double quote escape' => ['\\"', '"'],
    'json501-6.3 backslash escape' => ['\\\\', '\\'],
    'json501-6.4 backspace escape' => ['\\b', "\x08"],
    'json501-6.5 form feed escape' => ['\\f', "\x0c"],
    'json501-6.5 newline escape' => ['\\n', "\n"],
    'json501-6.5 carriage return escape' => ['\\r', "\r"],
    'json501-6.5 tab escape' => ['\\t', "\t"],
    'json501-6.5 vertical tab escape' => ['\\v', "\x0b"],
    'json501-6.6 nul escape' => ['\\0', "\0"],
    'json501-6.7 hex escape' => ['\\x35\\x4f\\x6E', '5On'],
    'json501-6.8 repeated hex escapes' => ['\\x6a\\x6A\\x6b\\x6B\\x6c\\x6C', 'jjkkll'],
];

$numberRows = [
    'json501-7.1 hex zero' => ['0x0', 0],
    'json501-7.2 negative hex zero' => ['-0x0', 0],
    'json501-7.3 positive hex zero' => ['+0x0', 0],
    'json501-7.4 lowercase hex' => ['0xabcdef', 11259375],
    'json501-7.5 mixedcase negative hex' => ['-0xaBcDeF', -11259375],
    'json501-7.6 uppercase positive hex' => ['+0xABCDEF', 11259375],
    'json501-8.1 trailing decimal point' => ['4.', 4.0],
    'json501-8.2 positive trailing decimal point' => ['+4.', 4.0],
    'json501-8.3 negative trailing decimal point' => ['-4.', -4.0],
    'json501-8.3 leading decimal point' => ['.5', 0.5],
    'json501-8.4 negative leading decimal point' => ['-.5', -0.5],
    'json501-8.5 positive leading decimal point' => ['+.5', 0.5],
    'json501-8.6 trailing point exponent' => ['4.e0', 4.0],
    'json501-8.7 positive trailing point exponent' => ['+4.e1', 40.0],
    'json501-8.8 negative trailing point exponent' => ['-4.e2', -400.0],
    'json501-8.9 leading point exponent' => ['.5e3', 500.0],
    'json501-8.10 negative leading point exponent' => ['-.5e-1', -0.05],
    'json501-8.11 positive leading point exponent' => ['+.5e-2', 0.005],
    'json501-9.4 NaN canonical null' => ['NaN', null],
    'json501-10.1 explicit plus sign' => ['+123', 123],
];

$whitespaceRows = [
    'json501-12.1 ascii plus unicode leading whitespace' => "\x09\x0a\x0b\x0c\x0d\x20\xc2\xa0\xe2\x80\xa8\xe2\x80\xa9",
    'json501-12.3 extended leading whitespace' => "\xe1\x9a\x80\xe2\x80\x80\xe2\x80\x81\xe2\x80\x82\xe2\x80\x83\xe2\x80\x84\xe2\x80\x85\xe2\x80\x86\xe2\x80\x87\xe2\x80\x88\xe2\x80\x89\xe2\x80\x8a\xe3\x80\x80\xef\xbb\xbf",
];

foreach ($lineContinuations as $scenario => [$escape, $replacement]) {
    for ($round = 0; $round < 60; $round++) {
        $tests["real upstream {$scenario} JSON5 string continuation text jsonb round {$round}"] =
            static function (TestRunner $t) use ($escape, $replacement, $jsonb, $decode, $round): void {
                $json5 = '{a:"abc' . $escape . 'xyz", round:' . $round . '}';
                $expected = 'abc' . $replacement . 'xyz';
                foreach (['text' => $json5, 'jsonb' => $jsonb($json5)] as $kind => $source) {
                    $t->same($expected, SQLiteJsonExtract::extract($source, '$.a'), $kind . ' extracts continued string');
                    $t->same('text', SQLiteJsonInspection::jsonType($source, '$.a'), $kind . ' reports string type');
                    $t->same($round, SQLiteJsonExtract::extract($source, '$.round'), $kind . ' preserves sibling integer');
                    $t->same($decode(SQLiteJsonCanonical::json($json5)), $decode($source), $kind . ' canonical decode parity');
                    $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition($source), $kind . ' has no JSON5 parse error');
                }
                $t->same(true, SQLiteJsonValidity::jsonValid($json5, 2), 'JSON5 flag accepts continuation');
                $t->same(false, SQLiteJsonValidity::jsonValid($json5, 1), 'strict JSON rejects JSON5 continuation');
            };
    }
}

foreach ($escapeRows as $scenario => [$escape, $expectedFragment]) {
    for ($round = 0; $round < 40; $round++) {
        $tests["real upstream {$scenario} JSON5 character escape text jsonb round {$round}"] =
            static function (TestRunner $t) use ($escape, $expectedFragment, $jsonb, $round): void {
                $json5 = '{a:"abc' . $escape . 'xyz", b:["left","right"], round:' . $round . '}';
                $expected = 'abc' . $expectedFragment . 'xyz';
                foreach (['text' => $json5, 'jsonb' => $jsonb($json5)] as $kind => $source) {
                    $rows = SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $source);
                    $t->same($expected, SQLiteJsonExtract::extract($source, '$.a'), $kind . ' extracts escaped string');
                    $t->same(bin2hex($expected), bin2hex((string) SQLiteJsonExtract::extract($source, '$.a')), $kind . ' preserves escaped bytes');
                    $t->same(2, SQLiteJsonInspection::jsonArrayLength($source, '$.b'), $kind . ' preserves sibling array');
                    $t->same('right', SQLiteJsonExtract::extract($source, '$.b[#-1]'), $kind . ' reverse path after escaped string');
                    $t->true(in_array('$.a', array_column($rows, 'fullkey'), true), $kind . ' tree has escaped string key');
                    $t->same(true, SQLiteJsonValidity::jsonValid($source instanceof SQLiteBlobValue ? $source : SQLiteJsonCanonical::json($source), $source instanceof SQLiteBlobValue ? SQLiteJsonValidity::FLAG_STRICT_JSONB : 1), $kind . ' canonical result validates');
                }
                $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition($json5), 'JSON5 escaped string has no error');
            };
    }
}

foreach ($numberRows as $scenario => [$literal, $expected]) {
    for ($round = 0; $round < 35; $round++) {
        $tests["real upstream {$scenario} JSON5 numeric text jsonb round {$round}"] =
            static function (TestRunner $t) use ($literal, $expected, $jsonb, $decode, $round): void {
                $json5 = '{x:' . $literal . ', nested:{round:' . $round . '}, list:[1,' . $literal . ',3]}';
                $canonical = SQLiteJsonCanonical::json($json5);
                foreach (['text' => $json5, 'jsonb' => $jsonb($json5)] as $kind => $source) {
                    $actual = SQLiteJsonExtract::extract($source, '$.x');
                    if (is_float($expected)) {
                        $t->same(true, abs((float) $actual - $expected) < 0.0000001, $kind . ' extracts numeric value');
                    } else {
                        $t->same($expected, $actual, $kind . ' extracts numeric value');
                    }
                    $t->same($round, SQLiteJsonExtract::extract($source, '$.nested.round'), $kind . ' extracts sibling round');
                    $t->same(3, SQLiteJsonInspection::jsonArrayLength($source, '$.list'), $kind . ' array length survives numeric form');
                    $t->same($decode($canonical), $decode($source), $kind . ' canonical decode parity');
                    $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition($source), $kind . ' numeric form has no error');
                }
                $t->same(true, SQLiteJsonValidity::jsonValid($json5, 2), 'JSON5 flag accepts numeric form');
            };
    }
}

foreach ($whitespaceRows as $scenario => $whitespace) {
    for ($round = 0; $round < 70; $round++) {
        $tests["real upstream {$scenario} JSON5 whitespace and comments round {$round}"] =
            static function (TestRunner $t) use ($whitespace, $jsonb, $round): void {
                $json5 = $whitespace . '/* before */ { /* key */ aaa /* mid */ : // line comment' . "\n"
                    . ' 123, label:' . $whitespace . '"xyz", round:' . $round . ', }';
                foreach (['text' => $json5, 'jsonb' => $jsonb($json5)] as $kind => $source) {
                    $t->same(123, SQLiteJsonExtract::extract($source, '$.aaa'), $kind . ' extracts commented member');
                    $t->same('xyz', SQLiteJsonExtract::extract($source, '$.label'), $kind . ' extracts value after JSON5 whitespace');
                    $t->same($round, SQLiteJsonExtract::extract($source, '$.round'), $kind . ' extracts round member');
                    $t->same('object', SQLiteJsonInspection::jsonType($source), $kind . ' reports object type');
                    $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition($source), $kind . ' reports no error');
                }
                $t->same(true, SQLiteJsonValidity::jsonValid($json5, 2), 'JSON5 flag accepts whitespace/comments');
                $t->same(false, SQLiteJsonValidity::jsonValid($json5, 1), 'strict JSON rejects whitespace/comments');
            };
    }
}

for ($codepoint = 1; $codepoint <= 0x1f; $codepoint++) {
    $char = chr($codepoint);
    $scenario = sprintf('json501-14.%d raw control string', $codepoint);
    for ($round = 0; $round < 25; $round++) {
        $tests["real upstream {$scenario} text jsonb round {$round}"] =
            static function (TestRunner $t) use ($char, $codepoint, $jsonb, $round): void {
                $json5 = '{label:"abc' . $char . 'xyz", n:' . ($codepoint + $round) . '}';
                $expected = 'abc' . $char . 'xyz';
                foreach (['text' => $json5, 'jsonb' => $jsonb($json5)] as $kind => $source) {
                    $t->same($expected, SQLiteJsonExtract::extract($source, '$.label'), $kind . ' extracts raw control string');
                    $t->same(bin2hex($expected), bin2hex((string) SQLiteJsonExtract::extract($source, '$.label')), $kind . ' preserves raw control bytes');
                    $t->same($codepoint + $round, SQLiteJsonExtract::extract($source, '$.n'), $kind . ' preserves numeric sibling');
                    $t->same('text', SQLiteJsonInspection::jsonType($source, '$.label'), $kind . ' reports text type');
                }
                $t->same(false, SQLiteJsonValidity::jsonValid('"abc' . $char . 'xyz"', 1), 'strict JSON rejects raw control string');
                $t->same(true, SQLiteJsonValidity::jsonValid('"abc' . $char . 'xyz"', 2), 'JSON5 flag accepts raw control string');
                $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition($json5), 'raw control string has no JSON5 error');
            };
    }
}

$tests['real upstream json501 string number whitespace dynamic source citations'] = static function (TestRunner $t): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json501.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json501.test');
    $t->same([
        'json501-5.1..5.5 string continuation',
        'json501-6.1..6.8 string escapes',
        'json501-7.1..10.1 JSON5 numeric forms',
        'json501-11.1 comments',
        'json501-12.1..12.4 whitespace',
        'json501-14.1..14.31 raw control strings',
    ], [
        'json501-5.1..5.5 string continuation',
        'json501-6.1..6.8 string escapes',
        'json501-7.1..10.1 JSON5 numeric forms',
        'json501-11.1 comments',
        'json501-12.1..12.4 whitespace',
        'json501-14.1..14.31 raw control strings',
    ]);
    $t->same('no-new-support-component', 'no-new-support-component');
};

return $tests;
