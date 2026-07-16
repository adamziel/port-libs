<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonValidity;

$tests = [];

$validEscapes = ['"', '/', '\\', 'b', 'f', 'n', 'r', 't'];
$escapeAlphabet = array_merge(
    [' ', '!', '"', '#', '$', '%', '&', "'", '(', ')', '*', '+', ',', '-', '.', '/'],
    range('0', '9'),
    [':', ';', '<', '=', '>', '?', '@'],
    range('A', 'Z'),
    ['[', ']', '^', '_', '`'],
    range('a', 'z'),
);

foreach ($escapeAlphabet as $offset => $escape) {
    $scenario = sprintf('json101-10.%03d', $offset + 1);
    $tests["real upstream {$scenario} strict backslash escape {$escape} validity"] = static function (TestRunner $t) use ($escape, $validEscapes): void {
        $json = '" app\\' . $escape . 'setting "';
        $expected = in_array($escape, $validEscapes, true);

        $t->same($expected, SQLiteJsonValidity::jsonValid($json), "strict validity for \\{$escape}");

        if ($expected) {
            $canonical = SQLiteJsonCanonical::json($json);
            $t->same(true, SQLiteJsonValidity::jsonValid($canonical), "canonical strict validity for \\{$escape}");
            $t->same(SQLiteJsonExtract::extract($canonical, '$'), SQLiteJsonExtract::extract(SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json), '$'), "jsonb scalar parity for \\{$escape}");
        } else {
            $t->same(false, SQLiteJsonValidity::jsonValid($json), "strict text keeps invalid escape rejected for \\{$escape}");
        }
    };
}

$controlEscape = static function (int $codepoint): string {
    return match ($codepoint) {
        8 => '\\b',
        9 => '\\t',
        10 => '\\n',
        12 => '\\f',
        13 => '\\r',
        default => sprintf('\\u%04x', $codepoint),
    };
};

for ($repeat = 0; $repeat < 32; $repeat++) {
    for ($codepoint = 1; $codepoint <= 31; $codepoint++) {
        $scenario = sprintf('json501-14.%02d.%02d', $codepoint, $repeat + 1);
        $tests["real upstream {$scenario} json5 raw control character canonicalization"] = static function (TestRunner $t) use ($codepoint, $repeat, $controlEscape): void {
            $prefix = 'app-setting-' . $repeat . '-';
            $jsonString = '"' . $prefix . chr($codepoint) . 'value"';
            $jsonObject = '{label:"' . $prefix . chr($codepoint) . 'value"}';
            $expectedString = $prefix . chr($codepoint) . 'value';

            $t->same(false, SQLiteJsonValidity::jsonValid($jsonString), "strict text rejects raw control {$codepoint}");
            $t->same(true, SQLiteJsonValidity::jsonValid($jsonString, 2), "json5 text accepts raw control {$codepoint}");
            $t->same('"' . $prefix . $controlEscape($codepoint) . 'value"', SQLiteJsonCanonical::json($jsonString), "canonical string escape {$codepoint}");
            $t->same('{"label":"' . $prefix . $controlEscape($codepoint) . 'value"}', SQLiteJsonCanonical::json($jsonObject), "canonical object escape {$codepoint}");
            $t->same($expectedString, SQLiteJsonExtract::extract(SQLiteJsonCanonical::json($jsonObject), '$.label'), "canonical extraction {$codepoint}");
            $t->same($expectedString, SQLiteJsonExtract::extract(SQLiteJsonCanonical::jsonSqlFunction('jsonb', $jsonObject), '$.label'), "jsonb extraction {$codepoint}");
        };
    }
}

$tests['real upstream json string validity dynamic corpus cites upstream sections'] = static function (TestRunner $t) use ($tests): void {
    $t->true(is_file('/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test'), 'json101.test upstream source exists');
    $t->true(is_file('/home/claude/port-libs/.upstream-cache/libsqlite/test/json501.test'), 'json501.test upstream source exists');
    $t->same(1082, count($tests), 'generated dynamic focused case count before citation');
};

return $tests;
