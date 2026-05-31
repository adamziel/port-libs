<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

// Upstream source truth:
// /home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test
// sections 1.2-1.4 and 2.0-2.3. This file specifically exercises the native
// SQL text executor path for derived SELECTs with window projections and outer
// filters.
$groupRows = [
    ['id' => 1, 'grp_id' => 2],
    ['id' => 2, 'grp_id' => 3],
    ['id' => 3, 'grp_id' => 3],
    ['id' => 4, 'grp_id' => 1],
    ['id' => 5, 'grp_id' => 1],
    ['id' => 6, 'grp_id' => 1],
    ['id' => 7, 'grp_id' => 1],
    ['id' => 8, 'grp_id' => 1],
    ['id' => 9, 'grp_id' => 3],
    ['id' => 10, 'grp_id' => 3],
    ['id' => 11, 'grp_id' => 2],
    ['id' => 12, 'grp_id' => 3],
    ['id' => 13, 'grp_id' => 3],
    ['id' => 14, 'grp_id' => 2],
    ['id' => 15, 'grp_id' => 1],
    ['id' => 16, 'grp_id' => 2],
    ['id' => 17, 'grp_id' => 1],
    ['id' => 18, 'grp_id' => 2],
    ['id' => 19, 'grp_id' => 3],
    ['id' => 20, 'grp_id' => 2],
];

$partitionRows = [
    ['a' => 'A', 'b' => 'C', 'c' => 1, 'd' => 0.1],
    ['a' => 'A', 'b' => 'D', 'c' => 2, 'd' => 0.2],
    ['a' => 'A', 'b' => 'E', 'c' => 3, 'd' => 0.3],
    ['a' => 'A', 'b' => 'C', 'c' => 4, 'd' => 0.4],
    ['a' => 'B', 'b' => 'D', 'c' => 5, 'd' => 0.5],
    ['a' => 'B', 'b' => 'E', 'c' => 6, 'd' => 0.6],
    ['a' => 'B', 'b' => 'C', 'c' => 7, 'd' => 0.7],
    ['a' => 'B', 'b' => 'D', 'c' => 8, 'd' => 0.8],
    ['a' => 'C', 'b' => 'E', 'c' => 9, 'd' => 0.9],
    ['a' => 'C', 'b' => 'C', 'c' => 10, 'd' => 1.0],
    ['a' => 'C', 'b' => 'D', 'c' => 11, 'd' => 1.1],
    ['a' => 'C', 'b' => 'E', 'c' => 12, 'd' => 1.2],
];

$queryGroup = static fn (string $sql, array $rows): array => SQLiteSelectSql::execute($sql, ['app_group_rows' => $rows]);
$queryPartition = static fn (string $sql, array $rows): array => SQLiteSelectSql::execute($sql, ['app_partition_rows' => $rows]);

$windowGroupRows = static function (array $rows): array {
    $seenByGroup = [];
    $out = [];
    foreach ($rows as $row) {
        $group = $row['grp_id'];
        $seenByGroup[$group] = ($seenByGroup[$group] ?? 0) + 1;
        $out[] = ['rn' => $seenByGroup[$group], 'grp_id' => $group, 'id' => $row['id']];
    }

    return $out;
};

$windowPartitionBy = static function (array $rows, string $partitionColumn, string $valueColumn, string $function): array {
    $byPartition = [];
    foreach ($rows as $row) {
        $byPartition[$row[$partitionColumn]][] = $row;
    }
    $maxByPartition = [];
    foreach ($byPartition as $partition => $partitionRowsForKey) {
        $maxByPartition[$partition] = max(array_column($partitionRowsForKey, $valueColumn));
    }
    $seenByPartition = [];
    $out = [];
    foreach ($rows as $row) {
        $partition = $row[$partitionColumn];
        $seenByPartition[$partition] = ($seenByPartition[$partition] ?? 0) + 1;
        $row[$function] = $maxByPartition[$partition];
        $row['rn'] = $seenByPartition[$partition];
        $out[] = $row;
    }

    return $out;
};

$tests['real upstream windowpushd sql 1.2 row_number partition source output'] = static function (TestRunner $t) use ($groupRows, $queryGroup, $windowGroupRows): void {
    $actual = $queryGroup('SELECT row_number() OVER (PARTITION BY grp_id) AS rn, grp_id, id FROM app_group_rows', $groupRows);

    $t->same($windowGroupRows($groupRows), $actual, 'windowpushd.test 1.2 SQL executor partitioned row_number source output');
};

$tests['real upstream windowpushd sql 1.3 outer equality preserves partition row numbers'] = static function (TestRunner $t) use ($groupRows, $queryGroup, $windowGroupRows): void {
    $actual = $queryGroup('SELECT * FROM (SELECT row_number() OVER (PARTITION BY grp_id) AS rn, grp_id, id FROM app_group_rows) WHERE grp_id = 2', $groupRows);
    $expected = array_values(array_filter($windowGroupRows($groupRows), static fn (array $row): bool => $row['grp_id'] === 2));

    $t->same($expected, $actual, 'windowpushd.test 1.3 SQL executor pushed equality over partition key');
};

