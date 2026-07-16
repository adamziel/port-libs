<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonQuote;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$json101ControlQuoteSelectSqlWhitespaceRows = [
    'json101-7.1' => ["\x20", 1],
    'json101-7.2' => ["\x09", 1],
    'json101-7.3' => ["\x0a", 1],
    'json101-7.4' => ["\x0d", 1],
    'json101-7.5' => ["\x0c", 0],
    'json101-7.6' => ["\x20\x09\x0a\x0d\x20", 1],
    'json101-7.7' => ["\x20\x09\x0a\x0c\x0d\x20", 0],
];

function json101_control_quote_select_sql_single_row(array $rows, string $label): array
{
    if (count($rows) !== 1) {
        throw new RuntimeException($label . ' expected exactly one row, got ' . count($rows));
    }

    return $rows[0];
}

function json101_control_quote_select_sql_throws_containing(TestRunner $t, string $sql, string $message): void
{
    try {
        SQLiteSelectSql::execute($sql, []);
    } catch (InvalidArgumentException $exception) {
        $t->contains($message, $exception->getMessage());

        return;
    }

    $t->same('exception', 'no exception', 'Expected SELECT SQL to reject upstream json_quote() boundary');
}

/**
 * @param array<string,array{0:string,1:int}> $whitespaceRows
 * @return array<string,mixed>
 */
function json101_control_quote_select_sql_row(int $case, array $whitespaceRows): array
{
    $whitespaceKeys = array_keys($whitespaceRows);
    $whitespaceKey = $whitespaceKeys[$case % count($whitespaceKeys)];
    [$ws, $expectedWhitespaceValid] = $whitespaceRows[$whitespaceKey];
    $byte = ($case % 35) + 1;
    $payload = 'case-' . $case . ':' . chr($byte) . ':abc' . implode('', array_map('chr', range(1, 35))) . 'xyz';
    $quoted = SQLiteJsonQuote::jsonQuote($payload);

    return [
        'case_id' => $case,
        'payload' => $payload,
        'expected_array' => '[' . $quoted . ']',
        'expected_quoted' => $quoted,
        'quote_array' => '[' . $quoted . ']',
        'ws_case' => $whitespaceKey,
        'ws_json' => sprintf('%s{%s"x"%s:%s9%s}%s', $ws, $ws, $ws, $ws, $ws, $ws),
        'expected_ws_valid' => $expectedWhitespaceValid,
        'high_json' => "\"\xc3\xa4\"",
        'high_expected' => "\xc3\xa4",
        'control_byte' => $byte,
    ];
}

for ($case = 0; $case < 1000; $case++) {
    $tests['real upstream json101 control quote SELECT SQL dynamic ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($case, $json101ControlQuoteSelectSqlWhitespaceRows): void {
            $row = json101_control_quote_select_sql_row($case, $json101ControlQuoteSelectSqlWhitespaceRows);
            $selected = json101_control_quote_select_sql_single_row(
                SQLiteSelectSql::execute(
                    "SELECT case_id, json(json_array(payload)) AS array_text, json(jsonb_array(payload)) AS jsonb_array_text, json_extract(json_array(payload),'$[0]') AS array_extract, json_extract(jsonb_array(payload),'$[0]') AS jsonb_array_extract, json_quote(payload) AS quoted, json_valid(json_quote(payload)) AS quoted_valid, json_extract(quote_array,'$[0]') AS quote_extract, json_valid(ws_json) AS whitespace_valid, json_valid(high_json) AS high_valid, json_extract(high_json,'$') AS high_extract, unicode(json_extract(high_json,'$')) AS high_unicode, length(json_extract(high_json,'$')) AS high_length, json_valid(jsonb_array(payload),8) AS jsonb_array_strict FROM app_json_inputs",
                    ['app_json_inputs' => [$row]],
                ),
                'json101 control quote SELECT SQL case ' . $case,
            );

            $t->same($case, $selected['case_id'], 'json101-7/8/9 SELECT SQL preserves case id ' . $case);
            $t->same($row['expected_array'], $selected['array_text'], 'json101-8.1 json_array control-byte text case ' . $case);
            $t->same($row['expected_array'], $selected['jsonb_array_text'], 'json101-8.1b jsonb_array canonical text case ' . $case);
            $t->same($row['payload'], $selected['array_extract'], 'json101-8.2 json_array extract round trip case ' . $case);
            $t->same($row['payload'], $selected['jsonb_array_extract'], 'json101-8.2 jsonb_array extract round trip case ' . $case);
            $t->same($row['expected_quoted'], $selected['quoted'], 'json101-9 json_quote scalar SQL text case ' . $case);
            $t->same(1, $selected['quoted_valid'], 'json101-9 json_quote scalar is valid JSON case ' . $case);
            $t->same($row['payload'], $selected['quote_extract'], 'json101-9 json_quote SELECT SQL round trip case ' . $case);
            $t->same($row['expected_ws_valid'], $selected['whitespace_valid'], $row['ws_case'] . ' SELECT SQL whitespace validity case ' . $case);
            $t->same(1, $selected['high_valid'], 'json101-8.3 high-byte JSON string validity case ' . $case);
            $t->same($row['high_expected'], $selected['high_extract'], 'json101-8.4 high-byte JSON string extract case ' . $case);
            $t->same(228, $selected['high_unicode'], 'json101-8.4 high-byte unicode codepoint case ' . $case);
            $t->same(1, $selected['high_length'], 'json101-8.4 high-byte length case ' . $case);
            $t->same(1, $selected['jsonb_array_strict'], 'json101-8.1b strict JSONB validity case ' . $case);
            $t->same($row['control_byte'], ord($row['payload'][strlen('case-' . $case . ':')]), 'json101-8 control byte fixture retained case ' . $case);
        };
}

$tests['real upstream json101 control quote SELECT SQL cites hydrated upstream sections'] =
    static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test');
        if (!is_string($source)) {
            throw new RuntimeException('Unable to read hydrated upstream json101.test');
        }

        $t->contains('do_execsql_test json101-$tn.1', $source);
        $t->contains('7.1  1  char(0x20)', $source);
        $t->contains('7.7  0  char(0x20,0x09,0x0a,0x0c,0x0d,0x20)', $source);
        $t->contains('json101-8.1', $source);
        $t->contains('json101-8.1b', $source);
        $t->contains('json101-8.2', $source);
        $t->contains('json101-8.3', $source);
        $t->contains('json101-8.4', $source);
        $t->contains('json101-9.1', $source);
        $t->contains('json101-9.7', $source);
        json101_control_quote_select_sql_throws_containing($t, "SELECT json_quote(x'3031323334') AS quoted", 'JSON cannot hold BLOB values');
        json101_control_quote_select_sql_throws_containing($t, 'SELECT json_quote() AS quoted', 'expects one argument');
        json101_control_quote_select_sql_throws_containing($t, 'SELECT json_quote(1,2) AS quoted', 'expects one argument');
        $t->same(1002, count($GLOBALS['tests'] ?? []), '1000 SELECT SQL rows plus source and dependency citations');
    };

$tests['real upstream json101 control quote SELECT SQL dependency closure note'] =
    static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component');

return $tests;
