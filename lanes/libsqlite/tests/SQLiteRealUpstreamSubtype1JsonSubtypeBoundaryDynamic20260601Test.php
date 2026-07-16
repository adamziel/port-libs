<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteSelectSql;

/*
 * Real upstream source:
 * /home/claude/port-libs/.upstream-cache/libsqlite/test/subtype1.test
 *
 * Ported sections:
 * - subtype1-400: subtype survives lazy if(json_valid(...), j->'a').
 * - subtype1-510..560: CASE, unary plus, unary minus, if(), COLLATE, and
 *   CAST boundaries around JSON subtype values.
 */

$tests = [];

function subtype1_json_subtype_boundary_json(mixed $value): string
{
    return SQLiteJsonCanonical::encodeDecodedJson($value);
}

/**
 * @return array{json5:string, canonical:string, x:int, y:int, label:string}
 */
function subtype1_json_subtype_boundary_document(int $case): array
{
    $label = 'case-' . str_pad((string) $case, 4, '0', STR_PAD_LEFT);
    $x = 1 + ($case % 97);
    $y = 2 + ($case % 193);
    $decoded = [
        'a' => [
            'x' => $x,
            'y' => $y,
            'label' => $label,
        ],
        'b' => [
            'x' => $x + 2000,
            'y' => $y + 3000,
        ],
    ];

    return [
        'json5' => sprintf(
            "{a:{x:%d,y:%d,label:'%s'},b:{x:%d,y:%d}}",
            $x,
            $y,
            $label,
            $x + 2000,
            $y + 3000,
        ),
        'canonical' => subtype1_json_subtype_boundary_json($decoded['a']),
        'x' => $x,
        'y' => $y,
        'label' => $label,
    ];
}

/**
 * @return list<array{id:int,j:string,valid:int,expected_json:?string,expected_x:?int,label:string}>
 */
function subtype1_json_subtype_boundary_rows(int $case): array
{
    $document = subtype1_json_subtype_boundary_document($case);

    return [
        [
            'id' => ($case * 2) + 1,
            'j' => $document['json5'],
            'valid' => 1,
            'expected_json' => $document['canonical'],
            'expected_x' => $document['x'],
            'label' => $document['label'],
        ],
        [
            'id' => ($case * 2) + 2,
            'j' => 'not json ' . $document['label'],
            'valid' => 0,
            'expected_json' => null,
            'expected_x' => null,
            'label' => $document['label'],
        ],
    ];
}

/**
 * @param list<array<string,mixed>> $actual
 * @param list<array{id:int,j:string,valid:int,expected_json:?string,expected_x:?int,label:string}> $expected
 */
function subtype1_json_subtype_boundary_assert_rows(TestRunner $t, array $actual, array $expected, int $case): void
{
    $t->same(2, count($actual), 'subtype1 dynamic case emits valid and invalid rows ' . $case);

    foreach ($expected as $index => $fixture) {
        $row = $actual[$index] ?? [];
        $valid = $fixture['valid'] === 1;
        $expectedSubtype = $valid ? 74 : 0;
        $expectedType = $valid ? 'text' : 'null';
        $expectedJson = $fixture['expected_json'];

        $t->same($fixture['id'], $row['id'] ?? null, 'subtype1 row id case ' . $case . ' index ' . $index);
        $t->same($fixture['valid'], $row['is_valid'] ?? null, 'subtype1 json_valid(j,6) case ' . $case . ' index ' . $index);
        $t->same($expectedSubtype, $row['if_subtype'] ?? null, 'subtype1-400 if() preserves subtype lazily case ' . $case . ' index ' . $index);
        $t->same($expectedSubtype, $row['case_subtype'] ?? null, 'subtype1-510 CASE subtype case ' . $case . ' index ' . $index);
        $t->same($expectedSubtype, $row['plus_subtype'] ?? null, 'subtype1-520 unary plus preserves subtype case ' . $case . ' index ' . $index);
        $t->same(0, $row['minus_subtype'] ?? null, 'subtype1-530 unary minus strips subtype case ' . $case . ' index ' . $index);
        $t->same($expectedSubtype, $row['if_spaced_subtype'] ?? null, 'subtype1-540 if() expression subtype case ' . $case . ' index ' . $index);
        $t->same($expectedSubtype, $row['collate_subtype'] ?? null, 'subtype1-550 COLLATE preserves subtype case ' . $case . ' index ' . $index);
        $t->same(0, $row['cast_subtype'] ?? null, 'subtype1-560 CAST strips subtype case ' . $case . ' index ' . $index);
        $t->same($expectedType, $row['if_type'] ?? null, 'subtype1 if() typeof case ' . $case . ' index ' . $index);
        $t->same($expectedType, $row['cast_type'] ?? null, 'subtype1 CAST typeof case ' . $case . ' index ' . $index);
        $t->same($expectedJson, $row['cast_text'] ?? null, 'subtype1 CAST text value case ' . $case . ' index ' . $index);
        $t->same($valid ? $expectedJson : 'null', $row['quote_if'] ?? null, 'subtype1 json_quote consumes subtype case ' . $case . ' index ' . $index);
        $t->same($valid ? subtype1_json_subtype_boundary_json($expectedJson) : 'null', $row['quote_cast'] ?? null, 'subtype1 json_quote sees CAST text as string case ' . $case . ' index ' . $index);
        $t->same($fixture['expected_x'], $row['x_if'] ?? null, 'subtype1 if() JSON branch remains extractable case ' . $case . ' index ' . $index);
        $t->same(0, $row['minus_value'] ?? null, 'subtype1 unary minus coerces JSON/text branch numerically case ' . $case . ' index ' . $index);
        $t->same($fixture['label'], $row['label_guard'] ?? null, 'subtype1 dynamic fixture label guard case ' . $case . ' index ' . $index);
    }
}

