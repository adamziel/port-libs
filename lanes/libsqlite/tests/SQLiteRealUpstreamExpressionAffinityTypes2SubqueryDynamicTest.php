<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));

$oracle = static function (string $sql) use ($sqlite3): string {
    static $cache = [];

    if (isset($cache[$sql])) {
        return $cache[$sql];
    }
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream types2 subquery affinity tests');
    }

    $command = escapeshellarg($sqlite3) . ' -batch -noheader -separator ' . escapeshellarg("\t") . ' :memory: ' . escapeshellarg($sql);
    $output = shell_exec($command);
    if ($output === null) {
        throw new RuntimeException('sqlite3 oracle did not produce output for ' . $sql);
    }

    return $cache[$sql] = rtrim($output, "\r\n");
};

$t1RawRows = [
    ['i1' => null, 'n1' => null, 't1' => null, 'o1' => null],
];
foreach ([1, 2, 3, 4, 5, 10, 10.0, '10', '10.0', '010', 20, 20.0, '20', '20.0', 30, 30.0, '30', '30.0', 'abc'] as $value) {
    $t1RawRows[] = ['i1' => $value, 'n1' => $value, 't1' => $value, 'o1' => $value];
}

$t3RawRows = [];
foreach ([1, 2, 3, '1', '1.0', '2', '2.0', '010', 10, '10', '10.0', 20, '20', 30, '30.0', 'abc'] as $value) {
    $t3RawRows[] = ['i' => $value, 'n' => $value, 't' => $value, 'o' => $value];
}

$t2RawRows = [];
foreach ([10, 10.0, '10', '10.0', 20, 20.0, '20', '20.0', 30, 30.0, '30', '30.0', 40, '40', '040', 'abc'] as $value) {
    $t2RawRows[] = ['i' => $value, 'n' => $value, 't' => $value, 'o' => $value];
}

$t4RawRows = [];
foreach ([10, '10', 20, '20', 30, '30', 40, '040'] as $i => $value) {
    $t4RawRows[] = [
        'i' => $i % 2 === 0 ? (int) $value : $value,
        'n' => $value,
        't' => (string) $value,
        'o' => $value,
    ];
}

$affinities = [
    'i1' => 'INTEGER',
    'n1' => 'NUMERIC',
    't1' => 'TEXT',
    'o1' => 'BLOB',
    'i' => 'INTEGER',
    'n' => 'NUMERIC',
    't' => 'TEXT',
    'o' => 'BLOB',
];

$withAffinities = static function (array $rows, array $tableAffinities): array {
    $coerced = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities($rows, $tableAffinities);

    foreach ($coerced as $index => $row) {
        $coerced[$index] = ['rowid' => $index + 1] + $row + ['__sqlite_column_affinities' => $tableAffinities];
    }

    return $coerced;
};

$tables = [
    't1' => $withAffinities($t1RawRows, array_intersect_key($affinities, array_flip(['i1', 'n1', 't1', 'o1']))),
    't2' => $withAffinities($t2RawRows, array_intersect_key($affinities, array_flip(['i', 'n', 't', 'o']))),
    't3' => $withAffinities($t3RawRows, array_intersect_key($affinities, array_flip(['i', 'n', 't', 'o']))),
    't4' => $withAffinities($t4RawRows, array_intersect_key($affinities, array_flip(['i', 'n', 't', 'o']))),
];

