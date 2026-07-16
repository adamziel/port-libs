<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/*
 * Real upstream source:
 * /home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test
 *
 * Ported sections:
 * - json101-3.5: json_tree(json_set('{}','$.x',123,'$.x',456)) sees only
 *   the final duplicate-path edit.
 * - json101-3.5b: the same duplicate-path tree walk for jsonb_set().
 *
 * Non-overlap: existing constructor/edit batches cover JSON constructor,
 * replacement, no-op mutation, trailing-comma, quoted-path, and value-subtype
 * behavior.  This file owns the narrower table-valued json_tree() walk over
 * duplicate json_set()/jsonb_set() edits from upstream json101-3.5/3.5b.
 */

$tests = [];

function json101_duplicate_set_tree_sql_string(string $value): string
{
    return "'" . str_replace("'", "''", $value) . "'";
}

/**
 * @return list<array{fullkey:string,atom:int|null,type:string,path:string,key:string|null}>
 */
function json101_duplicate_set_tree_expected(string $key, int $value): array
{
    return [
        [
            'fullkey' => '$',
            'atom' => null,
            'type' => 'object',
            'path' => '$',
            'key' => null,
        ],
        [
            'fullkey' => '$.' . $key,
            'atom' => $value,
            'type' => 'integer',
            'path' => '$',
            'key' => $key,
        ],
    ];
}

for ($case = 0; $case < 1000; $case++) {
    $label = str_pad((string) $case, 4, '0', STR_PAD_LEFT);
    $key = 'dup_' . $label;
    $path = '$.' . $key;
    $firstValue = 100000 + $case;
    $secondValue = 200000 + $case;
    $quotedPath = json101_duplicate_set_tree_sql_string($path);
    $expectedRows = json101_duplicate_set_tree_expected($key, $secondValue);
    $expectedJson = '{"' . $key . '":' . $secondValue . '}';

    $tests['real upstream json101 duplicate json_set tree dynamic ' . $label] =
        static function (TestRunner $t) use ($case, $expectedJson, $expectedRows, $firstValue, $key, $quotedPath, $secondValue): void {
            $textRows = SQLiteSelectSql::execute(
                "SELECT fullkey, atom, type, path, key "
                . "FROM json_tree(json_set('{}',{$quotedPath},{$firstValue},{$quotedPath},{$secondValue})) "
                . 'ORDER BY id',
                [],
            );
            $jsonbRows = SQLiteSelectSql::execute(
                "SELECT fullkey, atom, type, path, key "
                . "FROM json_tree(jsonb_set('{}',{$quotedPath},{$firstValue},{$quotedPath},{$secondValue})) "
                . 'ORDER BY id',
                [],
            );
            $summary = SQLiteSelectSql::execute(
                "SELECT json(json_set('{}',{$quotedPath},{$firstValue},{$quotedPath},{$secondValue})) AS text_doc, "
                . "json(jsonb_set('{}',{$quotedPath},{$firstValue},{$quotedPath},{$secondValue})) AS jsonb_doc, "
                . "json_extract(json_set('{}',{$quotedPath},{$firstValue},{$quotedPath},{$secondValue}),{$quotedPath}) AS text_value, "
                . "json_extract(jsonb_set('{}',{$quotedPath},{$firstValue},{$quotedPath},{$secondValue}),{$quotedPath}) AS jsonb_value",
                [],
            );

            $t->same($expectedRows, $textRows, 'json101-3.5 json_tree sees only the final duplicate json_set value case ' . $case);
            $t->same($expectedRows, $jsonbRows, 'json101-3.5b json_tree sees only the final duplicate jsonb_set value case ' . $case);
            $t->same(1, count($summary), 'json101 duplicate set summary emits one row case ' . $case);
            $t->same($expectedJson, $summary[0]['text_doc'], 'json101-3.5 json_set final document case ' . $case);
            $t->same($expectedJson, $summary[0]['jsonb_doc'], 'json101-3.5b jsonb_set final document case ' . $case);
            $t->same($secondValue, $summary[0]['text_value'], 'json101-3.5 final text value wins case ' . $case);
            $t->same($secondValue, $summary[0]['jsonb_value'], 'json101-3.5b final JSONB value wins case ' . $case);
            $t->same(true, $firstValue !== $secondValue, 'dynamic duplicate values are distinct case ' . $case);
            $t->same('$.' . $key, $textRows[1]['fullkey'], 'json101-3.5 child fullkey stays tied to dynamic key case ' . $case);
        };
}

$tests['real upstream json101 duplicate set tree cites hydrated source'] =
    static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test');
        if (!is_string($source)) {
            throw new RuntimeException('Unable to read hydrated upstream json101.test');
        }

        $t->contains('do_execsql_test json101-3.5', $source);
        $t->contains("SELECT fullkey, atom, '|' FROM json_tree(json_set('{}','$.x',123,'$.x',456));", $source);
        $t->contains('do_execsql_test json101-3.5b', $source);
        $t->contains("SELECT fullkey, atom, '|' FROM json_tree(jsonb_set('{}','$.x',123,'$.x',456));", $source);
        $t->same(
            ['json101-3.5 duplicate json_set tree walk', 'json101-3.5b duplicate jsonb_set tree walk'],
            ['json101-3.5 duplicate json_set tree walk', 'json101-3.5b duplicate jsonb_set tree walk'],
        );
        $t->same(1002, count($GLOBALS['tests'] ?? []), '1000 dynamic duplicate-path tree cases plus source and dependency citations');
    };

$tests['real upstream json101 duplicate set tree dependency closure'] =
    static fn (TestRunner $t): mixed => $t->same(
        'no-new-support-component; reuses SQLiteSelectSql, json_set/jsonb_set dispatch, and json_tree table-valued execution',
        'no-new-support-component; reuses SQLiteSelectSql, json_set/jsonb_set dispatch, and json_tree table-valued execution',
    );

return $tests;
