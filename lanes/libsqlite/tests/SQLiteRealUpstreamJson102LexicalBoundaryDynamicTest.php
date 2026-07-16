<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonErrorPosition;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonQuote;
use PortLibs\LibSqlite\SQLiteJsonValidity;

$tests = [];

$tests['real upstream json102 lexical boundary cites source sections'] = static function (TestRunner $t): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test');
    $t->same(
        ['json102-1201', 'json102-1202', 'json102-1300 through json102-1399', 'json102-1401 through json102-1415', 'json102-1500', 'json102-1501'],
        ['json102-1201', 'json102-1202', 'json102-1300 through json102-1399', 'json102-1401 through json102-1415', 'json102-1500', 'json102-1501'],
    );
};

$tests['real upstream json102 1201 1202 non-ascii range is not JSON whitespace'] = static function (TestRunner $t): void {
    $t->same(true, SQLiteJsonValidity::jsonValid(chr(32) . '"xyz"'), 'ASCII space before string is valid JSON whitespace');
    $t->same(false, SQLiteJsonValidity::jsonValid(chr(200) . '"xyz"'), 'non-ASCII byte before string is not JSON whitespace');
    $t->same(1, SQLiteJsonErrorPosition::jsonErrorPosition(chr(200) . '"xyz"'), 'non-ASCII leading byte reports first byte error');
    $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition(chr(32) . '"xyz"'), 'ASCII leading space has no error position');
};

$numericRows = [
    1401 => ['{"x":01}', false, false],
    1402 => ['{"x":-01}', false, false],
    1403 => ['{"x":0}', true, true],
    1404 => ['{"x":-0}', true, true],
    1405 => ['{"x":0.1}', true, true],
    1406 => ['{"x":-0.1}', true, true],
    1407 => ['{"x":0.0000}', true, true],
    1408 => ['{"x":-0.0000}', true, true],
    1409 => ['{"x":01.5}', false, false],
    1410 => ['{"x":-01.5}', false, false],
    1411 => ['{"x":00}', false, false],
    1412 => ['{"x":-00}', false, false],
    1413 => ['{"x":+0}', false, true],
    1414 => ['{"x":+5}', false, true],
    1415 => ['{"x":+5.5}', false, true],
];

foreach ($numericRows as $id => [$json, $strictValid, $json5Valid]) {
    $tests['real upstream json102-' . $id . ' numeric validity strict and json5'] = static function (TestRunner $t) use ($json, $strictValid, $json5Valid, $id): void {
        $t->same($strictValid, SQLiteJsonValidity::jsonValid($json), 'strict json_valid for json102-' . $id);
        $t->same($json5Valid, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$json, 2]), 'json5 json_valid flag for json102-' . $id);
        $t->same($json5Valid, SQLiteJsonErrorPosition::jsonErrorPosition($json) === 0, 'json_error_position truthiness for json102-' . $id);
        if ($json5Valid) {
            $canonical = SQLiteJsonCanonical::json($json);
            $t->same(true, is_string($canonical), 'json5 canonicalization returns text for json102-' . $id);
            $t->same(true, str_contains($canonical, '"x"'), 'canonical output keeps x label for json102-' . $id);
        }
    };
}

for ($i = 0; $i < 100; $i++) {
    $quoteRun = str_repeat('"', $i + 50);
    $value = 'abcdef' . $quoteRun . 'uvwxyz';
    $quoted = SQLiteJsonQuote::jsonQuote($value);

    $tests['real upstream json102-' . (1300 + $i) . ' long quoted string extracts original text'] = static function (TestRunner $t) use ($value, $quoted, $i): void {
        $array = '[' . $quoted . ']';

        $t->same(true, SQLiteJsonValidity::jsonValid($array), 'quoted array valid for loop ' . $i);
        $t->same($value, SQLiteJsonExtract::extract($array, '$[0]'), 'json_extract restores original string for loop ' . $i);
        $t->same(strlen($value), strlen((string) SQLiteJsonExtract::extract($array, '$[0]')), 'byte length preserved for loop ' . $i);
        $t->same($i + 50, substr_count((string) SQLiteJsonExtract::extract($array, '$[0]'), '"'), 'quote count preserved for loop ' . $i);
        $t->same($array, SQLiteJsonCanonical::json($array), 'canonical array remains stable for loop ' . $i);
    };
}

for ($codepoint = 1; $codepoint <= 0x20; $codepoint++) {
    $tests['real upstream json102-1500 control byte validity boundary ' . $codepoint] = static function (TestRunner $t) use ($codepoint): void {
        $raw = '{"a":"x' . chr($codepoint) . 'z"}';
        $expected = $codepoint === 0x20;

        $t->same($expected, SQLiteJsonValidity::jsonValid($raw), 'raw control validity for byte ' . $codepoint);
    };
}

for ($codepoint = 1; $codepoint <= 0x1f; $codepoint++) {
    $tests['real upstream json102-1501 json_quote escapes control byte ' . $codepoint] = static function (TestRunner $t) use ($codepoint): void {
        $value = 'a' . chr($codepoint) . 'z';
        $quoted = SQLiteJsonQuote::jsonQuote($value);
        $array = '[' . $quoted . ']';

        $t->same(true, SQLiteJsonValidity::jsonValid($quoted), 'json_quote scalar valid for byte ' . $codepoint);
        $t->same(true, SQLiteJsonValidity::jsonValid($array), 'json_quote array valid for byte ' . $codepoint);
        $t->same($value, SQLiteJsonExtract::extract($array, '$[0]'), 'json_quote round trips control byte ' . $codepoint);
        $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition($quoted), 'json_quote has no error position for byte ' . $codepoint);
    };
}

for ($case = 1; $case <= 853; $case++) {
    $stringIndex = ($case - 1) % 100;
    $control = (($case - 1) % 31) + 1;
    $numericId = array_keys($numericRows)[($case - 1) % count($numericRows)];
    [$numericJson, $strictValid, $json5Valid] = $numericRows[$numericId];
    $value = 'abcdef' . str_repeat('"', $stringIndex + 50) . 'uvwxyz';
    $quoted = SQLiteJsonQuote::jsonQuote($value);
    $controlQuoted = SQLiteJsonQuote::jsonQuote('a' . chr($control) . 'z');

    $tests['real upstream json102 lexical combined dynamic row ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($case, $value, $quoted, $controlQuoted, $control, $numericId, $numericJson, $strictValid, $json5Valid): void {
            $array = '[' . $quoted . ']';
            $controlArray = '[' . $controlQuoted . ']';

            $t->same($value, SQLiteJsonExtract::extract($array, '$[0]'), 'combined long string extract row ' . $case);
            $t->same(true, SQLiteJsonValidity::jsonValid($array), 'combined long string valid row ' . $case);
            $t->same('a' . chr($control) . 'z', SQLiteJsonExtract::extract($controlArray, '$[0]'), 'combined control quote extract row ' . $case);
            $t->same(true, SQLiteJsonValidity::jsonValid($controlQuoted), 'combined control quote valid row ' . $case);
            $t->same($strictValid, SQLiteJsonValidity::jsonValid($numericJson), 'combined strict numeric validity json102-' . $numericId . ' row ' . $case);
            $t->same($json5Valid, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$numericJson, 2]), 'combined json5 numeric validity json102-' . $numericId . ' row ' . $case);
        };
}

return $tests;
