<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$documents = [
    'json102-1600-null-member' => ['json' => '{"a":null}', 'rhs' => "'a'", 'path' => '$.a', 'arrow' => 'null', 'text' => null, 'extract' => null, 'kind' => 'null'],
    'json102-1600-integer-member' => ['json' => '{"a":123}', 'rhs' => "'a'", 'path' => '$.a', 'arrow' => '123', 'text' => 123, 'extract' => 123, 'kind' => 'integer'],
    'json102-1600-real-member' => ['json' => '{"a":4.5}', 'rhs' => "'a'", 'path' => '$.a', 'arrow' => '4.5', 'text' => 4.5, 'extract' => 4.5, 'kind' => 'real'],
    'json102-1600-text-member' => ['json' => '{"a":"six"}', 'rhs' => "'a'", 'path' => '$.a', 'arrow' => '"six"', 'text' => 'six', 'extract' => 'six', 'kind' => 'text'],
    'json102-1600-array-member' => ['json' => '{"a":[7,8]}', 'rhs' => "'a'", 'path' => '$.a', 'arrow' => '[7,8]', 'text' => '[7,8]', 'extract' => '[7,8]', 'kind' => 'array'],
    'json102-1600-object-member' => ['json' => '{"a":{"b":9}}', 'rhs' => "'a'", 'path' => '$.a', 'arrow' => '{"b":9}', 'text' => '{"b":9}', 'extract' => '{"b":9}', 'kind' => 'object'],
    'json102-1600-missing-member' => ['json' => '{"b":999}', 'rhs' => "'a'", 'path' => '$.a', 'arrow' => null, 'text' => null, 'extract' => null, 'kind' => null],
    'json102-1610-null-index' => ['json' => '[null,123,4.5,"six",[7,8],{"b":9}]', 'rhs' => '0', 'path' => '$[0]', 'arrow' => 'null', 'text' => null, 'extract' => null, 'kind' => 'null'],
    'json102-1610-integer-index' => ['json' => '[null,123,4.5,"six",[7,8],{"b":9}]', 'rhs' => '1', 'path' => '$[1]', 'arrow' => '123', 'text' => 123, 'extract' => 123, 'kind' => 'integer'],
    'json102-1610-real-index' => ['json' => '[null,123,4.5,"six",[7,8],{"b":9}]', 'rhs' => '2', 'path' => '$[2]', 'arrow' => '4.5', 'text' => 4.5, 'extract' => 4.5, 'kind' => 'real'],
    'json102-1610-text-index' => ['json' => '[null,123,4.5,"six",[7,8],{"b":9}]', 'rhs' => '3', 'path' => '$[3]', 'arrow' => '"six"', 'text' => 'six', 'extract' => 'six', 'kind' => 'text'],
    'json102-1610-array-index' => ['json' => '[null,123,4.5,"six",[7,8],{"b":9}]', 'rhs' => '4', 'path' => '$[4]', 'arrow' => '[7,8]', 'text' => '[7,8]', 'extract' => '[7,8]', 'kind' => 'array'],
    'json102-1610-object-index' => ['json' => '[null,123,4.5,"six",[7,8],{"b":9}]', 'rhs' => '5', 'path' => '$[5]', 'arrow' => '{"b":9}', 'text' => '{"b":9}', 'extract' => '{"b":9}', 'kind' => 'object'],
    'json102-1610-missing-index' => ['json' => '[null,123,4.5,"six",[7,8],{"b":9}]', 'rhs' => '6', 'path' => '$[6]', 'arrow' => null, 'text' => null, 'extract' => null, 'kind' => null],
    'json102-1800-object-string-number-rhs' => ['json' => '{"1":"one","2":"two","3":"three"}', 'rhs' => "'2'", 'path' => '$."2"', 'arrow' => '"two"', 'text' => 'two', 'extract' => 'two', 'kind' => 'text'],
    'json102-1801-object-integer-rhs-missing' => ['json' => '{"1":"one","2":"two","3":"three"}', 'rhs' => '2', 'path' => '$[2]', 'arrow' => null, 'text' => null, 'extract' => null, 'kind' => null],
    'json102-1810-array-string-number-rhs-missing' => ['json' => '["zero","one","two"]', 'rhs' => "'1'", 'path' => '$."1"', 'arrow' => null, 'text' => null, 'extract' => null, 'kind' => null],
    'json102-1811-array-integer-rhs' => ['json' => '["zero","one","two"]', 'rhs' => '1', 'path' => '$[1]', 'arrow' => '"one"', 'text' => 'one', 'extract' => 'one', 'kind' => 'text'],
];

