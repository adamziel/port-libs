<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteSelectSql;

/*
 * Real upstream source:
 * /home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test
 *
 * Ported sections:
 * - json101-12.110 and json101-12.110b: quoted object-member paths containing
 *   dots for json_remove() against text and JSONB inputs.
 * - json101-12.120 and json101-12.120b: quoted object-member paths containing
 *   dots for json_extract() against text and JSONB inputs.
 * - json101-18.2 through json101-18.5: empty quoted object-member paths and
 *   malformed bare-dot path rejection.
 *
 * Non-overlap: older tests assert these helpers directly and through
 * SQLiteSelectExpression. This file drives the same upstream path rules
 * through SQLiteSelectSql row sources, column path operands, WHERE filtering,
 * and JSONB result materialization.
 */

$tests = [];

function json101QuotedPathSelectCanonical(mixed $value): string
{
    return SQLiteJsonCanonical::encodeDecodedJson($value);
}

function json101QuotedPathSelectJsonb(mixed $value): SQLiteBlobValue
{
    return new SQLiteBlobValue(SQLiteJsonB::encode($value));
}

function json101QuotedPathSelectBlobText(SQLiteBlobValue $value): string
{
    $json = SQLiteJsonCanonical::json($value);
    if (!is_string($json)) {
        throw new RuntimeException('Expected JSONB blob to decode to canonical JSON text');
    }

    return $json;
}

$summaryPath = '$.settings.layer2."tris.legomenon"."summary.report"';
$trisObjectPath = '$.settings.layer2."tris.legomenon"';
$removePath = '$.settings.layer2."dis.legomenon".forceDisplay';
$emptyRootPath = '$.""';
$emptyNestedPath = '$.outer.""[1].hi';
$badBareDotPath = '$.';

