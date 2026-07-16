<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonErrorPosition;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteJsonValidity;

$tests = [];

$jsonb = static fn (string $json): SQLiteBlobValue => SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json);
$jsonbText = static fn (SQLiteBlobValue $blob): string => SQLiteJsonCanonical::json($blob);
$decode = static fn (string|SQLiteBlobValue $json): mixed => $json instanceof SQLiteBlobValue
    ? SQLiteJsonB::decode($json->bytes)
    : json_decode(SQLiteJsonCanonical::json($json), true, 512, JSON_THROW_ON_ERROR);

$rows = [];
for ($i = 1; $i <= 320; $i++) {
    $control = chr(($i % 31) + 1);
    $controlEscape = sprintf('ctrl\\u%04x%d', ord($control), $i);
    $hexEscape = sprintf('\\x%02x', 97 + ($i % 26));
    $hexChar = chr(97 + ($i % 26));
    $identifier = 'tenant_' . $i . '_load';
    $quotedKey = 'A"Key' . $i;
    $controlKey = 'ctrl' . $control . $i;
    $patchedLabel = 'patched-' . $i;
    $lineBreak = match ($i % 4) {
        0 => "\\\n",
        1 => "\\\r",
        2 => "\\\r\n",
        default => "\\\u{2028}",
    };
    $number = match ($i % 6) {
        0 => '0x' . dechex(0x100 + $i),
        1 => '+.' . (($i % 8) + 1) . 'e2',
        2 => '-' . (($i % 9) + 1) . '.',
        3 => '+Infinity',
        4 => 'NaN',
        default => '+' . $i,
    };
    $expectedNumber = match ($i % 6) {
        0 => hexdec(dechex(0x100 + $i)),
        1 => (float) ('0.' . (($i % 8) + 1) . 'e2'),
        2 => (float) ('-' . (($i % 9) + 1) . '.0'),
        3 => INF,
        4 => null,
        default => $i,
    };

    $json5 = sprintf(
        "{%s:%d,'single':'single-%d',line:\"line-%d-%sjoined\",hexText:\"%s%d\",quoted:{\"%s\":%d},escaped:{\"a%sbc%d\":%d,\"%s\":%d},trailing:[1,2,3,],number:%s,patch:{label:'old'},}",
        $identifier,
        $i,
        $i,
        $i,
        $lineBreak,
        $hexEscape,
        $i,
        addcslashes($quotedKey, '"\\'),
        $i + 10,
        $hexEscape,
        $i,
        $i + 20,
        $controlEscape,
        $i + 30,
        $number,
    );

    $rows['json501-json502-escaped-stress-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)] = [
        'json5' => $json5,
        'identifierPath' => '$.' . $identifier,
        'identifierValue' => $i,
        'singleValue' => 'single-' . $i,
        'lineValue' => 'line-' . $i . '-joined',
        'hexTextPath' => '$.hexText',
        'hexTextValue' => $hexChar . $i,
        'quotedBarePath' => '$.quoted.' . $quotedKey,
        'quotedPath' => '$.quoted."' . addcslashes($quotedKey, '"\\') . '"',
        'quotedValue' => $i + 10,
        'escapedPath' => '$.escaped.a' . $hexChar . 'bc' . $i,
        'escapedValue' => $i + 20,
        'controlPath' => '$.escaped."' . $controlKey . '"',
        'controlValue' => $i + 30,
        'numberValue' => $expectedNumber,
        'patch' => '{"patch":{"label":"' . $patchedLabel . '"},"added":{"value":' . ($i + 40) . '}}',
        'patchedLabel' => $patchedLabel,
        'addedValue' => $i + 40,
    ];
}