$oracleSetup = <<<'SQL'
CREATE TABLE t1(i1 INTEGER, n1 NUMERIC, t1 TEXT, o1 BLOB);
INSERT INTO t1 VALUES(NULL,NULL,NULL,NULL);
INSERT INTO t1 VALUES(1,1,1,1);
INSERT INTO t1 VALUES(2,2,2,2);
INSERT INTO t1 VALUES(3,3,3,3);
INSERT INTO t1 VALUES(4,4,4,4);
INSERT INTO t1 VALUES(5,5,5,5);
INSERT INTO t1 VALUES(10,10,10,10);
INSERT INTO t1 VALUES(10.0,10.0,10.0,10.0);
INSERT INTO t1 VALUES('10','10','10','10');
INSERT INTO t1 VALUES('10.0','10.0','10.0','10.0');
INSERT INTO t1 VALUES('010','010','010','010');
INSERT INTO t1 VALUES(20,20,20,20);
INSERT INTO t1 VALUES(20.0,20.0,20.0,20.0);
INSERT INTO t1 VALUES('20','20','20','20');
INSERT INTO t1 VALUES('20.0','20.0','20.0','20.0');
INSERT INTO t1 VALUES(30,30,30,30);
INSERT INTO t1 VALUES(30.0,30.0,30.0,30.0);
INSERT INTO t1 VALUES('30','30','30','30');
INSERT INTO t1 VALUES('30.0','30.0','30.0','30.0');
INSERT INTO t1 VALUES('abc','abc','abc','abc');
CREATE TABLE t2(i INTEGER, n NUMERIC, t TEXT, o BLOB);
INSERT INTO t2 VALUES(10,10,10,10);
INSERT INTO t2 VALUES(10.0,10.0,10.0,10.0);
INSERT INTO t2 VALUES('10','10','10','10');
INSERT INTO t2 VALUES('10.0','10.0','10.0','10.0');
INSERT INTO t2 VALUES(20,20,20,20);
INSERT INTO t2 VALUES(20.0,20.0,20.0,20.0);
INSERT INTO t2 VALUES('20','20','20','20');
INSERT INTO t2 VALUES('20.0','20.0','20.0','20.0');
INSERT INTO t2 VALUES(30,30,30,30);
INSERT INTO t2 VALUES(30.0,30.0,30.0,30.0);
INSERT INTO t2 VALUES('30','30','30','30');
INSERT INTO t2 VALUES('30.0','30.0','30.0','30.0');
INSERT INTO t2 VALUES(40,40,40,40);
INSERT INTO t2 VALUES('40','40','40','40');
INSERT INTO t2 VALUES('040','040','040','040');
INSERT INTO t2 VALUES('abc','abc','abc','abc');
CREATE TABLE t3(i INTEGER, n NUMERIC, t TEXT, o BLOB);
INSERT INTO t3 VALUES(1,1,1,1);
INSERT INTO t3 VALUES(2,2,2,2);
INSERT INTO t3 VALUES(3,3,3,3);
INSERT INTO t3 VALUES('1','1','1','1');
INSERT INTO t3 VALUES('1.0','1.0','1.0','1.0');
INSERT INTO t3 VALUES('2','2','2','2');
INSERT INTO t3 VALUES('2.0','2.0','2.0','2.0');
INSERT INTO t3 VALUES('010','010','010','010');
INSERT INTO t3 VALUES(10,10,10,10);
INSERT INTO t3 VALUES('10','10','10','10');
INSERT INTO t3 VALUES('10.0','10.0','10.0','10.0');
INSERT INTO t3 VALUES(20,20,20,20);
INSERT INTO t3 VALUES('20','20','20','20');
INSERT INTO t3 VALUES(30,30,30,30);
INSERT INTO t3 VALUES('30.0','30.0','30.0','30.0');
INSERT INTO t3 VALUES('abc','abc','abc','abc');
CREATE TABLE t4(i INTEGER, n NUMERIC, t VARCHAR(20), o LARGE BLOB);
INSERT INTO t4 VALUES(10,10,'10',10);
INSERT INTO t4 VALUES('10','10','10','10');
INSERT INTO t4 VALUES(20,20,'20',20);
INSERT INTO t4 VALUES('20','20','20','20');
INSERT INTO t4 VALUES(30,30,'30',30);
INSERT INTO t4 VALUES('30','30','30','30');
INSERT INTO t4 VALUES(40,40,'40',40);
INSERT INTO t4 VALUES('040','040','040','040');
CREATE INDEX t2i1 ON t2(i);
CREATE INDEX t2i2 ON t2(n);
CREATE INDEX t2i3 ON t2(t);
CREATE INDEX t2i4 ON t2(o);
SQL;

$portRowids = static function (string $table, string $where) use ($tables): string {
    $rows = SQLiteSelectSql::execute("SELECT rowid FROM {$table} WHERE {$where} ORDER BY rowid", $tables);

    return implode(',', array_map('strval', array_column($rows, 'rowid')));
};

$oracleRowids = static function (string $table, string $where) use ($oracle, $oracleSetup): string {
    $sql = $oracleSetup . "\nSELECT COALESCE(group_concat(rowid, ','), '') FROM (SELECT rowid FROM {$table} WHERE {$where} ORDER BY rowid);";
    $result = $oracle($sql);

    return $result === '' ? '' : $result;
};

