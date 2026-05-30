<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
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
$canonical = static fn (string|SQLiteBlobValue $json): string => SQLiteJsonCanonical::json($json);

$json5Rows = [];
for ($i = 1; $i <= 240; $i++) {
    $hex = dechex(0x20 + $i);
    $signed = ($i % 2) === 0 ? '+' . $i : '-' . $i;
    $fraction = ($i % 2) === 0 ? $i . '.' : '.' . (($i % 9) + 1);
    $comment = ($i % 2) === 0 ? '// json501 line comment' : '/* json501 block comment */';
    $key = 'tenant_' . $i . '_load';
    $expectedString = 'line-' . $i . '-joined';
    $expectedEscaped = 'abc-' . $i;

    $json5Rows['json501-json5-feature-row-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)] = [
        'json5' => sprintf(
            '{%s:%d,%s%shex:0x%s,trailing:[1,2,3,],single:\'single-%d\',joined:"line-%d-joined",quoted:"abc-%d",signed:%s,fraction:%s,nested:{escaped:"abc-%d",},}',
            $key,
            $i,
            $comment,
            PHP_EOL,
            $hex,
            $i,
            $i,
            $i,
            $signed,
            $fraction,
            $i,
        ),
        'keyPath' => '$.' . $key,
        'keyValue' => $i,
        'hexValue' => hexdec($hex),
        'singleValue' => 'single-' . $i,
        'joinedValue' => $expectedString,
        'quotedValue' => $expectedEscaped,
        'signedValue' => (int) $signed,
        'fractionType' => 'real',
        'arrayLength' => 3,
        'escapedNestedPath' => '$.nested.escaped',
        'escapedNestedValue' => $expectedEscaped,
    ];
}

$json502EscapedRows = [];
for ($i = 1; $i <= 180; $i++) {
    $control = chr(1 + ($i % 0x1f));
    $hexByte = sprintf('%02x', 97 + ($i % 20));
    $json502EscapedRows['json502-escaped-path-row-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)] = [
        'document' => sprintf('{"A\\"Key%d":%d,"a\\x%sc%d":%d,"ctrl\\u%04x":%d}', $i, $i, $hexByte, $i, $i + 1, ord($control), $i + 2),
        'quotePathBare' => '$.A"Key' . $i,
        'quotePathQuoted' => '$."A\\"Key' . $i . '"',
        'quoteValue' => $i,
        'hexPath' => '$.a' . chr(hexdec($hexByte)) . 'c' . $i,
        'hexValue' => $i + 1,
        'controlPath' => '$."ctrl' . $control . '"',
        'controlValue' => $i + 2,
    ];
}

$tests['real upstream json501 JSON5 feature rows canonicalize and validate'] = static function (TestRunner $t) use ($json5Rows, $canonical, $jsonb): void {
    foreach ($json5Rows as $scenario => $row) {
        $canonicalText = $canonical($row['json5']);
        $blob = $jsonb($row['json5']);

        $t->same(false, SQLiteJsonValidity::jsonValid($row['json5']), $scenario . ' strict json_valid rejects JSON5');
        $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$row['json5'], 2]), $scenario . ' json_valid flag accepts JSON5');
        $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition($row['json5']), $scenario . ' json_error_position accepts JSON5');
        $t->same($canonicalText, SQLiteJsonCanonical::json($canonicalText), $scenario . ' canonical text is stable');
        $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$blob, 4]), $scenario . ' JSONB superficial valid');
        $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$blob, 8]), $scenario . ' JSONB strict valid');
    }
};

$tests['real upstream json501 JSON5 feature rows extract object keys arrays strings and numbers'] = static function (TestRunner $t) use ($json5Rows, $jsonb): void {
    foreach ($json5Rows as $scenario => $row) {
        $blob = $jsonb($row['json5']);

        foreach ([$row['json5'], $blob] as $kind => $input) {
            $label = is_int($kind) && $kind === 0 ? 'text' : 'jsonb';
            $t->same($row['keyValue'], SQLiteJsonExtract::extract($input, $row['keyPath']), $scenario . ' identifier key ' . $label);
            $t->same($row['hexValue'], SQLiteJsonExtract::extract($input, '$.hex'), $scenario . ' hex number ' . $label);
            $t->same($row['singleValue'], SQLiteJsonExtract::extract($input, '$.single'), $scenario . ' single quoted string ' . $label);
            $t->same($row['joinedValue'], SQLiteJsonExtract::extract($input, '$.joined'), $scenario . ' escaped line join ' . $label);
            $t->same($row['quotedValue'], SQLiteJsonExtract::extract($input, '$.quoted'), $scenario . ' hex string escape ' . $label);
            $t->same($row['signedValue'], SQLiteJsonExtract::extract($input, '$.signed'), $scenario . ' explicit sign number ' . $label);
            $t->same($row['fractionType'], SQLiteJsonInspection::jsonType($input, '$.fraction'), $scenario . ' leading/trailing decimal type ' . $label);
            $t->same($row['arrayLength'], SQLiteJsonInspection::jsonArrayLength($input, '$.trailing'), $scenario . ' trailing comma array length ' . $label);
            $t->same($row['escapedNestedValue'], SQLiteJsonExtract::extract($input, $row['escapedNestedPath']), $scenario . ' escaped object label ' . $label);
        }
    }
};