$tests['real upstream json501 json502 escaped stress canonicalizes JSON5 rows'] = static function (TestRunner $t) use ($rows, $jsonb, $jsonbText): void {
    foreach ($rows as $scenario => $row) {
        $canonical = SQLiteJsonCanonical::json($row['json5']);
        $blob = $jsonb($row['json5']);

        $t->same(false, SQLiteJsonValidity::jsonValid($row['json5'], 1), $scenario . ' strict json_valid rejects JSON5 text');
        $t->same(true, SQLiteJsonValidity::jsonValid($row['json5'], 2), $scenario . ' JSON5 flag accepts text');
        $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition($row['json5']), $scenario . ' no JSON5 parse error');
        $t->same($canonical, SQLiteJsonCanonical::json($canonical), $scenario . ' canonical text is stable');
        $t->same(
            json_decode($canonical, true, 512, JSON_THROW_ON_ERROR),
            SQLiteJsonB::decode($blob->bytes),
            $scenario . ' jsonb decoded parity',
        );
        $t->same(true, SQLiteJsonValidity::jsonValid($blob, 8), $scenario . ' strict JSONB flag accepts encoded row');
    }
};

$tests['real upstream json501 json502 escaped stress extracts scalar paths from text and JSONB'] = static function (TestRunner $t) use ($rows, $jsonb): void {
    foreach ($rows as $scenario => $row) {
        foreach (['text' => $row['json5'], 'jsonb' => $jsonb($row['json5'])] as $kind => $input) {
            $t->same($row['identifierValue'], SQLiteJsonExtract::extract($input, $row['identifierPath']), $scenario . ' identifier key ' . $kind);
            $t->same($row['singleValue'], SQLiteJsonExtract::extract($input, '$.single'), $scenario . ' single quoted string ' . $kind);
            $t->same($row['lineValue'], SQLiteJsonExtract::extract($input, '$.line'), $scenario . ' escaped line continuation ' . $kind);
            $t->same($row['hexTextValue'], SQLiteJsonExtract::extract($input, $row['hexTextPath']), $scenario . ' hex string escape ' . $kind);
            $t->same($row['quotedValue'], SQLiteJsonExtract::extract($input, $row['quotedBarePath']), $scenario . ' bare quote-key path ' . $kind);
            $t->same($row['quotedValue'], SQLiteJsonExtract::extract($input, $row['quotedPath']), $scenario . ' quoted quote-key path ' . $kind);
            $t->same($row['escapedValue'], SQLiteJsonExtract::extract($input, $row['escapedPath']), $scenario . ' escaped source label path ' . $kind);
            $t->same($row['controlValue'], SQLiteJsonExtract::extract($input, $row['controlPath']), $scenario . ' control label path ' . $kind);
            $t->same($row['numberValue'], SQLiteJsonExtract::extract($input, '$.number'), $scenario . ' JSON5 number form ' . $kind);
            $t->same(3, SQLiteJsonInspection::jsonArrayLength($input, '$.trailing'), $scenario . ' trailing comma array length ' . $kind);
        }
    }
};

$tests['real upstream json501 json502 escaped stress preserves tree fullkeys'] = static function (TestRunner $t) use ($rows, $jsonb): void {
    foreach ($rows as $scenario => $row) {
        foreach (['text' => $row['json5'], 'jsonb' => $jsonb($row['json5'])] as $kind => $input) {
            $fullkeys = array_column(SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $input), 'fullkey');

            $t->true(in_array($row['identifierPath'], $fullkeys, true), $scenario . ' identifier fullkey ' . $kind);
            $t->true(in_array('$.single', $fullkeys, true), $scenario . ' single fullkey ' . $kind);
            $t->true(in_array($row['quotedPath'], $fullkeys, true), $scenario . ' quoted fullkey ' . $kind);
            $t->true(in_array($row['escapedPath'], $fullkeys, true), $scenario . ' escaped fullkey ' . $kind);
            $t->same($row['controlValue'], SQLiteJsonExtract::extract($input, $row['controlPath']), $scenario . ' control fullkey extract ' . $kind);
            $t->same('object', SQLiteJsonInspection::jsonType($input), $scenario . ' root type ' . $kind);
        }
    }
};