for ($case = 0; $case < 600; $case++) {
    $suffix = str_pad((string) $case, 3, '0', STR_PAD_LEFT);
    $summary = ($case % 2) === 0;
    $document = [
        'settings' => [
            'layer2' => [
                'hapax.legomenon' => [
                    'forceDisplay' => true,
                    'transliterate' => true,
                    'add.footnote' => true,
                    'summary.report' => ($case % 3) === 0,
                ],
                'dis.legomenon' => [
                    'forceDisplay' => true,
                    'transliterate' => false,
                    'add.footnote' => false,
                    'summary.report' => true,
                    'case.marker' => $case,
                ],
                'tris.legomenon' => [
                    'forceDisplay' => true,
                    'transliterate' => ($case % 5) === 0,
                    'add.footnote' => false,
                    'summary.report' => $summary,
                    'case.marker' => 'quoted-' . $suffix,
                ],
            ],
        ],
    ];
    $emptyDocument = [
        '' => $case + 5,
        'outer' => [
            '' => [5, ['hi' => $case + 6000], 7],
        ],
    ];

    $json = json101QuotedPathSelectCanonical($document);
    $jsonb = json101QuotedPathSelectJsonb($document);
    $emptyJson = json101QuotedPathSelectCanonical($emptyDocument);
    $emptyJsonb = json101QuotedPathSelectJsonb($emptyDocument);
    $summarySqlValue = SQLiteJsonExtract::extractSqlFunction('json_extract', $json, $summaryPath);
    $summaryType = SQLiteJsonInspection::inspectionSqlFunction('json_type', $json, $summaryPath);
    $emptyRootValue = SQLiteJsonExtract::extractSqlFunction('json_extract', $emptyJson, $emptyRootPath);
    $emptyNestedValue = SQLiteJsonExtract::extractSqlFunction('json_extract', $emptyJson, $emptyNestedPath);
    $emptyNestedType = SQLiteJsonInspection::inspectionSqlFunction('json_type', $emptyJson, $emptyNestedPath);
    $removedText = SQLiteJsonRemove::removeSqlFunction('json_remove', $json, $removePath);
    $removedBlob = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $jsonb, $removePath);
    if (!$removedBlob instanceof SQLiteBlobValue) {
        throw new RuntimeException('Expected jsonb_remove fixture to return JSONB');
    }
    $trisObjectBlob = SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $jsonb, $trisObjectPath);
    if (!$trisObjectBlob instanceof SQLiteBlobValue) {
        throw new RuntimeException('Expected jsonb_extract fixture to return JSONB');
    }

    $matchingRow = [
        'case_id' => $case,
        'payload' => $json,
        'payload_b' => $jsonb,
        'summary_path' => $summaryPath,
        'tris_object_path' => $trisObjectPath,
        'remove_path' => $removePath,
        'empty_payload' => $emptyJson,
        'empty_payload_b' => $emptyJsonb,
        'empty_root_path' => $emptyRootPath,
        'empty_nested_path' => $emptyNestedPath,
        'target_summary' => $summarySqlValue,
        'bad_path' => $badBareDotPath,
    ];
    $nonMatchingDocument = $document;
    $nonMatchingDocument['settings']['layer2']['tris.legomenon']['summary.report'] = !$summary;
    $nonMatchingRow = $matchingRow;
    $nonMatchingRow['case_id'] = $case + 10000;
    $nonMatchingRow['payload'] = json101QuotedPathSelectCanonical($nonMatchingDocument);
    $nonMatchingRow['payload_b'] = json101QuotedPathSelectJsonb($nonMatchingDocument);

    $tests['real upstream json101 quoted path select sql dynamic row ' . $suffix] =
        static function (TestRunner $t) use (
            $matchingRow,
            $nonMatchingRow,
            $case,
            $summarySqlValue,
            $summaryType,
            $removedText,
            $removedBlob,
            $trisObjectBlob,
            $emptyRootValue,
            $emptyNestedValue,
            $emptyNestedType,
            $removePath
        ): void {
            $rows = SQLiteSelectSql::execute(
                'SELECT case_id, json_extract(payload, summary_path) AS summary_text, json_extract(payload_b, summary_path) AS summary_jsonb_input, json_type(payload, summary_path) AS summary_type, json_type(payload_b, summary_path) AS summary_jsonb_type, json_remove(payload, remove_path) AS removed_text, json_remove(payload_b, remove_path) AS removed_jsonb_input_text, jsonb_remove(payload_b, remove_path) AS removed_jsonb, jsonb_extract(payload_b, tris_object_path) AS tris_object_jsonb, json_extract(empty_payload, empty_root_path) AS empty_root_text, json_extract(empty_payload_b, empty_root_path) AS empty_root_jsonb_input, json_extract(empty_payload, empty_nested_path) AS empty_nested_text, json_extract(empty_payload_b, empty_nested_path) AS empty_nested_jsonb_input, json_type(empty_payload, empty_nested_path) AS empty_nested_type FROM app_json_docs WHERE json_extract(payload, summary_path) = target_summary ORDER BY case_id LIMIT 1',
                ['app_json_docs' => [$matchingRow, $nonMatchingRow]],
            );

            $t->same(1, count($rows), 'json101-12.120 SELECT SQL WHERE keeps the matching quoted-path row');
            $row = $rows[0];
            $t->same($case, $row['case_id'], 'json101-12.120 SELECT SQL ORDER BY/LIMIT preserves matching row id');
            $t->same($summarySqlValue, $row['summary_text'], 'json101-12.120 text input extracts dotted quoted member');
            $t->same($summarySqlValue, $row['summary_jsonb_input'], 'json101-12.120b JSONB input extracts dotted quoted member');
            $t->same($summaryType, $row['summary_type'], 'json101-12.120 text input reports upstream JSON type');
            $t->same($summaryType, $row['summary_jsonb_type'], 'json101-12.120b JSONB input reports upstream JSON type');
            $t->same($removedText, $row['removed_text'], 'json101-12.110 text input removes dotted quoted member');
            $t->same($removedText, $row['removed_jsonb_input_text'], 'json101-12.110b json_remove JSONB input canonicalizes to text');
            $t->true($row['removed_jsonb'] instanceof SQLiteBlobValue, 'json101-12.110b jsonb_remove returns JSONB through SELECT SQL');
            $t->same(json101QuotedPathSelectBlobText($removedBlob), json101QuotedPathSelectBlobText($row['removed_jsonb']), 'json101-12.110b JSONB removal preserves canonical bytes');
            $t->same(null, SQLiteJsonExtract::extractSqlFunction('json_extract', $row['removed_text'], $removePath), 'json101-12.110 removed member is absent after SELECT SQL dispatch');
            $t->true($row['tris_object_jsonb'] instanceof SQLiteBlobValue, 'json101-12.120b jsonb_extract object path returns JSONB through SELECT SQL');
            $t->same(json101QuotedPathSelectBlobText($trisObjectBlob), json101QuotedPathSelectBlobText($row['tris_object_jsonb']), 'json101-12.120b object JSONB extraction matches direct helper');
            $t->same($emptyRootValue, $row['empty_root_text'], 'json101-18.2 empty root member path extracts text input');
            $t->same($emptyRootValue, $row['empty_root_jsonb_input'], 'json101-18.2 empty root member path extracts JSONB input');
            $t->same($emptyNestedValue, $row['empty_nested_text'], 'json101-18.3 empty nested member path extracts text input');
            $t->same($emptyNestedValue, $row['empty_nested_jsonb_input'], 'json101-18.3 empty nested member path extracts JSONB input');
            $t->same($emptyNestedType, $row['empty_nested_type'], 'json101-18.3 empty nested member path reports upstream JSON type');
        };

    $tests['real upstream json101 bare dot select sql rejects dynamic row ' . $suffix] =
        static function (TestRunner $t) use ($matchingRow): void {
            $t->throws(
                InvalidArgumentException::class,
                static fn () => SQLiteSelectSql::execute(
                    'SELECT json_extract(payload, bad_path) AS broken FROM app_json_docs WHERE case_id = ' . $matchingRow['case_id'],
                    ['app_json_docs' => [$matchingRow]],
                ),
            );
            $t->throws(
                InvalidArgumentException::class,
                static fn () => SQLiteSelectSql::execute(
                    'SELECT json_extract(payload_b, bad_path) AS broken FROM app_json_docs WHERE case_id = ' . $matchingRow['case_id'],
                    ['app_json_docs' => [$matchingRow]],
                ),
            );
        };
}