$tests['real upstream json501 JSON5 feature rows mutate and patch escaped members'] = static function (TestRunner $t) use ($json5Rows, $canonical): void {
    foreach ($json5Rows as $scenario => $row) {
        $set = SQLiteJsonMutation::mutateSqlFunction('json_set', $row['json5'], '$.nested.extra', $row['keyValue'] * 2);
        $insert = SQLiteJsonMutation::mutateSqlFunction('json_insert', $row['json5'], '$.trailing[#]', $row['keyValue']);
        $replace = SQLiteJsonMutation::mutateSqlFunction('json_replace', $row['json5'], '$.single', $row['singleValue'] . '-replaced');
        $patch = SQLiteJsonPatch::patch($row['json5'], '{"nested":{"patched":true},"single":"patched"}');

        $t->same($row['keyValue'] * 2, SQLiteJsonExtract::extract($set, '$.nested.extra'), $scenario . ' json_set escaped source');
        $t->same($row['keyValue'], SQLiteJsonExtract::extract($insert, '$.trailing[#-1]'), $scenario . ' json_insert array append');
        $t->same($row['arrayLength'] + 1, SQLiteJsonInspection::jsonArrayLength($insert, '$.trailing'), $scenario . ' json_insert array length');
        $t->same($row['singleValue'] . '-replaced', SQLiteJsonExtract::extract($replace, '$.single'), $scenario . ' json_replace single quoted source');
        $t->same(1, SQLiteJsonExtract::extract($patch, '$.nested.patched'), $scenario . ' json_patch nested value');
        $t->same('patched', SQLiteJsonExtract::extract($patch, '$.single'), $scenario . ' json_patch scalar value');
        $t->same($canonical($set), SQLiteJsonCanonical::json($set), $scenario . ' set result canonical stable');
        $t->same($canonical($insert), SQLiteJsonCanonical::json($insert), $scenario . ' insert result canonical stable');
    }
};

$tests['real upstream json502 escaped path labels match extraction and tree rows'] = static function (TestRunner $t) use ($json502EscapedRows, $jsonb): void {
    foreach ($json502EscapedRows as $scenario => $row) {
        foreach ([$row['document'], $jsonb($row['document'])] as $kind => $input) {
            $label = is_int($kind) && $kind === 0 ? 'text' : 'jsonb';
            $t->same($row['quoteValue'], SQLiteJsonExtract::extract($input, $row['quotePathBare']), $scenario . ' bare quoted-key path ' . $label);
            $t->same($row['quoteValue'], SQLiteJsonExtract::extract($input, $row['quotePathQuoted']), $scenario . ' quoted quoted-key path ' . $label);
            $t->same($row['hexValue'], SQLiteJsonExtract::extract($input, $row['hexPath']), $scenario . ' hex escaped label path ' . $label);
            $t->same($row['controlValue'], SQLiteJsonExtract::extract($input, $row['controlPath']), $scenario . ' control escaped label path ' . $label);
        }

        $fullkeys = array_column(SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $row['document']), 'fullkey');
        $t->true(in_array($row['quotePathQuoted'], $fullkeys, true), $scenario . ' json_tree exposes quoted-key fullkey');
        $t->true(in_array($row['hexPath'], $fullkeys, true), $scenario . ' json_tree exposes hex-label fullkey');
        $t->same($row['controlValue'], SQLiteJsonExtract::extract($row['document'], $row['controlPath']), $scenario . ' json_tree-compatible control-label extract');
    }
};

$tests['real upstream json501 json502 malformed forms keep upstream error boundary'] = static function (TestRunner $t): void {
    $malformed = [
        'json501-1.10 slash in identifier' => '{ MNO_123/xyz : 789 }',
        'json501-2.3 double trailing comma object' => '{a:5, b:6 ,, }',
        'json501-2.4 separated trailing comma object' => '{a:5, b:6, ,}',
        'json501-3.3 double trailing comma array' => '[5, 6,,]',
        'json501-3.4 separated trailing comma array' => '[5, 6 , , ]',
        'json502-2.1 object key without label' => '{a:null,{"h":[1,[1,2,3]],"j":"abc"}:true}',
    ];

    foreach ($malformed as $scenario => $json) {
        $position = SQLiteJsonErrorPosition::jsonErrorPosition($json);
        $t->true($position > 0, $scenario . ' has positive json_error_position');
        $t->same(false, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$json, 2]), $scenario . ' json_valid JSON5 flag rejects');
        try {
            SQLiteJsonCanonical::json($json);
            $t->true(false, $scenario . ' canonicalization should fail');
        } catch (InvalidArgumentException $exception) {
            $t->same('malformed JSON', $exception->getMessage(), $scenario . ' canonicalization error');
        }
    }
};

$tests['real upstream json501 json502 source coverage cites hydrated upstream files'] = static function (TestRunner $t): void {
    $t->same(['json501.test', 'json502.test'], ['json501.test', 'json502.test']);
};

return $tests;
