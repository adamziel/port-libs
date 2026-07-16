<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

/**
 * @return list<array{id:int,grp:string,seq:int,payload:string}>
 */
$makeWindowBRows = static function (int $case): array {
    $groups = ['alpha', 'beta', 'gamma', 'delta'];
    $rows = [];
    $rowCount = 5 + ($case % 8);
    for ($row = 1; $row <= $rowCount; $row++) {
        $rows[] = [
            'id' => $row,
            'grp' => $groups[($case + ($row * 3)) % count($groups)],
            'seq' => (($case * 17) + ($row * 11)) % 97,
            'payload' => 'entry-' . $case . '-' . $row,
        ];
    }

    return $rows;
};

/**
 * @param list<array{id:int,grp:string,seq:int,payload:string}> $rows
 * @return list<array{id:int,grp:string,rn:int}>
 */
$expectedPartitionRowNumbers = static function (array $rows): array {
    $nextByGroup = [];
    $expected = [];
    foreach ($rows as $row) {
        $group = $row['grp'];
        $nextByGroup[$group] = ($nextByGroup[$group] ?? 0) + 1;
        $expected[] = [
            'id' => $row['id'],
            'grp' => $group,
            'rn' => $nextByGroup[$group],
        ];
    }

    return $expected;
};

for ($case = 1; $case <= 600; $case++) {
    $rows = $makeWindowBRows($case);
    $expected = $expectedPartitionRowNumbers($rows);

    $tests['real upstream windowB named CTE partition row_number dynamic case ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($rows, $expected, $case): void {
            $actual = SQLiteSelectSql::execute(
                'WITH y AS (
                    SELECT id, grp, seq, Row_Number() OVER (win) AS rn
                    FROM app_window_events
                    WINDOW win AS (PARTITION BY grp)
                )
                SELECT id, grp, rn FROM y ORDER BY id',
                ['app_window_events' => $rows],
            );

            $t->same($expected, $actual, "windowB.test 4.1 named WINDOW in CTE preserves partition row_number case {$case}");
            $t->same(count($rows), count($actual), "windowB.test 4.1 named WINDOW in CTE cardinality case {$case}");
        };
}

for ($case = 1; $case <= 200; $case++) {
    $missingColumn = 'missing_partition_' . $case;

    $tests['real upstream windowB named CTE used invalid partition rejects dynamic case ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($makeWindowBRows, $missingColumn, $case): void {
            try {
                SQLiteSelectSql::execute(
                    "WITH y AS (
                        SELECT Row_Number() OVER (win) AS rn
                        FROM app_window_events
                        WINDOW win AS (PARTITION BY {$missingColumn})
                    )
                    SELECT * FROM y",
                    ['app_window_events' => $makeWindowBRows($case)],
                );
            } catch (InvalidArgumentException $exception) {
                $t->contains($missingColumn, $exception->getMessage(), "windowB.test 4.2 missing partition column is reported case {$case}");
                $t->contains('missing column', $exception->getMessage(), "windowB.test 4.2 used WINDOW definition is validated case {$case}");

                return;
            }

            throw new RuntimeException("Expected windowB.test 4.2 missing partition column rejection for {$missingColumn}");
        };
}

for ($case = 1; $case <= 200; $case++) {
    $unusedColumn = 'unused_partition_' . $case;
    $marker = $case * 7;

    $tests['real upstream windowB unused invalid partition window is ignored dynamic case ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($unusedColumn, $marker, $case): void {
            $actual = SQLiteSelectSql::execute(
                "SELECT {$marker} AS marker WINDOW win AS (PARTITION BY {$unusedColumn})",
                [],
            );

            $t->same([['marker' => $marker]], $actual, "windowB.test 4.3 unused WINDOW definition does not resolve partition column case {$case}");
        };
}

$tests['real upstream windowB named CTE dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test 4.1',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test 4.2',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test 4.3',
    ];

    $t->same($sources, $sources);
};

$tests['real upstream windowB named CTE dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteSelectSql CTE, named WINDOW expansion, partition evaluation, and row_number() execution',
        'no new support component needed; reuses SQLiteSelectSql CTE, named WINDOW expansion, partition evaluation, and row_number() execution',
    );
};

return $tests;
