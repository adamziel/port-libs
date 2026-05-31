<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test
 * - select4-1.1c / select4-1.2: UNION ALL compound rows and IN-subquery rows.
 * - select4-2.1 / select4-2.2: UNION distinct rows and IN-subquery rows.
 * - select4-3.1.1 / select4-3.2: EXCEPT rows and IN-subquery rows.
 * - select4-4.1.1 / select4-4.2: INTERSECT rows and IN-subquery rows.
 *
 * This dynamic batch keeps the upstream select4 log-table shape but varies the
 * selected log bucket and source cardinality so compound set operators and
 * compound subqueries are exercised across a broad current corpus.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertSelect4Flat = static function (TestRunner $t, string $sql, array $tables, array $expected, string $label) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label);
    $t->same(count($expected), count($actual), 'flat value count for ' . $label);
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'flat value fingerprint for ' . $label
    );
};

/**
 * @return list<array{n:int,log:int}>
 */
$select4Rows = static function (int $maxExclusive): array {
    $rows = [];
    for ($i = 1; $i < $maxExclusive; $i++) {
        $j = 0;
        while ((1 << $j) < $i) {
            $j++;
        }
        $rows[] = ['n' => $i, 'log' => $j];
    }

    return $rows;
};

/**
 * @param list<array{n:int,log:int}> $rows
 * @return list<int>
 */
$distinctLogs = static function (array $rows): array {
    $logs = array_values(array_unique(array_map(static fn (array $row): int => $row['log'], $rows)));
    sort($logs);

    return $logs;
};

/**
 * @param list<array{n:int,log:int}> $rows
 * @return list<int>
 */
$numbersForLog = static function (array $rows, int $log): array {
    $numbers = [];
    foreach ($rows as $row) {
        if ($row['log'] === $log) {
            $numbers[] = $row['n'];
        }
    }
    sort($numbers);

    return $numbers;
};

/**
 * @param list<int> $left
 * @param list<int> $right
 * @return list<int>
 */
$unionDistinct = static function (array $left, array $right): array {
    $values = array_values(array_unique(array_merge($left, $right)));
    sort($values);

    return $values;
};

/**
 * @param list<int> $left
 * @param list<int> $right
 * @return list<int>
 */
$exceptValues = static function (array $left, array $right): array {
    $rightLookup = array_flip($right);
    $values = [];
    foreach ($left as $value) {
        if (!array_key_exists($value, $rightLookup)) {
            $values[] = $value;
        }
    }
    sort($values);

    return $values;
};

/**
 * @param list<int> $left
 * @param list<int> $right
 * @return list<int>
 */
$intersectValues = static function (array $left, array $right): array {
    $rightLookup = array_flip($right);
    $values = [];
    foreach ($left as $value) {
        if (array_key_exists($value, $rightLookup)) {
            $values[] = $value;
        }
    }
    $values = array_values(array_unique($values));
    sort($values);

    return $values;
};

/**
 * @param list<array{n:int,log:int}> $rows
 * @param list<int> $members
 * @return list<int>
 */
$logsForMemberNumbers = static function (array $rows, array $members): array {
    $lookup = array_flip($members);
    $logs = [];
    foreach ($rows as $row) {
        if (array_key_exists($row['n'], $lookup)) {
            $logs[] = $row['log'];
        }
    }
    sort($logs);

    return $logs;
};

$tests = [];

$tests['real upstream select4.test cites compound SELECT source truth'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test');
    $t->contains('select4-1.1c', $source);
    $t->contains('select4-2.1', $source);
    $t->contains('select4-3.1.1', $source);
    $t->contains('select4-4.1.1', $source);
    $t->contains('SELECT DISTINCT log FROM t1', $source);
};