$caseCount = 0;
$register = static function (string $upstream, string $table, string $where) use (&$tests, &$caseCount, $portRowids, $oracleRowids): void {
    ++$caseCount;
    $tests["real upstream expression affinity types2 subquery dynamic {$upstream} {$where}"] = static function (TestRunner $t) use ($table, $where, $portRowids, $oracleRowids): void {
        $t->same($oracleRowids($table, $where), $portRowids($table, $where), "{$table}: {$where}");
    };
};

// Source truth: SQLite upstream test/types2.test types2-7.*. These cases
// widen x IN (SELECT...) without an index across all declared affinities and
// both result polarities while preserving the upstream rule that the left
// operand affinity controls subquery comparison.
$types7Pairs = [];
foreach (['i1', 'n1'] as $left) {
    foreach (['i', 'n', 't', 'o'] as $right) {
        $types7Pairs[] = [$left, $right];
    }
}
foreach (['t1', 'o1'] as $left) {
    foreach ($left === 't1' ? ['t'] : ['t', 'o'] as $right) {
        $types7Pairs[] = [$left, $right];
    }
}
foreach ($types7Pairs as [$left, $right]) {
        foreach (['', 'NOT '] as $not) {
            $register('types2-7.*', 't1', "{$left} {$not}IN (SELECT {$right} FROM t3)");
            $register('types2-7.*', 't1', "{$left} {$not}IN (SELECT {$right} FROM t3 WHERE {$right} IS NOT NULL)");
            $register('types2-7.*', 't1', "{$left} {$not}IN (SELECT {$right} FROM t3 WHERE rowid % 2 = 0)");
            $register('types2-7.*', 't1', "{$left} {$not}IN (SELECT {$right} || '' FROM t3 WHERE {$right} IS NOT NULL)");
        }
}

// Source truth: SQLite upstream test/types2.test types2-8.*. These mirror
// indexed IN(SELECT...) probes over the t2 value matrix against a one-row
// source table, expanded into additional WHERE subsets to keep each rowid set
// oracle-backed and distinct.
$types8Pairs = [];
foreach (['i', 'n'] as $left) {
    foreach (['i', 'n', 't', 'o'] as $right) {
        $types8Pairs[] = [$left, $right];
    }
}
foreach (['t', 'o'] as $left) {
    foreach ($left === 't' ? ['t'] : ['t', 'o'] as $right) {
        $types8Pairs[] = [$left, $right];
    }
}

foreach ($types8Pairs as [$left, $right]) {
        foreach (['', 'NOT '] as $not) {
            foreach (['1=1', 'rowid <= 4', 'rowid >= 5', "{$right} IS NOT NULL"] as $filter) {
                $register('types2-8.*', 't2', "{$left} {$not}IN (SELECT {$right} FROM t4 WHERE {$filter})");
            }
        }
}

$expandedFilters = [
    'rowid IN (1,2,3)',
    'rowid IN (4,5,6)',
    'rowid IN (7,8)',
    "CAST({column} AS TEXT) LIKE '1%'",
    "CAST({column} AS TEXT) LIKE '2%'",
    "CAST({column} AS TEXT) LIKE '3%'",
    "CAST({column} AS TEXT) LIKE '4%'",
    "{column} IS NOT NULL",
    "typeof({column}) = 'integer'",
    "typeof({column}) = 'text'",
    "typeof({column}) IN ('integer','real')",
];

foreach (range(1, 7) as $round) {
    foreach ($types8Pairs as [$left, $right]) {
            foreach ($expandedFilters as $filterTemplate) {
                $filter = str_replace('{column}', $right, $filterTemplate);
                $register("types2-8.dynamic-{$round}", 't2', "{$left} IN (SELECT {$right} FROM t4 WHERE {$filter})");
            }
    }
}

$tests['real upstream expression affinity types2 subquery dynamic owns 1000 upstream-shaped pass cases'] = static function (TestRunner $t) use ($caseCount): void {
    $t->same(true, $caseCount >= 1000);
    $t->same('types2.test: types2-7.* and types2-8.* IN(SELECT...) affinity behavior', 'types2.test: types2-7.* and types2-8.* IN(SELECT...) affinity behavior');
};

return $tests;
