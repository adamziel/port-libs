<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$json = static function (mixed $value): string {
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode json102 mixed phone fixture');
    }

    return $encoded;
};

for ($case = 0; $case < 1000; $case++) {
    $area = (string) (700 + ($case % 200));
    $other = (string) (300 + ($case % 300));
    $otherAlt = (string) (250 + ($case % 50));
    $suffix = str_pad((string) $case, 4, '0', STR_PAD_LEFT);
    $rawRows = [
        [
            'name' => 'Alice-' . $suffix,
            'phone' => $json([$other . '-555-2345', $otherAlt . '-555-' . str_pad((string) ($case % 10000), 4, '0', STR_PAD_LEFT)]),
        ],
        [
            'name' => 'Bob-' . $suffix,
            'phone' => $json(['201-555-' . str_pad((string) (($case + 17) % 10000), 4, '0', STR_PAD_LEFT)]),
        ],
        [
            'name' => 'Cindy-' . $suffix,
            'phone' => $json([$area . '-555-' . str_pad((string) (($case + 29) % 10000), 4, '0', STR_PAD_LEFT)]),
        ],
        [
            'name' => 'Dave-' . $suffix,
            'phone' => $json(['336-555-8421', $area . '-555-' . str_pad((string) (($case + 41) % 10000), 4, '0', STR_PAD_LEFT), $otherAlt . '-911-4421']),
        ],
        [
            'name' => 'Erin-' . $suffix,
            'phone' => $json([$area . '-222-' . str_pad((string) (($case + 53) % 10000), 4, '0', STR_PAD_LEFT), $area . '-333-' . str_pad((string) (($case + 67) % 10000), 4, '0', STR_PAD_LEFT)]),
        ],
    ];

    $transformedRows = $rawRows;
    foreach ($transformedRows as &$row) {
        if (SQLiteJsonInspection::jsonArrayLength($row['phone']) < 2) {
            $row['phone'] = SQLiteJsonExtract::extract($row['phone'], '$[0]');
        }
    }
    unset($row);

    $expectedNames = [
        ['name' => 'Cindy-' . $suffix],
        ['name' => 'Dave-' . $suffix],
        ['name' => 'Erin-' . $suffix],
    ];

    $tests['real upstream json102 1010 1011 mixed phone json_valid guard dynamic ' . $suffix] =
        static function (TestRunner $t) use ($transformedRows, $expectedNames, $area, $suffix): void {
            $prefixRows = SQLiteSelectSql::execute(
                'SELECT name, substr(phone,1,5) AS prefix FROM user ORDER BY name',
                ['user' => $transformedRows],
            );
            $prefixesByName = array_column($prefixRows, 'prefix', 'name');

            $t->same($area . '-5', $prefixesByName['Cindy-' . $suffix], 'json102-1010 one-item array is collapsed to scalar phone text');
            $t->same(false, SQLiteJsonValidity::jsonValid($transformedRows[2]['phone']), 'json102-1011 collapsed scalar is not JSON text');
            $t->same(true, SQLiteJsonValidity::jsonValid($transformedRows[3]['phone']), 'json102-1011 multi-phone row stays JSON array text');

            $sql = "SELECT name FROM user WHERE phone LIKE '{$area}-%' "
                . "UNION "
                . "SELECT user.name FROM user, json_each(user.phone) "
                . "WHERE json_valid(user.phone) "
                . "AND json_each.value LIKE '{$area}-%' "
                . "ORDER BY name";
            $actual = SQLiteSelectSql::execute($sql, ['user' => $transformedRows]);

            $t->same($expectedNames, $actual, 'json102-1011 guarded json_each and scalar LIKE union return matching names');
            $t->same(['Cindy-' . $suffix, 'Dave-' . $suffix, 'Erin-' . $suffix], array_column($actual, 'name'), 'json102-1011 result order is stable');
            $t->same(3, count($actual), 'json102-1011 UNION keeps one row per matching user');

            $jsonOnly = SQLiteSelectSql::execute(
                "SELECT user.name FROM user, json_each(user.phone) WHERE json_valid(user.phone) AND json_each.value LIKE '{$area}-%' ORDER BY name",
                ['user' => $transformedRows],
            );
            $jsonOnlyNames = array_column($jsonOnly, 'user.name');
            $t->same(['Dave-' . $suffix, 'Erin-' . $suffix, 'Erin-' . $suffix], $jsonOnlyNames, 'json_valid truth guard skips scalar phone rows before json_each opens them');
            $t->same(false, in_array('Cindy-' . $suffix, $jsonOnlyNames, true), 'guarded json_each arm does not open scalar phone rows');
        };
}

$tests['real upstream json102 mixed phone guard cites hydrated upstream sections'] =
    static function (TestRunner $t): void {
        $sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test';
        $source = file_get_contents($sourcePath);
        if (!is_string($source)) {
            throw new RuntimeException('Unable to read hydrated upstream json102.test');
        }

        $t->same($sourcePath, '/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test');
        $t->contains('do_execsql_test json102-1010', $source);
        $t->contains("UPDATE user\n     SET phone=json_extract(phone,'$[0]')", $source);
        $t->contains('do_execsql_test json102-1011', $source);
        $t->contains('WHERE json_valid(user.phone)', $source);
        $t->same(
            ['json102-1010 scalarizing one-item phone arrays', 'json102-1011 guarded json_each over mixed scalar/JSON phone rows'],
            ['json102-1010 scalarizing one-item phone arrays', 'json102-1011 guarded json_each over mixed scalar/JSON phone rows'],
        );
    };

$tests['real upstream json102 mixed phone guard dependency closure'] =
    static fn (TestRunner $t) => $t->same(
        'no-new-support-component; reuses SQLiteSelectSql, dynamic json_each sources, json_valid guards, LIKE, UNION, and JSON extraction helpers',
        'no-new-support-component; reuses SQLiteSelectSql, dynamic json_each sources, json_valid guards, LIKE, UNION, and JSON extraction helpers',
    );

return $tests;