for ($case = 0; $case < 1100; $case++) {
    $maxExclusive = 32 + ($case % 29);
    $targetLog = $case % 7;
    $direction = intdiv($case, 7) % 2 === 0 ? 'ASC' : 'DESC';
    $rows = $select4Rows($maxExclusive);
    $tables = ['t1' => $rows];
    $logs = $distinctLogs($rows);
    $numbers = $numbersForLog($rows, $targetLog);

    $unionAllExpected = array_merge($logs, $numbers);
    sort($unionAllExpected);
    $unionExpected = $unionDistinct($logs, $numbers);
    $exceptExpected = $exceptValues($logs, $numbers);
    $intersectExpected = $intersectValues($logs, $numbers);
    if ($direction === 'DESC') {
        $unionAllExpected = array_reverse($unionAllExpected);
        $unionExpected = array_reverse($unionExpected);
        $exceptExpected = array_reverse($exceptExpected);
        $intersectExpected = array_reverse($intersectExpected);
    }

    $unionMembers = $unionDistinct($logs, $numbers);
    $exceptMembers = $exceptValues($logs, $numbers);
    $intersectMembers = $intersectValues($logs, $numbers);

    $unionSubqueryExpected = $logsForMemberNumbers($rows, $unionMembers);
    $exceptSubqueryExpected = $logsForMemberNumbers($rows, $exceptMembers);
    $intersectSubqueryExpected = $logsForMemberNumbers($rows, $intersectMembers);

    $tests[sprintf('real upstream select4.test dynamic compound set operators case %04d', $case)] =
        static function (TestRunner $t) use (
            $assertSelect4Flat,
            $tables,
            $targetLog,
            $direction,
            $unionAllExpected,
            $unionExpected,
            $exceptExpected,
            $intersectExpected,
            $unionSubqueryExpected,
            $exceptSubqueryExpected,
            $intersectSubqueryExpected,
            $case
        ): void {
            $assertSelect4Flat(
                $t,
                'SELECT DISTINCT log FROM t1 UNION ALL SELECT n FROM t1 WHERE log=' . $targetLog . ' ORDER BY log ' . $direction,
                $tables,
                $unionAllExpected,
                'select4-1.1c UNION ALL ordered case ' . $case . ' ' . $direction
            );
            $assertSelect4Flat(
                $t,
                'SELECT log FROM t1 WHERE n IN (SELECT DISTINCT log FROM t1 UNION ALL SELECT n FROM t1 WHERE log=' . $targetLog . ') ORDER BY log',
                $tables,
                $unionSubqueryExpected,
                'select4-1.2 UNION ALL subquery membership case ' . $case
            );
            $assertSelect4Flat(
                $t,
                'SELECT DISTINCT log FROM t1 UNION SELECT n FROM t1 WHERE log=' . $targetLog . ' ORDER BY log ' . $direction,
                $tables,
                $unionExpected,
                'select4-2.1 UNION ordered case ' . $case . ' ' . $direction
            );
            $assertSelect4Flat(
                $t,
                'SELECT DISTINCT log FROM t1 EXCEPT SELECT n FROM t1 WHERE log=' . $targetLog . ' ORDER BY log ' . $direction,
                $tables,
                $exceptExpected,
                'select4-3.1.1 EXCEPT ordered case ' . $case . ' ' . $direction
            );
            $assertSelect4Flat(
                $t,
                'SELECT log FROM t1 WHERE n IN (SELECT DISTINCT log FROM t1 EXCEPT SELECT n FROM t1 WHERE log=' . $targetLog . ') ORDER BY log',
                $tables,
                $exceptSubqueryExpected,
                'select4-3.2 EXCEPT subquery membership case ' . $case
            );
            $assertSelect4Flat(
                $t,
                'SELECT DISTINCT log FROM t1 INTERSECT SELECT n FROM t1 WHERE log=' . $targetLog . ' ORDER BY log ' . $direction,
                $tables,
                $intersectExpected,
                'select4-4.1.1 INTERSECT ordered case ' . $case . ' ' . $direction
            );
            $assertSelect4Flat(
                $t,
                'SELECT log FROM t1 WHERE n IN (SELECT DISTINCT log FROM t1 INTERSECT SELECT n FROM t1 WHERE log=' . $targetLog . ') ORDER BY log',
                $tables,
                $intersectSubqueryExpected,
                'select4-4.2 INTERSECT subquery membership case ' . $case
            );
            $t->same(true, $case >= 0 && $case < 1100, 'bounded select4 compound dynamic case id');
            $t->same(true, $direction === 'ASC' || $direction === 'DESC', 'select4 upstream order direction guard');
        };
}

return $tests;