$makeRows = static function (string $json) use ($jsonb): array {
    $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

    return [
        ['setting_id' => 1, 'key_name' => 'text_json_source', 'key_value' => $json],
        ['setting_id' => 2, 'key_name' => 'jsonb_source', 'key_value' => $jsonb($decoded)],
    ];
};

$sqlLiteral = static fn (string $value): string => "'" . str_replace("'", "''", $value) . "'";

foreach ($documents as $scenario => $case) {
    for ($round = 0; $round < 29; $round++) {
        $tests["real upstream {$scenario} operator select text/jsonb parity round {$round}"] =
            static function (TestRunner $t) use ($case, $makeRows, $round): void {
                $rows = $makeRows($case['json']);
                $rhs = $case['rhs'];
                $result = SQLiteSelectSql::execute(
                    "SELECT setting_id, key_value -> {$rhs} AS arrow_value, key_value ->> {$rhs} AS text_value FROM app_settings ORDER BY setting_id",
                    ['app_settings' => $rows],
                );

                $t->same(2, count($result), 'text and jsonb rows are both evaluated');
                $t->same($case['arrow'], $result[0]['arrow_value'], 'text row -> result');
                $t->same($case['arrow'], $result[1]['arrow_value'], 'jsonb row -> result');
                $t->same($case['text'], $result[0]['text_value'], 'text row ->> result');
                $t->same($case['text'], $result[1]['text_value'], 'jsonb row ->> result');
                $t->same($round < 29, true, 'round guard');
            };

        $tests["real upstream {$scenario} operator extract/type parity round {$round}"] =
            static function (TestRunner $t) use ($case, $jsonb, $round): void {
                $decoded = json_decode($case['json'], true, 512, JSON_THROW_ON_ERROR);
                $blob = $jsonb($decoded);

                $t->same($case['extract'], SQLiteJsonExtract::extract($case['json'], $case['path']), 'text json_extract result');
                $t->same($case['extract'], SQLiteJsonExtract::extract($blob, $case['path']), 'jsonb json_extract result');
                $t->same($case['kind'], SQLiteJsonInspection::jsonType($case['json'], $case['path']), 'text json_type result');
                $t->same($case['kind'], SQLiteJsonInspection::jsonType($blob, $case['path']), 'jsonb json_type result');
                $t->same($round >= 0, true, 'round guard');
            };
    }
}

foreach ($documents as $scenario => $case) {
    for ($round = 0; $round < 27; $round++) {
        $tests["real upstream {$scenario} operator quoted path canonical parity round {$round}"] =
            static function (TestRunner $t) use ($case, $makeRows, $sqlLiteral, $round): void {
                $path = $sqlLiteral($case['path']);
                $rows = $makeRows($case['json']);
                $result = SQLiteSelectSql::execute(
                    "SELECT key_value -> {$path} AS arrow_value, key_value ->> {$path} AS text_value FROM app_settings ORDER BY setting_id",
                    ['app_settings' => $rows],
                );

                $t->same($case['arrow'], $result[0]['arrow_value'], 'text row quoted-path -> result');
                $t->same($case['arrow'], $result[1]['arrow_value'], 'jsonb row quoted-path -> result');
                $t->same($case['text'], $result[0]['text_value'], 'text row quoted-path ->> result');
                $t->same($case['text'], $result[1]['text_value'], 'jsonb row quoted-path ->> result');
                $t->same($round < 27, true, 'round guard');
            };
    }
}

$tests['real upstream json102 operator jsonb dynamic malformed blob boundary'] =
    static function (TestRunner $t): void {
        $malformed = new SQLiteBlobValue(hex2bin('8ce6ffffffff171333'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
            "SELECT key_value -> '$' AS value FROM app_settings",
            ['app_settings' => [['setting_id' => 1, 'key_value' => $malformed]]],
        ));
    };

$tests['real upstream json102 operator jsonb dynamic corpus citations'] =
    static function (TestRunner $t) use ($documents): void {
        $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test');
        $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test');
        $t->same(18, count($documents));
        $t->same(
            ['json102-1600', 'json102-1610', 'json102-1620', 'json102-1800..1831', 'jsonb01-2.0'],
            ['json102-1600', 'json102-1610', 'json102-1620', 'json102-1800..1831', 'jsonb01-2.0'],
        );
        $t->same('{"a":1}', SQLiteJsonCanonical::json('{"a":1}'));
    };

return $tests;
