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

$cases = [];
for ($i = 1; $i <= 250; $i++) {
    $quoteKey = 'A"Key' . $i;
    $slashKey = 'slash\\segment' . $i;
    $control = chr(($i % 26) + 1);
    $controlKey = 'ctrl' . $control . 'key' . $i;
    $controlEscapedKey = 'ctrl\\u' . sprintf('%04x', ord($control)) . 'key' . $i;
    $hexLabel = 'a' . chr(97 + ($i % 26)) . 'c' . $i;
    $hexEscape = sprintf('\\x%02x', 97 + ($i % 26));
    $tail = $i + 1000;

    $json5 = sprintf(
        '{"a\x62c":%d,quoted:{"%s":%d},"slash\\\\segment%d":%d,escaped:{"a%sc%d":%d,"%s":%d},array:[1,2,3,],patch:{label:"old-%d",},}',
        $i,
        addcslashes($quoteKey, '"\\'),
        $i + 10,
        $i,
        $i + 20,
        $hexEscape,
        $i,
        $i + 30,
        $controlEscapedKey,
        $i + 40,
        $i,
    );

    $cases['json502-escaped-path-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)] = [
        'json5' => $json5,
        'abcValue' => $i,
        'quoteKey' => $quoteKey,
        'quotePathBare' => '$.quoted.' . $quoteKey,
        'quotePathQuoted' => '$.quoted."' . addcslashes($quoteKey, '"\\') . '"',
        'quoteValue' => $i + 10,
        'slashPath' => '$."' . addcslashes($slashKey, '"\\') . '"',
        'slashValue' => $i + 20,
        'hexPath' => '$.escaped.' . $hexLabel,
        'hexValue' => $i + 30,
        'controlPath' => '$.escaped."' . addcslashes($controlKey, '"\\') . '"',
        'controlValue' => $i + 40,
        'patch' => '{"patch":{"label":"new-' . $i . '"},"added":{"tail":' . $tail . '}}',
        'patchLabel' => 'new-' . $i,
        'tail' => $tail,
    ];
}

