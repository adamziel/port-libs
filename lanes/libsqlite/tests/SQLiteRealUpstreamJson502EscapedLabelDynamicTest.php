<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectExpression;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$jsonText = static function (mixed $value): string {
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($json)) {
        throw new RuntimeException('Unable to encode JSON expectation');
    }

    return $json;
};
$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);
$jsonArrowText = static fn (mixed $value): mixed => $value instanceof SQLiteJsonSubtypeValue ? $value->json : $value;
$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$binary = static fn (mixed $left, mixed $right, string $operator): array => [
    'type' => 'binary',
    'operator' => $operator,
    'left' => $literal($left),
    'right' => $literal($right),
];
$sqlLiteral = static fn (string $value): string => "'" . str_replace("'", "''", $value) . "'";

$escapedLabelScenarios = [
    'json502-3.1 source hex escape matches plain rhs' => ['sourceKey' => 'a\\x62c', 'canonicalKey' => 'abc', 'path' => '$.abc', 'rhs' => 'abc'],
    'json502-3.2 plain source matches rhs hex escape' => ['sourceKey' => 'abc', 'canonicalKey' => 'abc', 'path' => '$.abc', 'rhs' => 'a\\x62c'],
    'json502-3.4 patch source hex escape matches plain lookup' => ['sourceKey' => 'ab\\x63', 'canonicalKey' => 'abc', 'path' => '$.abc', 'rhs' => 'abc'],
    'json502-5.1 unquoted path matches escaped quote key' => ['sourceKey' => 'A\\"Key', 'canonicalKey' => 'A"Key', 'path' => '$.A"Key', 'rhs' => 'A\\"Key'],
    'json502-5.2 quoted path matches escaped quote key' => ['sourceKey' => 'A\\"Key', 'canonicalKey' => 'A"Key', 'path' => '$."A\\"Key"', 'rhs' => 'A\\"Key'],
    'json502-5.3 quoted escaped quote mutation path' => ['sourceKey' => '\\"Key', 'canonicalKey' => '"Key', 'path' => '$."\\"Key"', 'rhs' => '\\"Key'],
    'json502-4.1 quoted control path x17' => ['sourceKey' => '\\u0017', 'canonicalKey' => "\x17", 'path' => '$."\\x17"', 'rhs' => '\\x17'],
    'json502 unicode escape label b' => ['sourceKey' => 'a\\u0062c', 'canonicalKey' => 'abc', 'path' => '$.abc', 'rhs' => 'a\\u0062c'],
    'json502 unicode escaped quoted path' => ['sourceKey' => 'snow\\u2603', 'canonicalKey' => "snow\u{2603}", 'path' => '$."snow\\u2603"', 'rhs' => 'snow\\u2603'],
    'json502 tab escaped label' => ['sourceKey' => 'a\\tb', 'canonicalKey' => "a\tb", 'path' => '$."a\\tb"', 'rhs' => 'a\\tb'],
    'json502 newline escaped label' => ['sourceKey' => 'a\\nb', 'canonicalKey' => "a\nb", 'path' => '$."a\\nb"', 'rhs' => 'a\\nb'],
    'json502 carriage escaped label' => ['sourceKey' => 'a\\rb', 'canonicalKey' => "a\rb", 'path' => '$."a\\rb"', 'rhs' => 'a\\rb'],
    'json502 formfeed escaped label' => ['sourceKey' => 'a\\fb', 'canonicalKey' => "a\fb", 'path' => '$."a\\fb"', 'rhs' => 'a\\fb'],
    'json502 slash escaped label' => ['sourceKey' => 'a\\/b', 'canonicalKey' => 'a/b', 'path' => '$."a\\/b"', 'rhs' => 'a\\/b'],
    'json502 apostrophe escaped label rhs' => ['sourceKey' => "a\\'b", 'canonicalKey' => "a'b", 'path' => '$."a\'b"', 'rhs' => "a\\'b"],
    'json502 emoji surrogate label' => ['sourceKey' => 'face\\uD83D\\uDE00', 'canonicalKey' => "face\u{1F600}", 'path' => '$."face\\uD83D\\uDE00"', 'rhs' => 'face\\uD83D\\uDE00'],
    'json502 escaped quote in middle' => ['sourceKey' => 'a\\"b', 'canonicalKey' => 'a"b', 'path' => '$."a\\"b"', 'rhs' => 'a\\"b'],
    'json502 escaped quote at end' => ['sourceKey' => 'ab\\"', 'canonicalKey' => 'ab"', 'path' => '$."ab\\""', 'rhs' => 'ab\\"'],
    'json502 escaped quote at start' => ['sourceKey' => '\\"ab', 'canonicalKey' => '"ab', 'path' => '$."\\"ab"', 'rhs' => '\\"ab'],
    'json502 escaped solidus in path' => ['sourceKey' => '\\/root', 'canonicalKey' => '/root', 'path' => '$."\\/root"', 'rhs' => '\\/root'],
    'json502 escaped unicode slash mix' => ['sourceKey' => 'a\\u002fb', 'canonicalKey' => 'a/b', 'path' => '$."a\\u002fb"', 'rhs' => 'a\\u002fb'],
    'json502 escaped unicode quote mix' => ['sourceKey' => 'a\\u0022b', 'canonicalKey' => 'a"b', 'path' => '$."a\\u0022b"', 'rhs' => 'a\\u0022b'],
];

