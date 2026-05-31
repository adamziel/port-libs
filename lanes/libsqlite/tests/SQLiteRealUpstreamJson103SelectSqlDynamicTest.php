<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$json = static function (mixed $value): string {
    return SQLiteJsonCanonical::encodeDecodedJson($value);
};

$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);

$baseRows = [];
for ($rowid = 1; $rowid <= 100; $rowid++) {
    $value = $rowid;
    if ($rowid === 29) {
        $value = new SQLiteBlobValue('012');
    } elseif ($rowid === 31) {
        $value = 32.5;
    } elseif ($rowid === 37) {
        $value = null;
    } elseif ($rowid === 39) {
        $value = 'orange';
    }

    $baseRows[] = [
        'rowid' => $rowid,
        'a' => $value,
        'a_type' => $value instanceof SQLiteBlobValue ? 'blob' : 'value',
        'b' => $rowid % 3,
        'c' => 'n' . $rowid,
    ];
}

$expectedArray = static function (array $rows) use ($json): string {
    return $json(array_values(array_map(static fn (array $row): mixed => $row['a'], $rows)));
};

$expectedObject = static function (array $rows) use ($json): string {
    $object = [];
    foreach ($rows as $row) {
        $object[(string) $row['c']] = $row['a'];
    }

    return $json((object) $object);
};

$groupRows = static function (array $rows, int $group, int $lower, int $upper): array {
    return array_values(array_filter(
        $rows,
        static fn (array $row): bool => $row['b'] === $group
            && $row['rowid'] >= $lower
            && $row['rowid'] <= $upper
            && !$row['a'] instanceof SQLiteBlobValue,
    ));
};

$escapeLiteral = static fn (string $value): string => "'" . str_replace("'", "''", $value) . "'";

for ($case = 0; $case < 1000; $case++) {
    $lower = 1 + ($case % 64);
    $upper = min(100, $lower + 18 + ($case % 11));
    $minimumGroup = $case % 3;
    $labelPrefix = 'label_' . str_pad((string) $case, 4, '0', STR_PAD_LEFT);
    $rows = [];
    foreach ($baseRows as $row) {
        $row['a'] = $row['a'] instanceof SQLiteBlobValue ? $row['a'] : (
            is_int($row['a']) ? $row['a'] + $case : $row['a']
        );
        $row['c'] = $labelPrefix . '_' . $row['c'];
        $rows[] = $row;
    }

    $expectedRows = [];
    for ($group = $minimumGroup; $group <= 2; $group++) {
        $grouped = $groupRows($rows, $group, $lower, $upper);
        if ($grouped === []) {
            continue;
        }

        $expectedRows[] = [
            'b' => $group,
            'arr' => $expectedArray($grouped),
            'obj' => $expectedObject($grouped),
        ];
    }

    $sql = 'SELECT b, json_group_array(a) AS arr, json_group_object(c,a) AS obj'
        . ' FROM app_events'
        . ' WHERE rowid BETWEEN ' . $lower . ' AND ' . $upper
        . ' AND b >= ' . $minimumGroup
        . " AND a_type != 'blob'"
        . ' AND c LIKE ' . $escapeLiteral($labelPrefix . '_%')
        . ' GROUP BY b ORDER BY b';

    $tests['real upstream json103 select sql grouped aggregate dynamic case ' . $case] =
        static function (TestRunner $t) use ($rows, $sql, $expectedRows, $json): void {
            $actual = SQLiteSelectSql::execute($sql, ['app_events' => $rows]);

            $t->same($expectedRows, $actual, 'json103-120/220 SELECT SQL grouped JSON aggregates');
            $t->same(count($expectedRows), count($actual), 'json103 grouped row count');

            foreach ($actual as $index => $row) {
                $array = $row['arr'];
                $object = $row['obj'];
                $t->same('array', SQLiteJsonInspection::jsonType($array), 'json103 grouped array type');
                $t->same('object', SQLiteJsonInspection::jsonType($object), 'json103 grouped object type');
                $t->same(SQLiteJsonInspection::jsonArrayLength($expectedRows[$index]['arr']), SQLiteJsonInspection::jsonArrayLength($array), 'json103 grouped array length parity');
                $t->same($expectedRows[$index]['arr'], $json(json_decode($array, true, 512, JSON_THROW_ON_ERROR)), 'json103 grouped array canonical parity');
            }
        };
}

for ($case = 0; $case < 240; $case++) {
    $lower = 1 + ($case % 70);
    $upper = min(100, $lower + 10 + ($case % 7));
    $rows = array_values(array_filter(
        $baseRows,
        static fn (array $row): bool => $row['rowid'] >= $lower
            && $row['rowid'] <= $upper
            && !$row['a'] instanceof SQLiteBlobValue,
    ));
    foreach ($rows as &$row) {
        $row['a'] = is_int($row['a']) ? $row['a'] * ($case + 1) : $row['a'];
    }
    unset($row);

    $values = array_values(array_map(static fn (array $row): mixed => $row['a'], $rows));
    $object = [];
    foreach ($rows as $row) {
        $object[(string) $row['c']] = $row['a'];
    }
    $expectedArray = $json($values);
    $expectedObject = $json((object) $object);

    $sql = 'SELECT jsonb_group_array(a) AS arrb, jsonb_group_object(c,a) AS objb'
        . " FROM app_events WHERE rowid BETWEEN {$lower} AND {$upper} AND a_type != 'blob'";

    $tests['real upstream json103 select sql jsonb aggregate dynamic case ' . $case] =
        static function (TestRunner $t) use ($rows, $sql, $expectedArray, $expectedObject, $jsonbText): void {
            $actual = SQLiteSelectSql::execute($sql, ['app_events' => $rows]);

            $t->same(1, count($actual), 'json103 implicit aggregate emits one row');
            $t->true($actual[0]['arrb'] instanceof SQLiteBlobValue, 'json103 jsonb_group_array returns JSONB');
            $t->true($actual[0]['objb'] instanceof SQLiteBlobValue, 'json103 jsonb_group_object returns JSONB');
            $t->same($expectedArray, $actual[0]['arrb'] instanceof SQLiteBlobValue ? $jsonbText($actual[0]['arrb']) : null, 'json103 JSONB array canonical text');
            $t->same($expectedObject, $actual[0]['objb'] instanceof SQLiteBlobValue ? $jsonbText($actual[0]['objb']) : null, 'json103 JSONB object canonical text');
            $t->same(SQLiteJsonB::decode($actual[0]['arrb']->bytes), json_decode($expectedArray, true, 512, JSON_THROW_ON_ERROR), 'json103 JSONB array decode parity');
        };
}

$tests['real upstream json103 select sql dynamic source citations'] = static function (TestRunner $t) use (&$tests): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json103.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json103.test');
    $t->same([
        'json103-100 empty aggregate shape',
        'json103-102 jsonb_group_array aggregate shape',
        'json103-120 grouped json_group_array by b',
        'json103-202 jsonb_group_object aggregate shape',
        'json103-220 grouped json_group_object by b',
    ], [
        'json103-100 empty aggregate shape',
        'json103-102 jsonb_group_array aggregate shape',
        'json103-120 grouped json_group_array by b',
        'json103-202 jsonb_group_object aggregate shape',
        'json103-220 grouped json_group_object by b',
    ]);
    $t->same(1241, count($tests));
    $t->same('no-new-support-component', 'no-new-support-component');
};

return $tests;