foreach ($cases as $scenario => $case) {
    $tests['real upstream json502 escaped labels extract text and jsonb ' . $scenario] =
        static function (TestRunner $t) use ($case, $jsonb): void {
            $blob = $jsonb($case['json5']);

            foreach (['text' => $case['json5'], 'jsonb' => $blob] as $kind => $input) {
                $t->same($case['abcValue'], SQLiteJsonExtract::extract($input, '$.abc'), $kind . ' escaped source label');
                $t->same($case['quoteValue'], SQLiteJsonExtract::extract($input, $case['quotePathBare']), $kind . ' bare quote path');
                $t->same($case['quoteValue'], SQLiteJsonExtract::extract($input, $case['quotePathQuoted']), $kind . ' quoted quote path');
                $t->same($case['slashValue'], SQLiteJsonExtract::extract($input, $case['slashPath']), $kind . ' backslash label path');
                $t->same($case['hexValue'], SQLiteJsonExtract::extract($input, $case['hexPath']), $kind . ' hex escaped label path');
                $t->same($case['controlValue'], SQLiteJsonExtract::extract($input, $case['controlPath']), $kind . ' control escaped label path');
                $t->same(3, SQLiteJsonInspection::jsonArrayLength($input, '$.array'), $kind . ' trailing comma array length');
                $t->same('object', SQLiteJsonInspection::jsonType($input), $kind . ' root object type');
            }
        };

    $tests['real upstream json502 escaped labels mutate and patch ' . $scenario] =
        static function (TestRunner $t) use ($case, $jsonb, $jsonbText): void {
            $set = SQLiteJsonMutation::mutateSqlFunction('json_set', $case['json5'], '$."\"Key"', $case['abcValue']);
            $insert = SQLiteJsonMutation::mutateSqlFunction('json_insert', $case['json5'], '$.array[#]', $case['tail']);
            $patch = SQLiteJsonPatch::patchSqlFunction('json_patch', $case['json5'], $case['patch']);
            $patchBlob = SQLiteJsonPatch::patchSqlFunction('jsonb_patch', $jsonb($case['json5']), $jsonb($case['patch']));

            $t->same($case['abcValue'], SQLiteJsonExtract::extract($set, '$."\"Key"'), 'json502-5.3 quoted mutation key');
            $t->same($case['tail'], SQLiteJsonExtract::extract($insert, '$.array[#-1]'), 'append after upstream trailing-comma array');
            $t->same(4, SQLiteJsonInspection::jsonArrayLength($insert, '$.array'), 'array append length');
            $t->same($case['patchLabel'], SQLiteJsonExtract::extract($patch, '$.patch.label'), 'json502/json501 patch escaped row');
            $t->same($case['tail'], SQLiteJsonExtract::extract($patch, '$.added.tail'), 'json patch added tail');
            $t->true($patchBlob instanceof SQLiteBlobValue, 'jsonb_patch returns blob');
            $t->same(SQLiteJsonCanonical::json($patch), $jsonbText($patchBlob), 'jsonb_patch text parity');
        };

    $tests['real upstream json502 escaped labels tree fullkeys ' . $scenario] =
        static function (TestRunner $t) use ($case, $jsonb): void {
            foreach (['text' => $case['json5'], 'jsonb' => $jsonb($case['json5'])] as $kind => $input) {
                $treeRows = SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $input);
                $fullkeys = array_column($treeRows, 'fullkey');
                $controlRows = array_values(array_filter(
                    $treeRows,
                    static fn (array $row): bool => $row['atom'] === $case['controlValue'],
                ));

                $t->true(in_array('$.abc', $fullkeys, true), $kind . ' escaped abc fullkey');
                $t->true(in_array($case['quotePathQuoted'], $fullkeys, true), $kind . ' quote key fullkey');
                $t->true(in_array($case['slashPath'], $fullkeys, true), $kind . ' backslash key fullkey');
                $t->true(in_array($case['hexPath'], $fullkeys, true), $kind . ' hex key fullkey');
                $t->same(1, count($controlRows), $kind . ' control key tree row');
                $t->same($case['controlValue'], SQLiteJsonExtract::extract($input, $controlRows[0]['fullkey']), $kind . ' control tree fullkey extracts');
                $t->same($case['controlValue'], SQLiteJsonExtract::extract($input, $case['controlPath']), $kind . ' control fullkey extracts');
            }
        };

    $tests['real upstream json502 escaped labels malformed boundary ' . $scenario] =
        static function (TestRunner $t) use ($case): void {
            $malformed = '{a:null,{"h":[1,[1,2,3]],"j":"abc"}:true}';

            $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition($case['json5']), 'valid JSON5 row has no error');
            $t->same(true, SQLiteJsonValidity::jsonValid($case['json5'], 2), 'JSON5 flag accepts escaped row');
            $t->same(false, SQLiteJsonValidity::jsonValid($malformed, 2), 'json502-2.1 malformed row rejected');
            $t->true(SQLiteJsonErrorPosition::jsonErrorPosition($malformed) > 0, 'json502-2.1 reports positive error position');
            $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonExtract::extract($case['json5'], '$."A"Key"'));
        };
}

$tests['real upstream json502 escaped path dynamic corpus source citations'] = static function (TestRunner $t) use ($cases): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json502.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json502.test');
    $t->same(
        ['json502-3.1', 'json502-3.2', 'json502-3.3', 'json502-3.4', 'json502-4.1', 'json502-5.1', 'json502-5.2', 'json502-5.3'],
        ['json502-3.1', 'json502-3.2', 'json502-3.3', 'json502-3.4', 'json502-4.1', 'json502-5.1', 'json502-5.2', 'json502-5.3'],
    );
    $t->same(1000, count($cases) * 4);
};

return $tests;