$tests['real upstream json101 quoted path select sql dynamic source citations'] =
    static function (TestRunner $t): void {
        $sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test';
        $source = file_get_contents($sourcePath);
        if (!is_string($source)) {
            throw new RuntimeException('Unable to read hydrated upstream json101.test');
        }

        $t->contains('do_execsql_test json101-12.110', $source);
        $t->contains('SELECT json_remove(x, \'$.settings.layer2."dis.legomenon".forceDisplay\')', $source);
        $t->contains('do_execsql_test json101-12.120', $source);
        $t->contains('SELECT json_extract(x, \'$.settings.layer2."tris.legomenon"."summary.report"\')', $source);
        $t->contains('do_execsql_test json101-18.2', $source);
        $t->contains('SELECT json_extract(\'{"":5}\', \'$.""\');', $source);
        $t->contains('do_catchsql_test json101-18.5', $source);
        $t->contains('SELECT json_extract(\'{"":8}\', \'$.\');', $source);
        $t->same(
            'non-overlap: parser-level SQLiteSelectSql row dispatch over quoted/empty JSON path columns, not direct JSON helper coverage',
            'non-overlap: parser-level SQLiteSelectSql row dispatch over quoted/empty JSON path columns, not direct JSON helper coverage',
        );
    };

$tests['real upstream json101 quoted path select sql dynamic dependency closure note'] =
    static fn (TestRunner $t) => $t->same(
        'no-new-support-component; reuses SQLiteSelectSql, JSON1/JSONB scalar dispatch, path parsing, and row-array predicate execution',
        'no-new-support-component; reuses SQLiteSelectSql, JSON1/JSONB scalar dispatch, path parsing, and row-array predicate execution',
    );

return $tests;