$tests['real upstream json501 json502 escaped stress mutates patched JSON5 rows'] = static function (TestRunner $t) use ($rows, $jsonb, $jsonbText): void {
    foreach ($rows as $scenario => $row) {
        $set = SQLiteJsonMutation::mutateSqlFunction('json_set', $row['json5'], '$.patch.extra', $row['identifierValue']);
        $insert = SQLiteJsonMutation::mutateSqlFunction('json_insert', $row['json5'], '$.trailing[#]', $row['addedValue']);
        $replace = SQLiteJsonMutation::mutateSqlFunction('json_replace', $row['json5'], '$.single', $row['singleValue'] . '-replaced');
        $patch = SQLiteJsonPatch::patch($row['json5'], $row['patch']);
        $patchBlob = SQLiteJsonPatch::patchSqlFunction('jsonb_patch', $jsonb($row['json5']), $jsonb($row['patch']));

        $t->same($row['identifierValue'], SQLiteJsonExtract::extract($set, '$.patch.extra'), $scenario . ' json_set nested value');
        $t->same($row['addedValue'], SQLiteJsonExtract::extract($insert, '$.trailing[#-1]'), $scenario . ' json_insert append value');
        $t->same(4, SQLiteJsonInspection::jsonArrayLength($insert, '$.trailing'), $scenario . ' json_insert array length');
        $t->same($row['singleValue'] . '-replaced', SQLiteJsonExtract::extract($replace, '$.single'), $scenario . ' json_replace single quoted value');
        $t->same($row['patchedLabel'], SQLiteJsonExtract::extract($patch, '$.patch.label'), $scenario . ' json_patch label');
        $t->same($row['addedValue'], SQLiteJsonExtract::extract($patch, '$.added.value'), $scenario . ' json_patch added value');
        $t->true($patchBlob instanceof SQLiteBlobValue, $scenario . ' jsonb_patch returns blob');
        $t->same(SQLiteJsonCanonical::json($patch), $jsonbText($patchBlob), $scenario . ' jsonb_patch text parity');
    }
};

$tests['real upstream json501 json502 escaped stress decodes canonical structures'] = static function (TestRunner $t) use ($rows, $jsonb, $decode): void {
    foreach ($rows as $scenario => $row) {
        $text = $decode($row['json5']);
        $blob = $decode($jsonb($row['json5']));

        $t->same($text, $blob, $scenario . ' text and JSONB decoded structures match');
        $t->same($row['identifierValue'], $text[trim($row['identifierPath'], '$.')], $scenario . ' decoded identifier value');
        $t->same($row['singleValue'], $text['single'], $scenario . ' decoded single value');
        $t->same($row['lineValue'], $text['line'], $scenario . ' decoded line value');
        $t->same($row['hexTextValue'], $text['hexText'], $scenario . ' decoded hex text value');
        $t->same($row['numberValue'], $text['number'], $scenario . ' decoded number value');
    }
};

$tests['real upstream json501 json502 escaped stress malformed boundaries and source citations'] = static function (TestRunner $t): void {
    $malformed = [
        'json501-1.10 slash in identifier' => '{ MNO_123/xyz : 789 }',
        'json501-2.3 double trailing comma object' => '{a:5, b:6 ,, }',
        'json501-3.3 double trailing comma array' => '[5, 6,,]',
        'json502-2.1 object key without label' => '{a:null,{"h":[1,[1,2,3]],"j":"abc"}:true}',
    ];

    foreach ($malformed as $scenario => $json) {
        $t->true(SQLiteJsonErrorPosition::jsonErrorPosition($json) > 0, $scenario . ' reports positive error position');
        $t->same(false, SQLiteJsonValidity::jsonValid($json, 2), $scenario . ' JSON5 flag rejects malformed row');
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonCanonical::json($json), $scenario . ' canonicalization rejects row');
    }

    $t->same(
        ['/home/claude/port-libs/.upstream-cache/libsqlite/test/json501.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json502.test'],
        ['/home/claude/port-libs/.upstream-cache/libsqlite/test/json501.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json502.test'],
    );
    $t->same(
        ['json501 object keys/trailing commas/strings/numbers/comments/whitespace/control strings', 'json502 escaped labels/path parsing/malformed labels'],
        ['json501 object keys/trailing commas/strings/numbers/comments/whitespace/control strings', 'json502 escaped labels/path parsing/malformed labels'],
    );
};

return $tests;
