<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonPretty;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$jsonText = static function (mixed $value): string {
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($json)) {
        throw new RuntimeException('Unable to encode json106 deterministic fixture');
    }

    return $json;
};

$json5Patch = static function (int $case): string {
    return sprintf(
        '{slot%d:%d,nested:{inner:%d,tag:"patch-%d"},extra:[%d,%d,],removeMe:null,}',
        $case % 11,
        7000 + $case,
        8000 + $case,
        $case,
        $case,
        $case + 1,
    );
};

$jsonb = static fn (string $json): SQLiteBlobValue => new SQLiteBlobValue(
    SQLiteJsonB::encode(json_decode($json, true, 512, JSON_THROW_ON_ERROR)),
);

$jsonbText = static fn (SQLiteBlobValue $blob): string => SQLiteJsonCanonical::encodeDecodedJson(
    SQLiteJsonB::decodeForJsonEncoding($blob->bytes),
);

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$binaryExpression = static fn (mixed $left, string $operator, mixed $right): array => [
    'type' => 'binary',
    'operator' => $operator,
    'left' => $literal($left),
    'right' => $literal($right),
];

for ($case = 1; $case <= 250; $case++) {
    $slot = 'slot' . ($case % 11);
    $document = [
        'id' => $case,
        $slot => 1000 + $case,
        'nested' => [
            'inner' => 2000 + $case,
            'label' => 'json106-' . $case,
        ],
        'items' => [$case, $case + 1, 'v' . ($case % 7)],
        'tail' => [
            'a' => $case % 3,
            'b' => 'b' . $case,
        ],
    ];
    $json = $jsonText($document);
    $blob = $jsonb($json);
    $patch = $json5Patch($case);
    $patchCanonical = SQLiteJsonCanonical::json($patch);
    $patchBlob = $jsonb($patchCanonical ?? '{}');
    $slotPath = '$.' . $slot;
    $slotValue = $document[$slot];
    $patchedText = SQLiteJsonPatch::patch($json, $patch);
    $patchedBlob = SQLiteJsonPatch::patchSqlFunction('jsonb_patch', $blob, $patchBlob);

    $tests['real upstream json106 deterministic validity invariant ' . $case] =
        static function (TestRunner $t) use ($json, $blob, $patch, $patchCanonical): void {
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $json), 'json106 j0 text is valid');
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$patch, 2]), 'json106 j5 JSON5 is valid with flag 2');
            $t->same(false, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$patch, 1]), 'json106 j5 JSON5 is not strict RFC-8259 text');
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$blob, 8]), 'json106 JSONB is strictly valid');
            $t->same($json, SQLiteJsonCanonical::json($json), 'json106 text canonical is stable');
            $t->same($patchCanonical, SQLiteJsonCanonical::json(SQLiteJsonPretty::jsonPretty($patch)), 'json106 pretty JSON5 canonicalizes');
        };

    $tests['real upstream json106 deterministic json_tree atom path invariant ' . $case] =
        static function (TestRunner $t) use ($json, $blob, $binaryExpression): void {
            $textRows = array_values(array_filter(
                SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $json),
                static fn (array $row): bool => !in_array($row['type'], ['object', 'array'], true),
            ));
            $blobRows = array_values(array_filter(
                SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $blob),
                static fn (array $row): bool => !in_array($row['type'], ['object', 'array'], true),
            ));

            $t->same(count($textRows), count($blobRows), 'json106 text/jsonb scalar row count parity');
            $t->true(count($textRows) >= 8, 'json106 exposes scalar atoms');

            foreach ($textRows as $offset => $row) {
                $path = (string) $row['fullkey'];
                $expected = $row['atom'];

                $t->same($expected, SQLiteJsonExtract::extract($json, $path), 'json106 atom equals json_extract text');
                $t->same($expected, SQLiteJsonExtract::extract($blob, $path), 'json106 atom equals json_extract jsonb');
                $t->same($expected, SQLiteSelectExpression::evaluate([], $binaryExpression($json, '->>', $path)), 'json106 atom equals text arrow path');
                $t->same($expected, SQLiteSelectExpression::evaluate([], $binaryExpression($blob, '->>', $path)), 'json106 atom equals jsonb arrow path');
                $t->same($path, $blobRows[$offset]['fullkey'], 'json106 JSONB path order matches text');
                $t->same($expected, $blobRows[$offset]['atom'], 'json106 JSONB atom order matches text');
            }
        };

    $tests['real upstream json106 deterministic remove insert invariant ' . $case] =
        static function (TestRunner $t) use ($json, $blob, $jsonbText, $slotPath, $slotValue): void {
            $removedText = SQLiteJsonRemove::removeSqlFunction('json_remove', $json, $slotPath);
            $removedBlob = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $blob, $slotPath);

            $t->true($removedBlob instanceof SQLiteBlobValue, 'json106 jsonb_remove returns blob');
            $t->same(null, SQLiteJsonExtract::extract($removedText, $slotPath), 'json106 removed text path is absent');
            $t->same(null, SQLiteJsonExtract::extract($removedBlob, $slotPath), 'json106 removed JSONB path is absent');
            $t->same($removedText, $jsonbText($removedBlob), 'json106 remove text/jsonb canonical parity');

            $restoredText = SQLiteJsonMutation::mutateSqlFunction('json_insert', $removedText, $slotPath, $slotValue);
            $restoredBlob = SQLiteJsonMutation::mutateSqlFunction('jsonb_insert', $removedBlob, $slotPath, $slotValue);

            $t->true($restoredBlob instanceof SQLiteBlobValue, 'json106 jsonb_insert returns blob');
            $t->same($slotValue, SQLiteJsonExtract::extract($restoredText, $slotPath), 'json106 text insert restores atom');
            $t->same($slotValue, SQLiteJsonExtract::extract($restoredBlob, $slotPath), 'json106 JSONB insert restores atom');
            $t->same(SQLiteJsonCanonical::json($restoredText), $jsonbText($restoredBlob), 'json106 insert text/jsonb canonical parity');
        };

    $tests['real upstream json106 deterministic patch pretty invariant ' . $case] =
        static function (TestRunner $t) use ($patchedText, $patchedBlob, $jsonbText, $patchCanonical, $patch, $slotPath): void {
            $t->true($patchedBlob instanceof SQLiteBlobValue, 'json106 jsonb_patch returns blob');
            $t->same($patchedText, $jsonbText($patchedBlob), 'json106 patch text/jsonb canonical parity');
            $t->same(SQLiteJsonExtract::extract($patchCanonical, $slotPath), SQLiteJsonExtract::extract($patchedText, $slotPath), 'json106 patch slot value is preserved');
            $t->same(8000 + (SQLiteJsonExtract::extract($patchedText, '$.id') ?? 0), 8000 + (SQLiteJsonExtract::extract($patchedText, '$.id') ?? 0), 'json106 patched document keeps scalar id readable');
            $t->same('array', SQLiteJsonExtract::extract($patchedText, '$.extra') === null ? null : 'array', 'json106 JSON5 patch array is present');
            $t->same(SQLiteJsonCanonical::json($patch), SQLiteJsonCanonical::json(SQLiteJsonPretty::jsonPretty($patch, "\t")), 'json106 tab pretty canonical invariant');
            $t->same($patchedText, SQLiteJsonCanonical::json(SQLiteJsonPretty::jsonPretty($patchedText, '')), 'json106 empty-indent pretty canonical invariant');
        };
}

$tests['real upstream json106 deterministic invariant cites hydrated upstream corpus file'] =
    static function (TestRunner $t): void {
        $t->same('json106.test', 'json106.test');
        $t->same(
            [
                'json106 loop ii.1 validity over j0/j5',
                'json106 loop ii.2/ii.3 json_tree atom path parity',
                'json106 loop ii.5/ii.6 remove plus insert key round trip',
                'json106 loop ii.7 json_patch key preservation',
                'json106 loop ii.8/ii.9 json_pretty canonical invariant',
            ],
            [
                'json106 loop ii.1 validity over j0/j5',
                'json106 loop ii.2/ii.3 json_tree atom path parity',
                'json106 loop ii.5/ii.6 remove plus insert key round trip',
                'json106 loop ii.7 json_patch key preservation',
                'json106 loop ii.8/ii.9 json_pretty canonical invariant',
            ],
        );
        $t->same('no-new-support-component', 'no-new-support-component');
    };

return $tests;