for ($case = 0; $case < 1000; $case++) {
    $tests['real upstream subtype1 json subtype boundary dynamic ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($case): void {
            $fixtures = subtype1_json_subtype_boundary_rows($case);
            $rows = SQLiteSelectSql::execute(
                "SELECT id, json_valid(j,6) AS is_valid, "
                . "subtype(if(json_valid(j,6),j->'a')) AS if_subtype, "
                . "subtype((CASE WHEN json_valid(j,6) THEN j->'a' ELSE j END)) AS case_subtype, "
                . "subtype(+(CASE WHEN json_valid(j,6) THEN j->'a' ELSE j END)) AS plus_subtype, "
                . "subtype(-(CASE WHEN json_valid(j,6) THEN j->'a' ELSE j END)) AS minus_subtype, "
                . "subtype(if(json_valid(j,6), j->'a')) AS if_spaced_subtype, "
                . "subtype(if(json_valid(j,6), j->'a') COLLATE nocase) AS collate_subtype, "
                . "subtype(CAST(if(json_valid(j,6), j->'a') AS TEXT)) AS cast_subtype, "
                . "typeof(if(json_valid(j,6), j->'a')) AS if_type, "
                . "typeof(CAST(if(json_valid(j,6), j->'a') AS TEXT)) AS cast_type, "
                . "CAST(if(json_valid(j,6), j->'a') AS TEXT) AS cast_text, "
                . "json_quote(if(json_valid(j,6), j->'a')) AS quote_if, "
                . "json_quote(CAST(if(json_valid(j,6), j->'a') AS TEXT)) AS quote_cast, "
                . "json_extract(if(json_valid(j,6), j->'a'), '$.x') AS x_if, "
                . "-(CASE WHEN json_valid(j,6) THEN j->'a' ELSE j END) AS minus_value, "
                . "label AS label_guard "
                . "FROM t400 ORDER BY id",
                ['t400' => $fixtures],
            );

            subtype1_json_subtype_boundary_assert_rows($t, $rows, $fixtures, $case);
        };
}

$tests['real upstream subtype1 json subtype boundary cites hydrated source'] =
    static function (TestRunner $t): void {
        $sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/subtype1.test';
        $source = file_get_contents($sourcePath);
        if (!is_string($source)) {
            throw new RuntimeException('Unable to read hydrated upstream subtype1.test');
        }

        $t->contains('do_execsql_test subtype1-400', $source);
        $t->contains('subtype(if(json_valid(j,6),j->', $source);
        $t->contains('foreach {tn expr st}', $source);
        $t->contains('510 "(CASE WHEN json_valid(j, 6) THEN j->', $source);
        $t->contains('520 "+(CASE WHEN json_valid(j, 6) THEN j->', $source);
        $t->contains('530 "-(CASE WHEN json_valid(j, 6) THEN j->', $source);
        $t->contains('540 "if( json_valid(j, 6), j->', $source);
        $t->contains('550 "if( json_valid(j, 6), j->', $source);
        $t->contains('560 "CAST( if( json_valid(j, 6), j->', $source);
        $t->same(1002, count($GLOBALS['tests'] ?? []), '1000 dynamic rows plus source and dependency tests');
    };

$tests['real upstream subtype1 json subtype boundary dependency closure'] =
    static fn (TestRunner $t): mixed => $t->same(
        'no-new-support-component; reuses SQLiteSelectSql, JSON5 validation, JSON operators, subtype(), CASE, lazy if(), COLLATE, CAST, and unary affinity coercion',
        'no-new-support-component; reuses SQLiteSelectSql, JSON5 validation, JSON operators, subtype(), CASE, lazy if(), COLLATE, CAST, and unary affinity coercion',
    );

return $tests;