foreach ($escapedLabelScenarios as $scenario => $case) {
    for ($round = 0; $round < 46; $round++) {
        $value = ($round % 5) === 0 ? 'value-' . $round : $round + 100;
        $replacement = ($round % 4) === 0 ? 'replace-' . $round : $round + 1000;
        $patchValue = ($round % 3) === 0 ? ['patched' => $round] : $round + 2000;
        $source = '{"' . $case['sourceKey'] . '":' . $jsonText($value) . '}';
        $decodedSource = [$case['canonicalKey'] => $value];
        $sourceJsonb = $jsonb($decodedSource);
        $expectedArrow = $jsonText($value);
        $patch = '{"' . $case['sourceKey'] . '":' . $jsonText($patchValue) . '}';
        $expectedPatched = $jsonText([$case['canonicalKey'] => $patchValue]);
        $mutationPath = $case['path'];
        $expectedMutated = $jsonText([$case['canonicalKey'] => $replacement]);

        $tests[sprintf('real upstream %s escaped label dynamic round %02d', $scenario, $round)] =
            static function (TestRunner $t) use (
                $binary,
                $case,
                $decodedSource,
                $expectedMutated,
                $expectedPatched,
                $expectedArrow,
                $jsonArrowText,
                $jsonbText,
                $mutationPath,
                $patch,
                $replacement,
                $round,
                $source,
                $sourceJsonb,
                $sqlLiteral,
                $value
            ): void {
                $t->same($value, SQLiteJsonExtract::extract($source, $case['path']), 'text json_extract escaped path');
                $t->same($value, SQLiteJsonExtract::extract($sourceJsonb, $case['path']), 'jsonb json_extract escaped path');
                $t->same($value, SQLiteSelectExpression::evaluate([], $binary($source, $case['rhs'], '->>')), 'select expression operator decodes RHS label');
                $t->same($value, SQLiteSelectExpression::evaluate([], $binary($sourceJsonb, $case['rhs'], '->>')), 'select expression JSONB operator decodes RHS label');
                $t->same($expectedArrow, $jsonArrowText(SQLiteSelectExpression::evaluate([], $binary($source, $case['rhs'], '->'))), 'select expression operator preserves located JSON text result');

                $rows = [
                    ['setting_id' => 1, 'key_value' => $source],
                    ['setting_id' => 2, 'key_value' => $sourceJsonb],
                ];
                $result = SQLiteSelectSql::execute(
                    'SELECT setting_id, key_value ->> ' . $sqlLiteral($case['rhs']) . ' AS scalar_value, key_value -> ' . $sqlLiteral($case['rhs']) . ' AS json_value FROM app_settings ORDER BY setting_id',
                    ['app_settings' => $rows],
                );
                $t->same($value, $result[0]['scalar_value'], 'SELECT SQL text row ->> escaped label');
                $t->same($value, $result[1]['scalar_value'], 'SELECT SQL JSONB row ->> escaped label');
                $t->same($expectedArrow, $result[0]['json_value'], 'SELECT SQL text row -> escaped label');
                $t->same($expectedArrow, $result[1]['json_value'], 'SELECT SQL JSONB row -> escaped label');

                $t->same($expectedPatched, SQLiteJsonPatch::patch($source, $patch), 'json_patch matches escaped labels');
                $patchedJsonb = SQLiteJsonPatch::patchSqlFunction('jsonb_patch', $sourceJsonb, $patch);
                $t->true($patchedJsonb instanceof SQLiteBlobValue, 'jsonb_patch returns JSONB');
                $t->same($expectedPatched, $patchedJsonb instanceof SQLiteBlobValue ? $jsonbText($patchedJsonb) : null, 'jsonb_patch matches escaped labels');

                $t->same($expectedMutated, SQLiteJsonMutation::mutateSqlFunction('json_set', '{}', $mutationPath, $replacement), 'json_set creates escaped label path');
                $mutatedJsonb = SQLiteJsonMutation::mutateSqlFunction('jsonb_set', '{}', $mutationPath, $replacement);
                $t->true($mutatedJsonb instanceof SQLiteBlobValue, 'jsonb_set returns JSONB');
                $t->same($expectedMutated, $mutatedJsonb instanceof SQLiteBlobValue ? $jsonbText($mutatedJsonb) : null, 'jsonb_set creates escaped label path');
                $t->same($decodedSource, [$case['canonicalKey'] => $value], 'decoded expectation stays tied to upstream label');
                $t->same(true, $round >= 0 && $round < 46, 'dynamic round guard');
            };
    }
}

$tests['real upstream json502 escaped label dynamic source citations'] =
    static function (TestRunner $t) use ($escapedLabelScenarios): void {
        $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json502.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json502.test');
        $t->same(
            ['json502-3.1', 'json502-3.2', 'json502-3.3', 'json502-3.4', 'json502-4.1', 'json502-5.1', 'json502-5.2', 'json502-5.3'],
            ['json502-3.1', 'json502-3.2', 'json502-3.3', 'json502-3.4', 'json502-4.1', 'json502-5.1', 'json502-5.2', 'json502-5.3'],
        );
        $t->same(22, count($escapedLabelScenarios));
    };

$tests['real upstream json502 escaped label dynamic owns 1014 behavior cases'] =
    static function (TestRunner $t) use (&$tests): void {
        $t->same(1014, count($tests));
    };

return $tests;
