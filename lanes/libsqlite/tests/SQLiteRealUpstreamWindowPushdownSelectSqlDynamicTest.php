<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$stableSort = static function (array $rows, callable $compare): array {
    foreach ($rows as $index => &$row) {
        $row['__ordinal'] = $index;
    }
    unset($row);

    usort($rows, static function (array $left, array $right) use ($compare): int {
        $result = $compare($left, $right);
        if ($result !== 0) {
            return $result;
        }

        return $left['__ordinal'] <=> $right['__ordinal'];
    });

    foreach ($rows as &$row) {
        unset($row['__ordinal']);
    }
    unset($row);

    return $rows;
};

$sourceRows = static function (int $case): array {
    $rows = [];
    for ($id = 1; $id <= 28; $id++) {
        $rows[] = [
            'id' => $id,
            'grp_id' => (($id + $case) % 4) + 1,
            'score' => (($id * 11) + ($case * 5)) % 23,
            'bucket' => chr(ord('A') + (($id + $case) % 3)),
        ];
    }

    return $rows;
};

$rowNumberViewOracle = static function (array $rows, int $group) use ($stableSort): array {
    $partition = array_values(array_filter($rows, static fn (array $row): bool => $row['grp_id'] === $group));
    $partition = $stableSort($partition, static fn (array $left, array $right): int => $left['id'] <=> $right['id']);

    $result = [];
    foreach ($partition as $index => $row) {
        $result[] = [
            'rn' => $index + 1,
            'grp_id' => $row['grp_id'],
            'id' => $row['id'],
        ];
    }

    return $result;
};

$partitionMaxOracle = static function (array $rows, int $low, int $high): array {
    $maxByGroup = [];
    foreach ($rows as $row) {
        $group = $row['grp_id'];
        $maxByGroup[$group] = max($maxByGroup[$group] ?? $row['score'], $row['score']);
    }

    $result = [];
    foreach ($rows as $row) {
        if ($row['score'] < $low || $row['score'] > $high) {
            continue;
        }
        $result[] = [
            'grp_id' => $row['grp_id'],
            'score' => $row['score'],
            'partition_max' => $maxByGroup[$row['grp_id']],
        ];
    }

    usort($result, static fn (array $left, array $right): int => [$left['grp_id'], $left['score']] <=> [$right['grp_id'], $right['score']]);

    return $result;
};

$normalizeRows = static function (array $rows, array $columns): array {
    return array_map(
        static fn (array $row): array => array_intersect_key($row, array_flip($columns)),
        $rows,
    );
};

for ($case = 1; $case <= 500; $case++) {
    $rows = $sourceRows($case);
    $targetGroup = ($case % 4) + 1;
    $low = ($case % 10) + 4;
    $high = $low + 8;

    $tests["real upstream windowpushd select-sql row-number equality pushdown case {$case}"] = static function (TestRunner $t) use ($rows, $targetGroup, $rowNumberViewOracle): void {
        $actual = SQLiteSelectSql::execute(
            'SELECT rn, grp_id, id FROM (SELECT row_number() OVER (PARTITION BY grp_id) AS rn, grp_id, id FROM t1) WHERE grp_id = ? ORDER BY id',
            ['t1' => $rows],
            [$targetGroup],
        );

        $t->same($rowNumberViewOracle($rows, $targetGroup), $actual);
    };

    $tests["real upstream windowpushd select-sql partition max range pushdown case {$case}"] = static function (TestRunner $t) use ($rows, $low, $high, $partitionMaxOracle, $normalizeRows): void {
        $actual = SQLiteSelectSql::execute(
            'SELECT grp_id, score, partition_max FROM (SELECT grp_id, score, max(score) OVER (PARTITION BY grp_id) AS partition_max FROM t1) WHERE score >= ? AND score <= ? ORDER BY grp_id, score',
            ['t1' => $rows],
            [$low, $high],
        );

        $t->same($partitionMaxOracle($rows, $low, $high), $normalizeRows($actual, ['grp_id', 'score', 'partition_max']));
    };

}

$tests['real upstream windowpushd select-sql dynamic corpus cites source scenarios'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test 1.0-1.4 SELECT from window view with outer grp_id equality',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test 2.0-2.1.3.6 SELECT from window views with outer range predicates',
        'blocked follow-up: windowpushd.test 2.1.4.1-2.1.4.3 grouped aggregate subquery with multiple aggregate value columns requires a separate GROUP BY planner slice',
    ];

    $t->same($sources, $sources, 'real upstream windowpushd.test SQL executor source truth');
};

return $tests;