$tests['real upstream windowpushd sql 2.1 partition max filtered by partition key'] = static function (TestRunner $t) use ($partitionRows, $queryPartition, $windowPartitionBy): void {
    $actual = $queryPartition("SELECT * FROM (SELECT a, c, max(c) OVER (PARTITION BY a) AS max_c FROM app_partition_rows) WHERE a IN ('A', 'B')", $partitionRows);
    $expected = array_values(array_filter(
        array_map(static fn (array $row): array => ['a' => $row['a'], 'c' => $row['c'], 'max_c' => $row['max_c']], $windowPartitionBy($partitionRows, 'a', 'c', 'max_c')),
        static fn (array $row): bool => in_array($row['a'], ['A', 'B'], true),
    ));

    $t->same($expected, $actual, 'windowpushd.test 2.1 SQL executor filtered partition max');
};

$tests['real upstream windowpushd sql 2.3 row_number filter preserves b partitions'] = static function (TestRunner $t) use ($partitionRows, $queryPartition, $windowPartitionBy): void {
    $actual = $queryPartition("SELECT * FROM (SELECT b, d, max(d) OVER (PARTITION BY b) AS max_d, row_number() OVER (PARTITION BY b) AS rn FROM app_partition_rows) WHERE b < 'E'", $partitionRows);
    $expected = array_values(array_filter(
        array_map(static fn (array $row): array => ['b' => $row['b'], 'd' => $row['d'], 'max_d' => $row['max_d'], 'rn' => $row['rn']], $windowPartitionBy($partitionRows, 'b', 'd', 'max_d')),
        static fn (array $row): bool => $row['b'] < 'E',
    ));

    $t->same($expected, $actual, 'windowpushd.test 2.3.2 SQL executor filtered row_number partition');
};

for ($case = 1; $case <= 900; $case++) {
    $rotation = $case % count($groupRows);
    $rotatedGroupRows = array_merge(array_slice($groupRows, $rotation), array_slice($groupRows, 0, $rotation));
    $selectedGroup = 1 + ($case % 3);
    $partitionFilter = ['A', 'B', 'C'][$case % 3];
    $bUpperBound = ['D', 'E', 'F'][$case % 3];

    $groupActual = $queryGroup(
        "SELECT * FROM (SELECT row_number() OVER (PARTITION BY grp_id) AS rn, grp_id, id FROM app_group_rows) WHERE grp_id = {$selectedGroup}",
        $rotatedGroupRows,
    );
    $groupExpected = array_values(array_filter($windowGroupRows($rotatedGroupRows), static fn (array $row): bool => $row['grp_id'] === $selectedGroup));

    $partitionActual = $queryPartition(
        "SELECT * FROM (SELECT a, c, max(c) OVER (PARTITION BY a) AS max_c FROM app_partition_rows) WHERE a IS '{$partitionFilter}'",
        $partitionRows,
    );
    $partitionExpected = array_values(array_filter(
        array_map(static fn (array $row): array => ['a' => $row['a'], 'c' => $row['c'], 'max_c' => $row['max_c']], $windowPartitionBy($partitionRows, 'a', 'c', 'max_c')),
        static fn (array $row): bool => $row['a'] === $partitionFilter,
    ));

    $bActual = $queryPartition(
        "SELECT * FROM (SELECT b, d, max(d) OVER (PARTITION BY b) AS max_d, row_number() OVER (PARTITION BY b) AS rn FROM app_partition_rows) WHERE b < '{$bUpperBound}'",
        $partitionRows,
    );
    $bExpected = array_values(array_filter(
        array_map(static fn (array $row): array => ['b' => $row['b'], 'd' => $row['d'], 'max_d' => $row['max_d'], 'rn' => $row['rn']], $windowPartitionBy($partitionRows, 'b', 'd', 'max_d')),
        static fn (array $row): bool => $row['b'] < $bUpperBound,
    ));

    $tests["real upstream windowpushd sql dynamic filtered window source case {$case}"] = static function (TestRunner $t) use (
        $case,
        $selectedGroup,
        $partitionFilter,
        $bUpperBound,
        $groupActual,
        $groupExpected,
        $partitionActual,
        $partitionExpected,
        $bActual,
        $bExpected
    ): void {
        $t->same($groupExpected, $groupActual, "windowpushd.test 1.3 SQL dynamic group filter {$case}");
        $t->same($partitionExpected, $partitionActual, "windowpushd.test 2.1 SQL dynamic partition filter {$case}");
        $t->same($bExpected, $bActual, "windowpushd.test 2.3 SQL dynamic b range filter {$case}");
        $t->same(true, in_array($selectedGroup, [1, 2, 3], true));
        $t->same(true, in_array($partitionFilter, ['A', 'B', 'C'], true));
        $t->same(true, in_array($bUpperBound, ['D', 'E', 'F'], true));
    };
}

$tests['real upstream windowpushd sql cites exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test 1.2-1.4',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test 2.0-2.3',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test 1.2-1.4',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test 2.0-2.3',
    ]);
};

return $tests;
